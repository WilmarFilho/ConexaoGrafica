import { Capacitor } from '@capacitor/core';
import { Directory, Filesystem } from '@capacitor/filesystem';
import { Preferences } from '@capacitor/preferences';
import type { Livro } from '../api/tipos';
import { ehNativo } from './nativo';

/**
 * Livros baixados para ler sem internet.
 *  - App nativo: arquivos em Directory.Data/livros (pasta privada do app), índice no Preferences.
 *  - Web: IndexedDB (banco "conexao-leitor").
 * Nada vai para armazenamento público.
 */

export interface LivroBaixado {
  id: number;
  livro: Livro;
  /** capa guardada como data URL para aparecer sem internet (quando foi possível baixá-la) */
  capaLocal: string | null;
  marca: string | null;
  tamanho: number;
  baixado_em: string;
}

export interface Armazem {
  listar(): Promise<LivroBaixado[]>;
  salvar(meta: LivroBaixado, dados: Uint8Array): Promise<void>;
  ler(id: number): Promise<Uint8Array | null>;
  remover(id: number): Promise<void>;
  removerTodos(): Promise<void>;
}

// ---------------- Nativo ----------------

const INDICE = 'cx-baixados';
const PASTA = 'livros';

async function lerIndiceNativo(): Promise<LivroBaixado[]> {
  const v = (await Preferences.get({ key: INDICE })).value;
  try {
    return v ? (JSON.parse(v) as LivroBaixado[]) : [];
  } catch {
    return [];
  }
}
async function gravarIndiceNativo(lista: LivroBaixado[]) {
  await Preferences.set({ key: INDICE, value: JSON.stringify(lista) });
}

function paraBase64(bytes: Uint8Array) {
  let s = '';
  const passo = 0x8000;
  for (let i = 0; i < bytes.length; i += passo) {
    s += String.fromCharCode.apply(null, bytes.subarray(i, i + passo) as unknown as number[]);
  }
  return btoa(s);
}
function deBase64(b64: string) {
  const bin = atob(b64);
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}

const armazemNativo: Armazem = {
  listar: lerIndiceNativo,
  async salvar(meta, dados) {
    const caminho = `${PASTA}/${meta.id}.pdf`;
    // Grava em partes (múltiplos de 3 bytes → base64 sem preenchimento no meio) para não estourar a ponte JS/nativo.
    const parte = 3 * 1024 * 512;
    for (let i = 0; i < dados.length || i === 0; i += parte) {
      const pedaco = paraBase64(dados.subarray(i, i + parte));
      if (i === 0) await Filesystem.writeFile({ path: caminho, data: pedaco, directory: Directory.Data, recursive: true });
      else await Filesystem.appendFile({ path: caminho, data: pedaco, directory: Directory.Data });
      if (dados.length === 0) break;
    }
    const lista = (await lerIndiceNativo()).filter((l) => l.id !== meta.id);
    lista.push(meta);
    await gravarIndiceNativo(lista);
  },
  async ler(id) {
    const caminho = `${PASTA}/${id}.pdf`;
    try {
      // Caminho rápido: o servidor local do Capacitor entrega o arquivo sem passar base64 pela ponte.
      const { uri } = await Filesystem.getUri({ path: caminho, directory: Directory.Data });
      const resp = await fetch(Capacitor.convertFileSrc(uri));
      if (resp.ok) return new Uint8Array(await resp.arrayBuffer());
    } catch {
      /* tenta pela ponte */
    }
    try {
      const r = await Filesystem.readFile({ path: caminho, directory: Directory.Data });
      return typeof r.data === 'string' ? deBase64(r.data) : new Uint8Array(await r.data.arrayBuffer());
    } catch {
      return null;
    }
  },
  async remover(id) {
    try {
      await Filesystem.deleteFile({ path: `${PASTA}/${id}.pdf`, directory: Directory.Data });
    } catch {
      /* já não existia */
    }
    await gravarIndiceNativo((await lerIndiceNativo()).filter((l) => l.id !== id));
  },
  async removerTodos() {
    try {
      await Filesystem.rmdir({ path: PASTA, directory: Directory.Data, recursive: true });
    } catch {
      /* pasta não existia */
    }
    await Preferences.remove({ key: INDICE });
  },
};

// ---------------- Web (IndexedDB) ----------------

const BANCO = 'conexao-leitor';
let bancoAberto: Promise<IDBDatabase> | null = null;

function abrirBanco(): Promise<IDBDatabase> {
  if (!bancoAberto) {
    bancoAberto = new Promise((ok, falha) => {
      const r = indexedDB.open(BANCO, 1);
      r.onupgradeneeded = () => {
        const db = r.result;
        if (!db.objectStoreNames.contains('arquivos')) db.createObjectStore('arquivos');
        if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta', { keyPath: 'id' });
      };
      r.onsuccess = () => ok(r.result);
      r.onerror = () => {
        bancoAberto = null;
        falha(r.error);
      };
    });
  }
  return bancoAberto;
}

function transacao<T>(lojas: string[], modo: IDBTransactionMode, fn: (t: IDBTransaction) => IDBRequest<T> | void): Promise<T> {
  return abrirBanco().then(
    (db) =>
      new Promise<T>((ok, falha) => {
        const t = db.transaction(lojas, modo);
        const r = fn(t);
        t.oncomplete = () => ok(r ? r.result : (undefined as T));
        t.onerror = () => falha(t.error);
        t.onabort = () => falha(t.error);
      })
  );
}

const armazemWeb: Armazem = {
  async listar() {
    try {
      return await transacao<LivroBaixado[]>(['meta'], 'readonly', (t) => t.objectStore('meta').getAll() as IDBRequest<LivroBaixado[]>);
    } catch {
      return [];
    }
  },
  async salvar(meta, dados) {
    const blob = new Blob([dados as BlobPart], { type: 'application/pdf' });
    await transacao(['arquivos', 'meta'], 'readwrite', (t) => {
      t.objectStore('arquivos').put(blob, meta.id);
      t.objectStore('meta').put(meta);
    });
  },
  async ler(id) {
    try {
      const blob = await transacao<Blob | undefined>(['arquivos'], 'readonly', (t) => t.objectStore('arquivos').get(id) as IDBRequest<Blob | undefined>);
      return blob ? new Uint8Array(await blob.arrayBuffer()) : null;
    } catch {
      return null;
    }
  },
  async remover(id) {
    await transacao(['arquivos', 'meta'], 'readwrite', (t) => {
      t.objectStore('arquivos').delete(id);
      t.objectStore('meta').delete(id);
    });
  },
  async removerTodos() {
    try {
      await transacao(['arquivos', 'meta'], 'readwrite', (t) => {
        t.objectStore('arquivos').clear();
        t.objectStore('meta').clear();
      });
    } catch {
      /* banco indisponível */
    }
  },
};

export const armazem: Armazem = ehNativo ? armazemNativo : armazemWeb;

/** Tenta guardar a capa como data URL (falha silenciosa se o servidor de imagens não liberar CORS). */
export async function capaParaDataUrl(url: string | null): Promise<string | null> {
  if (!url) return null;
  if (url.startsWith('data:')) return url;
  try {
    const resp = await fetch(url, { mode: 'cors', credentials: 'omit' });
    if (!resp.ok) return null;
    const blob = await resp.blob();
    if (blob.size > 1_500_000) return null;
    return await new Promise((ok) => {
      const leitor = new FileReader();
      leitor.onload = () => ok(String(leitor.result));
      leitor.onerror = () => ok(null);
      leitor.readAsDataURL(blob);
    });
  } catch {
    return null;
  }
}
