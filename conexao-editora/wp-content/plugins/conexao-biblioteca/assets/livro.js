/**
 * Modo livro: as páginas do PDF viram como num livro de papel (StPageFlip),
 * em página dupla no computador e página única no celular. O pdf.js desenha
 * só as páginas perto da que está aberta; as outras ficam em branco até chegar
 * a vez. Setas, clique nas bordas, arrasto, teclado, sumário e tela cheia.
 */
import * as pdfjs from './pdfjs/build/pdf.mjs';

pdfjs.GlobalWorkerOptions.workerSrc = new URL('./pdfjs/build/pdf.worker.mjs', import.meta.url).href;

const palco = document.querySelector('[data-livro-flip]');

if (palco) {
    iniciar(palco).catch((erro) => {
        console.error(erro);
        aviso(palco, 'Não foi possível abrir o livro. Tente o modo rolagem.');
    });
}

function aviso(onde, texto) {
    onde.innerHTML = '';
    const p = document.createElement('p');
    p.className = 'livro__aviso';
    p.textContent = texto;
    onde.appendChild(p);
}

async function iniciar(palco) {
    const produto = palco.dataset.produto;
    const chaveProgresso = 'conexao-leitor-pagina-' + produto;
    const pdf = await pdfjs.getDocument({ url: palco.dataset.arquivo, withCredentials: true }).promise;
    const total = pdf.numPages;
    const primeira = await pdf.getPage(1);
    const base = primeira.getViewport({ scale: 1 });
    const proporcao = base.width / base.height; // largura / altura da página

    palco.innerHTML = '';
    let livro = null;

    const paginas = [];

    for (let n = 1; n <= total; n++) {
        const pagina = document.createElement('div');
        pagina.className = 'livro__pagina';
        pagina.dataset.n = String(n);

        // capa e contracapa são duras, como num livro de verdade
        if (n === 1 || n === total) {
            pagina.dataset.density = 'hard';
        }

        const tela = document.createElement('canvas');
        tela.className = 'livro__tela';
        pagina.appendChild(tela);
        paginas.push({ el: pagina, tela, desenhada: 0, desenhando: null });
    }

    const indicador = document.querySelector('[data-livro-pagina]');
    const totalEl = document.querySelector('[data-livro-total]');

    if (totalEl) {
        totalEl.textContent = String(total);
    }

    let flip = null;
    let atual = Math.min(total - 1, Math.max(0, lerProgresso(chaveProgresso)));
    let medidaAtual = '';
    let esperaSincronizar = 0;

    function medidas() {
        const larguraPalco = palco.clientWidth;
        const alturaPalco = palco.clientHeight;
        const retrato = larguraPalco < 760;
        const margem = retrato ? 8 : 24;
        const colunas = retrato ? 1 : 2;

        let altura = alturaPalco - margem * 2;
        let largura = altura * proporcao;

        if (largura * colunas > larguraPalco - margem * 2) {
            largura = (larguraPalco - margem * 2) / colunas;
            altura = largura / proporcao;
        }

        return { largura: Math.floor(largura), altura: Math.floor(altura), retrato };
    }

    function montar() {
        const m = medidas();
        medidaAtual = m.largura + 'x' + m.altura + (m.retrato ? 'r' : 'p');

        // o destroy() da biblioteca leva junto o contêiner: um novo a cada montagem
        if (flip) {
            flip.destroy();
        }

        livro = document.createElement('div');
        livro.className = 'livro__paginas';
        paginas.forEach((p) => livro.appendChild(p.el));
        palco.appendChild(livro);

        flip = new St.PageFlip(livro, {
            width: m.largura,
            height: m.altura,
            size: 'fixed',
            showCover: true,
            usePortrait: m.retrato,
            autoSize: false,
            maxShadowOpacity: 0.45,
            flippingTime: 700,
            mobileScrollSupport: false,
            startPage: atual,
            swipeDistance: 20,
            showPageCorners: true,
        });

        flip.loadFromHTML(paginas.map((p) => p.el));
        palco.livroFlip = flip; // para testes e depuração

        // os eventos chegam em ordens diferentes conforme a virada (seta, arrasto, salto):
        // sincroniza na hora e de novo depois que a animação (700 ms) acabou
        const sincronizarDepois = () => {
            sincronizar();
            clearTimeout(esperaSincronizar);
            esperaSincronizar = setTimeout(sincronizar, 900);
        };

        flip.on('flip', sincronizarDepois);
        flip.on('changeState', sincronizarDepois);

        atualizarIndicador();
        desenharPerto(true);
    }

    /** O que está aberto agora, pela própria biblioteca (o evento nem sempre traz a página certa). */
    function sincronizar() {
        if (!flip) {
            return;
        }

        atual = flip.getCurrentPageIndex();
        atualizarIndicador();
        desenharPerto();
        guardarProgresso(chaveProgresso, atual);
    }

    function atualizarIndicador() {
        if (!indicador) {
            return;
        }

        const esquerda = atual + 1;
        const dupla = flip && flip.getOrientation() === 'landscape' && atual > 0 && atual < total - 1;
        indicador.value = dupla ? esquerda + '-' + (esquerda + 1) : String(esquerda);
    }

    /** Desenha as páginas em volta da atual e libera as que ficaram longe. */
    function desenharPerto(forcar) {
        const perto = 3;
        const m = medidas();
        const escala = (m.largura / base.width) * Math.min(window.devicePixelRatio || 1, 2);

        paginas.forEach((p, i) => {
            const distancia = Math.abs(i - atual);

            if (distancia <= perto) {
                desenhar(p, i + 1, escala, forcar);
            } else if (distancia > perto + 6 && p.desenhada) {
                p.tela.width = 0;
                p.tela.height = 0;
                p.desenhada = 0;
            }
        });
    }

    function desenhar(p, numero, escala, forcar) {
        if (p.desenhando || (p.desenhada === escala && !forcar)) {
            return;
        }

        p.desenhando = pdf.getPage(numero).then((pagina) => {
            const viewport = pagina.getViewport({ scale: escala });
            p.tela.width = Math.floor(viewport.width);
            p.tela.height = Math.floor(viewport.height);

            return pagina.render({ canvasContext: p.tela.getContext('2d'), viewport }).promise;
        }).then(() => {
            p.desenhada = escala;
            p.desenhando = null;
        }).catch(() => {
            p.desenhando = null;
        });
    }

    montar();

    // recria só quando o tamanho útil mudou de fato (janela, tela cheia)
    let esperaRedimensionar = 0;
    const remontarSeMudou = () => {
        const m = medidas();

        if (m.largura + 'x' + m.altura + (m.retrato ? 'r' : 'p') !== medidaAtual) {
            montar();
        }
    };

    window.addEventListener('resize', () => {
        clearTimeout(esperaRedimensionar);
        esperaRedimensionar = setTimeout(remontarSeMudou, 200);
    });

    document.addEventListener('fullscreenchange', () => setTimeout(remontarSeMudou, 80));

    // controles
    const ir = (n) => {
        if (!flip) {
            return;
        }

        // salto direto (sumário, campo de página): abre a dupla certa na hora; a
        // biblioteca avisa pelo evento 'flip', que sincroniza o indicador
        flip.turnToPage(Math.min(total - 1, Math.max(0, n)));
    };

    document.querySelectorAll('[data-livro-anterior]').forEach((b) => b.addEventListener('click', () => flip && flip.flipPrev()));
    document.querySelectorAll('[data-livro-proxima]').forEach((b) => b.addEventListener('click', () => flip && flip.flipNext()));

    if (indicador) {
        indicador.addEventListener('change', () => {
            const n = parseInt(indicador.value, 10);

            if (n >= 1 && n <= total) {
                ir(n - 1);
            } else {
                atualizarIndicador();
            }
        });

        indicador.addEventListener('focus', () => indicador.select());
    }

    document.addEventListener('keydown', (evento) => {
        if (evento.target && /^(INPUT|TEXTAREA)$/.test(evento.target.tagName)) {
            return;
        }

        if (evento.key === 'ArrowRight' || evento.key === 'PageDown' || evento.key === ' ') {
            evento.preventDefault();
            flip && flip.flipNext();
        } else if (evento.key === 'ArrowLeft' || evento.key === 'PageUp') {
            evento.preventDefault();
            flip && flip.flipPrev();
        } else if (evento.key === 'Home') {
            ir(0);
        } else if (evento.key === 'End') {
            ir(total - 1);
        }
    });

    // sumário (marcadores do próprio PDF)
    const botaoSumario = document.querySelector('[data-livro-sumario]');
    const gaveta = document.querySelector('[data-livro-gaveta]');

    if (botaoSumario && gaveta) {
        let itens = await sumario(pdf);

        // PDF sem marcadores: o sumário digitado no produto (página do PDF, 1-based)
        if (!itens.length) {
            try {
                itens = (JSON.parse(palco.dataset.sumario || '[]') || [])
                    .filter((i) => i && i.titulo && i.pagina >= 1 && i.pagina <= total)
                    .map((i) => ({ titulo: String(i.titulo), pagina: Number(i.pagina) - 1, nivel: 0 }));
            } catch (erro) {
                itens = [];
            }
        }

        if (!itens.length) {
            botaoSumario.hidden = true;
        } else {
            const lista = gaveta.querySelector('ol');

            itens.forEach((item) => {
                const li = document.createElement('li');
                li.style.paddingLeft = (item.nivel * 14) + 'px';
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.textContent = item.titulo;
                botao.addEventListener('click', () => {
                    ir(item.pagina);
                    gaveta.hidden = true;
                    botaoSumario.setAttribute('aria-expanded', 'false');
                });
                li.appendChild(botao);
                lista.appendChild(li);
            });

            botaoSumario.addEventListener('click', () => {
                gaveta.hidden = !gaveta.hidden;
                botaoSumario.setAttribute('aria-expanded', gaveta.hidden ? 'false' : 'true');
            });
        }
    }
}

async function sumario(pdf) {
    const saida = [];
    const raiz = await pdf.getOutline();

    async function percorrer(nos, nivel) {
        for (const no of nos || []) {
            let pagina = null;

            try {
                const dest = typeof no.dest === 'string' ? await pdf.getDestination(no.dest) : no.dest;

                if (Array.isArray(dest) && dest[0]) {
                    pagina = await pdf.getPageIndex(dest[0]);
                }
            } catch (erro) {
                pagina = null;
            }

            if (pagina !== null && no.title) {
                saida.push({ titulo: no.title.trim(), pagina, nivel });
            }

            await percorrer(no.items, nivel + 1);
        }
    }

    await percorrer(raiz, 0);

    return saida;
}

function lerProgresso(chave) {
    try {
        return parseInt(window.localStorage.getItem(chave) || '0', 10) || 0;
    } catch (erro) {
        return 0;
    }
}

function guardarProgresso(chave, pagina) {
    try {
        window.localStorage.setItem(chave, String(pagina));
    } catch (erro) {
        // sem armazenamento: começa do início da próxima vez
    }
}
