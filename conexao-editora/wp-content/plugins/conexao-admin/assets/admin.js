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
