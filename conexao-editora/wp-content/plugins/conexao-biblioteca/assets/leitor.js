/**
 * Tela de leitura: marca d'água com a identificação do leitor, tela cheia e,
 * no leitor de PDF, os botões de baixar/imprimir/abrir saem de cena.
 */
(function () {
    'use strict';

    var marca = document.querySelector('[data-marca]');

    if (marca) {
        var texto = marca.getAttribute('data-marca') || '';

        for (var i = 0; i < 18; i++) {
            var linha = document.createElement('span');
            linha.textContent = texto;
            marca.appendChild(linha);
        }
    }

    var botao = document.querySelector('[data-tela-cheia]');
    var palco = document.querySelector('.leitor__palco');

    if (botao && palco) {
        if (!document.fullscreenEnabled) {
            botao.hidden = true;
        }

        botao.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                palco.requestFullscreen();
            }
        });

        document.addEventListener('fullscreenchange', function () {
            botao.textContent = document.fullscreenElement ? 'Sair da tela cheia' : 'Tela cheia';
        });
    }

    // Ctrl+S, Ctrl+P e Ctrl+O não fazem parte da leitura (fora e dentro do livro)
    function bloqueiaAtalhos(evento) {
        var tecla = (evento.key || '').toLowerCase();

        if ((evento.ctrlKey || evento.metaKey) && (tecla === 's' || tecla === 'p' || tecla === 'o')) {
            evento.preventDefault();
            evento.stopPropagation();
        }
    }

    document.addEventListener('keydown', bloqueiaAtalhos, true);

    var livro = document.querySelector('[data-livro]');

    if (livro && livro.getAttribute('data-modelo') === 'pdf') {
        livro.addEventListener('load', function () {
            try {
                var documento = livro.contentDocument;
                var estilo = documento.createElement('style');

                // o leitor desenha o PDF; baixar, imprimir e abrir outro arquivo
                // não fazem parte da leitura
                estilo.textContent = [
                    '#download, #downloadButton, #secondaryDownload',
                    '#print, #printButton, #secondaryPrint',
                    '#openFile, #secondaryOpenFile',
                    '#viewBookmark, #secondaryViewBookmark',
                    '#editorModeButtons, #editorModeSeparator',
                    '#documentProperties'
                ].join(',') + '{display:none !important}';

                documento.head.appendChild(estilo);

                // impressão pelo menu do navegador ou pelo botão direito dentro do livro:
                // o pdf.js montaria o livro inteiro para imprimir. Ele não chega a montar
                // (o aviso de impressão é barrado antes dele) e a folha sai em branco.
                var janela = livro.contentWindow;
                var semImpressao = documento.createElement('style');
                semImpressao.media = 'print';
                semImpressao.textContent = 'html, body { display: none !important; }';
                documento.head.appendChild(semImpressao);

                janela.addEventListener('beforeprint', function (evento) {
                    evento.stopImmediatePropagation();
                }, true);

                janela.print = function () {};

                if (janela.PDFViewerApplication) {
                    janela.PDFViewerApplication.triggerPrinting = function () {};
                }

                documento.addEventListener('contextmenu', function (evento) {
                    evento.preventDefault();
                }, true);

                // Ctrl+S e Ctrl+P dentro do leitor
                documento.addEventListener('keydown', bloqueiaAtalhos, true);
            } catch (erro) {
                // leitor em outra origem: segue sem o ajuste
            }
        });
    }

    document.addEventListener('contextmenu', function (evento) {
        evento.preventDefault();
    });
}());
