/**
 * Comportamentos do tema: menu no celular, pontos do carrossel do topo,
 * arrastar as vitrines com o mouse e o botão de voltar ao topo.
 */
(function () {
    'use strict';

    /* menu no celular: tela cheia, com sanfona nos itens que têm painel */
    var botao = document.querySelector('.menu-botao');
    var menu = document.getElementById('menu-principal');
    var fechar = document.querySelector('.menu-fechar');

    if (botao && menu) {
        var mostrar = function (aberto) {
            menu.classList.toggle('aberto', aberto);
            document.body.classList.toggle('menu-aberto', aberto);
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');

            if (aberto && fechar) {
                fechar.focus();
            } else {
                botao.focus();
            }
        };

        botao.addEventListener('click', function () {
            mostrar(!menu.classList.contains('aberto'));
        });

        if (fechar) {
            fechar.addEventListener('click', function () {
                mostrar(false);
            });
        }

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && menu.classList.contains('aberto')) {
                mostrar(false);
            }
        });

        // no celular, tocar no item com painel abre e fecha a sanfona
        menu.addEventListener('click', function (evento) {
            if (window.innerWidth > 920) {
                return;
            }

            var link = evento.target.closest('.menu > li > a');

            if (!link) {
                return;
            }

            var item = link.parentElement;

            if (!item.querySelector('.sub-menu')) {
                return;
            }

            evento.preventDefault();
            item.classList.toggle('aberto');
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

    /* o painel do menu cobre a faixa de abertura inteira */
    document.querySelectorAll('.menu-principal .menu > li.menu-item-has-children').forEach(function (item) {
        var painel = item.querySelector('.sub-menu');

        if (!painel) {
            return;
        }

        var hero = document.querySelector('.hero');

        var alinhar = function () {
            if (window.innerWidth <= 900 || !hero) {
                painel.style.removeProperty('--altura-hero');
                return;
            }

            painel.style.setProperty('--altura-hero', Math.round(hero.getBoundingClientRect().height) + 'px');
        };

        item.addEventListener('mouseenter', alinhar);
        item.addEventListener('focusin', alinhar);
        window.addEventListener('resize', alinhar);
    });

    /* setas dos carrosséis do celular: data-rolar diz o sentido e data-trilho
       de quem é a régua que anda */
    document.querySelectorAll('[data-rolar]').forEach(function (seta) {
        var trilho = document.querySelector(seta.dataset.trilho);

        if (!trilho) {
            return;
        }

        seta.addEventListener('click', function () {
            trilho.scrollBy({ left: Number(seta.dataset.rolar) * trilho.clientWidth, behavior: 'smooth' });
        });
    });

    /* clicar numa categoria filtra a vitrine logo abaixo */
    var listaCategorias = document.querySelector('[data-filtro-categorias]');
    var vitrine = document.querySelector('[data-vitrine-categorias]');

    if (listaCategorias && vitrine) {
        var cartoes = Array.prototype.slice.call(vitrine.querySelectorAll('[data-categorias]'));
        var aviso = vitrine.querySelector('.destaque__vazio');

        var aplicar = function (categoria) {
            var visiveis = 0;

            cartoes.forEach(function (cartao) {
                var pertence = !categoria || (' ' + cartao.dataset.categorias + ' ').indexOf(' ' + categoria + ' ') > -1;
                cartao.hidden = !pertence;

                if (pertence) {
                    visiveis++;
                }
            });

            if (aviso) {
                aviso.hidden = visiveis > 0;
            }

            listaCategorias.querySelectorAll('[data-categoria]').forEach(function (link) {
                link.setAttribute('aria-pressed', link.dataset.categoria === categoria ? 'true' : 'false');
            });

            vitrine.scrollTo({ left: 0, behavior: 'smooth' });
        };

        listaCategorias.addEventListener('click', function (evento) {
            var link = evento.target.closest('[data-categoria]');

            if (!link) {
                return;
            }

            evento.preventDefault();

            // clicar de novo na categoria já escolhida mostra tudo outra vez
            var selecionada = link.getAttribute('aria-pressed') === 'true' ? '' : link.dataset.categoria;
            aplicar(selecionada);
        });
    }

    /* quantidade no carrinho: os botões mudam o número e o carrinho se atualiza */
    var carrinho = document.querySelector('[data-carrinho]');

    if (carrinho) {
        var atualizar = null;

        var pedirAtualizacao = function () {
            clearTimeout(atualizar);
            atualizar = setTimeout(function () {
                var botao = carrinho.querySelector('[name="update_cart"]');

                if (botao) {
                    botao.disabled = false;
                    botao.click();
                }
            }, 600);
        };

        carrinho.addEventListener('click', function (evento) {
            var botao = evento.target.closest('[data-passo]');

            if (!botao) {
                return;
            }

            var campo = botao.parentElement.querySelector('.passo__campo');
            var minimo = Number(campo.min || 0);
            var maximo = campo.max ? Number(campo.max) : Infinity;
            var novo = Number(campo.value || 0) + Number(botao.dataset.passo);

            campo.value = Math.min(maximo, Math.max(minimo, novo));
            pedirAtualizacao();
        });

        carrinho.addEventListener('change', function (evento) {
            if (evento.target.classList.contains('passo__campo')) {
                pedirAtualizacao();
            }
        });
    }

    /* compartilhar post: usa o compartilhamento do celular, ou copia o link */
    document.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-compartilhar]');

        if (!botao) {
            return;
        }

        var dados = { title: botao.dataset.titulo, url: botao.dataset.url };

        if (navigator.share) {
            navigator.share(dados).catch(function () {});
            return;
        }

        if (navigator.clipboard) {
            navigator.clipboard.writeText(dados.url).then(function () {
                var antes = botao.getAttribute('aria-label');
                botao.setAttribute('aria-label', 'Link copiado');
                setTimeout(function () {
                    botao.setAttribute('aria-label', antes);
                }, 2000);
            });
        }
    });

    /* sanfonas (F.A.Q e central de ajuda): um item aberto por vez, com a altura
       animada — o <details> sozinho troca de estado sem transição nenhuma */
    var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)');
    var duracaoSanfona = 240;

    var animarPainel = function (painel, de, para, aoTerminar) {
        var encerrado = false;

        var encerrar = function () {
            if (encerrado) {
                return;
            }

            encerrado = true;
            var corrente = painel.animacao;
            painel.animacao = null;

            // pelo relógio a animação ainda pode estar rodando; sem largar dela
            // o painel ficaria preso na altura do meio do caminho
            if (corrente && corrente.playState === 'running') {
                corrente.cancel();
            }

            aoTerminar();
        };

        if (painel.animacao) {
            painel.animacao.cancel();
        }

        if (semMovimento.matches) {
            encerrar();
            return;
        }

        painel.animacao = painel.animate(
            { height: [de + 'px', para + 'px'], opacity: [de ? 1 : 0, para ? 1 : 0] },
            { duration: duracaoSanfona, easing: 'ease' }
        );

        painel.animacao.onfinish = encerrar;

        // se outro clique cancelar esta animação, o estado é de quem cancelou
        painel.animacao.oncancel = function () {
            encerrado = true;
        };

        // em aba de segundo plano os quadros não rodam e o onfinish nunca chega;
        // o relógio continua andando e fecha o item mesmo assim
        setTimeout(encerrar, duracaoSanfona + 30);
    };

    var montarSanfona = function (lista, seletorGatilho, seletorPainel, aoAbrir) {
        if (!lista) {
            return null;
        }

        var itens = [].slice.call(lista.children).filter(function (filho) {
            return filho.tagName === 'DETAILS';
        });

        var fechar = function (item) {
            var painel = item.querySelector(seletorPainel);

            animarPainel(painel, painel.offsetHeight, 0, function () {
                item.open = false;
            });
        };

        var abrir = function (item) {
            itens.forEach(function (outro) {
                if (outro !== item && outro.open) {
                    fechar(outro);
                }
            });

            if (item.open) {
                return;
            }

            item.open = true;
            var painel = item.querySelector(seletorPainel);
            animarPainel(painel, 0, painel.offsetHeight, function () {});

            if (aoAbrir) {
                aoAbrir(item);
            }
        };

        itens.forEach(function (item) {
            item.querySelector(seletorGatilho).addEventListener('click', function (evento) {
                // o clique abriria o details na hora, sem passar pela animação
                evento.preventDefault();

                if (item.open) {
                    fechar(item);
                    return;
                }

                abrir(item);
            });
        });

        return { abrir: abrir };
    };

    montarSanfona(document.querySelector('.faq'), '.faq__pergunta', '.faq__resposta');

    /* central de ajuda: os atalhos do topo abrem o cartão do tema */
    var atalhos = [].slice.call(document.querySelectorAll('.ajuda-atalho'));

    var marcarAtalho = function (cartao) {
        atalhos.forEach(function (atalho) {
            atalho.classList.toggle('ajuda-atalho--ativo', atalho.getAttribute('href') === '#' + cartao.id);
        });
    };

    var ajuda = montarSanfona(document.querySelector('.ajuda-lista'), '.ajuda-card__resumo', '.ajuda-card__painel', marcarAtalho);

    if (ajuda) {
        atalhos.forEach(function (atalho) {
            atalho.addEventListener('click', function (evento) {
                var cartao = document.querySelector(atalho.getAttribute('href'));

                if (!cartao) {
                    return;
                }

                evento.preventDefault();
                ajuda.abrir(cartao);
                marcarAtalho(cartao);
                cartao.scrollIntoView({ behavior: semMovimento.matches ? 'auto' : 'smooth', block: 'center' });
            });
        });
    }

    /* envio do manuscrito: arrastar o arquivo e mostrar o nome escolhido */
    var areaArquivo = document.querySelector('[data-arquivo]');

    if (areaArquivo) {
        var entrada = areaArquivo.querySelector('input[type="file"]');
        var chamada = areaArquivo.querySelector('.arquivo__chamada');
        var textoOriginal = chamada.innerHTML;

        var mostrarEscolhido = function () {
            chamada.textContent = entrada.files && entrada.files.length
                ? entrada.files[0].name
                : '';

            if (!chamada.textContent) {
                chamada.innerHTML = textoOriginal;
            }
        };

        entrada.addEventListener('change', mostrarEscolhido);

        ['dragenter', 'dragover'].forEach(function (evento) {
            areaArquivo.addEventListener(evento, function (e) {
                e.preventDefault();
                areaArquivo.classList.add('arquivo--sobre');
            });
        });

        ['dragleave', 'drop'].forEach(function (evento) {
            areaArquivo.addEventListener(evento, function (e) {
                e.preventDefault();
                areaArquivo.classList.remove('arquivo--sobre');
            });
        });

        areaArquivo.addEventListener('drop', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.files.length) {
                return;
            }

            entrada.files = e.dataTransfer.files;
            mostrarEscolhido();
        });
    }

    /* no celular a coluna de filtros começa fechada, para os livros aparecerem
       logo; sem JavaScript ela fica aberta, que também funciona */
    var caixaFiltros = document.querySelector('.filtros-caixa');

    if (caixaFiltros && window.matchMedia('(max-width: 760px)').matches) {
        caixaFiltros.open = false;
    }

    /* filtros do catálogo: "ver todas" e a busca dentro do grupo */
    document.querySelectorAll('[data-filtro]').forEach(function (grupo) {
        var mais = grupo.querySelector('[data-filtro-mais]');
        var busca = grupo.querySelector('[data-filtro-busca]');

        if (mais) {
            mais.addEventListener('click', function () {
                var escondidos = grupo.querySelectorAll('[data-extra]');
                var abrindo = escondidos[0] && escondidos[0].hidden;

                escondidos.forEach(function (item) {
                    item.hidden = !abrindo;
                });

                mais.textContent = abrindo ? 'Ver menos' : 'Ver todas';
            });
        }

        if (busca) {
            busca.addEventListener('input', function () {
                var procurado = busca.value.trim().toLowerCase();

                grupo.querySelectorAll('.filtros__lista li').forEach(function (item) {
                    var texto = item.textContent.trim().toLowerCase();
                    // com busca em curso o "ver todas" não esconde mais nada
                    item.hidden = procurado ? texto.indexOf(procurado) === -1 : item.hasAttribute('data-extra');
                });
            });
        }
    });

    /* voltar ao topo */
    var topo = document.querySelector('.flutuante--topo');

    if (topo) {
        topo.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
}());
