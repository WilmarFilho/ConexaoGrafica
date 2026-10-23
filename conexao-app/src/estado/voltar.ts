import { useEffect, useRef } from 'react';

/**
 * Pilha de "voltar" para o botão físico do Android e a tecla Esc:
 * folhas, diálogos e painéis abertos fecham antes de a navegação voltar.
 */
const pilha: { id: number; fn: () => void }[] = [];
let proximo = 1;

export function tratarVoltar(): boolean {
  const topo = pilha[pilha.length - 1];
  if (!topo) return false;
  topo.fn();
  return true;
}

export function useVoltar(ativo: boolean, fn: () => void) {
  const ref = useRef(fn);
  ref.current = fn;
  useEffect(() => {
    if (!ativo) return;
    const id = proximo++;
    pilha.push({ id, fn: () => ref.current() });
    return () => {
      const i = pilha.findIndex((x) => x.id === id);
      if (i >= 0) pilha.splice(i, 1);
    };
  }, [ativo]);
}
