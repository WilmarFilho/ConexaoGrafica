import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../api';
import type { Estante, Livro } from '../api/tipos';
import { useAviso, useConfirmar } from '../componentes/Feedback';
import { Icone } from '../componentes/Icone';
import { BarraProgresso, Botao, BotaoIcone, Cabecalho, Capa, Carregando, Erro, Estrelas } from '../componentes/ui';
import { atualizarMetaBaixado, baixarLivro, cancelarDownload, limparErroDownload, removerBaixado, useDownloads } from '../estado/downloads';
import { useOnline } from '../estado/rede';
import { lerCache } from '../plataforma/armazenamento';
import { abrirExterno, compartilhar } from '../plataforma/nativo';
import { autores, dataLonga, tamanhoArquivo } from '../util/formato';

export function LivroDetalhe() {
  const id = Number(useParams().id);
  const online = useOnline();
  const navegar = useNavigate();
  const avisar = useAviso();
  const confirmar = useConfirmar();
  const { baixados, ativos } = useDownloads();
  const baixado = baixados[id];
  const ativo = ativos[id];

  const [livro, setLivro] = useState<Livro | null>(() => baixado?.livro ?? lerCache<Estante>('estante')?.livros.find((l) => l.id === id) ?? null);
  const [erro, setErro] = useState<string | null>(null);
  const [abrindoSite, setAbrindoSite] = useState(false);

  const carregar = useCallback(async () => {
    setErro(null);
    try {
      const l = await api.livro(id);
      setLivro(l);
      atualizarMetaBaixado(l);
    } catch (e) {
      if (!(e instanceof ErroApi && e.status === 401)) setErro(mensagemDeErro(e));
    }
  }, [id]);

  useEffect(() => {
    if (online) void carregar();
  }, [online, carregar]);

  if (!livro) {
    return (
      <>
        <Cabecalho titulo="Livro" voltar="/estante" />
        <div className="pagina">{erro ? <Erro mensagem={erro} aoTentar={online ? carregar : undefined} /> : <Carregando />}</div>
      </>
    );
  }

  const p = livro.progresso;
  const podeLer = livro.modelo === 'pdf' && livro.disponivel && (online || !!baixado);
  const tamanho = baixado?.tamanho ?? livro.tamanho_bytes;

  async function baixar() {
    if (!livro) return;
    limparErroDownload(livro.id);
    try {
      const ok = await baixarLivro(livro);
      if (ok) avisar('Livro baixado. Agora você pode ler sem internet.', 'sucesso');
    } catch (e) {
      avisar(mensagemDeErro(e), 'erro');
    }
  }

  async function remover() {
    if (!livro) return;
    const sim = await confirmar({
      titulo: 'Remover do aparelho?',
      texto: 'O livro continua na sua estante e pode ser baixado de novo quando quiser. Seu progresso e marcadores não se perdem.',
      confirmar: 'Remover',
      perigo: true,
    });
    if (!sim) return;
    await removerBaixado(livro.id);
    avisar('Livro removido do aparelho.');
  }

  async function lerNoSite() {
    if (!livro) return;
    setAbrindoSite(true);
    try {
      const { url } = await api.acessoWeb(livro.id);
      await abrirExterno(url);
    } catch (e) {
      avisar(mensagemDeErro(e), 'erro');
    } finally {
      setAbrindoSite(false);
    }
  }

  const linkCompartilhar = livro.ficha?.pagina_doi || null;

  return (
    <>
      <Cabecalho
        titulo=""
        voltar="/estante"
        acoes={
          linkCompartilhar ? (
            <BotaoIcone
              icone="compartilhar"
              rotulo="Compartilhar"
              onClick={async () => {
                const r = await compartilhar({ titulo: livro.titulo, texto: `${livro.titulo} · Conexão Editora`, url: linkCompartilhar });
                if (r === 'copiado') avisar('Link copiado.');
              }}
            />
          ) : undefined
        }
      />
      <div className="pagina livro">
        <div className="livro__topo">
          <div className="livro__capa">
            <Capa src={baixado?.capaLocal ?? livro.capa} titulo={livro.titulo} />
          </div>
          <div className="livro__cabecalho">
            <h1 className="livro__titulo">{livro.titulo}</h1>
            {livro.subtitulo && <p className="livro__subtitulo">{livro.subtitulo}</p>}
            <p className="livro__autores">{autores(livro.autores, 4)}</p>
            {livro.avaliacao && <Estrelas media={livro.avaliacao.media} quantidade={livro.avaliacao.quantidade} />}
          </div>
        </div>

        {p && p.pagina > 1 && livro.modelo === 'pdf' && (
          <div className="livro__progresso">
            <BarraProgresso valor={p.percentual} rotulo="Progresso de leitura" />
            <span>
              Página {p.pagina} de {p.total_paginas} · {Math.round(p.percentual)}%
            </span>
          </div>
        )}

        <div className="livro__acoes">
          {livro.modelo === 'pdf' && (
            <>
              <Botao bloco icone="livro-aberto" disabled={!podeLer} onClick={() => navegar(`/ler/${livro.id}`)}>
                {p && p.pagina > 1 && p.pagina < p.total_paginas ? 'Continuar lendo' : 'Ler livro'}
              </Botao>
              {!livro.disponivel && <p className="livro__nota">Este livro está indisponível no momento.</p>}
              {!online && !baixado && <p className="livro__nota">Sem internet. Conecte-se para ler ou baixar este livro.</p>}

              {ativo && !ativo.erro ? (
                <div className="download" aria-live="polite">
                  <div className="download__linha">
                    <span>
                      Baixando… {ativo.total ? `${Math.min(100, Math.round((ativo.recebido / ativo.total) * 100))}%` : tamanhoArquivo(ativo.recebido)}
                    </span>
                    <button type="button" className="botao botao--texto botao--pequeno" onClick={() => cancelarDownload(livro.id)}>
                      <span>Cancelar</span>
                    </button>
                  </div>
                  <BarraProgresso valor={ativo.total ? (ativo.recebido / ativo.total) * 100 : 10} rotulo="Progresso do download" />
                </div>
              ) : baixado ? (
                <div className="download download--feito">
                  <span className="download__ok">
                    <Icone nome="check-circulo" tamanho={20} />
                    Disponível sem internet · {tamanhoArquivo(baixado.tamanho)}
                  </span>
                  <button type="button" className="botao botao--texto botao--pequeno botao--perigo-texto" onClick={remover}>
                    <Icone nome="lixeira" tamanho={18} />
                    <span>Remover do aparelho</span>
                  </button>
                </div>
              ) : (
                <Botao variante="contorno" bloco icone="download" disabled={!online || !livro.disponivel} onClick={baixar}>
                  Baixar para ler offline{tamanho ? ` · ${tamanhoArquivo(tamanho)}` : ''}
                </Botao>
              )}
              {ativo?.erro && (
                <p className="alerta alerta--erro" role="alert">
                  {ativo.erro}
                </p>
              )}
            </>
          )}
          {livro.modelo === 'flipbook' && (
            <>
              <Botao bloco icone="externo" carregando={abrindoSite} disabled={!online} onClick={lerNoSite}>
                Ler no leitor do site
              </Botao>
              <p className="livro__nota">Este livro usa o formato antigo e abre no leitor do site, com o seu acesso. Não é possível baixá-lo para ler sem internet.</p>
            </>
          )}
          {livro.modelo === '' && (
            <>
              <Botao bloco icone="relogio" disabled>
                Em breve
              </Botao>
              <p className="livro__nota">O arquivo deste livro ainda está sendo preparado. Ele aparece aqui assim que estiver pronto.</p>
            </>
          )}
        </div>

        {livro.descricao && (
          <section className="livro__secao" aria-labelledby="titulo-sobre">
            <h2 id="titulo-sobre" className="secao__titulo">
              Sobre o livro
            </h2>
            <Descricao texto={livro.descricao} />
          </section>
        )}

        <Ficha livro={livro} />
        {erro && (
          <p className="alerta alerta--erro" role="alert">
            {erro}
          </p>
        )}
      </div>
    </>
  );
}

