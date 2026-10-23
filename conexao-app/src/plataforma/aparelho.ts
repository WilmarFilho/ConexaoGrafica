import type { DadosAparelho } from '../api/tipos';
import { idLocal } from './armazenamento';
import { plataforma } from './nativo';

/** Nome amigável do aparelho para a lista "Aparelhos conectados" (ex.: "Chrome · Windows", "Android · SM-S911B"). */
export function nomeDoAparelho(): string {
  const ua = navigator.userAgent;
  if (plataforma === 'ios') return /iPad/.test(ua) ? 'iPad' : 'iPhone';
  if (plataforma === 'android') {
    const m = ua.match(/Android [\d.]+; ([^;)]+?)(?: Build|\))/);
    const modelo = m?.[1]?.trim();
    return modelo && modelo !== 'K' ? `Android · ${modelo}` : 'Celular Android';
  }
  const navegador = /Edg\//.test(ua)
    ? 'Edge'
    : /OPR\//.test(ua)
      ? 'Opera'
      : /Firefox\//.test(ua)
        ? 'Firefox'
        : /Chrome\//.test(ua)
          ? 'Chrome'
          : /Safari\//.test(ua)
            ? 'Safari'
            : 'Navegador';
  const sistema = /Windows/.test(ua)
    ? 'Windows'
    : /Android/.test(ua)
      ? 'Android'
      : /iPhone|iPad/.test(ua)
        ? 'iOS'
        : /Mac OS X/.test(ua)
          ? 'macOS'
          : /CrOS/.test(ua)
            ? 'ChromeOS'
            : /Linux/.test(ua)
              ? 'Linux'
              : '';
  return sistema ? `${navegador} · ${sistema}` : navegador;
}

export async function dadosDoAparelho(): Promise<DadosAparelho> {
  return { nome: nomeDoAparelho(), plataforma, id_local: await idLocal() };
}
