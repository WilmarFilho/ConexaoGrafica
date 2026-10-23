import { useEffect, useRef, useState, type FormEvent } from 'react';
import type { PDFDocumentProxy } from 'pdfjs-dist';
import type { Marcador } from '../../api/tipos';
import { Icone } from '../../componentes/Icone';
import { BarraProgresso, Botao, BotaoIcone, Carregando, Interruptor, Segmentado } from '../../componentes/ui';
import { mudarAjusteLeitura, useAjustesLeitura, type ModoPaginas, type TemaLeitura } from '../../estado/ajustesLeitura';
import { ehNativo, type Orientacao } from '../../plataforma/nativo';
import { dataCurta, normalizar } from '../../util/formato';
import { lerSumario, textoDaPagina, type ItemSumario } from './pdf';

// ---------------- Sumário ----------------

export function PainelSumario({ doc, paginaAtual, aoIr }: { doc: PDFDocumentProxy; paginaAtual: number; aoIr: (p: number) => void }) {
  const [itens, setItens] = useState<ItemSumario[] | null>(null);
  useEffect(() => {
    let vivo = true;
    lerSumario(doc)
      .then((s) => vivo && setItens(s))
      .catch(() => vivo && setItens([]));
    return () => {
      vivo = false;
    };
  }, [doc]);

  if (!itens) return <Carregando texto="Lendo o sumário…" />;
  if (!itens.length) {
    return (
      <div className="painel-vazio">
        <Icone nome="sumario" tamanho={30} />
        <p>Este arquivo não tem sumário navegável.</p>
        <p className="texto-suave">Use a busca ou a régua de páginas na parte de baixo para navegar.</p>
      </div>
    );
  }
  // Capítulo atual = último item cuja página é ≤ página atual
  const planos: ItemSumario[] = [];
  const achatar = (l: ItemSumario[]) => l.forEach((i) => (planos.push(i), achatar(i.filhos)));
  achatar(itens);
  const atual = planos.filter((i) => i.pagina !== null && i.pagina <= paginaAtual).pop();

  const lista = (l: ItemSumario[], nivel: number) => (
    <ul className={`sumario sumario--nivel-${Math.min(nivel, 3)}`}>
      {l.map((i, n) => (
        <li key={n}>
          <button type="button" className={`sumario__item${i === atual ? ' sumario__item--atual' : ''}`} disabled={i.pagina === null} onClick={() => i.pagina && aoIr(i.pagina)} aria-current={i === atual ? 'true' : undefined}>
            <span className="sumario__titulo">{i.titulo}</span>
            {i.pagina !== null && <span className="sumario__pagina">{i.pagina}</span>}
          </button>
          {i.filhos.length > 0 && lista(i.filhos, nivel + 1)}
        </li>
      ))}
    </ul>
  );
  return <nav aria-label="Sumário do livro">{lista(itens, 0)}</nav>;
}

// ---------------- Busca ----------------

interface Resultado {
  pagina: number;
  antes: string;
  termo: string;
  depois: string;
}

const MAX_RESULTADOS = 300;

