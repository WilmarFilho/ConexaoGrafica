import { api, ehAbort, mensagemDeErro } from '../api';
import type { Livro } from '../api/tipos';
import { armazem, capaParaDataUrl, type LivroBaixado } from '../plataforma/arquivos';
import { criarLoja, useLoja } from './loja';

/** Downloads em andamento e livros baixados (para ler sem internet). */

export interface DownloadAtivo {
  recebido: number;
  total: number | null;
  erro?: string;
}

interface EstadoDownloads {
  carregado: boolean;
  baixados: Record<number, LivroBaixado>;
  ativos: Record<number, DownloadAtivo>;
}

export const lojaDownloads = criarLoja<EstadoDownloads>({ carregado: false, baixados: {}, ativos: {} });
const controles = new Map<number, AbortController>();

export function useDownloads() {
  return useLoja(lojaDownloads);
}

export async function carregarBaixados() {
  const lista = await armazem.listar();
  lojaDownloads.definir((e) => ({ ...e, carregado: true, baixados: Object.fromEntries(lista.map((l) => [l.id, l])) }));
}

function definirAtivo(id: number, ativo: DownloadAtivo | null) {
  lojaDownloads.definir((e) => {
    const ativos = { ...e.ativos };
    if (ativo) ativos[id] = ativo;
    else delete ativos[id];
    return { ...e, ativos };
  });
}

/** Baixa o PDF e guarda no aparelho. Resolve true quando terminou, false se foi cancelado. */
export async function baixarLivro(livro: Livro): Promise<boolean> {
  if (controles.has(livro.id)) return false;
  const controle = new AbortController();
  controles.set(livro.id, controle);
  definirAtivo(livro.id, { recebido: 0, total: livro.tamanho_bytes });
  try {
    let ultimo = 0;
    const { dados, marca } = await api.pdf(livro.id, {
      signal: controle.signal,
      tamanhoEsperado: livro.tamanho_bytes,
      aoProgredir: (recebido, total) => {
        const agora = performance.now();
        if (agora - ultimo > 120 || recebido === total) {
          ultimo = agora;
          definirAtivo(livro.id, { recebido, total });
        }
      },
    });
    if (controle.signal.aborted) return false;
    const meta: LivroBaixado = {
      id: livro.id,
      livro: { ...livro },
      capaLocal: await capaParaDataUrl(livro.capa),
      marca,
      tamanho: dados.byteLength,
      baixado_em: new Date().toISOString(),
    };
    await armazem.salvar(meta, dados);
    lojaDownloads.definir((e) => ({ ...e, baixados: { ...e.baixados, [livro.id]: meta } }));
    definirAtivo(livro.id, null);
    return true;
  } catch (erro) {
    if (ehAbort(erro)) {
      definirAtivo(livro.id, null);
      return false;
    }
    definirAtivo(livro.id, { recebido: 0, total: null, erro: mensagemDeErro(erro) });
    throw erro;
  } finally {
    controles.delete(livro.id);
  }
}

export function cancelarDownload(id: number) {
  controles.get(id)?.abort();
  definirAtivo(id, null);
}

export function limparErroDownload(id: number) {
  const a = lojaDownloads.obter().ativos[id];
  if (a?.erro) definirAtivo(id, null);
}

export async function removerBaixado(id: number) {
  await armazem.remover(id);
  lojaDownloads.definir((e) => {
    const baixados = { ...e.baixados };
    delete baixados[id];
    return { ...e, baixados };
  });
}

export async function removerTodosBaixados() {
  controles.forEach((c) => c.abort());
  controles.clear();
  await armazem.removerTodos();
  lojaDownloads.definir((e) => ({ ...e, baixados: {}, ativos: {} }));
}

/** Atualiza os dados do livro guardados junto do download (título, progresso...). */
export function atualizarMetaBaixado(livro: Livro) {
  const atual = lojaDownloads.obter().baixados[livro.id];
  if (!atual) return;
  lojaDownloads.definir((e) => ({ ...e, baixados: { ...e.baixados, [livro.id]: { ...atual, livro: { ...atual.livro, ...livro } } } }));
}

export function tamanhoTotalBaixado(baixados: Record<number, LivroBaixado>) {
  return Object.values(baixados).reduce((s, l) => s + (l.tamanho || 0), 0);
}
