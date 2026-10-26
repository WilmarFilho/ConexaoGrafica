import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../api';
import { mostrarAvisosDemo, semLinksDeCompra } from '../vitrine';
import type { Estante as DadosEstante, Livro } from '../api/tipos';
import { CONFIG } from '../config';
import { Icone } from '../componentes/Icone';
import { PuxarParaAtualizar } from '../componentes/PuxarParaAtualizar';
import { BarraProgresso, BotaoIcone, Capa, Carregando, Erro, Vazio } from '../componentes/ui';
import { atualizarMetaBaixado, lojaDownloads, removerBaixado, useDownloads, type DownloadAtivo } from '../estado/downloads';
import { paginaLocal } from '../estado/leitura';
import { useOnline } from '../estado/rede';
import { useSessao } from '../estado/sessao';
import { gravarCache, lerCache } from '../plataforma/armazenamento';
import { abrirExterno } from '../plataforma/nativo';
import { autores, normalizar } from '../util/formato';

export function Estante() {
  const online = useOnline();
  const { usuario } = useSessao();
  const { baixados, ativos, carregado } = useDownloads();
  const [dados, setDados] = useState<DadosEstante | null>(() => lerCache<DadosEstante>('estante'));
  const [erro, setErro] = useState<string | null>(null);
  const [carregando, setCarregando] = useState(false);
  const [busca, setBusca] = useState('');
  const [buscaAberta, setBuscaAberta] = useState(false);

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro(null);
    try {
      const r = await api.estante();
      setDados(r);
      gravarCache('estante', r);
      // Revalida o acesso: livros baixados que saíram da estante são apagados do aparelho.
      const ids = new Set(r.livros.map((l) => l.id));
      for (const id of Object.keys(lojaDownloads.obter().baixados).map(Number)) {
        if (!ids.has(id)) await removerBaixado(id);
      }
      r.livros.forEach(atualizarMetaBaixado);
    } catch (e) {
      if (!(e instanceof ErroApi && e.status === 401)) setErro(mensagemDeErro(e));
    } finally {
      setCarregando(false);
    }
  }, []);

  useEffect(() => {
    if (online && carregado) void carregar();
  }, [online, carregado, carregar]);

  // Sem internet: só os livros baixados.
  const livros: Livro[] = useMemo(() => {
    if (online && dados) return dados.livros;
    return Object.values(baixados).map((b) => {
      const pagina = paginaLocal(b.id);
      const total = b.livro.progresso?.total_paginas ?? b.livro.ficha?.paginas ?? null;
      return {
        ...b.livro,
        capa: b.capaLocal ?? b.livro.capa,
        progresso: pagina && total ? { pagina, total_paginas: total, percentual: (pagina / total) * 100, atualizado_em: null } : b.livro.progresso,
      };
    });
  }, [online, dados, baixados]);

  const continuar = useMemo(() => {
    if (online && dados) return dados.continuar;
    return livros.filter((l) => l.progresso && l.progresso.pagina < l.progresso.total_paginas)[0] ?? null;
  }, [online, dados, livros]);

  const filtrados = useMemo(() => {
    const q = normalizar(busca.trim());
    if (!q) return livros;
    return livros.filter((l) => normalizar(`${l.titulo} ${l.subtitulo ?? ''} ${l.autores.join(' ')}`).includes(q));
  }, [livros, busca]);

  const primeiroNome = usuario?.nome.split(' ')[0];
  const semNada = !livros.length;

  return (
    <PuxarParaAtualizar aoAtualizar={carregar} desativado={!online}>
      <header className="cabecalho cabecalho--grande">
        <div className="cabecalho__bloco">
          <p className="cabecalho__sobre">{primeiroNome ? `Olá, ${primeiroNome}` : 'Olá'}</p>
          <h1 className="cabecalho__titulo">Minha estante</h1>
        </div>
        <div className="cabecalho__acoes">
          {!semNada && <BotaoIcone icone="busca" rotulo="Buscar na estante" ativo={buscaAberta} onClick={() => setBuscaAberta((v) => !v)} aria-expanded={buscaAberta} />}
        </div>
      </header>

      <div className="pagina">
        {mostrarAvisosDemo && <p className="selo-demo">Dados de exemplo · modo de demonstração</p>}

        {(buscaAberta || busca) && !semNada && (
          <div className="busca">
            <Icone nome="busca" tamanho={20} />
            <input type="search" placeholder="Título ou autor" value={busca} onChange={(e) => setBusca(e.target.value)} aria-label="Buscar na estante" autoFocus enterKeyHint="search" />
            {busca && <BotaoIcone icone="fechar" rotulo="Limpar busca" onClick={() => setBusca('')} />}
          </div>
        )}

        {!busca && continuar && <ContinuarLendo livro={continuar} capaLocal={baixados[continuar.id]?.capaLocal} />}

        {erro && !dados && <Erro mensagem={erro} aoTentar={carregar} />}
        {erro && dados && (
          <p className="alerta alerta--erro" role="alert">
            {erro}
          </p>
        )}

        {!dados && carregando && online && <EsqueletoGrade />}

        {(dados || !online) && semNada && !carregando && (
          online ? (
            semLinksDeCompra ? (
              <Vazio icone="estante" titulo="Sua estante está vazia" texto="Os livros digitais que você adquirir aparecem aqui para ler no app, inclusive sem internet." />
            ) : (
              <Vazio icone="estante" titulo="Sua estante está vazia" texto="Os livros digitais que você adquirir aparecem aqui para ler no app, inclusive sem internet.">
                <button type="button" className="botao botao--primario" onClick={() => void abrirExterno(CONFIG.lojaUrl)}>
                  <Icone nome="loja" tamanho={20} />
                  <span>Conhecer a loja</span>
                </button>
              </Vazio>
            )
          ) : (
            <Vazio icone="sem-internet" titulo="Nenhum livro baixado" texto="Sem internet, só os livros baixados no aparelho podem ser lidos. Quando a conexão voltar, toque em “Baixar para ler offline” na página do livro." />
          )
        )}

        {!semNada && (
          <section aria-labelledby="titulo-meus-livros">
            <div className="secao__topo">
              <h2 id="titulo-meus-livros" className="secao__titulo">
                {online ? 'Meus livros' : 'Livros no aparelho'}
              </h2>
              <span className="secao__contagem">{filtrados.length}</span>
            </div>
            {filtrados.length === 0 ? (
              <p className="texto-suave">Nenhum livro encontrado para “{busca}”.</p>
            ) : (
              <ul className="grade-livros">
                {filtrados.map((l) => (
                  <CartaoLivro key={l.id} livro={l} baixado={!!baixados[l.id]} ativo={ativos[l.id]} capaLocal={baixados[l.id]?.capaLocal} />
                ))}
              </ul>
            )}
          </section>
        )}
        {carregando && dados && <span className="sr-only" role="status">Atualizando a estante…</span>}
      </div>
    </PuxarParaAtualizar>
  );
}