function Descricao({ texto }: { texto: string }) {
  const longo = texto.length > 420;
  const [aberto, setAberto] = useState(!longo);
  return (
    <div className="descricao">
      {(aberto ? texto : texto.slice(0, 380).replace(/\s+\S*$/, '') + '…').split(/\n{2,}/).map((par, i) => (
        <p key={i}>{par}</p>
      ))}
      {longo && (
        <button type="button" className="link link--botao" onClick={() => setAberto((v) => !v)} aria-expanded={aberto}>
          {aberto ? 'Mostrar menos' : 'Ler mais'}
        </button>
      )}
    </div>
  );
}

function Ficha({ livro }: { livro: Livro }) {
  const f = livro.ficha;
  if (!f) return null;
  const itens: { rotulo: string; valor: React.ReactNode }[] = [];
  if (f.paginas) itens.push({ rotulo: 'Páginas', valor: f.paginas });
  if (f.idioma) itens.push({ rotulo: 'Idioma', valor: f.idioma });
  if (f.ano) itens.push({ rotulo: 'Ano', valor: f.ano });
  if (f.editora) itens.push({ rotulo: 'Editora', valor: f.editora });
  if (f.dimensoes) itens.push({ rotulo: 'Dimensões', valor: f.dimensoes });
  if (f.isbn) itens.push({ rotulo: 'ISBN', valor: f.isbn });
  if (f.doi)
    itens.push({
      rotulo: 'DOI',
      valor: (
        <button type="button" className="link link--botao ficha__doi" onClick={() => void abrirExterno(`https://doi.org/${f.doi}`)}>
          {f.doi}
          <Icone nome="externo" tamanho={14} />
        </button>
      ),
    });
  if (!itens.length) return null;
  return (
    <section className="livro__secao" aria-labelledby="titulo-ficha">
      <h2 id="titulo-ficha" className="secao__titulo">
        Ficha técnica
      </h2>
      <dl className="ficha">
        {itens.map((i) => (
          <div key={i.rotulo} className={`ficha__item${i.rotulo === 'DOI' || i.rotulo === 'ISBN' ? ' ficha__item--largo' : ''}`}>
            <dt>{i.rotulo}</dt>
            <dd>{i.valor}</dd>
          </div>
        ))}
      </dl>
      {f.pagina_doi && (
        <button type="button" className="link link--botao" onClick={() => void abrirExterno(f.pagina_doi!)}>
          Ver página do livro no site
          <Icone nome="externo" tamanho={14} />
        </button>
      )}
      {livro.atualizado_em && <p className="texto-suave texto-pequeno">Arquivo atualizado em {dataLonga(livro.atualizado_em)}</p>}
    </section>
  );
}
