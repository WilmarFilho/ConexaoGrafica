import { Preferences } from '@capacitor/preferences';

/**
 * Dados da sessão (token do aparelho, usuário). No app nativo ficam no Preferences
 * (SharedPreferences / UserDefaults); na web o Preferences usa o localStorage.
 */
const TOKEN = 'cx-token';
const USUARIO = 'cx-usuario';
const ID_LOCAL = 'cx-id-local';

/** Sessão sem "manter conectado": só na memória/aba (some ao fechar o app ou a aba). */
let tokenTemporario: string | null = null;

export async function lerToken() {
  const salvo = (await Preferences.get({ key: TOKEN })).value;
  if (salvo) return salvo;
  try {
    return tokenTemporario ?? sessionStorage.getItem(TOKEN);
  } catch {
    return tokenTemporario;
  }
}
export async function gravarToken(token: string, manter = true) {
  if (manter) {
    await Preferences.set({ key: TOKEN, value: token });
    return;
  }
  tokenTemporario = token;
  try {
    sessionStorage.setItem(TOKEN, token);
  } catch {
    /* só memória */
  }
}
export async function lerUsuarioSalvo<T>() {
  const v = (await Preferences.get({ key: USUARIO })).value;
  if (!v) return null;
  try {
    return JSON.parse(v) as T;
  } catch {
    return null;
  }
}
export async function gravarUsuario(usuario: unknown) {
  await Preferences.set({ key: USUARIO, value: JSON.stringify(usuario) });
}
export async function apagarSessao() {
  tokenTemporario = null;
  try {
    sessionStorage.removeItem(TOKEN);
  } catch {
    /* ignora */
  }
  await Preferences.remove({ key: TOKEN });
  await Preferences.remove({ key: USUARIO });
}

/** Identificador deste aparelho/instalação (não muda ao sair). */
export async function idLocal() {
  const atual = (await Preferences.get({ key: ID_LOCAL })).value;
  if (atual) return atual;
  const novo = gerarUuid();
  await Preferences.set({ key: ID_LOCAL, value: novo });
  return novo;
}

function gerarUuid() {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

// ---------- Preferências e caches simples (localStorage, síncrono) ----------

const PREF = 'cx-pref:';
const CACHE = 'cx-cache:';

export function lerPref<T>(chave: string, padrao: T): T {
  try {
    const v = localStorage.getItem(PREF + chave);
    return v === null ? padrao : (JSON.parse(v) as T);
  } catch {
    return padrao;
  }
}
export function gravarPref(chave: string, valor: unknown) {
  try {
    localStorage.setItem(PREF + chave, JSON.stringify(valor));
  } catch {
    /* armazenamento cheio ou bloqueado */
  }
}

export function lerCache<T>(chave: string): T | null {
  try {
    const v = localStorage.getItem(CACHE + chave);
    return v ? (JSON.parse(v) as T) : null;
  } catch {
    return null;
  }
}
export function gravarCache(chave: string, valor: unknown) {
  try {
    localStorage.setItem(CACHE + chave, JSON.stringify(valor));
  } catch {
    /* ignora */
  }
}

/** Apaga caches e dados de leitura locais (mantém preferências do app e o id do aparelho). */
export function apagarDadosDaConta() {
  const apagar: string[] = [];
  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    if (k && (k.startsWith(CACHE) || k.startsWith('cx-leitura:') || k.startsWith('cx-pendente'))) apagar.push(k);
  }
  apagar.forEach((k) => localStorage.removeItem(k));
}
