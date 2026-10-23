import { Network } from '@capacitor/network';
import { criarLoja, useLoja } from './loja';

/** Situação da internet (Network do Capacitor; na web usa navigator.onLine e os eventos online/offline). */
export const lojaRede = criarLoja<boolean>(typeof navigator === 'undefined' ? true : navigator.onLine);

const aoVoltar = new Set<() => void>();

export function iniciarRede() {
  Network.getStatus()
    .then((s) => lojaRede.definir(s.connected))
    .catch(() => undefined);
  Network.addListener('networkStatusChange', (s) => {
    const antes = lojaRede.obter();
    lojaRede.definir(s.connected);
    if (!antes && s.connected) aoVoltar.forEach((fn) => fn());
  }).catch(() => undefined);
}

export function estaOnline() {
  return lojaRede.obter();
}

export function useOnline() {
  return useLoja(lojaRede);
}

/** Executa quando a internet volta (ex.: enviar progresso pendente). */
export function quandoReconectar(fn: () => void) {
  aoVoltar.add(fn);
  return () => aoVoltar.delete(fn);
}
