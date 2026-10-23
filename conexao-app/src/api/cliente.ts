import { CONFIG } from '../config';
import { ErroApi, ehAbort } from './erros';
import type { Api, ArquivoPdf, FontePdfRemota, Estante, Eu, Leitura, ListaAparelhos, ListaPedidos, Livro, Marcador, OpcoesDownload, Pedido, RespostaLogin } from './tipos';

/** Cliente HTTP real do plugin `conexao-app` (REST do WordPress). */

let token: string | null = null;
let aoPerderSessao: (() => void) | null = null;

export function definirToken(novo: string | null) {
  token = novo;
}

export function tokenAtual() {
  return token;
}

/** Chamado quando uma rota autenticada responde 401 (token revogado, senha trocada, conta removida). */
export function aoReceber401(fn: () => void) {
  aoPerderSessao = fn;
}

export function notificar401() {
  aoPerderSessao?.();
}

interface OpcoesReq {
  corpo?: unknown;
  signal?: AbortSignal;
  keepalive?: boolean;
  /** Rotas de entrada (login/cadastro): 401 é "senha errada", não "sessão terminou". */
  publica?: boolean;
}

async function req<T>(metodo: string, rota: string, opcoes: OpcoesReq = {}): Promise<T> {
  const cabecalhos: Record<string, string> = { Accept: 'application/json' };
  if (opcoes.corpo !== undefined) cabecalhos['Content-Type'] = 'application/json';
  if (token && !opcoes.publica) cabecalhos.Authorization = `Bearer ${token}`;

  let resp: Response;
  try {
    resp = await fetch(CONFIG.apiBase + rota, {
      method: metodo,
      headers: cabecalhos,
      body: opcoes.corpo !== undefined ? JSON.stringify(opcoes.corpo) : undefined,
      signal: opcoes.signal,
      keepalive: opcoes.keepalive,
      credentials: 'omit',
      cache: 'no-store',
    });
  } catch (e) {
    if (ehAbort(e)) throw e;
    throw ErroApi.semConexao();
  }

  if (resp.status === 204) return undefined as T;
  let corpo: unknown = null;
  try {
    corpo = await resp.json();
  } catch {
    corpo = null;
  }
  if (!resp.ok) {
    const erro = ErroApi.deResposta(resp.status, corpo);
    if (resp.status === 401 && !opcoes.publica) notificar401();
    throw erro;
  }
  return corpo as T;
}

async function baixarPdf(id: number, opcoes: OpcoesDownload = {}): Promise<ArquivoPdf> {
  let resp: Response;
  try {
    resp = await fetch(`${CONFIG.apiBase}/livros/${id}/pdf`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
      signal: opcoes.signal,
      credentials: 'omit',
      cache: 'no-store',
    });
  } catch (e) {
    if (ehAbort(e)) throw e;
    throw ErroApi.semConexao();
  }
  if (!resp.ok) throw await erroDaResposta(resp);
  // O servidor não manda Content-Length (resposta "chunked"): o progresso usa tamanho_bytes do livro.
  const dados = await lerComProgresso(resp, opcoes);
  // X-Conexao-Marca vem percent-encoded (UTF-8) e é exposto pelo CORS.
  const marca = resp.headers.get('X-Conexao-Marca');
  return { dados, marca: marca ? decodificarMarca(marca) : null };
}

export const BLOCO_PDF = 256 * 1024;

async function erroDaResposta(resp: Response) {
  let corpo: unknown = null;
  try {
    corpo = await resp.json();
  } catch {
    /* corpo não é JSON */
  }
  if (resp.status === 401) notificar401();
  return ErroApi.deResposta(resp.status, corpo);
}

/** Busca um intervalo de bytes (Range). Usado pelo leitor para abrir o PDF por partes. */
export async function buscarIntervalo(url: string, cabecalhos: Record<string, string>, inicio: number, fim: number, signal?: AbortSignal) {
  let resp: Response;
  try {
    resp = await fetch(url, { headers: { ...cabecalhos, Range: `bytes=${inicio}-${fim - 1}` }, signal, credentials: 'omit', cache: 'no-store' });
  } catch (e) {
    if (ehAbort(e)) throw e;
    throw ErroApi.semConexao();
  }
  if (resp.status !== 206 && resp.status !== 200) throw await erroDaResposta(resp);
  const buf = new Uint8Array(await resp.arrayBuffer());
  // 200 = servidor mandou o arquivo inteiro; recorta o pedaço pedido.
  return resp.status === 200 ? buf.slice(inicio, fim) : buf;
}

