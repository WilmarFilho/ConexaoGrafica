/* Conexão DOI: abas acessíveis (clique, setas, Home/End, hash na URL),
   atalhos "ver sumário completo" que abrem a aba certa e botões de copiar. */
(function () {
    'use strict';

    var raiz = document.querySelector('.doi-pagina');

    if (!raiz) {
        return;
    }

    /* ---------- abas ---------- */
    function painelDe(aba) {
        var id = aba.getAttribute('aria-controls');

        return id ? document.getElementById(id) : null;
    }

    function ativar(lista, aba, foco) {
        var abas = lista.querySelectorAll('[role="tab"]');

        abas.forEach(function (outra) {
            var ativa = outra === aba;
            var painel = painelDe(outra);

            outra.classList.toggle('e-ativa', ativa);
            outra.setAttribute('aria-selected', ativa ? 'true' : 'false');
            outra.setAttribute('tabindex', ativa ? '0' : '-1');

            if (painel) {
                painel.hidden = !ativa;
            }
        });

        if (foco) {
            aba.focus();
        }

        if (lista.closest('[data-doi-abas-principal]')) {
            var id = aba.getAttribute('aria-controls');

            if (id && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + id);
            }
        }
    }

    var listas = raiz.querySelectorAll('[role="tablist"]');

    listas.forEach(function (lista) {
        var abas = Array.prototype.slice.call(lista.querySelectorAll('[role="tab"]'));

        abas.forEach(function (aba, i) {
            aba.addEventListener('click', function () {
                ativar(lista, aba, false);
            });

            aba.addEventListener('keydown', function (evento) {
                var destino = null;

                if (evento.key === 'ArrowRight' || evento.key === 'ArrowDown') {
                    destino = abas[(i + 1) % abas.length];
                } else if (evento.key === 'ArrowLeft' || evento.key === 'ArrowUp') {
                    destino = abas[(i - 1 + abas.length) % abas.length];
                } else if (evento.key === 'Home') {
                    destino = abas[0];
                } else if (evento.key === 'End') {
                    destino = abas[abas.length - 1];
                }

                if (destino) {
                    evento.preventDefault();
                    ativar(lista, destino, true);
                }
            });
        });
    });

    /* abre a aba principal pelo hash (#sumario, #capitulos…) e pelos atalhos */
    var principal = raiz.querySelector('[data-doi-abas-principal] [role="tablist"]');

    function abrirPrincipal(id, rolar) {
        if (!principal) {
            return false;
        }

        var aba = principal.querySelector('[role="tab"][aria-controls="' + id + '"]');

        if (!aba) {
            return false;
        }

        ativar(principal, aba, false);

        if (rolar) {
            principal.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        return true;
    }

    if (window.location.hash) {
        abrirPrincipal(window.location.hash.slice(1), false);
    }

    raiz.addEventListener('click', function (evento) {
        var atalho = evento.target.closest('[data-doi-ir]');

        if (atalho && abrirPrincipal(atalho.getAttribute('data-doi-ir'), true)) {
            evento.preventDefault();
        }
    });

    window.addEventListener('hashchange', function () {
        abrirPrincipal(window.location.hash.slice(1), true);
    });

    /* ---------- copiar ---------- */
    function copiar(texto) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(texto);
        }

        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = texto;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.top = '-1000px';
            document.body.appendChild(area);
            area.select();

            try {
                document.execCommand('copy') ? resolve() : reject();
            } catch (erro) {
                reject(erro);
            }

            document.body.removeChild(area);
        });
    }

    raiz.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-copiar]');

        if (!botao) {
            return;
        }

        var aviso = botao.querySelector('.doi-copiar__feito');

        copiar(botao.getAttribute('data-copiar')).then(function () {
            botao.classList.add('e-copiado');

            if (aviso) {
                aviso.textContent = 'Copiado!';
            }

            setTimeout(function () {
                botao.classList.remove('e-copiado');

                if (aviso) {
                    aviso.textContent = '';
                }
            }, 2000);
        }, function () {
            if (aviso) {
                aviso.textContent = 'Não foi possível copiar';
            }
        });
    });
})();
