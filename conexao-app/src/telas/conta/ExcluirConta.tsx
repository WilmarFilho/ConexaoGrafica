import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../../api';
import { CONFIG } from '../../config';
import { Icone } from '../../componentes/Icone';
import { Botao, Cabecalho, Campo, CampoSenha } from '../../componentes/ui';
import { useOnline } from '../../estado/rede';
import { useSessao } from '../../estado/sessao';
import { abrirSistema } from '../../plataforma/nativo';
import { TelaAuth } from '../auth/TelaAuth';

const PALAVRA = 'EXCLUIR';

/** O que acontece ao excluir a conta (mesmo texto no app e na página pública). */
export function ExplicacaoExclusao() {
  return (
    <div className="exclusao__explicacao">
      <p>Ao excluir sua conta da Conexão Editora:</p>
      <ul className="exclusao__lista">
        <li>
          <Icone nome="conta" tamanho={20} />
          <span>sua conta e seus dados de acesso (nome, e-mail e senha) são apagados;</span>
        </li>
        <li>
          <Icone nome="celular" tamanho={20} />
          <span>todos os aparelhos conectados são desconectados e os livros baixados neles são apagados;</span>
        </li>
        <li>
          <Icone nome="marcador" tamanho={20} />
          <span>seu progresso de leitura e as páginas marcadas são apagados;</span>
        </li>
        <li>
          <Icone nome="livro" tamanho={20} />
          <span>você perde o acesso aos e-books no app e no site.</span>
        </li>
      </ul>
      <p className="texto-suave">
        Os registros dos pedidos já feitos são guardados pela editora somente pelo tempo exigido pela legislação fiscal e não são usados para
        outra finalidade.
      </p>
      <p className="exclusao__aviso">
        <Icone nome="alerta" tamanho={18} /> Essa ação não pode ser desfeita.
      </p>
    </div>
  );
}

/** Senha + digitar EXCLUIR + botão. Depois de excluir, mostra a despedida e limpa o aparelho. */
export function FormularioExclusao() {
  const { encerrarAposExclusao } = useSessao();
  const navegar = useNavigate();
  const online = useOnline();
  const [senha, setSenha] = useState('');
  const [palavra, setPalavra] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [erroSenha, setErroSenha] = useState<string | null>(null);

  const confirmado = palavra.trim().toUpperCase() === PALAVRA;

  async function enviar(e: FormEvent) {
    e.preventDefault();
    setErro(null);
    setErroSenha(null);
    if (!senha) {
      setErroSenha('Informe sua senha.');
      return;
    }
    if (!confirmado) {
      setErro(`Digite ${PALAVRA} para confirmar.`);
      return;
    }
    setEnviando(true);
    try {
      await api.excluirConta(senha);
      // A rota de despedida existe com e sem sessão: navega antes de limpar.
      navegar('/conta-excluida', { replace: true });
      await encerrarAposExclusao();
    } catch (falha) {
      if (falha instanceof ErroApi && falha.codigo === 'invalido') setErroSenha(falha.message || 'Senha incorreta.');
      else setErro(mensagemDeErro(falha));
      setEnviando(false);
    }
  }

  return (
    <form className="formulario exclusao__form" onSubmit={enviar} noValidate>
      <CampoSenha rotulo="Sua senha" autoComplete="current-password" value={senha} onChange={(e) => setSenha(e.target.value)} erro={erroSenha} />
      <Campo
        rotulo={`Para confirmar, digite ${PALAVRA}`}
        icone="alerta"
        autoCapitalize="characters"
        autoComplete="off"
        spellCheck={false}
        value={palavra}
        onChange={(e) => setPalavra(e.target.value)}
        placeholder={PALAVRA}
      />
      {erro && (
        <p className="alerta alerta--erro" role="alert">
          {erro}
        </p>
      )}
      {!online && <p className="alerta alerta--info">Conecte-se à internet para excluir a conta.</p>}
      <Botao type="submit" variante="perigo" bloco icone="lixeira" carregando={enviando} disabled={!online || !confirmado || !senha}>
        Excluir minha conta
      </Botao>
    </form>
  );
}

/** Tela dentro do app: Conta → Excluir conta. */
export function ExcluirConta() {
  const { usuario } = useSessao();
  return (
    <>
      <Cabecalho titulo="Excluir conta" voltar="/conta" />
      <div className="pagina pagina--estreita exclusao">
        {usuario && (
          <p className="exclusao__conta">
            Conta: <b>{usuario.email}</b>
          </p>
        )}
        <ExplicacaoExclusao />
        <FormularioExclusao />
      </div>
    </>
  );
}

/** Página pública /excluir-conta (link informado ao Google Play). Funciona sem login. */
export function ExcluirContaPublica() {
  const { fase, usuario } = useSessao();
  const logado = fase === 'logado';
  return (
    <TelaAuth titulo="Excluir sua conta" subtitulo="Conexão Editora · app do leitor" voltar={logado ? '/conta' : undefined}>
      <div className="exclusao exclusao--publica">
        <section className="cartao">
          <h2 className="cartao__titulo">Como excluir</h2>
          <ol className="exclusao__passos">
            <li>
              <b>No app:</b> abra a aba <b>Conta</b> e toque em <b>Excluir conta</b>.
            </li>
            <li>
              <b>Por esta página:</b> entre com o e-mail e a senha da sua conta e confirme a exclusão.
            </li>
          </ol>
        </section>
        <ExplicacaoExclusao />
        {logado ? (
          <>
            {usuario && (
              <p className="exclusao__conta">
                Conectado como <b>{usuario.email}</b>
              </p>
            )}
            <FormularioExclusao />
          </>
        ) : (
          <Link to="/entrar?voltar=/excluir-conta" className="botao botao--primario botao--bloco">
            <span>Entrar para excluir a conta</span>
          </Link>
        )}
        {CONFIG.suporteEmail && (
          <p className="texto-suave exclusao__suporte">
            Não consegue entrar? Escreva para{' '}
            <button
              type="button"
              className="link link--botao"
              onClick={() => abrirSistema(`mailto:${CONFIG.suporteEmail}?subject=${encodeURIComponent('Excluir minha conta')}`)}
            >
              {CONFIG.suporteEmail}
            </button>{' '}
            a partir do e-mail da conta, pedindo a exclusão.
          </p>
        )}
      </div>
    </TelaAuth>
  );
}

/** Despedida depois da exclusão. */
export function ContaExcluida() {
  const navegar = useNavigate();
  return (
    <TelaAuth titulo="Conta excluída">
      <div className="confirmacao">
        <div className="confirmacao__icone">
          <Icone nome="check-circulo" tamanho={32} />
        </div>
        <p>Sua conta foi excluída e os dados deste aparelho foram apagados. Obrigado por ter lido com a Conexão Editora.</p>
        <Botao bloco onClick={() => navegar('/entrar', { replace: true })}>
          Voltar ao início
        </Botao>
      </div>
    </TelaAuth>
  );
}
