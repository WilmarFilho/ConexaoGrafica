import { GlobalWorkerOptions, getDocument, PDFDataRangeTransport, type PDFDocumentProxy } from 'pdfjs-dist';
import urlWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { buscarIntervalo } from '../../api/cliente';
import type { FontePdfRemota } from '../../api/tipos';

GlobalWorkerOptions.workerSrc = urlWorker;

const base = import.meta.env.BASE_URL;
const OPCOES = {
  cMapUrl: `${base}pdfjs/cmaps/`,
  cMapPacked: true,
  standardFontDataUrl: `${base}pdfjs/standard_fonts/`,
  wasmUrl: `${base}pdfjs/wasm/`,
  iccUrl: `${base}pdfjs/iccs/`,
  // Sem scripts nem formulários XFA: o leitor só mostra o conteúdo.
  enableXfa: false,
  stopAtErrors: false,
};

export type FonteDocumento = { dados: Uint8Array } | { remota: FontePdfRemota };

/**
 * Transporte por partes: o pdf.js pede intervalos de bytes conforme precisa
 * (páginas visíveis primeiro), usando o nosso fetch com o token do aparelho.
 */
class TransporteRange extends PDFDataRangeTransport {
  private fonte: FontePdfRemota;
  private controle = new AbortController();

  constructor(fonte: FontePdfRemota) {
    super(fonte.total, fonte.inicial);
    this.fonte = fonte;
  }

  override requestDataRange(inicio: number, fim: number) {
    buscarIntervalo(this.fonte.url, this.fonte.cabecalhos, inicio, fim, this.controle.signal)
      .then((pedaco) => this.onDataRange(inicio, pedaco))
      .catch(() => {
        /* o pdf.js trata a falta do pedaço como erro de renderização daquela página */
      });
  }

  override abort() {
    this.controle.abort();
  }
}

export async function abrirDocumento(fonte: FonteDocumento): Promise<PDFDocumentProxy> {
  if ('dados' in fonte) {
    // O pdf.js transfere o buffer para o worker; passa uma cópia para não invalidar o original.
    return getDocument({ ...OPCOES, data: fonte.dados.slice() }).promise;
  }
  return getDocument({
    ...OPCOES,
    range: new TransporteRange(fonte.remota),
    rangeChunkSize: 256 * 1024,
    disableAutoFetch: true,
    disableStream: true,
  }).promise;
}

export interface ItemSumario {
  titulo: string;
  pagina: number | null;
  filhos: ItemSumario[];
}

type Destino = string | unknown[] | null;

async function paginaDoDestino(doc: PDFDocumentProxy, dest: Destino): Promise<number | null> {
  try {
    const explicito = typeof dest === 'string' ? await doc.getDestination(dest) : dest;
    if (!Array.isArray(explicito) || !explicito.length) return null;
    const alvo = explicito[0];
    if (typeof alvo === 'number') return alvo + 1;
    return (await doc.getPageIndex(alvo as Parameters<PDFDocumentProxy['getPageIndex']>[0])) + 1;
  } catch {
    return null;
  }
}

export async function lerSumario(doc: PDFDocumentProxy): Promise<ItemSumario[]> {
  const outline = await doc.getOutline();
  if (!outline?.length) return [];
  const converter = async (itens: typeof outline): Promise<ItemSumario[]> =>
    Promise.all(
      itens.map(async (i) => ({
        titulo: (i.title || '').trim() || 'Sem título',
        pagina: await paginaDoDestino(doc, i.dest as Destino),
        filhos: i.items?.length ? await converter(i.items) : [],
      }))
    );
  return converter(outline);
}

/** Texto da página para a busca (quebras de linha viram espaço). */
export async function textoDaPagina(doc: PDFDocumentProxy, n: number): Promise<string> {
  const pagina = await doc.getPage(n);
  const conteudo = await pagina.getTextContent();
  let texto = '';
  let ultimoY: number | null = null;
  for (const item of conteudo.items) {
    if (!('str' in item)) continue;
    const y = item.transform[5];
    if (ultimoY !== null && Math.abs(y - ultimoY) > 1 && !/\s$/.test(texto)) texto += ' ';
    texto += item.str;
    if (item.hasEOL && !/\s$/.test(texto)) texto += ' ';
    ultimoY = y;
  }
  return texto.replace(/\s+/g, ' ').trim();
}
