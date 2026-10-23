import { gravarPref, lerPref } from '../plataforma/armazenamento';
import type { Orientacao } from '../plataforma/nativo';
import { criarLoja, useLoja } from './loja';

export type TemaLeitura = 'claro' | 'sepia' | 'escuro';
export type ModoPaginas = 'auto' | 'uma' | 'duas';

export interface AjustesLeitura {
  tema: TemaLeitura;
  virarPorToque: boolean;
  paginas: ModoPaginas;
  orientacao: Orientacao;
}

const PADRAO: AjustesLeitura = { tema: 'claro', virarPorToque: true, paginas: 'auto', orientacao: 'auto' };

export const lojaAjustesLeitura = criarLoja<AjustesLeitura>({ ...PADRAO, ...lerPref<Partial<AjustesLeitura>>('leitura', {}) });

export function mudarAjusteLeitura<K extends keyof AjustesLeitura>(chave: K, valor: AjustesLeitura[K]) {
  lojaAjustesLeitura.definir((a) => {
    const novo = { ...a, [chave]: valor };
    gravarPref('leitura', novo);
    return novo;
  });
}

export function useAjustesLeitura() {
  return useLoja(lojaAjustesLeitura);
}
