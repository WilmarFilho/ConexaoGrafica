import type { ReactNode } from 'react';
import { useNavigate } from 'react-router';
import logo from '../../assets/marca/logo.png';
import logoBranco from '../../assets/marca/logo-branco.png';
import { mostrarAvisosDemo } from '../../vitrine';
import { FaixaSemInternet } from '../../componentes/FaixaSemInternet';
import { BotaoIcone } from '../../componentes/ui';
import { temaEfetivo, useTema } from '../../estado/tema';

export function TelaAuth({ titulo, subtitulo, voltar, children, rodape }: { titulo: string; subtitulo?: ReactNode; voltar?: string; children: ReactNode; rodape?: ReactNode }) {
  const navegar = useNavigate();
  const tema = temaEfetivo(useTema());
  return (
    <div className="auth">
      <FaixaSemInternet />
      <div className="auth__topo">
        {voltar && <BotaoIcone icone="voltar" rotulo="Voltar" onClick={() => navegar(voltar)} />}
      </div>
      <main className="auth__caixa">
        <img className="auth__logo" src={tema === 'escuro' ? logoBranco : logo} alt="Conexão Editora" width={220} />
        <h1 className="auth__titulo">{titulo}</h1>
        {subtitulo && <p className="auth__subtitulo">{subtitulo}</p>}
        {children}
      </main>
      {rodape && <footer className="auth__rodape">{rodape}</footer>}
    </div>
  );
}

export function AvisoDemonstracao({ children }: { children: ReactNode }) {
  if (!mostrarAvisosDemo) return null;
  return (
    <aside className="demo" aria-label="Modo de demonstração">
      <strong>Modo de demonstração</strong>
      <span>{children}</span>
    </aside>
  );
}
