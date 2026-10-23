/* Conexão Admin: grupo "Mais" no fim do menu (Rank Math, Compliance, Melhor Envio legado).
   Fica recolhido, a não ser que a tela aberta seja de um desses itens. */
(function () {
  var separador = document.querySelector('#adminmenu li.cxa-mais');

  if (!separador) {
    return;
  }

  var itens = [];
  var no = separador.nextElementSibling;

  while (no) {
    if (no.id !== 'collapse-menu') {
      itens.push(no);
    }
    no = no.nextElementSibling;
  }

  if (!itens.length) {
    return;
  }

  var ativo = itens.some(function (li) {
    return li.classList.contains('wp-has-current-submenu') || li.classList.contains('current');
  });

  var guardado = null;
  try {
    guardado = window.localStorage.getItem('cxaMenuMais');
  } catch (e) {}

  var aberto = ativo || guardado === '1';
  var botao = document.createElement('button');
  botao.type = 'button';
  botao.className = 'cxa-mais__botao';
  botao.textContent = 'Mais';

  function aplica() {
    botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    separador.classList.toggle('cxa-mais--aberto', aberto);
    itens.forEach(function (li) {
      li.classList.toggle('cxa-mais__oculto', !aberto);
    });
  }

  botao.addEventListener('click', function () {
    aberto = !aberto;
    try {
      window.localStorage.setItem('cxaMenuMais', aberto ? '1' : '0');
    } catch (e) {}
    aplica();
  });

  separador.removeAttribute('aria-hidden');
  separador.innerHTML = '';
  separador.appendChild(botao);
  aplica();
})();

/* Conexão Admin: botão "Modo claro / Modo escuro" da barra do topo.
   Troca a classe do <body> (e do editor clássico) na hora e grava a escolha do usuário. */
(function () {
  var item = document.querySelector('#wp-admin-bar-cxa-tema > .ab-item');
  var dados = window.cxaTema;

  if (!item || !dados) {
    return;
  }

  item.setAttribute('role', 'button');

  function aplica(claro) {
    document.body.classList.toggle('cxa-claro', claro);

    var rotulo = item.querySelector('.cxa-tema__rotulo');
    if (rotulo) {
      rotulo.textContent = claro ? 'Modo escuro' : 'Modo claro';
    }

    // iframes do TinyMCE
    if (window.tinymce && window.tinymce.editors) {
      window.tinymce.editors.forEach(function (editor) {
        var corpo = editor.getBody && editor.getBody();
        if (corpo) {
          corpo.classList.toggle('cxa-claro', claro);
        }
      });
    }
  }

  item.addEventListener('click', function (evento) {
    evento.preventDefault();

    var claro = !document.body.classList.contains('cxa-claro');
    aplica(claro);

    var corpo = new FormData();
    corpo.append('action', 'conexao_admin_tema');
    corpo.append('nonce', dados.nonce);
    corpo.append('tema', claro ? 'claro' : 'escuro');

    fetch(dados.ajax, { method: 'POST', body: corpo, credentials: 'same-origin' })
      .then(function (resposta) {
        return resposta.json();
      })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error('falhou');
        }
      })
      .catch(function () {
        // não gravou: volta ao que estava
        aplica(!claro);
      });
  });
})();
