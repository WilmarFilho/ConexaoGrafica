import { useCallback, useEffect, useState } from 'react';
import { api, ErroApi, mensagemDeErro, modoDemonstracao } from '../api';
import type { ListaAparelhos } from '../api/tipos';
import { CONFIG } from '../config';
import { useAviso, useConfirmar } from '../componentes/Feedback';
import { Icone } from '../componentes/Icone';
import { ItemAparelho } from '../componentes/ItemAparelho';
import { Botao, Cabecalho, Carregando, Segmentado } from '../componentes/ui';
import { removerTodosBaixados, tamanhoTotalBaixado, useDownloads } from '../estado/downloads';
import { useOnline } from '../estado/rede';
import { useSessao } from '../estado/sessao';
import { definirTema, useTema, type PreferenciaTema } from '../estado/tema';
import { abrirExterno, abrirSistema } from '../plataforma/nativo';
import { tamanhoArquivo } from '../util/formato';

export function Conta() {
  const { usuario, sair } = useSessao();
  const online = useOnline();
  const avisar = useAviso();
  const confirmar = useConfirmar();
  const tema = useTema();
  const { baixados } = useDownloads();
  const [aparelhos, setAparelhos] = useState<ListaAparelhos | null>(null);
  const [erroAparelhos, setErroAparelhos] = useState<string | null>(null);
  const [desconectando, setDesconectando] = useState<number | null>(null);
  const [saindo, setSaindo] = useState(false);

  const carregarAparelhos = useCallback(async () => {
    setErroAparelhos(null);
    try {
      setAparelhos(await api.aparelhos());
    } catch (e) {
      if (!(e instanceof ErroApi && e.status === 401)) setErroAparelhos(mensagemDeErro(e));
    }
  }, []);

  useEffect(() => {
    if (online) void carregarAparelhos();
  }, [online, carregarAparelhos]);

  const listaBaixados = Object.values(baixados);
  const espaco = tamanhoTotalBaixado(baixados);

  async function desconectar(id: number, nome: string) {
    const sim = await confirmar({
      titulo: `Desconectar “${nome}”?`,
      texto: 'Esse aparelho sai da sua conta e os livros baixados nele serão apagados no próximo uso.',
      confirmar: 'Desconectar',
      perigo: true,
    });
    if (!sim) return;
    setDesconectando(id);
    try {
      await api.desconectarAparelho(id);
      avisar('Aparelho desconectado.', 'sucesso');
      await carregarAparelhos();
    } catch (e) {
      avisar(mensagemDeErro(e), 'erro');
    } finally {
      setDesconectando(null);
    }
  }

  async function removerTodos() {
    const sim = await confirmar({
      titulo: 'Remover todos os downloads?',
      texto: `${listaBaixados.length} ${listaBaixados.length === 1 ? 'livro será apagado' : 'livros serão apagados'} deste aparelho. Eles continuam na sua estante para ler online ou baixar de novo.`,
      confirmar: 'Remover todos',
      perigo: true,
    });
    if (!sim) return;
    await removerTodosBaixados();
    avisar('Downloads removidos.');
  }

  async function confirmarSaida() {
    const sim = await confirmar({
      titulo: 'Sair da conta?',
      texto: listaBaixados.length
        ? 'Os livros baixados neste aparelho serão apagados. Seu progresso e marcadores ficam guardados na sua conta.'
        : 'Você precisará entrar de novo para ler seus livros.',
      confirmar: 'Sair',
      perigo: true,
    });
    if (!sim) return;
    setSaindo(true);
    await sair();
  }

  return (
    <>
      <Cabecalho titulo="Minha conta" grande />
      <div className="pagina conta">
        {usuario && (
          <section className="cartao perfil">
            <span className="perfil__avatar" aria-hidden="true">
              {usuario.iniciais || usuario.nome.slice(0, 2).toUpperCase()}
            </span>
            <div className="perfil__texto">
              <strong>{usuario.nome}</strong>
              <span>{usuario.email}</span>
              {usuario.email_verificado && (
                <small className="perfil__verificado">
                  <Icone nome="check-circulo" tamanho={14} /> E-mail confirmado
                </small>
              )}
            </div>
          </section>
        )}

        <section className="secao" aria-labelledby="titulo-aparelhos">
          <div className="secao__topo">
            <h2 id="titulo-aparelhos" className="secao__titulo">
              Aparelhos conectados
            </h2>
            {aparelhos && (
              <span className="secao__contagem">
                {aparelhos.aparelhos.length} de {aparelhos.limite}
              </span>
            )}
          </div>
          {!online && <p className="texto-suave">Conecte-se à internet para ver e gerenciar seus aparelhos.</p>}
          {online && !aparelhos && !erroAparelhos && <Carregando texto="Carregando aparelhos…" />}
          {erroAparelhos && (
            <p className="alerta alerta--erro" role="alert">
              {erroAparelhos}
            </p>
          )}
          {aparelhos && (
            <>
              <ul className="lista-cartoes">
                {aparelhos.aparelhos.map((a) => (
                  <ItemAparelho
                    key={a.id}
                    aparelho={a}
                    acao={{ texto: 'Desconectar', carregando: desconectando === a.id, desativado: desconectando !== null, aoClicar: () => void desconectar(a.id, a.nome) }}
                  />
                ))}
              </ul>
              <p className="texto-suave texto-pequeno">Você pode ler em até {aparelhos.limite} aparelhos ao mesmo tempo.</p>
            </>
          )}
        </section>

        <section className="secao" aria-labelledby="titulo-espaco">
          <h2 id="titulo-espaco" className="secao__titulo">
            Espaço utilizado
          </h2>
          <div className="cartao espaco">
            <span className="espaco__icone">
              <Icone nome="baixado" />
            </span>
            <div className="espaco__texto">
              <strong>{tamanhoArquivo(espaco)}</strong>
              <span>
                {listaBaixados.length === 0 ? 'Nenhum livro baixado' : `${listaBaixados.length} ${listaBaixados.length === 1 ? 'livro baixado' : 'livros baixados'} neste aparelho`}
              </span>
            </div>
            {listaBaixados.length > 0 && (
              <Botao variante="contorno" pequeno onClick={removerTodos}>
                Remover todos
              </Botao>
            )}
          </div>
        </section>

        <section className="secao" aria-labelledby="titulo-ajustes">
          <h2 id="titulo-ajustes" className="secao__titulo">
            Ajustes do app
          </h2>
          <div className="cartao">
            <p className="cartao__rotulo" id="rotulo-tema">
              Aparência
            </p>
            <Segmentado<PreferenciaTema>
              rotulo="Aparência do app"
              valor={tema}
              aoMudar={definirTema}
              opcoes={[
                { valor: 'sistema', texto: 'Sistema', icone: 'celular' },
                { valor: 'claro', texto: 'Claro', icone: 'sol' },
                { valor: 'escuro', texto: 'Escuro', icone: 'lua' },
              ]}
            />
            <p className="texto-suave texto-pequeno">Os temas de leitura (claro, sépia e escuro) ficam nos ajustes dentro do leitor.</p>
          </div>
        </section>

        <section className="secao" aria-labelledby="titulo-ajuda">
          <h2 id="titulo-ajuda" className="secao__titulo">
            Ajuda e suporte
          </h2>
          <ul className="menu-lista cartao">
            {CONFIG.suporteWhatsapp && (
              <li>
                <button type="button" onClick={() => abrirSistema(`https://wa.me/${CONFIG.suporteWhatsapp}?text=${encodeURIComponent('Olá! Preciso de ajuda com o app da Conexão Editora.')}`)}>
                  <Icone nome="whatsapp" />
                  <span>
                    WhatsApp
                    <small>Atendimento em horário comercial</small>
                  </span>
                  <Icone nome="externo" tamanho={18} />
                </button>
              </li>
            )}
            {CONFIG.suporteEmail && (
              <li>
                <button type="button" onClick={() => abrirSistema(`mailto:${CONFIG.suporteEmail}?subject=${encodeURIComponent('Ajuda com o app')}`)}>
                  <Icone nome="email" />
                  <span>
                    E-mail
                    <small>{CONFIG.suporteEmail}</small>
                  </span>
                  <Icone nome="externo" tamanho={18} />
                </button>
              </li>
            )}
            <li>
              <button type="button" onClick={() => void abrirExterno(CONFIG.lojaUrl)}>
                <Icone nome="loja" />
                <span>
                  Loja Conexão Editora
                  <small>Conheça novos títulos</small>
                </span>
                <Icone nome="externo" tamanho={18} />
              </button>
            </li>
            <li>
              <button type="button" onClick={() => void abrirExterno(`${CONFIG.siteUrl}/politica-de-privacidade/`)}>
                <Icone nome="escudo" />
                <span>Política de privacidade</span>
                <Icone nome="externo" tamanho={18} />
              </button>
            </li>
          </ul>
        </section>

        <Botao variante="perigo" bloco icone="sair" carregando={saindo} onClick={confirmarSaida}>
          Sair da conta
        </Botao>
        <p className="versao">
          Conexão Editora · versão {CONFIG.versao}
          {modoDemonstracao ? ' · modo de demonstração' : ''}
        </p>
      </div>
    </>
  );
}
