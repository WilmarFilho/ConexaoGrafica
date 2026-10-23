import { useEffect, useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../api';
import { useAviso } from '../componentes/Feedback';
import { Icone } from '../componentes/Icone';
import { Botao, Cabecalho } from '../componentes/ui';
import { AvisoDemonstracao } from './auth/TelaAuth';
import { useSessao } from '../estado/sessao';

const ESPERA_REENVIO = 60;

export function ConfirmarEmail() {
  const { usuario, atualizarUsuario } = useSessao();
  const navegar = useNavigate();
  const avisar = useAviso();
  const [codigo, setCodigo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [espera, setEspera] = useState(ESPERA_REENVIO);

  useEffect(() => {
    if (espera <= 0) return;
    const t = setTimeout(() => setEspera((s) => s - 1), 1000);
    return () => clearTimeout(t);
  }, [espera]);

  async function confirmar(e: FormEvent) {
    e.preventDefault();
    if (!/^\d{6}$/.test(codigo)) {
      setErro('Digite os 6 números do código.');
      return;
    }
    setErro(null);
    setEnviando(true);
    try {
      await api.confirmarEmail(codigo);
      if (usuario) atualizarUsuario({ ...usuario, email_verificado: true });
      avisar('E-mail confirmado. Atualizando seus pedidos…', 'sucesso');
      navegar('/pedidos', { replace: true });
    } catch (falha) {
      setErro(falha instanceof ErroApi && falha.codigo === 'invalido' ? falha.message || 'Código incorreto ou vencido.' : mensagemDeErro(falha));
    } finally {
      setEnviando(false);
    }
  }

  async function reenviar() {
    setErro(null);
    try {
      await api.enviarCodigoEmail();
      setEspera(ESPERA_REENVIO);
      avisar('Enviamos um novo código.', 'sucesso');
    } catch (falha) {
      setErro(mensagemDeErro(falha));
    }
  }

  return (
    <>
      <Cabecalho titulo="Confirmar e-mail" voltar="/pedidos" />
      <div className="pagina pagina--estreita">
        <div className="confirmacao">
          <div className="confirmacao__icone">
            <Icone nome="email" tamanho={32} />
          </div>
          <p>
            Enviamos um código de 6 números para <b>{usuario?.email ?? 'o e-mail da sua conta'}</b>. Ele vale por alguns minutos.
          </p>
        </div>
        <AvisoDemonstracao>O código de exemplo é <b>123456</b>.</AvisoDemonstracao>
        <form className="formulario" onSubmit={confirmar} noValidate>
          <div className={`campo${erro ? ' campo--erro' : ''}`}>
            <label htmlFor="codigo">Código</label>
            <div className="campo__caixa">
              <input
                id="codigo"
                className="campo__codigo"
                inputMode="numeric"
                autoComplete="one-time-code"
                pattern="\d{6}"
                maxLength={6}
                value={codigo}
                onChange={(e) => setCodigo(e.target.value.replace(/\D/g, '').slice(0, 6))}
                aria-invalid={erro ? true : undefined}
                aria-describedby={erro ? 'codigo-erro' : undefined}
                autoFocus
              />
            </div>
            {erro && (
              <p id="codigo-erro" className="campo__erro" role="alert">
                {erro}
              </p>
            )}
          </div>
          <Botao type="submit" bloco carregando={enviando}>
            Confirmar
          </Botao>
          <button type="button" className="botao botao--texto botao--bloco" disabled={espera > 0} onClick={reenviar}>
            <span>{espera > 0 ? `Reenviar código em ${espera} s` : 'Reenviar código'}</span>
          </button>
        </form>
      </div>
    </>
  );
}
