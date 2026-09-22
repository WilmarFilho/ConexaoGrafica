/**
 * Comportamentos do tema: menu no celular, pontos do carrossel do topo,
 * arrastar as vitrines com o mouse e o botão de voltar ao topo.
 */
(function () {
    'use strict';

    /* menu no celular */
    var botao = document.querySelector('.menu-botao');
    var menu = document.getElementById('menu-principal');

    if (botao && menu) {
        botao.addEventListener('click', function () {
            var aberto = menu.classList.toggle('aberto');
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
    }

    /* pontos do carrossel de capas do topo */
    document.querySelectorAll('[data-carrossel]').forEach(function (carrossel) {
        var trilho = carrossel.querySelector('[data-carrossel-trilho]');
        var pontos = carrossel.querySelector('[data-carrossel-pontos]');

        if (!trilho || !pontos) {
            return;
        }

        var itens = Array.prototype.slice.call(trilho.children);
        var porPagina = Math.max(1, Math.round(trilho.clientWidth / (itens[0] ? itens[0].offsetWidth + 22 : 1)));
        var paginas = Math.ceil(itens.length / porPagina);

        if (paginas < 2) {
            return;
        }

        for (var i = 0; i < paginas; i++) {
            var ponto = document.createElement('button');
            ponto.type = 'button';
            ponto.setAttribute('aria-label', 'Ir para a página ' + (i + 1));
            ponto.setAttribute('aria-current', i === 0 ? 'true' : 'false');
            ponto.dataset.pagina = String(i);
            pontos.appendChild(ponto);
        }

        pontos.addEventListener('click', function (evento) {
            var alvo = evento.target.closest('button');

            if (!alvo) {
                return;
            }

            trilho.scrollTo({
                left: Number(alvo.dataset.pagina) * trilho.clientWidth,
                behavior: 'smooth'
            });
        });

        trilho.addEventListener('scroll', function () {
            var atual = Math.round(trilho.scrollLeft / trilho.clientWidth);

            pontos.querySelectorAll('button').forEach(function (ponto, indice) {
                ponto.setAttribute('aria-current', indice === atual ? 'true' : 'false');
            });
        }, { passive: true });
    });

    /* arrastar as vitrines com o mouse */
    document.querySelectorAll('[data-arrastavel]').forEach(function (trilho) {
        var arrastando = false;
        var inicioX = 0;
        var inicioScroll = 0;

        trilho.addEventListener('pointerdown', function (evento) {
            if (evento.pointerType !== 'mouse') {
                return;
            }

            arrastando = true;
            inicioX = evento.clientX;
            inicioScroll = trilho.scrollLeft;
        });

        trilho.addEventListener('pointermove', function (evento) {
            if (!arrastando) {
                return;
            }

            var distancia = evento.clientX - inicioX;

            if (Math.abs(distancia) > 4) {
                trilho.scrollLeft = inicioScroll - distancia;
                trilho.style.cursor = 'grabbing';
            }
        });

        ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (evento) {
            trilho.addEventListener(evento, function () {
                arrastando = false;
                trilho.style.cursor = '';
            });
        });
    });

    /* voltar ao topo */
    var topo = document.querySelector('.flutuante--topo');

    if (topo) {
        topo.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
}());
