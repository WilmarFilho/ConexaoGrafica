import { useState, type FormEvent } from 'react';
import { Link, useNavigate } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../../api';
import { Botao, Campo, CampoSenha } from '../../componentes/ui';
import { guardarLoginPendente, useSessao } from '../../estado/sessao';
import { dadosDoAparelho } from '../../plataforma/aparelho';
import { lerPref, gravarPref } from '../../plataforma/armazenamento';
import { AvisoDemonstracao, TelaAuth } from './TelaAuth';

export function Entrar() {
  const { entrar, aviso, limparAviso } = useSessao();
  const navegar = useNavigate();
  const [email, setEmail] = useState(() => lerPref('ultimo-email', ''));
  const [senha, setSenha] = useState('');
  const [manter, setManter] = useState(true);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [erros, setErros] = useState<{ email?: string; senha?: string }>({});

  async function enviar(e: FormEvent) {
    e.preventDefault();
    const novos: typeof erros = {};
    if (!email.trim()) novos.email = 'Informe seu e-mail.';
    if (!senha) novos.senha = 'Informe sua senha.';
    setErros(novos);
    if (Object.keys(novos).length) return;
    setErro(null);
    limparAviso();
    setEnviando(true);
    try {
      const r = await api.login({ email: email.trim(), senha, aparelho: await dadosDoAparelho() });
      gravarPref('ultimo-email', email.trim());
      await entrar(r, manter);
    } catch (falha) {
      if (falha instanceof ErroApi && falha.codigo === 'limite_aparelhos') {
        guardarLoginPendente({ email: email.trim(), senha, aparelhos: falha.aparelhos, mensagem: falha.message, manterConectado: manter });
        navegar('/limite-aparelhos');
        return;
      }
      setErro(falha instanceof ErroApi && falha.status === 401 ? 'E-mail ou senha incorretos.' : mensagemDeErro(falha));
    } finally {
      setEnviando(false);
    }
  }

  return (
    <TelaAuth
      titulo="Acesse sua conta"
      subtitulo="Use o mesmo e-mail e senha da loja conexaoeditora.com.br."
      rodape={
        <p>
          Ainda não tem conta? <Link to="/criar-conta">Criar conta</Link>
        </p>
      }
    >
      <AvisoDemonstracao>
        Use qualquer e-mail e senha. Para ver o limite de aparelhos, entre com <b>limite@exemplo.com</b>; a senha <b>errada</b> simula erro.
      </AvisoDemonstracao>
      {aviso && (
        <p className="alerta alerta--info" role="status">
          {aviso}
        </p>
      )}
      <form className="formulario" onSubmit={enviar} noValidate>
        <Campo
          rotulo="E-mail"
          icone="email"
          type="email"
          inputMode="email"
          autoComplete="username"
          autoCapitalize="none"
          spellCheck={false}
          placeholder="voce@exemplo.com"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          erro={erros.email}
        />
        <CampoSenha rotulo="Senha" autoComplete="current-password" placeholder="Sua senha" value={senha} onChange={(e) => setSenha(e.target.value)} erro={erros.senha} />
        <div className="formulario__linha">
          <label className="marcar">
            <input type="checkbox" checked={manter} onChange={(e) => setManter(e.target.checked)} />
            <span>Lembrar de mim</span>
          </label>
          <Link to="/esqueci-senha" className="link">
            Esqueci minha senha
          </Link>
        </div>
        {erro && (
          <p className="alerta alerta--erro" role="alert">
            {erro}
          </p>
        )}
        <Botao type="submit" bloco carregando={enviando}>
          Entrar
        </Botao>
      </form>
    </TelaAuth>
  );
}
