import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { useVoltar } from '../estado/voltar';
import { prenderFoco } from './ui';
import { Icone } from './Icone';

// ---------- Avisos rápidos (toast) ----------

interface Aviso {
  id: number;
  texto: string;
  tipo: 'info' | 'sucesso' | 'erro';
}

type FnAviso = (texto: string, tipo?: Aviso['tipo']) => void;
const CtxAviso = createContext<FnAviso>(() => undefined);

// ---------- Confirmação ----------

interface PedidoConfirmacao {
  titulo: string;
  texto?: string;
  confirmar?: string;
  cancelar?: string;
  perigo?: boolean;
}
type FnConfirmar = (p: PedidoConfirmacao) => Promise<boolean>;
const CtxConfirmar = createContext<FnConfirmar>(async () => false);

export function FeedbackProvider({ children }: { children: ReactNode }) {
  const [avisos, setAvisos] = useState<Aviso[]>([]);
  const [confirmacao, setConfirmacao] = useState<(PedidoConfirmacao & { responder: (v: boolean) => void }) | null>(null);
  const proximo = useRef(1);

  const avisar = useCallback<FnAviso>((texto, tipo = 'info') => {
    const id = proximo.current++;
    setAvisos((a) => [...a.slice(-2), { id, texto, tipo }]);
    setTimeout(() => setAvisos((a) => a.filter((x) => x.id !== id)), 3800);
  }, []);

  const confirmar = useCallback<FnConfirmar>(
    (p) =>
      new Promise((ok) => {
        setConfirmacao({
          ...p,
          responder: (v) => {
            setConfirmacao(null);
            ok(v);
          },
        });
      }),
    []
  );

  return (
    <CtxAviso.Provider value={avisar}>
      <CtxConfirmar.Provider value={confirmar}>
        {children}
        <div className="avisos" aria-live="polite" aria-atomic="false">
          {avisos.map((a) => (
            <div key={a.id} className={`aviso aviso--${a.tipo}`} role={a.tipo === 'erro' ? 'alert' : 'status'}>
              <Icone nome={a.tipo === 'erro' ? 'alerta' : a.tipo === 'sucesso' ? 'check-circulo' : 'info'} tamanho={20} />
              <span>{a.texto}</span>
            </div>
          ))}
        </div>
        {confirmacao && <DialogoConfirmar {...confirmacao} />}
      </CtxConfirmar.Provider>
    </CtxAviso.Provider>
  );
}

function DialogoConfirmar({ titulo, texto, confirmar = 'Confirmar', cancelar = 'Cancelar', perigo, responder }: PedidoConfirmacao & { responder: (v: boolean) => void }) {
  const ref = useRef<HTMLDivElement>(null);
  useVoltar(true, () => responder(false));
  useEffect(() => {
    ref.current?.querySelector<HTMLButtonElement>('[data-cancelar]')?.focus();
  }, []);
  return (
    <div className="dialogo-fundo" onClick={() => responder(false)}>
      <div
        ref={ref}
        className="dialogo"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="dialogo-titulo"
        aria-describedby={texto ? 'dialogo-texto' : undefined}
        onClick={(e) => e.stopPropagation()}
        onKeyDown={(e) => {
          if (e.key === 'Escape') responder(false);
          if (e.key === 'Tab') prenderFoco(e, ref.current);
        }}
      >
        <h2 id="dialogo-titulo">{titulo}</h2>
        {texto && <p id="dialogo-texto">{texto}</p>}
        <div className="dialogo__acoes">
          <button type="button" className="botao botao--texto" data-cancelar onClick={() => responder(false)}>
            <span>{cancelar}</span>
          </button>
          <button type="button" className={`botao ${perigo ? 'botao--perigo' : 'botao--primario'}`} onClick={() => responder(true)}>
            <span>{confirmar}</span>
          </button>
        </div>
      </div>
    </div>
  );
}

export function useAviso() {
  return useContext(CtxAviso);
}

export function useConfirmar() {
  return useContext(CtxConfirmar);
}
