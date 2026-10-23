import type { Pedido, Situacao } from '../api/tipos';
import { Icone } from './Icone';

export const ETAPAS: { situacao: Situacao; rotulo: string }[] = [
  { situacao: 'aguardando_pagamento', rotulo: 'Pedido feito' },
  { situacao: 'pago', rotulo: 'Pago' },
  { situacao: 'em_separacao', rotulo: 'Em separação' },
  { situacao: 'a_caminho', rotulo: 'A caminho' },
  { situacao: 'entregue', rotulo: 'Entregue' },
];

const TOM: Record<Situacao, string> = {
  aguardando_pagamento: 'amarelo',
  pago: 'azul',
  em_separacao: 'azul',
  a_caminho: 'azul',
  entregue: 'verde',
  concluido_digital: 'verde',
  cancelado: 'cinza',
  problema: 'vermelho',
};

const ROTULO_PADRAO: Record<Situacao, string> = {
  aguardando_pagamento: 'Aguardando pagamento',
  pago: 'Pagamento confirmado',
  em_separacao: 'Em separação',
  a_caminho: 'A caminho',
  entregue: 'Entregue',
  concluido_digital: 'Disponível na estante',
  cancelado: 'Cancelado',
  problema: 'Precisa de atenção',
};

export function SeloSituacao({ pedido }: { pedido: Pick<Pedido, 'situacao' | 'situacao_rotulo'> }) {
  const tom = TOM[pedido.situacao] ?? 'cinza';
  return <span className={`selo selo--${tom}`}>{pedido.situacao_rotulo || ROTULO_PADRAO[pedido.situacao] || pedido.situacao}</span>;
}

export function etapaAtual(s: Situacao) {
  return ETAPAS.findIndex((e) => e.situacao === s);
}

/** Linha do tempo das etapas de entrega (pedidos físicos). */
export function LinhaEtapas({ situacao, compacta }: { situacao: Situacao; compacta?: boolean }) {
  const atual = etapaAtual(situacao);
  if (atual < 0) return null;
  return (
    <ol className={`etapas${compacta ? ' etapas--compacta' : ''}`} aria-label={`Etapa ${atual + 1} de ${ETAPAS.length}: ${ETAPAS[atual].rotulo}`}>
      {ETAPAS.map((e, i) => (
        <li key={e.situacao} className={`etapas__item${i < atual ? ' etapas__item--feita' : ''}${i === atual ? ' etapas__item--atual' : ''}`} aria-current={i === atual ? 'step' : undefined}>
          <span className="etapas__ponto">{i < atual ? <Icone nome="check" tamanho={compacta ? 10 : 14} /> : null}</span>
          {!compacta && <span className="etapas__rotulo">{e.rotulo}</span>}
        </li>
      ))}
    </ol>
  );
}
