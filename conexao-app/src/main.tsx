import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '@fontsource/nunito/latin-400.css';
import '@fontsource/nunito/latin-600.css';
import '@fontsource/nunito/latin-700.css';
import '@fontsource/nunito/latin-800.css';
import './estilos/base.css';
import './estilos/telas.css';
import './estilos/leitor.css';
import { App } from './App';
import { iniciarRede } from './estado/rede';
import { iniciarTema } from './estado/tema';
import { ehNativo } from './plataforma/nativo';

iniciarTema();
iniciarRede();

createRoot(document.getElementById('raiz')!).render(
  <StrictMode>
    <App />
  </StrictMode>
);

// Service worker só no web app publicado (não no Capacitor nem no modo de desenvolvimento).
if (!ehNativo && import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register(`${import.meta.env.BASE_URL}sw.js`, { scope: import.meta.env.BASE_URL }).catch(() => undefined);
  });
}