function ContinuarLendo({ livro, capaLocal }: { livro: Livro; capaLocal?: string | null }) {
  const navegar = useNavigate();
  const p = livro.progresso!;
  return (
    <section className="continuar" aria-labelledby="titulo-continuar">
      <h2 id="titulo-continuar" className="continuar__rotulo">
        Continuar lendo
      </h2>
      <div className="continuar__corpo">
        <Link to={`/livro/${livro.id}`} className="continuar__capa" aria-label={`Detalhes de ${livro.titulo}`}>
          <Capa src={capaLocal ?? livro.capa} titulo={livro.titulo} />
        </Link>
        <div className="continuar__info">
          <h3 className="continuar__titulo">{livro.titulo}</h3>
          <p className="continuar__autores">{autores(livro.autores)}</p>
          <BarraProgresso valor={p.percentual} rotulo={`Progresso de leitura de ${livro.titulo}`} />
          <p className="continuar__pagina">
            Página {p.pagina} de {p.total_paginas} · {Math.round(p.percentual)}%
          </p>
          <button type="button" className="botao botao--primario botao--pequeno" onClick={() => navegar(`/ler/${livro.id}`)}>
            <Icone nome="livro-aberto" tamanho={18} />
            <span>Continuar</span>
          </button>
        </div>
      </div>
    </section>
  );
}

function CartaoLivro({ livro, baixado, ativo, capaLocal }: { livro: Livro; baixado: boolean; ativo?: DownloadAtivo; capaLocal?: string | null }) {
  const p = livro.progresso;
  const emBreve = livro.modelo === '';
  return (
    <li className="cartao-livro">
      <Link to={`/livro/${livro.id}`} className="cartao-livro__link">
        <div className="cartao-livro__capa">
          <Capa src={capaLocal ?? livro.capa} titulo={livro.titulo} />
          {baixado && (
            <span className="cartao-livro__selo" title="Baixado no aparelho">
              <Icone nome="check" tamanho={14} />
              <span className="sr-only">Baixado no aparelho</span>
            </span>
          )}
          {emBreve && <span className="cartao-livro__faixa">Em breve</span>}
          {ativo && !ativo.erro && (
            <span className="cartao-livro__baixando">
              <BarraProgresso fina valor={ativo.total ? (ativo.recebido / ativo.total) * 100 : 5} rotulo={`Baixando ${livro.titulo}`} />
            </span>
          )}
        </div>
        <h3 className="cartao-livro__titulo">{livro.titulo}</h3>
        <p className="cartao-livro__autores">{autores(livro.autores, 1)}</p>
        {p && p.pagina > 1 && (
          <div className="cartao-livro__progresso">
            <BarraProgresso fina valor={p.percentual} rotulo={`Lido ${Math.round(p.percentual)}%`} />
            <span>{p.pagina >= p.total_paginas ? 'Lido' : `${Math.round(p.percentual)}%`}</span>
          </div>
        )}
      </Link>
    </li>
  );
}

function EsqueletoGrade() {
  return (
    <div aria-hidden="true">
      <div className="esqueleto esqueleto--continuar" />
      <ul className="grade-livros">
        {[1, 2, 3, 4].map((i) => (
          <li key={i} className="cartao-livro">
            <div className="esqueleto esqueleto--capa" />
            <div className="esqueleto esqueleto--linha" />
            <div className="esqueleto esqueleto--linha curta" />
          </li>
        ))}
      </ul>
      <Carregando texto="Carregando sua estante…" />
    </div>
  );
}
