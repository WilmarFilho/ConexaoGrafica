import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState, type PointerEvent as PE } from 'react';
import { useNavigate, useParams } from 'react-router';
import type { PDFDocumentProxy } from 'pdfjs-dist';
import { App as AppNativo } from '@capacitor/app';
import { api, ErroApi, ehAbort } from '../../api';
import type { Estante, Livro, Marcador, Usuario } from '../../api/tipos';
import { FaixaSemInternet } from '../../componentes/FaixaSemInternet';
import { useAviso } from '../../componentes/Feedback';
import { Icone } from '../../componentes/Icone';
import { BarraProgresso, Botao, BotaoIcone, Capa, Folha, podeVoltar } from '../../componentes/ui';
import { useAjustesLeitura } from '../../estado/ajustesLeitura';
import { lojaDownloads } from '../../estado/downloads';
import { adicionarMarcador, carregarLeitura, enviarProgresso, paginaLocal, registrarPagina, removerMarcador } from '../../estado/leitura';
import { estaOnline } from '../../estado/rede';
import { useSessao } from '../../estado/sessao';
import { armazem } from '../../plataforma/arquivos';
import { lerCache } from '../../plataforma/armazenamento';
import { barraStatusVisivel, ehNativo, travarOrientacao } from '../../plataforma/nativo';
import { CONFIG } from '../../config';
import { modoCapturas } from '../../vitrine';
import { abrirDocumento, type FonteDocumento } from './pdf';
import { PaginaPdf } from './PaginaPdf';
import { PainelAjustes, PainelBusca, PainelMarcadores, PainelSumario, type Resultado } from './Paineis';

type Painel = 'sumario' | 'busca' | 'marcadores' | 'ajustes' | null;
type Abertura = { fase: 'abrindo'; progresso: number | null } | { fase: 'pronto' } | { fase: 'erro'; mensagem: string; tentar: boolean };

const ZOOM_MAX = 4;
const MARGEM = 12;

function emailParcial(email: string) {
  const [u, d] = email.split('@');
  return `${u.slice(0, 2)}***@${d ?? ''}`;
}
function marcaPadrao(u: Usuario | null) {
  return u ? `${u.nome} · ${emailParcial(u.email)}` : 'Conexão Editora';
}
const limitar = (v: number, min: number, max: number) => Math.min(max, Math.max(min, v));

