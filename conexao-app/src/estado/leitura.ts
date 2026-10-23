import { api, ErroApi } from '../api';
import type { Marcador } from '../api/tipos';
import { estaOnline } from './rede';

/**
 * Progresso e marcadores de leitura com cópia local:
 * funciona sem internet e envia o que ficou pendente quando a conexão volta.
 */

interface LeituraLocal {
  pagina: number | null;
  total: number | null;
  em: string | null;
  pendente: boolean;
  marcadores: Marcador[];
  /** páginas marcadas sem internet, ainda não enviadas */
  criar: number[];
  /** marcadores removidos sem internet, ainda não enviados */
  remover: (number | string)[];
}

const PREFIXO = 'cx-leitura:';

function ler(id: number): LeituraLocal {
  try {
    const v = localStorage.getItem(PREFIXO + id);
    if (v) return { criar: [], remover: [], marcadores: [], ...(JSON.parse(v) as Partial<LeituraLocal>) } as LeituraLocal;
  } catch {
    /* ignora */
  }
  return { pagina: null, total: null, em: null, pendente: false, marcadores: [], criar: [], remover: [] };
}

function gravar(id: number, l: LeituraLocal) {
  try {
    localStorage.setItem(PREFIXO + id, JSON.stringify(l));
  } catch {
    /* ignora */
  }
}

export interface EstadoLeitura {
  pagina: number | null;
  marcadores: Marcador[];
}

function ordenar(ms: Marcador[]) {
  return [...ms].sort((a, b) => a.pagina - b.pagina);
}

/** Lê a posição salva (servidor quando online, senão a cópia local). */
export async function carregarLeitura(id: number): Promise<EstadoLeitura> {
  const local = ler(id);
  if (estaOnline()) {
    try {
      await enviarPendentes(id);
      const srv = await api.leitura(id);
      const atual = ler(id);
      const localMaisNovo = atual.pendente && atual.em && (!srv.atualizado_em || atual.em > srv.atualizado_em);
      const novo: LeituraLocal = {
        ...atual,
        pagina: localMaisNovo ? atual.pagina : srv.pagina ?? atual.pagina,
        total: localMaisNovo ? atual.total : srv.total_paginas ?? atual.total,
        em: localMaisNovo ? atual.em : srv.atualizado_em ?? atual.em,
        marcadores: ordenar([
          ...srv.marcadores.filter((m) => !atual.remover.some((r) => String(r) === String(m.id))),
          ...atual.marcadores.filter((m) => String(m.id).startsWith('local-')),
        ]),
      };
      gravar(id, novo);
      return { pagina: novo.pagina, marcadores: novo.marcadores };
    } catch (e) {
      if (e instanceof ErroApi && e.status === 401) throw e;
      /* sem servidor: usa a cópia local */
    }
  }
  return { pagina: local.pagina, marcadores: ordenar(local.marcadores) };
}

/** Guarda a página atual localmente (o envio ao servidor é feito por enviarProgresso). */
export function registrarPagina(id: number, pagina: number, total: number) {
  const l = ler(id);
  if (l.pagina === pagina && l.total === total) return;
  gravar(id, { ...l, pagina, total, em: new Date().toISOString(), pendente: true });
}

export function paginaLocal(id: number) {
  return ler(id).pagina;
}

export async function enviarProgresso(id: number, opcoes: { keepalive?: boolean } = {}) {
  const l = ler(id);
  if (!l.pendente || !l.pagina || !l.total || !estaOnline()) return;
  try {
    await api.salvarLeitura(id, { pagina: l.pagina, total_paginas: l.total }, opcoes);
    const depois = ler(id);
    if (depois.pagina === l.pagina) gravar(id, { ...depois, pendente: false });
  } catch {
    /* fica pendente para a próxima */
  }
}

export async function adicionarMarcador(id: number, pagina: number): Promise<Marcador[]> {
  const l = ler(id);
  if (l.marcadores.some((m) => m.pagina === pagina)) return ordenar(l.marcadores);
  const temporario: Marcador = { id: `local-${Date.now()}`, pagina, criado_em: new Date().toISOString() };
  gravar(id, { ...l, marcadores: [...l.marcadores, temporario], criar: [...l.criar, pagina] });
  if (estaOnline()) {
    try {
      const m = await api.criarMarcador(id, pagina);
      const d = ler(id);
      gravar(id, {
        ...d,
        marcadores: d.marcadores.map((x) => (x.id === temporario.id ? m : x)),
        criar: d.criar.filter((p) => p !== pagina),
      });
    } catch {
      /* fica pendente */
    }
  }
  return ordenar(ler(id).marcadores);
}

export async function removerMarcador(id: number, marcador: Marcador): Promise<Marcador[]> {
  const l = ler(id);
  const local = String(marcador.id).startsWith('local-');
  gravar(id, {
    ...l,
    marcadores: l.marcadores.filter((m) => String(m.id) !== String(marcador.id)),
    criar: local ? l.criar.filter((p) => p !== marcador.pagina) : l.criar,
    remover: local ? l.remover : [...l.remover, marcador.id],
  });
  if (!local && estaOnline()) {
    try {
      await api.removerMarcador(id, marcador.id);
      const d = ler(id);
      gravar(id, { ...d, remover: d.remover.filter((r) => String(r) !== String(marcador.id)) });
    } catch (e) {
      if (e instanceof ErroApi && e.status === 404) {
        const d = ler(id);
        gravar(id, { ...d, remover: d.remover.filter((r) => String(r) !== String(marcador.id)) });
      }
    }
  }
  return ordenar(ler(id).marcadores);
}

/** Envia progresso e marcadores que ficaram pendentes para um livro. */
export async function enviarPendentes(id: number) {
  if (!estaOnline()) return;
  await enviarProgresso(id);
  const l = ler(id);
  for (const pagina of l.criar) {
    try {
      const m = await api.criarMarcador(id, pagina);
      const d = ler(id);
      gravar(id, {
        ...d,
        criar: d.criar.filter((p) => p !== pagina),
        marcadores: d.marcadores.map((x) => (String(x.id).startsWith('local-') && x.pagina === pagina ? m : x)),
      });
    } catch {
      return;
    }
  }
  for (const r of l.remover) {
    try {
      await api.removerMarcador(id, r);
    } catch (e) {
      if (!(e instanceof ErroApi && e.status === 404)) return;
    }
    const d = ler(id);
    gravar(id, { ...d, remover: d.remover.filter((x) => String(x) !== String(r)) });
  }
}

/** Percorre todos os livros com algo pendente (chamado ao abrir o app e quando a internet volta). */
export async function sincronizarTudo() {
  const ids: number[] = [];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (k?.startsWith(PREFIXO)) {
      const id = Number(k.slice(PREFIXO.length));
      const l = ler(id);
      if (l.pendente || l.criar.length || l.remover.length) ids.push(id);
    }
  }
  for (const id of ids) await enviarPendentes(id);
}
