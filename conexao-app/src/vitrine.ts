import { ehNativo } from './plataforma/nativo';

/**
 * Regras de exibição que dependem de onde o app roda.
 *
 * - App nativo (Google Play / App Store): sem links ou botões que levem a comprar (regras das lojas).
 * - Modo de capturas de tela (só no build de demonstração): abrir com `?capturas=ios|android|1`.
 *   Esconde os avisos de "dados de exemplo" e os links de compra, para as imagens das lojas.
 *   O valor fica guardado na aba (sessionStorage) enquanto ela estiver aberta.
 */

const demonstracao = import.meta.env.VITE_MOCK === '1' || import.meta.env.VITE_MOCK === 'true';

function lerCapturas(): string {
  try {
    const param = new URLSearchParams(window.location.search).get('capturas');
    if (param) sessionStorage.setItem('cx-capturas', param);
    return sessionStorage.getItem('cx-capturas') ?? '';
  } catch {
    return '';
  }
}

/** '' fora do modo de capturas; 'ios', 'android' ou '1' dentro dele. */
export const modoCapturas = demonstracao ? lerCapturas() : '';

/** Mostrar os avisos "modo de demonstração / dados de exemplo". */
export const mostrarAvisosDemo = demonstracao && !modoCapturas;

/** Esconder links para a loja e qualquer chamada para comprar. */
export const semLinksDeCompra = ehNativo || !!modoCapturas;
