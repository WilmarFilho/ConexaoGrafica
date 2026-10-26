// Capturas de tela para as lojas (App Store e Google Play), a partir do build de DEMONSTRAÇÃO.
//
// Pré-requisitos:
//   npm run build:mock && npm run preview      (serve em http://localhost:4180/app/)
// Uso:
//   node scripts/capturas-lojas.mjs                 → todos os conjuntos
//   node scripts/capturas-lojas.mjs ios-iphone69    → só um conjunto
// Variáveis: BASE (padrão http://localhost:4180/app), CHROME (caminho do Chrome/Edge).
//
// Abre o app com ?capturas=<aparelho>, que esconde os avisos de "dados de exemplo" e os links de compra
// (ver src/vitrine.ts). Cada conjunto usa um perfil próprio do Chrome em .capturas-perfil/ e só encerra
// o processo que ele mesmo abriu.
import { spawn } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = (process.env.BASE || 'http://localhost:4180/app').replace(/\/$/, '');
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));

const CELULAR = ['estante', 'leitor', 'busca', 'livro', 'pedido', 'conta'];
const CONJUNTOS = {
  'ios-iphone69': { w: 440, h: 956, dpr: 3, cap: 'ios', saida: 'loja/ios/iphone-6.9', telas: CELULAR, entrar: true },
  'ios-ipad13': { w: 1032, h: 1376, dpr: 2, cap: 'ipad', saida: 'loja/ios/ipad-13', telas: ['estante', 'leitor', 'livro', 'pedido'] },
  'android-celular': { w: 360, h: 780, dpr: 3, cap: 'android', saida: 'loja/android/celular', telas: CELULAR, entrar: true },
  'android-tablet10': { w: 800, h: 1280, dpr: 2, cap: 'android-tablet', saida: 'loja/android/tablet-10', telas: ['estante', 'leitor', 'pedido'] },
};

// Passos de cada tela: rota + script opcional rodado na página antes da captura.
const TELAS = {
  estante: { rota: '/estante', pronto: '.cartao-livro img' },
  leitor: { rota: '/ler/101', pronto: '.leitor__spread .pagina-pdf--pronta', depois: 500 },
  busca: {
    rota: '/ler/101',
    pronto: '.leitor__spread .pagina-pdf--pronta',
    script: `
      document.querySelector('button[aria-label="Buscar no livro"]').click();
      await dormir(500);
      const campo = document.querySelector('.painel-busca input');
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(campo, 'leitura');
      campo.dispatchEvent(new Event('input', { bubbles: true }));
      campo.form.requestSubmit();
      await dormir(4000);
      campo.blur();
    `,
  },
  livro: { rota: '/livro/101', pronto: '.livro__capa img' },
  pedido: { rota: '/pedidos/hub-9001', pronto: '.rastreio' },
  conta: { rota: '/conta', pronto: '.aparelho' },
};

async function conjunto(nome, c) {
  const perfil = join(raiz, '.capturas-perfil', nome);
  rmSync(perfil, { recursive: true, force: true });
  mkdirSync(perfil, { recursive: true });
  const saida = join(raiz, c.saida);
  rmSync(saida, { recursive: true, force: true });
  mkdirSync(saida, { recursive: true });
  const porta = 9400 + Object.keys(CONJUNTOS).indexOf(nome);
  const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${porta}`, `--user-data-dir=${perfil}`, '--no-first-run', '--hide-scrollbars', '--lang=pt-BR', 'about:blank'], { stdio: 'ignore' });
  try {
    let alvo;
    for (let i = 0; i < 200 && !alvo; i++) {
      try {
        alvo = (await (await fetch(`http://127.0.0.1:${porta}/json`)).json()).find((t) => t.type === 'page');
      } catch {
        /* ainda abrindo */
      }
      if (!alvo) await dormir(150);
    }
    if (!alvo) throw new Error('Chrome não respondeu');
    const ws = new WebSocket(alvo.webSocketDebuggerUrl);
    await new Promise((r) => (ws.onopen = r));
    let id = 0;
    const pendentes = new Map();
    ws.onmessage = (m) => {
      const d = JSON.parse(m.data);
      if (d.id && pendentes.has(d.id)) pendentes.get(d.id)(d), pendentes.delete(d.id);
    };
    const cmd = (method, params = {}) => new Promise((r) => { const n = ++id; pendentes.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
    const avaliar = async (codigo) => {
      const r = await cmd('Runtime.evaluate', { expression: `(async () => { const dormir = (ms) => new Promise(r => setTimeout(r, ms)); ${codigo} })()`, awaitPromise: true, returnByValue: true });
      if (r.result?.exceptionDetails) throw new Error(r.result.exceptionDetails.exception?.description || 'erro no script');
      return r.result?.result?.value;
    };
    const esperar = (seletor, ms = 15000) => avaliar(`const fim = Date.now() + ${ms}; while (Date.now() < fim) { if (document.querySelector(${JSON.stringify(seletor)})) return true; await dormir(150); } throw new Error('não apareceu: ${seletor}');`);
    const ir = async (rota) => {
      await cmd('Page.navigate', { url: `${BASE}${rota}${rota.includes('?') ? '&' : '?'}capturas=${c.cap}` });
      await dormir(1200);
    };
    const capturar = async (arquivo) => {
      const r = await cmd('Page.captureScreenshot', { format: 'png' });
      writeFileSync(join(saida, arquivo), Buffer.from(r.result.data, 'base64'));
      console.log(`  ${c.saida}/${arquivo}`);
    };

    await cmd('Page.enable');
    await cmd('Runtime.enable');
    await cmd('Emulation.setDeviceMetricsOverride', { width: c.w, height: c.h, deviceScaleFactor: c.dpr, mobile: c.w < 900 });
    await cmd('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
    await cmd('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }, { name: 'prefers-reduced-motion', value: 'reduce' }] });

    // Entrar (a tela de login também vira captura nos celulares)
    await ir('/entrar');
    await esperar('input[type=email]');
    await avaliar(`
      const set = (el, v) => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, v); el.dispatchEvent(new Event('input', { bubbles: true })); };
      set(document.querySelector('input[type=email]'), 'ana.souza@email.com');
      set(document.querySelector('input[type=password]'), 'minhasenha');
      document.activeElement?.blur();
    `);
    if (c.entrar) await capturar('07-entrar.png');
    await avaliar(`document.querySelector('form').requestSubmit(); await dormir(2500);`);

    let n = 1;
    for (const tela of c.telas) {
      const t = TELAS[tela];
      await ir(t.rota);
      await esperar(t.pronto);
      await dormir(t.depois ?? 700);
      if (t.script) await avaliar(t.script);
      await capturar(`${String(n++).padStart(2, '0')}-${tela}.png`);
    }
    ws.close();
  } finally {
    chrome.kill(); // só o processo aberto por este script
  }
}

const pedidos = process.argv.slice(2);
for (const [nome, c] of Object.entries(CONJUNTOS)) {
  if (pedidos.length && !pedidos.includes(nome)) continue;
  console.log(nome);
  await conjunto(nome, c);
}
