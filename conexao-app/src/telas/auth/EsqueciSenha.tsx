import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api, mensagemDeErro } from '../../api';
import { Icone } from '../../componentes/Icone';
import { Botao, Campo } from '../../componentes/ui';
import { lerPref } from '../../plataforma/armazenamento';
import { TelaAuth } from './TelaAuth';

export function EsqueciSenha() {
  const [email, setEmail] = useState(() => lerPref('ultimo-email', ''));
  const [enviando, setEnviando] = useState(false);
  const [enviado, setEnviado] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  async function enviar(e: FormEvent) {
    e.preventDefault();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      setErro('Informe um e-mail válido.');
      return;
    }
    setErro(null);
    setEnviando(true);
    try {
      await api.senha(email.trim());
      setEnviado(true);
    } catch (falha) {
      setErro(mensagemDeErro(falha));
    } finally {
      setEnviando(false);
    }
  }

  if (enviado) {
    return (
      <TelaAuth titulo="Confira seu e-mail" voltar="/entrar">
        <div className="confirmacao">
          <div className="confirmacao__icone">
            <Icone nome="email" tamanho={32} />
          </div>
          <p>
            Se existir uma conta com <b>{email.trim()}</b>, você vai receber um e-mail com o link para criar uma nova senha. Veja também a caixa de spam.
          </p>
          <Link to="/entrar" className="botao botao--primario botao--bloco">
            <span>Voltar para entrar</span>
          </Link>
        </div>
      </TelaAuth>
    );
  }

  return (
    <TelaAuth titulo="Esqueci minha senha" subtitulo="Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha." voltar="/entrar">
      <form className="formulario" onSubmit={enviar} noValidate>
        <Campo
          rotulo="E-mail"
          icone="email"
          type="email"
          inputMode="email"
          autoComplete="email"
          autoCapitalize="none"
          spellCheck={false}
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          erro={erro}
        />
        <Botao type="submit" bloco carregando={enviando}>
          Enviar link
        </Botao>
      </form>
    </TelaAuth>
  );
}
