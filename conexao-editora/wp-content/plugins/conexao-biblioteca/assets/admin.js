/**
 * Envio do PDF em pedaços, com barra de progresso. Cada pedaço cabe no limite
 * de upload do servidor; se um falhar, tenta de novo antes de desistir.
 */
(function () {
    'use strict';

    var config = window.conexaoBiblioteca;

    if (!config) {
        return;
    }

    var identificador = function () {
        var letras = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var saida = '';

        for (var i = 0; i < 16; i++) {
            saida += letras.charAt(Math.floor(Math.random() * letras.length));
        }

        return saida;
    };

    var enviarPedaco = function (dados, tentativa) {
        return fetch(config.ajax, { method: 'POST', body: dados, credentials: 'same-origin' })
            .then(function (resposta) {
                return resposta.json().then(function (corpo) {
                    return { ok: resposta.ok && corpo.success, corpo: corpo };
                });
            })
            .catch(function () {
                return { ok: false, corpo: { data: { mensagem: 'Falha de conexão.' } }, rede: true };
            })
            .then(function (resultado) {
                // erro de rede merece nova tentativa; recusa do servidor, não
                if (!resultado.ok && resultado.rede && tentativa < 3) {
                    return new Promise(function (resolver) {
                        setTimeout(resolver, 1500 * tentativa);
                    }).then(function () {
                        return enviarPedaco(dados, tentativa + 1);
                    });
                }

                return resultado;
            });
    };

    document.querySelectorAll('[data-cb-envio]').forEach(function (caixa) {
        var arquivo = caixa.querySelector('[data-cb-arquivo]');
        var escolher = caixa.querySelector('[data-cb-escolher]');
        var remover = caixa.querySelector('[data-cb-remover]');
        var barra = caixa.querySelector('[data-cb-barra]');
        var progresso = caixa.querySelector('[data-cb-progresso]');
        var estado = caixa.querySelector('[data-cb-estado]');
        var produto = caixa.getAttribute('data-produto');

        var avisar = function (texto, erro) {
            estado.textContent = texto;
            estado.classList.toggle('cb-envio__estado--erro', !!erro);
        };

        escolher.addEventListener('click', function () {
            arquivo.click();
        });

        arquivo.addEventListener('change', function () {
            var pdf = arquivo.files && arquivo.files[0];

            if (!pdf) {
                return;
            }

            if (!/\.pdf$/i.test(pdf.name)) {
                avisar('Escolha um arquivo PDF.', true);
                return;
            }

            if (pdf.size > config.maximo) {
                avisar('O arquivo passa do tamanho máximo permitido.', true);
                return;
            }

            var total = Math.max(1, Math.ceil(pdf.size / config.pedaco));
            var envio = identificador();
            var indice = 0;

            escolher.disabled = true;
            barra.hidden = false;
            progresso.style.width = '0%';
            avisar('Enviando…');

            var proximo = function () {
                var inicio = indice * config.pedaco;
                var dados = new FormData();

                dados.append('action', 'conexao_biblioteca_envio');
                dados.append('nonce', config.nonce);
                dados.append('produto', produto);
                dados.append('envio', envio);
                dados.append('indice', String(indice));
                dados.append('total', String(total));
                dados.append('nome', pdf.name);
                dados.append('pedaco', pdf.slice(inicio, inicio + config.pedaco), 'pedaco.bin');

                enviarPedaco(dados, 1).then(function (resultado) {
                    if (!resultado.ok) {
                        var mensagem = resultado.corpo && resultado.corpo.data && resultado.corpo.data.mensagem;
                        avisar(mensagem || 'Não foi possível enviar. Tente de novo.', true);
                        escolher.disabled = false;
                        barra.hidden = true;
                        arquivo.value = '';
                        return;
                    }

                    indice++;
                    progresso.style.width = Math.round((indice / total) * 100) + '%';

                    if (indice < total) {
                        avisar('Enviando… ' + Math.round((indice / total) * 100) + '%');
                        proximo();
                        return;
                    }

                    avisar('PDF recebido (' + resultado.corpo.data.tamanho + '). Atualizando…');
                    window.location.reload();
                });
            };

            proximo();
        });

        if (remover) {
            remover.addEventListener('click', function () {
                if (!window.confirm('Remover o PDF deste livro? Se houver flipbook, a leitura volta para ele.')) {
                    return;
                }

                var dados = new FormData();
                dados.append('action', 'conexao_biblioteca_remove_pdf');
                dados.append('nonce', config.nonce);
                dados.append('produto', produto);

                fetch(config.ajax, { method: 'POST', body: dados, credentials: 'same-origin' })
                    .then(function () {
                        window.location.reload();
                    });
            });
        }
    });
}());
