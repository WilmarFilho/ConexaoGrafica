/**
 * Mapa azul da página de contato (Google Maps com o estilo "Bluish").
 * Só carrega a API quando o mapa chega perto da tela. Se a chave falhar ou o
 * endereço não for encontrado, o mapa comum do Google (o iframe) continua lá.
 */
(function () {
  'use strict';

  var caixa = document.querySelector('.mapa-caixa[data-chave]');

  if (!caixa) {
    return;
  }

  var reserva = caixa.innerHTML;

  var estilo = [
    { stylers: [{ hue: '#007fff' }, { saturation: 89 }] },
    { featureType: 'water', stylers: [{ color: '#ffffff' }] },
    { featureType: 'administrative.country', elementType: 'labels', stylers: [{ visibility: 'off' }] }
  ];

  function volta() {
    caixa.innerHTML = reserva;
    caixa.classList.remove('mapa-caixa--estilizado');
  }

  // o Google chama isto quando a chave é inválida ou não vale para o domínio
  window.gm_authFailure = volta;

  function posicao() {
    var coordenadas = (caixa.dataset.coordenadas || '').split(',');

    if (coordenadas.length === 2) {
      return Promise.resolve({ lat: parseFloat(coordenadas[0]), lng: parseFloat(coordenadas[1]) });
    }

    return new google.maps.Geocoder()
      .geocode({ address: caixa.dataset.endereco, region: 'br' })
      .then(function (resposta) {
        var local = resposta.results[0] && resposta.results[0].geometry.location;

        return local ? { lat: local.lat(), lng: local.lng() } : null;
      })
      .catch(function () {
        return null;
      });
  }

  window.conexaoMapaPronto = function () {
    posicao().then(function (centro) {
      if (!centro) {
        return;
      }

      caixa.innerHTML = '';
      caixa.classList.add('mapa-caixa--estilizado');

      var mapa = new google.maps.Map(caixa, {
        center: centro,
        zoom: 16,
        styles: estilo,
        disableDefaultUI: true,
        zoomControl: true,
        fullscreenControl: true,
        gestureHandling: 'cooperative'
      });

      new google.maps.Marker({
        position: centro,
        map: mapa,
        title: caixa.dataset.titulo || '',
        icon: {
          path: 'M12 1.5C7.6 1.5 4 5 4 9.4 4 15.3 12 22.5 12 22.5s8-7.2 8-13.1C20 5 16.4 1.5 12 1.5zm0 11a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4z',
          fillColor: '#00448B',
          fillOpacity: 1,
          strokeColor: '#ffffff',
          strokeWeight: 1.5,
          scale: 1.8,
          anchor: new google.maps.Point(12, 22.5)
        }
      });
    });
  };

  function carrega() {
    var script = document.createElement('script');

    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(caixa.dataset.chave) +
      '&callback=conexaoMapaPronto&loading=async&language=pt-BR&region=BR';
    script.async = true;
    script.onerror = volta;
    document.head.appendChild(script);
  }

  if ('IntersectionObserver' in window) {
    var observador = new IntersectionObserver(function (entradas) {
      if (entradas.some(function (e) { return e.isIntersecting; })) {
        observador.disconnect();
        carrega();
      }
    }, { rootMargin: '300px' });

    observador.observe(caixa);
  } else {
    carrega();
  }
})();
