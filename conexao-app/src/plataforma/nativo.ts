import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { Share } from '@capacitor/share';
import { StatusBar, Style } from '@capacitor/status-bar';
import { ScreenOrientation } from '@capacitor/screen-orientation';
import type { Plataforma } from '../api/tipos';

export const ehNativo = Capacitor.isNativePlatform();
export const plataforma = Capacitor.getPlatform() as Plataforma;

/** Abre um endereço externo: navegador interno no app nativo, nova aba na web. */
export async function abrirExterno(url: string) {
  if (ehNativo) {
    await Browser.open({ url, presentationStyle: 'popover' });
  } else {
    window.open(url, '_blank', 'noopener,noreferrer');
  }
}

/**
 * Links que o sistema deve tratar (WhatsApp, e-mail). No app nativo, navegar para um endereço
 * externo faz o Capacitor entregar o link ao sistema (abre o WhatsApp / app de e-mail).
 */
export function abrirSistema(url: string) {
  if (!ehNativo && /^https?:/i.test(url)) {
    window.open(url, '_blank', 'noopener,noreferrer');
    return;
  }
  window.location.href = url;
}

export async function compartilhar(dados: { titulo: string; texto?: string; url?: string }) {
  try {
    if (ehNativo) {
      await Share.share({ title: dados.titulo, text: dados.texto, url: dados.url, dialogTitle: 'Compartilhar' });
      return true;
    }
    if (navigator.share) {
      await navigator.share({ title: dados.titulo, text: dados.texto, url: dados.url });
      return true;
    }
    if (dados.url) {
      await copiarTexto(dados.url);
      return 'copiado' as const;
    }
  } catch {
    /* pessoa cancelou */
  }
  return false;
}

export async function copiarTexto(texto: string) {
  try {
    await navigator.clipboard.writeText(texto);
    return true;
  } catch {
    // Alternativa para WebViews sem a API de área de transferência
    const campo = document.createElement('textarea');
    campo.value = texto;
    campo.setAttribute('readonly', '');
    campo.style.position = 'fixed';
    campo.style.opacity = '0';
    document.body.appendChild(campo);
    campo.select();
    let ok = false;
    try {
      ok = document.execCommand('copy');
    } catch {
      ok = false;
    }
    campo.remove();
    return ok;
  }
}

export async function estiloBarraStatus(escuro: boolean) {
  if (!ehNativo) return;
  try {
    // Style.Dark = texto claro (para fundo escuro)
    await StatusBar.setStyle({ style: escuro ? Style.Dark : Style.Light });
  } catch {
    /* sem suporte */
  }
}

export async function barraStatusVisivel(visivel: boolean) {
  if (!ehNativo) return;
  try {
    if (visivel) await StatusBar.show();
    else await StatusBar.hide();
  } catch {
    /* sem suporte */
  }
}

export type Orientacao = 'auto' | 'retrato' | 'paisagem';

export async function travarOrientacao(o: Orientacao) {
  if (!ehNativo) return;
  try {
    if (o === 'auto') await ScreenOrientation.unlock();
    else await ScreenOrientation.lock({ orientation: o === 'retrato' ? 'portrait' : 'landscape' });
  } catch {
    /* sem suporte */
  }
}
