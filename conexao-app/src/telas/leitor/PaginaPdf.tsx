import { memo, useEffect, useRef, useState } from 'react';
import type { PDFDocumentProxy, RenderTask } from 'pdfjs-dist';

/** Limite de pixels por página (memória baixa em celulares). */
const MAX_PIXELS = typeof navigator !== 'undefined' && /Android|iPhone|iPad/.test(navigator.userAgent) ? 6_000_000 : 12_000_000;

interface Props {
  doc: PDFDocumentProxy;
  numero: number;
  /** tamanho em px CSS na tela */
  largura: number;
  altura: number;
  /** páginas vizinhas ficam montadas (já desenhadas) mas escondidas */
  oculta?: boolean;
  aoFalhar?: (numero: number) => void;
}

function PaginaPdfBase({ doc, numero, largura, altura, oculta, aoFalhar }: Props) {
  const ocultaRef = useRef(oculta);
  ocultaRef.current = oculta;
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const [pronta, setPronta] = useState(false);
  const [erro, setErro] = useState(false);

  useEffect(() => {
    let tarefa: RenderTask | null = null;
    let cancelado = false;
    setErro(false);
    const canvas = canvasRef.current;
    if (!canvas || largura < 10) return;

    const t = setTimeout(async () => {
      try {
        const pagina = await doc.getPage(numero);
        if (cancelado) return;
        const base = pagina.getViewport({ scale: 1 });
        const dpr = Math.min(window.devicePixelRatio || 1, 3);
        let escala = (largura / base.width) * dpr;
        const pixels = base.width * escala * base.height * escala;
        if (pixels > MAX_PIXELS) escala *= Math.sqrt(MAX_PIXELS / pixels);
        const viewport = pagina.getViewport({ scale: escala });
        // Desenha num canvas fora da tela e só troca no fim: sem piscar branco ao ampliar.
        const rascunho = document.createElement('canvas');
        rascunho.width = Math.floor(viewport.width);
        rascunho.height = Math.floor(viewport.height);
        tarefa = pagina.render({ canvas: rascunho, viewport, background: '#ffffff' });
        await tarefa.promise;
        if (cancelado) {
          rascunho.width = 0;
          return;
        }
        canvas.width = rascunho.width;
        canvas.height = rascunho.height;
        canvas.getContext('2d')?.drawImage(rascunho, 0, 0);
        rascunho.width = 0;
        rascunho.height = 0;
        pagina.cleanup();
        setPronta(true);
      } catch (e) {
        if ((e as { name?: string })?.name === 'RenderingCancelledException' || cancelado) return;
        setErro(true);
        aoFalhar?.(numero);
      }
    }, ocultaRef.current ? 220 : 0);

    return () => {
      cancelado = true;
      clearTimeout(t);
      tarefa?.cancel();
    };
  }, [doc, numero, largura, aoFalhar]);

  // Libera a memória do canvas ao sair da tela.
  useEffect(() => {
    const canvas = canvasRef.current;
    return () => {
      if (canvas) {
        canvas.width = 0;
        canvas.height = 0;
      }
    };
  }, []);

  return (
    <div className={`pagina-pdf${pronta ? ' pagina-pdf--pronta' : ''}${oculta ? ' pagina-pdf--oculta' : ''}`} style={{ width: largura, height: altura }} data-pagina={numero}>
      <canvas ref={canvasRef} className="pagina-pdf__canvas" aria-hidden="true" />
      <div className="pagina-pdf__tom" aria-hidden="true" />
      {!pronta && !erro && <span className="pagina-pdf__carregando" aria-hidden="true" />}
      {erro && <span className="pagina-pdf__erro">Não foi possível mostrar a página {numero}.</span>}
    </div>
  );
}

export const PaginaPdf = memo(PaginaPdfBase);
