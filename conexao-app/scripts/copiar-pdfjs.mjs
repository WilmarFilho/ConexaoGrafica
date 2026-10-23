// Copia os recursos de tempo de execução do pdf.js (cmaps, fontes padrão, wasm, perfis ICC)
// para public/pdfjs/, de onde o leitor carrega sob demanda. A pasta é gerada (está no .gitignore).
import { cpSync, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
const origem = join(raiz, 'node_modules', 'pdfjs-dist');
const destino = join(raiz, 'public', 'pdfjs');
const versao = JSON.parse(readFileSync(join(origem, 'package.json'), 'utf8')).version;
const marca = join(destino, '.versao');

if (existsSync(marca) && readFileSync(marca, 'utf8') === versao) {
  process.exit(0);
}

mkdirSync(destino, { recursive: true });
for (const pasta of ['cmaps', 'standard_fonts', 'iccs']) {
  cpSync(join(origem, pasta), join(destino, pasta), { recursive: true });
}
// wasm: decodificadores de imagem (JBIG2, JPEG 2000) e cores (QCMS). O QuickJS (scripts do PDF) fica de fora.
cpSync(join(origem, 'wasm'), join(destino, 'wasm'), {
  recursive: true,
  filter: (src) => !/quickjs/i.test(src),
});
writeFileSync(marca, versao);
console.log(`pdf.js ${versao}: recursos copiados para public/pdfjs/`);
