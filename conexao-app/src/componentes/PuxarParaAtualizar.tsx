import { useRef, useState, type ReactNode } from 'react';
import { Icone } from './Icone';

const LIMIAR = 72;

/** Puxar para baixo no topo da lista para atualizar (toque). */
export function PuxarParaAtualizar({ aoAtualizar, children, desativado }: { aoAtualizar: () => Promise<unknown>; children: ReactNode; desativado?: boolean }) {
  const [puxado, setPuxado] = useState(0);
  const [atualizando, setAtualizando] = useState(false);
  const inicio = useRef<number | null>(null);
  const raiz = useRef<HTMLDivElement>(null);

  function rolador(): HTMLElement | null {
    return raiz.current?.closest('.app__conteudo') ?? null;
  }

  return (
    <div
      ref={raiz}
      className="puxar"
      onTouchStart={(e) => {
        if (desativado || atualizando || (rolador()?.scrollTop ?? 0) > 0) return;
        inicio.current = e.touches[0].clientY;
      }}
      onTouchMove={(e) => {
        if (inicio.current === null) return;
        const d = e.touches[0].clientY - inicio.current;
        setPuxado(d > 0 ? Math.min(110, d * 0.5) : 0);
      }}
      onTouchEnd={() => {
        if (inicio.current === null) return;
        inicio.current = null;
        if (puxado >= LIMIAR * 0.5 + 20) {
          setAtualizando(true);
          setPuxado(56);
          aoAtualizar().finally(() => {
            setAtualizando(false);
            setPuxado(0);
          });
        } else setPuxado(0);
      }}
    >
      <div className="puxar__indicador" style={{ height: puxado, opacity: Math.min(1, puxado / 50) }} aria-hidden={!atualizando}>
        <span className={`puxar__icone${atualizando ? ' puxar__icone--girando' : ''}`} style={{ transform: atualizando ? undefined : `rotate(${puxado * 3}deg)` }}>
          <Icone nome="atualizar" tamanho={22} />
        </span>
        {atualizando && <span className="sr-only" role="status">Atualizando…</span>}
      </div>
      {children}
    </div>
  );
}
