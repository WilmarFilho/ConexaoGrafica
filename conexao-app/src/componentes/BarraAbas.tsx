import { NavLink } from 'react-router';
import { Icone, type NomeIcone } from './Icone';

const ABAS: { para: string; texto: string; icone: NomeIcone }[] = [
  { para: '/estante', texto: 'Estante', icone: 'estante' },
  { para: '/pedidos', texto: 'Pedidos', icone: 'pedidos' },
  { para: '/conta', texto: 'Conta', icone: 'conta' },
];

export function BarraAbas() {
  return (
    <nav className="abas" aria-label="Navegação principal">
      {ABAS.map((a) => (
        <NavLink key={a.para} to={a.para} className={({ isActive }) => `abas__item${isActive ? ' abas__item--ativa' : ''}`}>
          <span className="abas__icone">
            <Icone nome={a.icone} tamanho={24} />
          </span>
          <span className="abas__texto">{a.texto}</span>
        </NavLink>
      ))}
    </nav>
  );
}
