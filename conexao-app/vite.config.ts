import { rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig, type Plugin } from 'vite';
import react from '@vitejs/plugin-react';
import pacote from './package.json' with { type: 'json' };

/**
 * Modos:
 *  - (padrão) / mock      → web app servido em https://conexaoeditora.com.br/app/  (base /app/, com service worker)
 *  - native / native-mock → pacote do Capacitor (base /, sem service worker)
 * Os modos "mock" ligam VITE_MOCK=1 (arquivos .env.mock e .env.native-mock).
 */
export default defineConfig(({ mode }) => {
  const nativo = mode.startsWith('native');
  const mock = mode.endsWith('mock');
  return {
    base: nativo ? '/' : '/app/',
    define: {
      __VERSAO_APP__: JSON.stringify(pacote.version),
    },
    plugins: [react(), ...(nativo ? [] : [serviceWorker()]), ...(mock ? [] : [semDemonstracao()])],
    build: {
      target: 'es2022',
      chunkSizeWarningLimit: 1600,
      sourcemap: false,
    },
    server: { port: 5180, strictPort: false },
    preview: { port: 4180 },
  };
});

/** Fora dos modos de demonstração, o PDF de exemplo (public/mock) não vai para o pacote. */
function semDemonstracao(): Plugin {
  let saida = 'dist';
  return {
    name: 'conexao-sem-demonstracao',
    apply: 'build',
    configResolved(config) {
      saida = resolve(config.root, config.build.outDir);
    },
    closeBundle() {
      rmSync(resolve(saida, 'mock'), { recursive: true, force: true });
    },
  };
}

/**
 * Service worker escrito à mão (sem dependências): pré-carrega o "casco" do app
 * (HTML, JS, CSS, fontes, worker do pdf.js, ícones) para o PWA abrir sem internet.
 * A lista de arquivos é gerada no build; a versão muda a cada build.
 */
function serviceWorker(): Plugin {
  let base = '/app/';
  return {
    name: 'conexao-service-worker',
    apply: 'build',
    enforce: 'post',
    configResolved(config) {
      base = config.base;
    },
    generateBundle(_opcoes, bundle) {
      const arquivos = Object.keys(bundle)
        .filter((nome) => !nome.endsWith('.map') && !nome.endsWith('.woff') && nome !== 'sw.js')
        .map((nome) => base + nome);
      const fixos = ['', 'manifest.webmanifest', 'icons/icone-192.png', 'icons/icone-512.png', 'icons/favicon-64.png'].map((n) => base + n);
      const lista = Array.from(new Set([...fixos, ...arquivos]));
      const versao = Date.now().toString(36);
      const codigo = `/* Gerado no build. Não editar. */
const VERSAO = ${JSON.stringify(versao)};
const BASE = ${JSON.stringify(base)};
const CASCO = 'conexao-leitor-casco-' + VERSAO;
const EXTRA = 'conexao-leitor-extra';
const ARQUIVOS = ${JSON.stringify(lista)};

self.addEventListener('install', (evento) => {
  evento.waitUntil(caches.open(CASCO).then((c) => c.addAll(ARQUIVOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches.keys()
      .then((nomes) => Promise.all(nomes.filter((n) => n.startsWith('conexao-leitor-casco-') && n !== CASCO).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (evento) => {
  const req = evento.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith(BASE)) return;

  // Navegação: rede primeiro; sem internet, devolve o index.html guardado (rotas do app).
  if (req.mode === 'navigate') {
    evento.respondWith(fetch(req).catch(() => caches.match(BASE, { ignoreSearch: true }).then((r) => r || Response.error())));
    return;
  }
  // Arquivos estáticos: cache primeiro. Recursos do pdf.js e da demonstração entram no cache extra quando usados.
  evento.respondWith(
    caches.match(req, { ignoreSearch: true }).then((salvo) => {
      if (salvo) return salvo;
      return fetch(req).then((resp) => {
        if (resp.ok && (url.pathname.startsWith(BASE + 'pdfjs/') || url.pathname.startsWith(BASE + 'mock/'))) {
          const copia = resp.clone();
          caches.open(EXTRA).then((c) => c.put(req, copia));
        }
        return resp;
      });
    })
  );
});
`;
      this.emitFile({ type: 'asset', fileName: 'sw.js', source: codigo });
    },
  };
}
