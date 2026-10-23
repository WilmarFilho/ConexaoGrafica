import type { Aparelho } from '../api/tipos';
import { relativo } from '../util/formato';
import { Icone } from './Icone';
import { Botao } from './ui';

export function ItemAparelho({
  aparelho,
  acao,
}: {
  aparelho: Aparelho;
  acao?: { texto: string; carregando?: boolean; desativado?: boolean; aoClicar: () => void };
}) {
  const icone = aparelho.plataforma === 'web' ? 'computador' : /tab|ipad/i.test(aparelho.nome) ? 'tablet' : 'celular';
  return (
    <li className={`cartao aparelho${aparelho.atual ? ' aparelho--atual' : ''}`}>
      <span className="aparelho__icone">
        <Icone nome={icone} />
      </span>
      <div className="aparelho__texto">
        <strong>{aparelho.nome}</strong>
        <small>
          {aparelho.atual ? 'Este aparelho' : aparelho.usado_em ? `Último uso ${relativo(aparelho.usado_em)}` : 'Sem uso recente'}
          {' · '}
          {aparelho.plataforma === 'web' ? 'Navegador' : aparelho.plataforma === 'ios' ? 'iOS' : 'Android'}
        </small>
      </div>
      {aparelho.atual ? (
        <span className="selo selo--azul">Atual</span>
      ) : (
        acao && (
          <Botao variante="contorno" pequeno carregando={acao.carregando} disabled={acao.desativado} onClick={acao.aoClicar} aria-label={`${acao.texto}: ${aparelho.nome}`}>
            {acao.texto}
          </Botao>
        )
      )}
    </li>
  );
}