export function PainelBusca({ doc, cacheTexto, aoIr, estadoInicial, aoGuardar }: {
  doc: PDFDocumentProxy;
  cacheTexto: Map<number, string>;
  aoIr: (p: number) => void;
  estadoInicial: { termo: string; resultados: Resultado[] | null };
  aoGuardar: (e: { termo: string; resultados: Resultado[] | null }) => void;
}) {
  const [termo, setTermo] = useState(estadoInicial.termo);
  const [resultados, setResultados] = useState<Resultado[] | null>(estadoInicial.resultados);
  const [progresso, setProgresso] = useState<number | null>(null);
  const busca = useRef(0);

  useEffect(() => () => void (busca.current++), []);
  useEffect(() => aoGuardar({ termo, resultados }), [termo, resultados, aoGuardar]);

  async function buscar(e?: FormEvent) {
    e?.preventDefault();
    const q = normalizar(termo.trim());
    if (q.length < 2) return;
    const minha = ++busca.current;
    const achados: Resultado[] = [];
    setResultados([]);
    setProgresso(0);
    for (let p = 1; p <= doc.numPages; p++) {
      if (busca.current !== minha) return;
      let texto = cacheTexto.get(p);
      if (texto === undefined) {
        try {
          texto = await textoDaPagina(doc, p);
        } catch {
          texto = '';
        }
        cacheTexto.set(p, texto);
      }
      // Normaliza caractere a caractere para manter as posições do texto original.
      const norm = Array.from(texto, (c) => normalizar(c)[0] ?? ' ').join('');
      let i = norm.indexOf(q);
      let porPagina = 0;
      while (i >= 0 && porPagina < 20 && achados.length < MAX_RESULTADOS) {
        const ini = Math.max(0, i - 50);
        const fim = Math.min(texto.length, i + q.length + 70);
        achados.push({
          pagina: p,
          antes: (ini > 0 ? '…' : '') + texto.slice(ini, i).replace(/^\S*\s/, ini > 0 ? '' : '$&'),
          termo: texto.slice(i, i + q.length),
          depois: texto.slice(i + q.length, fim) + (fim < texto.length ? '…' : ''),
        });
        porPagina++;
        i = norm.indexOf(q, i + q.length);
      }
      if (p % 5 === 0 || p === doc.numPages) {
        setResultados([...achados]);
        setProgresso(p / doc.numPages);
        await new Promise((r) => setTimeout(r, 0));
      }
      if (achados.length >= MAX_RESULTADOS) break;
    }
    if (busca.current === minha) {
      setResultados([...achados]);
      setProgresso(null);
    }
  }

  const buscando = progresso !== null;
  return (
    <div className="painel-busca">
      <form className="busca busca--painel" onSubmit={buscar} role="search">
        <Icone nome="busca" tamanho={20} />
        <input
          type="search"
          value={termo}
          onChange={(e) => setTermo(e.target.value)}
          placeholder="Buscar no livro"
          aria-label="Buscar no livro"
          enterKeyHint="search"
          data-foco-inicial
        />
        {termo && !buscando && <BotaoIcone icone="fechar" rotulo="Limpar" onClick={() => (setTermo(''), setResultados(null))} />}
        <Botao type="submit" pequeno disabled={termo.trim().length < 2} carregando={buscando}>
          Buscar
        </Botao>
      </form>
      {buscando && (
        <div className="painel-busca__progresso" role="status">
          <BarraProgresso fina valor={(progresso ?? 0) * 100} rotulo="Progresso da busca" />
          <span>
            Buscando… página {Math.round((progresso ?? 0) * doc.numPages)} de {doc.numPages}
          </span>
        </div>
      )}
      {resultados && !buscando && (
        <p className="texto-suave texto-pequeno" role="status">
          {resultados.length === 0
            ? `Nada encontrado para “${termo.trim()}”.`
            : `${resultados.length >= MAX_RESULTADOS ? 'Mais de ' + MAX_RESULTADOS : resultados.length} ${resultados.length === 1 ? 'resultado' : 'resultados'}`}
        </p>
      )}
      {resultados && resultados.length > 0 && (
        <ul className="resultados">
          {resultados.map((r, n) => (
            <li key={n}>
              <button type="button" className="resultados__item" onClick={() => aoIr(r.pagina)}>
                <span className="resultados__trecho">
                  {r.antes}
                  <mark>{r.termo}</mark>
                  {r.depois}
                </span>
                <span className="resultados__pagina">p. {r.pagina}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ---------------- Marcadores ----------------

export function PainelMarcadores({ marcadores, paginaAtual, marcada, aoIr, aoAlternar, aoRemover }: {
  marcadores: Marcador[];
  paginaAtual: number;
  marcada: boolean;
  aoIr: (p: number) => void;
  aoAlternar: () => void;
  aoRemover: (m: Marcador) => void;
}) {
  return (
    <div className="painel-marcadores">
      <Botao variante={marcada ? 'contorno' : 'primario'} bloco icone={marcada ? 'marcador-cheio' : 'marcador'} onClick={aoAlternar}>
        {marcada ? `Desmarcar página ${paginaAtual}` : `Marcar página ${paginaAtual}`}
      </Botao>
      {marcadores.length === 0 ? (
        <div className="painel-vazio">
          <Icone nome="marcador" tamanho={30} />
          <p>Nenhuma página marcada ainda.</p>
          <p className="texto-suave">Toque no marcador na barra de cima para guardar a página atual.</p>
        </div>
      ) : (
        <ul className="marcadores">
          {marcadores.map((m) => (
            <li key={String(m.id)} className={m.pagina === paginaAtual ? 'marcadores__item marcadores__item--atual' : 'marcadores__item'}>
              <button type="button" className="marcadores__ir" onClick={() => aoIr(m.pagina)}>
                <Icone nome="marcador-cheio" tamanho={18} />
                <span>
                  Página {m.pagina}
                  <small>
                    Marcada em {dataCurta(m.criado_em)}
                    {String(m.id).startsWith('local-') ? ' · aguardando internet' : ''}
                  </small>
                </span>
              </button>
              <BotaoIcone icone="lixeira" rotulo={`Remover marcador da página ${m.pagina}`} onClick={() => aoRemover(m)} />
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ---------------- Ajustes de leitura ----------------

export function PainelAjustes({ zoom, aoZoom }: { zoom: number; aoZoom: (z: number) => void }) {
  const a = useAjustesLeitura();
  return (
    <div className="painel-ajustes">
      <div className="painel-ajustes__grupo">
        <p className="cartao__rotulo">Tema de leitura</p>
        <div className="temas-leitura" role="radiogroup" aria-label="Tema de leitura">
          {(
            [
              ['claro', 'Claro'],
              ['sepia', 'Sépia'],
              ['escuro', 'Escuro'],
            ] as [TemaLeitura, string][]
          ).map(([v, t]) => (
            <button key={v} type="button" role="radio" aria-checked={a.tema === v} className={`tema-leitura tema-leitura--${v}${a.tema === v ? ' tema-leitura--ativo' : ''}`} onClick={() => mudarAjusteLeitura('tema', v)}>
              <span className="tema-leitura__amostra">Aa</span>
              <span>{t}</span>
            </button>
          ))}
        </div>
      </div>

      <div className="painel-ajustes__grupo">
        <p className="cartao__rotulo">Páginas na tela</p>
        <Segmentado<ModoPaginas>
          rotulo="Páginas na tela"
          valor={a.paginas}
          aoMudar={(v) => mudarAjusteLeitura('paginas', v)}
          opcoes={[
            { valor: 'auto', texto: 'Automático' },
            { valor: 'uma', texto: 'Uma', icone: 'pagina' },
            { valor: 'duas', texto: 'Duas', icone: 'paginas-duplas' },
          ]}
        />
        <p className="texto-suave texto-pequeno">No automático, mostra duas páginas em tablets e na horizontal.</p>
      </div>

      <div className="painel-ajustes__grupo">
        <p className="cartao__rotulo">Tamanho</p>
        <div className="zoom">
          <BotaoIcone icone="menos" rotulo="Diminuir" disabled={zoom <= 1} onClick={() => aoZoom(Math.max(1, Math.round((zoom - 0.25) * 4) / 4))} />
          <span aria-live="polite">{Math.round(zoom * 100)}%</span>
          <BotaoIcone icone="mais" rotulo="Aumentar" disabled={zoom >= 4} onClick={() => aoZoom(Math.min(4, Math.round((zoom + 0.25) * 4) / 4))} />
        </div>
        <p className="texto-suave texto-pequeno">Dica: use dois dedos para ampliar ou toque duas vezes na página.</p>
      </div>

      <div className="painel-ajustes__grupo">
        <Interruptor rotulo="Virar página com toque nas bordas" descricao="Toque à direita para avançar e à esquerda para voltar." ligado={a.virarPorToque} aoMudar={(v) => mudarAjusteLeitura('virarPorToque', v)} />
      </div>

      {ehNativo && (
        <div className="painel-ajustes__grupo">
          <p className="cartao__rotulo">Orientação da tela</p>
          <Segmentado<Orientacao>
            rotulo="Orientação da tela"
            valor={a.orientacao}
            aoMudar={(v) => mudarAjusteLeitura('orientacao', v)}
            opcoes={[
              { valor: 'auto', texto: 'Girar', icone: 'girar' },
              { valor: 'retrato', texto: 'Retrato', icone: 'celular' },
              { valor: 'paisagem', texto: 'Paisagem', icone: 'tablet' },
            ]}
          />
        </div>
      )}
    </div>
  );
}

export type { Resultado };
