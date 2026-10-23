import { lazy, Suspense, useEffect } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes, useLocation, useNavigate } from 'react-router';
import { App as AppNativo } from '@capacitor/app';
import { SplashScreen } from '@capacitor/splash-screen';
import { FeedbackProvider } from './componentes/Feedback';
import { BarraAbas } from './componentes/BarraAbas';
import { FaixaSemInternet } from './componentes/FaixaSemInternet';
import { LimiteDeErro } from './componentes/LimiteDeErro';
import { Carregando } from './componentes/ui';
import { SessaoProvider, useSessao } from './estado/sessao';
import { tratarVoltar } from './estado/voltar';
import { ehNativo } from './plataforma/nativo';
import { Abertura } from './telas/Abertura';
import { Entrar } from './telas/auth/Entrar';
import { CriarConta } from './telas/auth/CriarConta';
import { EsqueciSenha } from './telas/auth/EsqueciSenha';
import { LimiteAparelhos } from './telas/auth/LimiteAparelhos';
import { Estante } from './telas/Estante';
import { LivroDetalhe } from './telas/Livro';
import { Pedidos } from './telas/Pedidos';
import { PedidoDetalhe } from './telas/Pedido';
import { ConfirmarEmail } from './telas/ConfirmarEmail';
import { Conta } from './telas/Conta';

// O leitor (pdf.js) é carregado só quando um livro é aberto.
const Leitor = lazy(() => import('./telas/leitor/Leitor').then((m) => ({ default: m.Leitor })));

const basename = import.meta.env.BASE_URL.replace(/\/$/, '') || undefined;

export function App() {
  return (
    <BrowserRouter basename={basename}>
      <FeedbackProvider>
        <SessaoProvider>
          <Rotas />
          <IntegracaoNativa />
        </SessaoProvider>
      </FeedbackProvider>
    </BrowserRouter>
  );
}

const RAIZES = ['/estante', '/pedidos', '/conta', '/entrar'];

function IntegracaoNativa() {
  const { fase } = useSessao();
  const navegar = useNavigate();
  const local = useLocation();

  useEffect(() => {
    if (fase !== 'carregando' && ehNativo) void SplashScreen.hide({ fadeOutDuration: 200 }).catch(() => undefined);
  }, [fase]);

  // Tecla Esc (web) e botão voltar do Android: fecha painéis antes de navegar.
  useEffect(() => {
    const tecla = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !e.defaultPrevented) tratarVoltar();
    };
    window.addEventListener('keydown', tecla);
    return () => window.removeEventListener('keydown', tecla);
  }, []);

  useEffect(() => {
    if (!ehNativo) return;
    const h = AppNativo.addListener('backButton', () => {
      if (tratarVoltar()) return;
      if (RAIZES.includes(local.pathname)) {
        if (local.pathname !== '/estante' && local.pathname !== '/entrar') navegar('/estante');
        else void AppNativo.minimizeApp().catch(() => AppNativo.exitApp());
      } else {
        navegar(-1);
      }
    });
    return () => {
      void h.then((x) => x.remove());
    };
  }, [local.pathname, navegar]);
  return null;
}

function Rotas() {
  const { fase } = useSessao();
  if (fase === 'carregando') return <Abertura />;

  if (fase === 'anonimo') {
    return (
      <Routes>
        <Route path="/entrar" element={<Entrar />} />
        <Route path="/criar-conta" element={<CriarConta />} />
        <Route path="/esqueci-senha" element={<EsqueciSenha />} />
        <Route path="/limite-aparelhos" element={<LimiteAparelhos />} />
        <Route path="*" element={<Navigate to="/entrar" replace />} />
      </Routes>
    );
  }

  return (
    <Routes>
      <Route element={<LayoutAbas />}>
        <Route path="/estante" element={<Estante />} />
        <Route path="/livro/:id" element={<LivroDetalhe />} />
        <Route path="/pedidos" element={<Pedidos />} />
        <Route path="/pedidos/confirmar-email" element={<ConfirmarEmail />} />
        <Route path="/pedidos/:ref" element={<PedidoDetalhe />} />
        <Route path="/conta" element={<Conta />} />
      </Route>
      <Route
        path="/ler/:id"
        element={
          <LimiteDeErro>
            <Suspense fallback={<div className="leitor leitor--carregando"><Carregando texto="Abrindo o leitor…" /></div>}>
              <Leitor />
            </Suspense>
          </LimiteDeErro>
        }
      />
      <Route path="*" element={<Navigate to="/estante" replace />} />
    </Routes>
  );
}

function LayoutAbas() {
  return (
    <div className="app">
      <FaixaSemInternet />
      <main className="app__conteudo" id="conteudo">
        <LimiteDeErro>
          <Outlet />
        </LimiteDeErro>
      </main>
      <BarraAbas />
    </div>
  );
}
