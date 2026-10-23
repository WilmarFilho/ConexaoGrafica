import { useOnline } from '../estado/rede';
import { Icone } from './Icone';

export function FaixaSemInternet({ escura }: { escura?: boolean }) {
  const online = useOnline();
  if (online) return null;
  return (
    <div className={`faixa-offline${escura ? ' faixa-offline--escura' : ''}`} role="status">
      <Icone nome="sem-internet" tamanho={18} />
      <span>Sem internet · mostrando só o que está no aparelho</span>
    </div>
  );
}
