import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { aoReceber401, api, definirToken } from '../api';
import type { RespostaLogin, Usuario } from '../api/tipos';
import { apagarDadosDaConta, apagarSessao, gravarToken, gravarUsuario, lerToken, lerUsuarioSalvo } from '../plataforma/armazenamento';
import { carregarBaixados, removerTodosBaixados } from './downloads';
import { sincronizarTudo } from './leitura';
import { estaOnline, quandoReconectar } from './rede';

type Fase = 'carregando' | 'anonimo' | 'logado';

interface ContextoSessao {
  fase: Fase;
  usuario: Usuario | null;
  /** aviso mostrado no login (ex.: sessão terminou em outro aparelho) */
  aviso: string | null;
  entrar(resposta: RespostaLogin, manterConectado?: boolean): Promise<void>;
  sair(): Promise<void>;
  atualizarUsuario(u: Usuario): void;
  limparAviso(): void;
}

const Contexto = createContext<ContextoSessao | null>(null);

/** Apaga tudo o que é da conta neste aparelho: token, caches e livros baixados. */
async function limparTudo() {
  definirToken(null);
  await apagarSessao();
  apagarDadosDaConta();
  await removerTodosBaixados();
}

export function SessaoProvider({ children }: { children: ReactNode }) {
  const [fase, setFase] = useState<Fase>('carregando');
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);

  const encerrarPorServidor = useCallback(() => {
    void limparTudo().then(() => {
      setUsuario(null);
      setAviso('Sua sessão terminou neste aparelho. Entre de novo para continuar.');
      setFase('anonimo');
    });
  }, []);

  useEffect(() => {
    aoReceber401(encerrarPorServidor);
    let vivo = true;
    (async () => {
      const token = await lerToken();
      if (!token) {
        // Sem sessão válida: livros que tenham ficado no aparelho são apagados.
        await limparTudo();
        if (vivo) setFase('anonimo');
        return;
      }
      definirToken(token);
      const salvo = await lerUsuarioSalvo<Usuario>();
      await carregarBaixados();
      if (!vivo) return;
      setUsuario(salvo);
      setFase('logado');
      if (estaOnline()) {
        try {
          const eu = await api.eu();
          if (!vivo) return;
          setUsuario(eu.usuario);
          await gravarUsuario(eu.usuario);
          void sincronizarTudo();
        } catch {
          /* 401 já tratado; outros erros: segue com o usuário salvo */
        }
      }
    })();
    const parar = quandoReconectar(() => {
      if (fase !== 'anonimo') void sincronizarTudo();
    });
    return () => {
      vivo = false;
      parar();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const entrar = useCallback(async (r: RespostaLogin, manterConectado = true) => {
    // Começa limpo: nada de outra conta fica no aparelho.
    apagarDadosDaConta();
    await removerTodosBaixados();
    definirToken(r.token);
    await gravarToken(r.token, manterConectado);
    await gravarUsuario(r.usuario);
    await carregarBaixados();
    setUsuario(r.usuario);
    setAviso(null);
    setFase('logado');
  }, []);

  const sair = useCallback(async () => {
    if (estaOnline()) {
      try {
        await api.sair();
      } catch {
        /* sai mesmo assim */
      }
    }
    await limparTudo();
    setUsuario(null);
    setFase('anonimo');
  }, []);

  const atualizarUsuario = useCallback((u: Usuario) => {
    setUsuario(u);
    void gravarUsuario(u);
  }, []);

  const valor = useMemo(
    () => ({ fase, usuario, aviso, entrar, sair, atualizarUsuario, limparAviso: () => setAviso(null) }),
    [fase, usuario, aviso, entrar, sair, atualizarUsuario]
  );
  return <Contexto.Provider value={valor}>{children}</Contexto.Provider>;
}

export function useSessao() {
  const c = useContext(Contexto);
  if (!c) throw new Error('useSessao fora do SessaoProvider');
  return c;
}

// ---------- Login em andamento (tela de limite de aparelhos) ----------
// Fica só na memória: a senha nunca vai para o histórico nem para o armazenamento.

export interface LoginPendente {
  email: string;
  senha: string;
  aparelhos: import('../api/tipos').Aparelho[];
  mensagem: string;
  manterConectado: boolean;
}

let pendente: LoginPendente | null = null;
export function guardarLoginPendente(p: LoginPendente | null) {
  pendente = p;
}
export function loginPendente() {
  return pendente;
}
