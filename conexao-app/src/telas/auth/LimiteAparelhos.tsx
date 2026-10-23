import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../../api';
import { ItemAparelho } from '../../componentes/ItemAparelho';
import { guardarLoginPendente, loginPendente, useSessao } from '../../estado/sessao';
import { dadosDoAparelho } from '../../plataforma/aparelho';
import { TelaAuth } from './TelaAuth';

export function LimiteAparelhos() {
  const navegar = useNavigate();
  const { entrar } = useSessao();
  const [pendente, setPendente] = useState(loginPendente);
  const [desconectando, setDesconectando] = useState<number | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  if (!pendente) return <Navigate to="/entrar" replace />;

  async function desconectar(id: number) {
    if (!pendente) return;
    setErro(null);
    setDesconectando(id);
    try {
      const r = await api.login({ email: pendente.email, senha: pendente.senha, aparelho: await dadosDoAparelho(), substituir: id });
      guardarLoginPendente(null);
      await entrar(r, pendente.manterConectado);
    } catch (falha) {
      if (falha instanceof ErroApi && falha.codigo === 'limite_aparelhos') {
        const novo = { ...pendente, aparelhos: falha.aparelhos, mensagem: falha.message };
        guardarLoginPendente(novo);
        setPendente(novo);
      }
      setErro(mensagemDeErro(falha));
    } finally {
      setDesconectando(null);
    }
  }

  return (
    <TelaAuth
      titulo="Limite de aparelhos"
      subtitulo={pendente.mensagem || 'Sua conta já está conectada no número máximo de aparelhos.'}
      voltar="/entrar"
    >
      <p className="auth__texto">Escolha um aparelho para desconectar. Os livros baixados nele serão apagados no próximo uso, e você entra neste aparelho.</p>
      <ul className="lista-cartoes" aria-label="Aparelhos conectados">
        {pendente.aparelhos.map((a) => (
          <ItemAparelho
            key={a.id}
            aparelho={a}
            acao={{ texto: 'Desconectar', carregando: desconectando === a.id, desativado: desconectando !== null, aoClicar: () => void desconectar(a.id) }}
          />
        ))}
      </ul>
      {erro && (
        <p className="alerta alerta--erro" role="alert">
          {erro}
        </p>
      )}
      <button
        type="button"
        className="botao botao--texto botao--bloco"
        onClick={() => {
          guardarLoginPendente(null);
          navegar('/entrar');
        }}
      >
        <span>Cancelar</span>
      </button>
    </TelaAuth>
  );
}
