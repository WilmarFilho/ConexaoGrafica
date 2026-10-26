// Arte do Google Play: imagem de destaque 1024×500 (loja/android/destaque-1024x500.png).
// Desenhada em HTML (fonte Nunito da marca) e fotografada pelo Chrome headless.
// Usa a captura loja/android/celular/01-estante.png — rode antes scripts/capturas-lojas.mjs.
// Uso: node scripts/gerar-arte-lojas.mjs      (variável CHROME para outro navegador)
import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const dormir = (ms) => new Promise((r) => setTimeout(r, ms));
const b64 = (arq) => readFileSync(join(raiz, arq)).toString('base64');

const fonte = (peso) => `@font-face{font-family:Nunito;font-weight:${peso};src:url(data:font/woff2;base64,${b64(`node_modules/@fontsource/nunito/files/nunito-latin-${peso}-normal.woff2`)}) format('woff2')}`;

const html = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
${fonte(700)}${fonte(800)}
*{box-sizing:border-box;margin:0}
html,body{width:1024px;height:500px;overflow:hidden}
body{font-family:Nunito,sans-serif;color:#fff;background:radial-gradient(120% 140% at 0% 0%,#22b0ea 0%,#0096D6 38%,#0b4f8a 100%);position:relative}
.bolha{position:absolute;border-radius:50%;background:#fff;opacity:.07}
.texto{position:absolute;left:64px;top:0;bottom:0;width:560px;display:flex;flex-direction:column;justify-content:center;gap:26px}
.logo{width:300px;height:auto}
h1{font-size:44px;line-height:1.12;font-weight:800;letter-spacing:-.01em;text-wrap:balance}
p{font-size:21px;font-weight:700;opacity:.9}
.celular{position:absolute;right:70px;top:46px;width:270px;height:560px;border-radius:40px;background:#0d1b26;padding:11px;box-shadow:0 30px 60px rgba(0,20,40,.45);transform:rotate(-7deg)}
.celular img{width:100%;height:100%;object-fit:cover;object-position:top;border-radius:30px;display:block}
</style></head><body>
<span class="bolha" style="width:420px;height:420px;right:-120px;top:-180px"></span>
<span class="bolha" style="width:260px;height:260px;left:-90px;bottom:-140px"></span>
<div class="texto">
  <img class="logo" src="data:image/png;base64,${b64('src/assets/marca/logo-branco.png')}" alt="">
  <h1>Seus livros da Conexão Editora em qualquer lugar</h1>
  <p>Leia online ou sem internet, marque páginas e acompanhe seus pedidos.</p>
</div>
<div class="celular"><img src="data:image/png;base64,${b64('loja/android/celular/01-estante.png')}" alt=""></div>
</body></html>`;

const perfil = join(raiz, '.capturas-perfil', 'arte');
rmSync(perfil, { recursive: true, force: true });
mkdirSync(perfil, { recursive: true });
const porta = 9450;
const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${porta}`, `--user-data-dir=${perfil}`, '--no-first-run', '--hide-scrollbars', 'about:blank'], { stdio: 'ignore' });
try {
  let alvo;
  for (let i = 0; i < 200 && !alvo; i++) {
    try {
      alvo = (await (await fetch(`http://127.0.0.1:${porta}/json`)).json()).find((t) => t.type === 'page');
    } catch {
      /* abrindo */
    }
    if (!alvo) await dormir(150);
  }
  if (!alvo) throw new Error('Chrome não respondeu');
  const ws = new WebSocket(alvo.webSocketDebuggerUrl);
  await new Promise((r) => (ws.onopen = r));
  let id = 0;
  const pend = new Map();
  ws.onmessage = (m) => {
    const d = JSON.parse(m.data);
    if (d.id && pend.has(d.id)) pend.get(d.id)(d), pend.delete(d.id);
  };
  const cmd = (method, params = {}) => new Promise((r) => { const n = ++id; pend.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
  await cmd('Page.enable');
  await cmd('Emulation.setDeviceMetricsOverride', { width: 1024, height: 500, deviceScaleFactor: 1, mobile: false });
  const { result } = await cmd('Page.getFrameTree');
  await cmd('Page.setDocumentContent', { frameId: result.frameTree.frame.id, html });
  await dormir(1500);
  const shot = await cmd('Page.captureScreenshot', { format: 'png' });
  mkdirSync(join(raiz, 'loja/android'), { recursive: true });
  writeFileSync(join(raiz, 'loja/android/destaque-1024x500.png'), Buffer.from(shot.result.data, 'base64'));
  console.log('loja/android/destaque-1024x500.png');
  ws.close();
} finally {
  chrome.kill();
}