export function Leitor() {
  const id = Number(useParams().id);
  const navegar = useNavigate();
  const avisar = useAviso();
  const { usuario } = useSessao();
  const ajustes = useAjustesLeitura();

  const [abertura, setAbertura] = useState<Abertura>({ fase: 'abrindo', progresso: null });
  const [tentativa, setTentativa] = useState(0);
  const [livro, setLivro] = useState<Livro | null>(() => lojaDownloads.obter().baixados[id]?.livro ?? lerCache<Estante>('estante')?.livros.find((l) => l.id === id) ?? null);
  const [doc, setDoc] = useState<PDFDocumentProxy | null>(null);
  const [marca, setMarca] = useState('');
  const [pagina, setPagina] = useState(1);
  const [marcadores, setMarcadores] = useState<Marcador[]>([]);
  const [barras, setBarras] = useState(true);
  const [painel, setPainel] = useState<Painel>(null);
  const [zoom, setZoom] = useState(1);
  const [area, setArea] = useState({ w: 0, h: 0 });
  const [tamanhos, setTamanhos] = useState<Record<number, { w: number; h: number }>>({});
  const [previa, setPrevia] = useState<number | null>(null);

  const palcoRef = useRef<HTMLDivElement>(null);
  const spreadRef = useRef<HTMLDivElement>(null);
  const cacheTexto = useRef(new Map<number, string>());
  const estadoBusca = useRef<{ termo: string; resultados: Resultado[] | null }>({ termo: '', resultados: null });
  const total = doc?.numPages ?? 0;
  const sair = useCallback(() => (podeVoltar() ? navegar(-1) : navegar(`/livro/${id}`, { replace: true })), [navegar, id]);

  // ---------- Abrir o livro ----------
  useEffect(() => {
    let vivo = true;
    const controle = new AbortController();
    let documento: PDFDocumentProxy | null = null;
    setAbertura({ fase: 'abrindo', progresso: null });
    (async () => {
      try {
        const baixado = lojaDownloads.obter().baixados[id];
        let l = baixado?.livro ?? lerCache<Estante>('estante')?.livros.find((x) => x.id === id) ?? null;
        if (!l && estaOnline()) l = await api.livro(id);
        if (!l) throw new ErroApi(404, 'nao_encontrado', 'Livro não encontrado na sua estante.');
        if (vivo) setLivro(l);
        if (l.modelo !== 'pdf') throw new ErroApi(422, 'invalido', 'Este livro não abre no leitor do app. Veja as opções na página do livro.');

        let fonte: FonteDocumento | null = null;
        let m: string | null = null;
        if (baixado) {
          const dados = await armazem.ler(id);
          if (dados) {
            fonte = { dados };
            m = baixado.marca;
          }
        }
        if (!fonte) {
          if (!estaOnline()) throw new ErroApi(0, 'sem_conexao', 'Sem internet. Este livro não está baixado no aparelho; conecte-se para ler.');
          const remota = await api.pdfRemoto(id, l.tamanho_bytes, controle.signal);
          if (remota) {
            fonte = { remota };
            m = remota.marca;
          } else {
            const arq = await api.pdf(id, {
              signal: controle.signal,
              tamanhoEsperado: l.tamanho_bytes,
              aoProgredir: (r, t) => vivo && setAbertura({ fase: 'abrindo', progresso: t ? r / t : null }),
            });
            fonte = { dados: arq.dados };
            m = arq.marca;
          }
        }
        documento = await abrirDocumento(fonte);
        if (!vivo) return;
        let leitura: { pagina: number | null; marcadores: Marcador[] };
        try {
          leitura = await carregarLeitura(id);
        } catch {
          leitura = { pagina: paginaLocal(id), marcadores: [] };
        }
        if (!vivo) return;
        const inicial = limitar(leitura.pagina ?? 1, 1, documento.numPages);
        setMarca(m || '');
        setPagina(inicial);
        setMarcadores(leitura.marcadores);
        setDoc(documento);
        setAbertura({ fase: 'pronto' });
        if (inicial > 1 && !modoCapturas) avisar(`Continuando da página ${inicial}.`);
      } catch (e) {
        if (!vivo || ehAbort(e)) return;
        const semConexao = e instanceof ErroApi && e.codigo === 'sem_conexao';
        const mensagem = e instanceof ErroApi ? e.message : 'Não foi possível abrir este arquivo. Ele pode estar corrompido; tente baixar de novo.';
        setAbertura({ fase: 'erro', mensagem, tentar: semConexao || !(e instanceof ErroApi) || e.status >= 500 });
      }
    })();
    return () => {
      vivo = false;
      controle.abort();
      void documento?.loadingTask.destroy();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, tentativa]);

  // ---------- Medidas da tela ----------
  useLayoutEffect(() => {
    const el = palcoRef.current;
    if (!el) return;
    const obs = new ResizeObserver(([e]) => setArea({ w: e.contentRect.width, h: e.contentRect.height }));
    obs.observe(el);
    return () => obs.disconnect();
  }, [abertura.fase]);

  const duas = total > 1 && (ajustes.paginas === 'duas' || (ajustes.paginas === 'auto' && area.w > area.h && area.w >= 640));

  const spreadDe = useCallback(
    (p: number): number[] => {
      if (!duas) return [p];
      if (p <= 1) return [1];
      const ini = p % 2 === 0 ? p : p - 1;
      return ini + 1 <= total ? [ini, ini + 1] : [ini];
    },
    [duas, total]
  );
  const atual = spreadDe(pagina);
  const inicioSpread = atual[0];
  const proximoInicio = duas ? (inicioSpread === 1 ? 2 : inicioSpread + 2) : pagina + 1;
  const anteriorInicio = duas ? (inicioSpread <= 2 ? 1 : inicioSpread - 2) : pagina - 1;

  // tamanhos das páginas (em pontos do PDF) para montar o layout antes de desenhar
  useEffect(() => {
    if (!doc) return;
    const precisa = [...spreadDe(anteriorInicio), ...atual, ...spreadDe(proximoInicio)].filter((n) => n >= 1 && n <= total && !tamanhos[n]);
    if (!precisa.length) return;
    let vivo = true;
    Promise.all(
      precisa.map(async (n) => {
        const v = (await doc.getPage(n)).getViewport({ scale: 1 });
        return [n, { w: v.width, h: v.height }] as const;
      })
    )
      .then((pares) => vivo && setTamanhos((t) => ({ ...t, ...Object.fromEntries(pares) })))
      .catch(() => undefined);
    return () => {
      vivo = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [doc, pagina, duas]);

  const tamanho = useCallback((n: number) => tamanhos[n] ?? tamanhos[1] ?? { w: 420, h: 595 }, [tamanhos]);

  /** escala (px CSS por ponto do PDF) com zoom 1 para um conjunto de páginas */
  const escalaBase = useCallback(
    (paginas: number[]) => {
      const w = paginas.reduce((s, n) => s + tamanho(n).w, 0);
      const h = Math.max(...paginas.map((n) => tamanho(n).h));
      const dispW = Math.max(100, area.w - MARGEM * 2);
      const dispH = Math.max(100, area.h - MARGEM * 2);
      const ajusteLargura = dispW / w;
      // Retrato: ajusta à largura (rola se a página for mais alta). Paisagem / duas páginas: cabe inteira.
      return area.w > area.h || duas ? Math.min(ajusteLargura, dispH / h) : ajusteLargura;
    },
    [area, duas, tamanho]
  );

  const escala = escalaBase(atual) * zoom;
  const spreadW = atual.reduce((s, n) => s + tamanho(n).w * escala, 0);
  const spreadH = Math.max(...atual.map((n) => tamanho(n).h * escala));

  // ---------- Navegação ----------
  const irPara = useCallback(
    (p: number) => {
      if (!total) return;
      const destino = limitar(Math.round(p), 1, total);
      setPagina(destino);
      setZoom(1);
      palcoRef.current?.scrollTo({ top: 0, left: 0 });
    },
    [total]
  );
  const proxima = useCallback(() => {
    if (proximoInicio <= total) irPara(proximoInicio);
  }, [proximoInicio, total, irPara]);
  const anterior = useCallback(() => {
    if (anteriorInicio >= 1) irPara(anteriorInicio);
  }, [anteriorInicio, irPara]);

  // Ao trocar para duas páginas, alinha no início do par.
  useEffect(() => {
    if (pagina !== inicioSpread) setPagina(inicioSpread);
  }, [pagina, inicioSpread]);

  // ---------- Progresso: guarda local já; envia no máximo a cada 5 s e ao sair ----------
  const ultimoEnvio = useRef(0);
  const temporizador = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => {
    if (!doc) return;
    registrarPagina(id, pagina, total);
    if (temporizador.current) return;
    const espera = Math.max(0, CONFIG.intervaloProgressoMs - (Date.now() - ultimoEnvio.current));
    temporizador.current = setTimeout(() => {
      temporizador.current = null;
      ultimoEnvio.current = Date.now();
      void enviarProgresso(id);
    }, Math.max(espera, 1500));
  }, [doc, id, pagina, total]);

  useEffect(() => {
    const salvarAgora = () => void enviarProgresso(id, { keepalive: true });
    const aoOcultar = () => document.visibilityState === 'hidden' && salvarAgora();
    document.addEventListener('visibilitychange', aoOcultar);
    window.addEventListener('pagehide', salvarAgora);
    const pausa = ehNativo ? AppNativo.addListener('pause', salvarAgora) : null;
    return () => {
      document.removeEventListener('visibilitychange', aoOcultar);
      window.removeEventListener('pagehide', salvarAgora);
      void pausa?.then((h) => h.remove());
      if (temporizador.current) clearTimeout(temporizador.current);
      temporizador.current = null;
      salvarAgora();
    };
  }, [id]);

  // ---------- Barras e sistema ----------
  useEffect(() => {
    if (!barras || painel || abertura.fase !== 'pronto') return;
    const t = setTimeout(() => setBarras(false), 5000);
    return () => clearTimeout(t);
  }, [barras, painel, pagina, abertura.fase]);

  useEffect(() => {
    void barraStatusVisivel(barras || !!painel);
  }, [barras, painel]);
  useEffect(() => () => void barraStatusVisivel(true), []);

  useEffect(() => {
    void travarOrientacao(ajustes.orientacao);
  }, [ajustes.orientacao]);
  useEffect(() => () => void travarOrientacao('auto'), []);

  // ---------- Marcadores ----------
  const marcadorAtual = marcadores.find((m) => atual.includes(m.pagina));
  const alternarMarcador = useCallback(async () => {
    if (marcadorAtual) {
      setMarcadores(await removerMarcador(id, marcadorAtual));
      avisar(`Marcador da página ${marcadorAtual.pagina} removido.`);
    } else {
      setMarcadores(await adicionarMarcador(id, inicioSpread));
      avisar(`Página ${inicioSpread} marcada.`, 'sucesso');
    }
  }, [marcadorAtual, id, inicioSpread, avisar]);

  // ---------- Teclado ----------
  useEffect(() => {
    if (abertura.fase !== 'pronto') return;
    const tecla = (e: KeyboardEvent) => {
      if (painel || (e.target as HTMLElement)?.closest?.('input, textarea, select')) return;
      if (['ArrowRight', 'PageDown', ' '].includes(e.key)) {
        e.preventDefault();
        proxima();
      } else if (['ArrowLeft', 'PageUp'].includes(e.key)) {
        e.preventDefault();
        anterior();
      } else if (e.key === 'Home') irPara(1);
      else if (e.key === 'End') irPara(total);
      else if (e.key === '+' || e.key === '=') setZoom((z) => Math.min(ZOOM_MAX, z + 0.25));
      else if (e.key === '-') setZoom((z) => Math.max(1, z - 0.25));
    };
    window.addEventListener('keydown', tecla);
    return () => window.removeEventListener('keydown', tecla);
  }, [abertura.fase, painel, proxima, anterior, irPara, total]);

  // ---------- Gestos: toque nas bordas, deslizar, pinça e toque duplo ----------
  const ponteiros = useRef(new Map<number, { x: number; y: number }>());
  const gesto = useRef<{
    tipo: 'nenhum' | 'toque' | 'arrasto' | 'pinca';
    x0: number;
    y0: number;
    t0: number;
    dist0: number;
    razao: number;
    foco: { u: number; v: number; fx: number; fy: number };
  }>({ tipo: 'nenhum', x0: 0, y0: 0, t0: 0, dist0: 1, razao: 1, foco: { u: 0.5, v: 0.5, fx: 0, fy: 0 } });
  const ultimoToque = useRef<{ t: number; x: number; y: number } | null>(null);
  const timerToque = useRef<ReturnType<typeof setTimeout> | null>(null);
  const rolagemPendente = useRef<{ u: number; v: number; fx: number; fy: number } | null>(null);

  const pontoRelativo = (x: number, y: number) => {
    const r = palcoRef.current!.getBoundingClientRect();
    return { x: x - r.left, y: y - r.top };
  };
  const focoEm = (cx: number, cy: number) => {
    const r = spreadRef.current!.getBoundingClientRect();
    const p = pontoRelativo(cx, cy);
    return { u: limitar((cx - r.left) / r.width, 0, 1), v: limitar((cy - r.top) / r.height, 0, 1), fx: p.x, fy: p.y };
  };

  const aplicarZoom = useCallback((novo: number, foco?: { u: number; v: number; fx: number; fy: number }) => {
    const z = limitar(novo, 1, ZOOM_MAX);
    rolagemPendente.current = foco ?? null;
    setZoom(z);
  }, []);

  // Depois de mudar o zoom, mantém o ponto do gesto sob o dedo.
  useLayoutEffect(() => {
    const f = rolagemPendente.current;
    const palco = palcoRef.current;
    if (!f || !palco) return;
    rolagemPendente.current = null;
    const esquerda = spreadW + MARGEM * 2 < palco.clientWidth ? (palco.clientWidth - spreadW) / 2 : MARGEM;
    const topo = spreadH + MARGEM * 2 < palco.clientHeight ? (palco.clientHeight - spreadH) / 2 : MARGEM;
    palco.scrollLeft = esquerda + f.u * spreadW - f.fx;
    palco.scrollTop = topo + f.v * spreadH - f.fy;
  }, [zoom, spreadW, spreadH]);

  function aoTocar(cx: number, cy: number) {
    const { x } = pontoRelativo(cx, cy);
    const zona = x / Math.max(1, area.w);
    if (zoom === 1 && ajustes.virarPorToque && (zona < 0.22 || zona > 0.78)) {
      setBarras(false);
      if (zona < 0.22) anterior();
      else proxima();
      return;
    }
    const agora = Date.now();
    const u = ultimoToque.current;
    if (u && agora - u.t < 320 && Math.hypot(cx - u.x, cy - u.y) < 40) {
      if (timerToque.current) clearTimeout(timerToque.current);
      ultimoToque.current = null;
      aplicarZoom(zoom > 1 ? 1 : 2.5, focoEm(cx, cy));
      return;
    }
    ultimoToque.current = { t: agora, x: cx, y: cy };
    timerToque.current = setTimeout(() => {
      ultimoToque.current = null;
      setBarras((b) => !b);
    }, 280);
  }

  function aoApertar(e: PE<HTMLDivElement>) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    ponteiros.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
    const g = gesto.current;
    if (ponteiros.current.size === 2) {
      const [a, b] = Array.from(ponteiros.current.values());
      g.tipo = 'pinca';
      g.dist0 = Math.max(10, Math.hypot(a.x - b.x, a.y - b.y));
      g.razao = 1;
      g.foco = focoEm((a.x + b.x) / 2, (a.y + b.y) / 2);
      if (spreadRef.current) spreadRef.current.style.transformOrigin = `${g.foco.u * 100}% ${g.foco.v * 100}%`;
    } else if (ponteiros.current.size === 1) {
      g.tipo = 'toque';
      g.x0 = e.clientX;
      g.y0 = e.clientY;
      g.t0 = Date.now();
    }
  }

  function aoMover(e: PE<HTMLDivElement>) {
    if (!ponteiros.current.has(e.pointerId)) return;
    ponteiros.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
    const g = gesto.current;
    if (g.tipo === 'pinca' && ponteiros.current.size >= 2) {
      const [a, b] = Array.from(ponteiros.current.values());
      g.razao = limitar(Math.hypot(a.x - b.x, a.y - b.y) / g.dist0, 1 / zoom, ZOOM_MAX / zoom);
      if (spreadRef.current) spreadRef.current.style.transform = `scale(${g.razao})`;
      return;
    }
    const dx = e.clientX - g.x0;
    const dy = e.clientY - g.y0;
    if (g.tipo === 'toque' && Math.hypot(dx, dy) > 10) g.tipo = 'arrasto';
    if (g.tipo === 'arrasto' && zoom === 1 && Math.abs(dx) > Math.abs(dy) && spreadRef.current) {
      spreadRef.current.style.transform = `translateX(${dx * 0.35}px)`;
    }
  }

  function aoSoltar(e: PE<HTMLDivElement>) {
    if (!ponteiros.current.has(e.pointerId)) return;
    ponteiros.current.delete(e.pointerId);
    const g = gesto.current;
    const spread = spreadRef.current;
    if (g.tipo === 'pinca') {
      if (ponteiros.current.size > 0) return; // espera soltar o último dedo
      if (spread) {
        spread.style.transform = '';
        spread.style.transformOrigin = '';
      }
      g.tipo = 'nenhum';
      if (Math.abs(g.razao - 1) > 0.02) aplicarZoom(zoom * g.razao, g.foco);
      return;
    }
    if (spread) spread.style.transform = '';
    const dx = e.clientX - g.x0;
    const dy = e.clientY - g.y0;
    if (g.tipo === 'toque' && Date.now() - g.t0 < 450) aoTocar(e.clientX, e.clientY);
    else if (g.tipo === 'arrasto' && zoom === 1 && Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.4) {
      setBarras(false);
      if (dx < 0) proxima();
      else anterior();
    }
    g.tipo = 'nenhum';
  }

  function aoCancelar(e: PE<HTMLDivElement>) {
    ponteiros.current.delete(e.pointerId);
    if (spreadRef.current) {
      spreadRef.current.style.transform = '';
      spreadRef.current.style.transformOrigin = '';
    }
    gesto.current.tipo = 'nenhum';
  }

  // Ctrl + roda do mouse / pinça no touchpad
  useEffect(() => {
    const palco = palcoRef.current;
    if (!palco) return;
    const roda = (e: WheelEvent) => {
      if (!e.ctrlKey) return;
      e.preventDefault();
      setZoom((z) => limitar(z * (e.deltaY < 0 ? 1.1 : 1 / 1.1), 1, ZOOM_MAX));
    };
    palco.addEventListener('wheel', roda, { passive: false });
    return () => palco.removeEventListener('wheel', roda);
  }, [abertura.fase]);

  // ---------- Marca d'água ----------
  const textoMarca = marca || marcaPadrao(usuario);
  const fundoMarca = useMemo(() => {
    const cor = ajustes.tema === 'escuro' ? '#ffffff' : '#1f2933';
    const esc = textoMarca.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    // Ladrilho do tamanho do texto girado; cópias nos cantos fecham o padrão "tijolo" sem cortes.
    const comp = Math.max(120, textoMarca.length * 7.4);
    const w = Math.round(comp * 0.87 + 70);
    const h = Math.round(comp * 0.5 + 110);
    const texto = (x: number, y: number) => `<text x="${x}" y="${y}" transform="rotate(-30 ${x} ${y})" text-anchor="middle" dominant-baseline="middle">${esc}</text>`;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><g fill="${cor}" fill-opacity="0.07" font-family="Nunito, Arial, sans-serif" font-size="13" font-weight="700">${texto(w / 2, h / 2)}${texto(0, 0)}${texto(w, 0)}${texto(0, h)}${texto(w, h)}</g></svg>`;
    return { imagem: `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`, tamanho: `${w}px ${h}px` };
  }, [textoMarca, ajustes.tema]);

  const guardarBusca = useCallbackEstavel((e: { termo: string; resultados: Resultado[] | null }) => {
    estadoBusca.current = e;
  });

  // ---------- Telas de abertura e erro ----------
  if (abertura.fase !== 'pronto' || !doc) {
    const progressoAbertura = abertura.fase === 'abrindo' ? abertura.progresso : null;
    return (
      <div className={`leitor leitor--${ajustes.tema} leitor--abrindo`}>
        <div className="leitor__abrindo">
          {abertura.fase === 'erro' ? (
            <>
              <span className="leitor__abrindo-icone">
                <Icone nome="alerta" tamanho={32} />
              </span>
              <h1>Não foi possível abrir o livro</h1>
              <p>{abertura.mensagem}</p>
              <div className="leitor__abrindo-acoes">
                {abertura.tentar && (
                  <Botao icone="atualizar" onClick={() => setTentativa((t) => t + 1)}>
                    Tentar de novo
                  </Botao>
                )}
                <Botao variante="texto" onClick={sair}>
                  Voltar
                </Botao>
              </div>
            </>
          ) : (
            <>
              {livro && (
                <div className="leitor__abrindo-capa">
                  <Capa src={lojaDownloads.obter().baixados[id]?.capaLocal ?? livro.capa} titulo={livro.titulo} />
                </div>
              )}
              <h1>{livro?.titulo ?? 'Abrindo livro'}</h1>
              <div className="leitor__abrindo-progresso" role="status">
                <BarraProgresso valor={progressoAbertura !== null ? progressoAbertura * 100 : 35} rotulo="Abrindo o livro" />
                <span>{progressoAbertura !== null ? `Carregando… ${Math.round(progressoAbertura * 100)}%` : 'Abrindo o livro…'}</span>
              </div>
              <Botao variante="texto" onClick={sair}>
                Cancelar
              </Botao>
            </>
          )}
        </div>
      </div>
    );
  }

  // páginas montadas: par atual (visível) + vizinhos (escondidos, já desenhados)
  const vizinhos = [...(anteriorInicio >= 1 ? spreadDe(anteriorInicio) : []), ...(proximoInicio <= total ? spreadDe(proximoInicio) : [])];
  const escalaVizinho = (n: number) => escalaBase(spreadDe(n));
  const titulo = livro?.titulo ?? 'Livro';
  const paginaMostrada = previa ?? pagina;
  const rotuloPaginas = atual.length > 1 ? `Páginas ${atual[0]}–${atual[1]} de ${total}` : `Página ${pagina} de ${total}`;
  const lateral = area.w >= 900;

  return (
    <div className={`leitor leitor--${ajustes.tema}${barras ? ' leitor--barras' : ''}`}>
      <h1 className="sr-only">{titulo}</h1>
      <header className="leitor__topo" aria-hidden={!barras} inert={!barras}>
        <FaixaSemInternet escura />
        <div className="leitor__topo-linha">
          <BotaoIcone icone="voltar" rotulo="Fechar o livro" onClick={sair} />
          <span className="leitor__titulo">{titulo}</span>
          <BotaoIcone icone="sumario" rotulo="Sumário" onClick={() => setPainel('sumario')} />
          <BotaoIcone icone="busca" rotulo="Buscar no livro" onClick={() => setPainel('busca')} />
          <BotaoIcone
            icone={marcadorAtual ? 'marcador-cheio' : 'marcador'}
            rotulo={marcadorAtual ? 'Remover marcador desta página' : 'Marcar esta página'}
            ativo={!!marcadorAtual}
            aria-pressed={!!marcadorAtual}
            onClick={alternarMarcador}
            onContextMenu={(e) => {
              e.preventDefault();
              setPainel('marcadores');
            }}
          />
          <BotaoIcone icone="ajustes" rotulo="Ajustes de leitura" onClick={() => setPainel('ajustes')} />
        </div>
      </header>

      <div
        ref={palcoRef}
        className={`leitor__palco${zoom > 1 ? ' leitor__palco--ampliado' : ''}`}
        onPointerDown={aoApertar}
        onPointerMove={aoMover}
        onPointerUp={aoSoltar}
        onPointerCancel={aoCancelar}
        onContextMenu={(e) => e.preventDefault()}
        role="region"
        aria-label={`${rotuloPaginas}. Toque nas bordas ou use as setas para virar a página.`}
        aria-roledescription="leitor"
      >
        <div className="leitor__folha" style={{ minWidth: '100%', minHeight: '100%', width: spreadW + MARGEM * 2, height: spreadH + MARGEM * 2 }}>
          <div ref={spreadRef} className="leitor__spread" style={{ width: spreadW, height: spreadH }}>
            {atual.map((n) => (
              <PaginaPdf key={n} doc={doc} numero={n} largura={tamanho(n).w * escala} altura={tamanho(n).h * escala} />
            ))}
            <div className="leitor__marca" style={{ backgroundImage: fundoMarca.imagem, backgroundSize: fundoMarca.tamanho }} aria-hidden="true" />
            {marcadorAtual && <span className="leitor__fita" aria-hidden="true" />}
            {atual.length > 1 && <span className="leitor__lombada" aria-hidden="true" />}
          </div>
          {vizinhos
            .filter((n) => !atual.includes(n))
            .map((n) => (
              <PaginaPdf key={n} doc={doc} numero={n} largura={tamanho(n).w * escalaVizinho(n)} altura={tamanho(n).h * escalaVizinho(n)} oculta />
            ))}
        </div>
      </div>

      <footer className="leitor__base" aria-hidden={!barras} inert={!barras}>
        <div className="leitor__regua">
          <BotaoIcone icone="voltar" rotulo="Página anterior" disabled={anteriorInicio < 1} onClick={anterior} />
          <div className="leitor__regua-meio">
            <label htmlFor="regua" className="leitor__rotulo-pagina">
              {previa !== null ? `Ir para a página ${paginaMostrada}` : rotuloPaginas}
            </label>
            <input
              id="regua"
              type="range"
              min={1}
              max={total}
              step={1}
              value={paginaMostrada}
              style={{ ['--preenchido' as string]: `${total > 1 ? ((paginaMostrada - 1) / (total - 1)) * 100 : 100}%` }}
              aria-valuetext={`Página ${paginaMostrada} de ${total}`}
              onPointerDown={() => setPrevia(pagina)}
              onChange={(e) => {
                const v = Number(e.target.value);
                if (previa !== null) setPrevia(v);
                else irPara(v);
              }}
              onPointerUp={() => {
                if (previa !== null) irPara(previa);
                setPrevia(null);
              }}
              onPointerCancel={() => setPrevia(null)}
            />
          </div>
          <BotaoIcone icone="avancar" rotulo="Próxima página" disabled={proximoInicio > total} onClick={proxima} />
        </div>
        <div className="leitor__atalhos">
          <button type="button" onClick={() => setPainel('marcadores')}>
            <Icone nome="marcador" tamanho={18} />
            <span>Marcadores{marcadores.length ? ` (${marcadores.length})` : ''}</span>
          </button>
          <span className="leitor__porcentagem">{Math.round((pagina / total) * 100)}% lido</span>
        </div>
      </footer>

      <Folha aberta={painel === 'sumario'} aoFechar={() => setPainel(null)} titulo="Sumário" lateral={lateral} className="folha--leitor">
        <PainelSumario doc={doc} paginaAtual={pagina} aoIr={(p) => (irPara(p), setPainel(null))} />
      </Folha>
      <Folha aberta={painel === 'busca'} aoFechar={() => setPainel(null)} titulo="Buscar no livro" lateral={lateral} className="folha--leitor">
        <PainelBusca
          doc={doc}
          cacheTexto={cacheTexto.current}
          estadoInicial={estadoBusca.current}
          aoGuardar={guardarBusca}
          aoIr={(p) => (irPara(p), setPainel(null))}
        />
      </Folha>
      <Folha aberta={painel === 'marcadores'} aoFechar={() => setPainel(null)} titulo="Páginas marcadas" lateral={lateral} className="folha--leitor">
        <PainelMarcadores
          marcadores={marcadores}
          paginaAtual={inicioSpread}
          marcada={!!marcadorAtual}
          aoIr={(p) => (irPara(p), setPainel(null))}
          aoAlternar={alternarMarcador}
          aoRemover={async (m) => setMarcadores(await removerMarcador(id, m))}
        />
      </Folha>
      <Folha aberta={painel === 'ajustes'} aoFechar={() => setPainel(null)} titulo="Ajustes de leitura" lateral={lateral} className="folha--leitor">
        <PainelAjustes zoom={zoom} aoZoom={(z) => aplicarZoom(z)} />
      </Folha>
    </div>
  );
}

/** Função estável que sempre chama a versão mais recente (evita refazer efeitos). */
function useCallbackEstavel<T extends (...a: never[]) => unknown>(fn: T): T {
  const ref = useRef(fn);
  ref.current = fn;
  return useRef(((...a: Parameters<T>) => ref.current(...a)) as T).current;
}
