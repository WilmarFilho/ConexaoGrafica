import { gravarPref, lerPref } from '../plataforma/armazenamento';
import { estiloBarraStatus } from '../plataforma/nativo';
import { criarLoja, useLoja } from './loja';

export type PreferenciaTema = 'sistema' | 'claro' | 'escuro';

export const lojaTema = criarLoja<PreferenciaTema>(lerPref<PreferenciaTema>('tema', 'sistema'));
const consulta = typeof matchMedia !== 'undefined' ? matchMedia('(prefers-color-scheme: dark)') : null;

export function temaEfetivo(pref: PreferenciaTema = lojaTema.obter()): 'claro' | 'escuro' {
  if (pref === 'sistema') return consulta?.matches ? 'escuro' : 'claro';
  return pref;
}

export function aplicarTema() {
  const efetivo = temaEfetivo();
  const raiz = document.documentElement;
  raiz.dataset.tema = efetivo;
  raiz.style.colorScheme = efetivo === 'escuro' ? 'dark' : 'light';
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', efetivo === 'escuro' ? '#11171D' : '#FAFAFA');
  void estiloBarraStatus(efetivo === 'escuro');
}

export function definirTema(pref: PreferenciaTema) {
  gravarPref('tema', pref);
  lojaTema.definir(pref);
  aplicarTema();
}

export function iniciarTema() {
  aplicarTema();
  consulta?.addEventListener('change', () => {
    if (lojaTema.obter() === 'sistema') aplicarTema();
  });
}

export function useTema() {
  return useLoja(lojaTema);
}