/** Pede o primeiro bloco por Range; devolve null se o servidor não aceitar Range. */
export async function abrirPorPartes(url: string, cabecalhos: Record<string, string>, tamanho: number | null, signal?: AbortSignal): Promise<FontePdfRemota | null> {
  const controle = new AbortController();
  signal?.addEventListener('abort', () => controle.abort());
  const fim = tamanho ? Math.min(tamanho, BLOCO_PDF) : BLOCO_PDF;
  let resp: Response;
  try {
    resp = await fetch(url, { headers: { ...cabecalhos, Range: `bytes=0-${fim - 1}` }, signal: controle.signal, credentials: 'omit', cache: 'no-store' });
  } catch (e) {
    if (ehAbort(e)) throw e;
    throw ErroApi.semConexao();
  }
  if (resp.status === 416) return null;
  if (!resp.ok) throw await erroDaResposta(resp);
  const marcaBruta = resp.headers.get('X-Conexao-Marca');
  const marca = marcaBruta ? decodificarMarca(marcaBruta) : null;
  const intervalo = resp.headers.get('Content-Range')?.match(/\/(\d+)\s*$/);
  const total = intervalo ? Number(intervalo[1]) : tamanho;
  if (resp.status !== 206 || !total) {
    controle.abort(); // sem Range: o app baixa o arquivo inteiro pelo caminho normal
    return null;
  }
  const inicial = new Uint8Array(await resp.arrayBuffer());
  return { url, cabecalhos, total, inicial, marca };
}

/** Lê o corpo em partes para mostrar o progresso e permitir cancelar. */
export async function lerComProgresso(resp: Response, opcoes: OpcoesDownload): Promise<Uint8Array> {
  const cabecalho = Number(resp.headers.get('Content-Length'));
  const total = cabecalho > 0 ? cabecalho : opcoes.tamanhoEsperado ?? null;
  if (!resp.body) {
    const buf = new Uint8Array(await resp.arrayBuffer());
    opcoes.aoProgredir?.(buf.byteLength, buf.byteLength);
    return buf;
  }
  const leitor = resp.body.getReader();
  const partes: Uint8Array[] = [];
  let recebido = 0;
  opcoes.aoProgredir?.(0, total);
  for (;;) {
    const { done, value } = await leitor.read();
    if (done) break;
    partes.push(value);
    recebido += value.byteLength;
    opcoes.aoProgredir?.(recebido, total);
  }
  const saida = new Uint8Array(recebido);
  let pos = 0;
  for (const p of partes) {
    saida.set(p, pos);
    pos += p.byteLength;
  }
  return saida;
}

/** Cabeçalhos HTTP são ASCII; o servidor pode mandar a marca em UTF-8 "cru" ou percent-encoded. */
function decodificarMarca(valor: string) {
  try {
    if (/%[0-9A-F]{2}/i.test(valor)) return decodeURIComponent(valor);
    const bytes = Uint8Array.from(valor, (c) => c.charCodeAt(0) & 0xff);
    return new TextDecoder('utf-8', { fatal: true }).decode(bytes);
  } catch {
    return valor;
  }
}

export const apiReal: Api = {
  login: (dados) => req<RespostaLogin>('POST', '/login', { corpo: dados, publica: true }),
  cadastro: (dados) => req<RespostaLogin>('POST', '/cadastro', { corpo: dados, publica: true }),
  senha: (email) => req<void>('POST', '/senha', { corpo: { email }, publica: true }),
  sair: () => req<void>('POST', '/sair'),
  eu: () => req<Eu>('GET', '/eu'),
  aparelhos: () => req<ListaAparelhos>('GET', '/aparelhos'),
  desconectarAparelho: (id) => req<void>('DELETE', `/aparelhos/${id}`),
  estante: () => req<Estante>('GET', '/estante'),
  livro: (id) => req<Livro>('GET', `/livros/${id}`),
  pdf: baixarPdf,
  pdfRemoto: (id, tamanho, signal) =>
    abrirPorPartes(`${CONFIG.apiBase}/livros/${id}/pdf`, token ? { Authorization: `Bearer ${token}` } : {}, tamanho, signal),
  acessoWeb: (id) => req<{ url: string }>('POST', `/livros/${id}/acesso-web`),
  leitura: (id) => req<Leitura>('GET', `/livros/${id}/leitura`),
  salvarLeitura: (id, dados, opcoes) => req<Leitura>('PUT', `/livros/${id}/leitura`, { corpo: dados, keepalive: opcoes?.keepalive }),
  criarMarcador: (id, pagina) => req<Marcador>('POST', `/livros/${id}/marcadores`, { corpo: { pagina } }),
  removerMarcador: (id, marcador) => req<void>('DELETE', `/livros/${id}/marcadores/${marcador}`),
  pedidos: () => req<ListaPedidos>('GET', '/pedidos'),
  pedido: (ref) => req<Pedido>('GET', `/pedidos/${encodeURIComponent(ref)}`),
  enviarCodigoEmail: () => req<void>('POST', '/email/codigo'),
  confirmarEmail: (codigo) => req<void>('POST', '/email/confirmar', { corpo: { codigo } }),
};
