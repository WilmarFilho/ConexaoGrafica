import { useSyncExternalStore } from 'react';

/** Mini "store" observável para estado global simples (sem bibliotecas). */
export interface Loja<T> {
  obter(): T;
  definir(valor: T | ((anterior: T) => T)): void;
  assinar(fn: () => void): () => void;
}

export function criarLoja<T>(inicial: T): Loja<T> {
  let valor = inicial;
  const ouvintes = new Set<() => void>();
  return {
    obter: () => valor,
    definir(novo) {
      valor = typeof novo === 'function' ? (novo as (a: T) => T)(valor) : novo;
      ouvintes.forEach((fn) => fn());
    },
    assinar(fn) {
      ouvintes.add(fn);
      return () => ouvintes.delete(fn);
    },
  };
}

export function useLoja<T>(loja: Loja<T>): T {
  return useSyncExternalStore(loja.assinar, loja.obter, loja.obter);
}
