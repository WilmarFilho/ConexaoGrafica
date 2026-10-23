import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../api';
import type { ListaPedidos, Pedido } from '../api/tipos';
import { CONFIG } from '../config';
import { useAviso } from '../componentes/Feedback';
import { Icone } from '../componentes/Icone';
import { LinhaEtapas, SeloSituacao } from '../componentes/Situacao';
import { Botao, Cabecalho, Carregando, Erro } from '../componentes/ui';
import { useOnline } from '../estado/rede';
import { gravarCache, lerCache } from '../plataforma/armazenamento';
import { abrirExterno, abrirSistema, copiarTexto } from '../plataforma/nativo';
import { dataHora, dataLonga, dinheiro } from '../util/formato';

export function PedidoDetalhe() {
  const ref = decodeURIComponent(useParams().ref ?? '');
  const online = useOnline();
  const avisar = useAviso();
  const [pedido, setPedido] = useState<Pedido | null>(
    () => lerCache<Pedido>(`pedido:${ref}`) ?? lerCache<ListaPedidos>('pedidos')?.pedidos.find((p) => p.ref === ref) ?? null
  );
  const [erro, setErro] = useState<string | null>(null);

  const carregar = useCallback(async () => {
    setErro(null);
    try {
      const p = await api.pedido(ref);
      setPedido(p);
      gravarCache(`pedido:${ref}`, p);
    } catch (e) {
      if (!(e instanceof ErroApi && e.status === 401)) setErro(mensagemDeErro(e));
    }
  }, [ref]);

  useEffect(() => {
    if (online) void carregar();
  }, [online, carregar]);

  if (!pedido) {
    return (
      <>
        <Cabecalho titulo="Pedido" voltar="/pedidos" />
        <div className="pagina">{erro ? <Erro mensagem={erro} aoTentar={carregar} /> : online ? <Carregando /> : <Erro mensagem="Sem internet. Conecte-se para ver este pedido." />}</div>
      </>
    );
  }

  const envio = pedido.envio;
  const eventos = [...(pedido.eventos ?? [])].reverse();
  const quantidade = pedido.itens?.reduce((s, i) => s + i.quantidade, 0) ?? 0;

  async function copiar(codigo: string) {
    const ok = await copiarTexto(codigo);
    avisar(ok ? 'Código de rastreio copiado.' : 'Não foi possível copiar.', ok ? 'sucesso' : 'erro');
  }

  return (
    <>
      <Cabecalho titulo={`Pedido ${pedido.numero}`} voltar="/pedidos" />
      <div className="pagina detalhe-pedido">
        <section className="cartao">
          <div className="pedido__topo">
            <div>
              <span className="pedido__meta">Feito em {dataLonga(pedido.feito_em)}</span>
              <span className="pedido__meta">{pedido.canal}</span>
            </div>
            <SeloSituacao pedido={pedido} />
          </div>
          {!pedido.digital && pedido.situacao !== 'cancelado' && pedido.situacao !== 'problema' && <LinhaEtapas situacao={pedido.situacao} />}
          {pedido.situacao === 'problema' && (
            <div className="alerta alerta--erro">
              <strong>Este pedido precisa de atenção.</strong> Nossa equipe já foi avisada. Se quiser, fale com a gente pelo suporte.
              {CONFIG.suporteWhatsapp && (
                <Botao
                  variante="contorno"
                  pequeno
                  icone="whatsapp"
                  onClick={() => abrirSistema(`https://wa.me/${CONFIG.suporteWhatsapp}?text=${encodeURIComponent(`Olá! Preciso de ajuda com o pedido ${pedido.numero}.`)}`)}
                >
                  Falar no WhatsApp
                </Botao>
              )}
            </div>
          )}
          {pedido.situacao === 'concluido_digital' && <p className="texto-suave">Seu e-book já está na estante, pronto para ler.</p>}
          {pedido.situacao === 'aguardando_pagamento' && <p className="texto-suave">Assim que o pagamento for confirmado, você verá a próxima etapa aqui.</p>}
        </section>

        {envio && (envio.codigo || envio.transportadora) && (
          <section className="cartao" aria-labelledby="titulo-entrega">
            <h2 id="titulo-entrega" className="cartao__titulo">
              <Icone nome="caminhao" tamanho={20} /> Entrega
            </h2>
            <p className="texto-suave">{[envio.transportadora, envio.servico].filter(Boolean).join(' · ')}</p>
            {envio.codigo ? (
              <div className="rastreio">
                <div className="rastreio__codigo">
                  <small>Código de rastreio</small>
                  <strong>{envio.codigo}</strong>
                </div>
                <button type="button" className="botao botao--contorno botao--pequeno" onClick={() => copiar(envio.codigo!)} aria-label={`Copiar código ${envio.codigo}`}>
                  <Icone nome="copiar" tamanho={18} />
                  <span>Copiar</span>
                </button>
              </div>
            ) : (
              <p className="texto-suave">O código de rastreio aparece aqui quando o pedido for postado.</p>
            )}
            {envio.previsao && !envio.entregue_em && <p>Previsão de entrega: <b>{dataLonga(envio.previsao)}</b></p>}
            {envio.entregue_em && <p>Entregue em <b>{dataLonga(envio.entregue_em)}</b></p>}
            {envio.url_rastreio && (
              <Botao bloco icone="externo" onClick={() => void abrirExterno(envio.url_rastreio!)}>
                Acompanhar entrega
              </Botao>
            )}
          </section>
        )}

        {pedido.itens && pedido.itens.length > 0 && (
          <section className="cartao" aria-labelledby="titulo-itens">
            <h2 id="titulo-itens" className="cartao__titulo">
              <Icone nome="pacote" tamanho={20} /> Itens ({quantidade})
            </h2>
            <ul className="itens-pedido">
              {pedido.itens.map((i, n) => (
                <li key={n}>
                  <span className="itens-pedido__qtd">{i.quantidade}×</span>
                  <div className="itens-pedido__nome">
                    {i.livro_id ? <Link to={`/livro/${i.livro_id}`}>{i.nome}</Link> : i.nome}
                    {i.formato && <small>{i.formato}</small>}
                  </div>
                </li>
              ))}
            </ul>
            <div className="itens-pedido__total">
              <span>Total</span>
              <strong>{dinheiro(pedido.total_centavos)}</strong>
            </div>
          </section>
        )}

        {eventos.length > 0 && (
          <section className="cartao" aria-labelledby="titulo-historico">
            <h2 id="titulo-historico" className="cartao__titulo">
              <Icone nome="relogio" tamanho={20} /> Histórico
            </h2>
            <ol className="historico">
              {eventos.map((ev, i) => (
                <li key={i} className={i === 0 ? 'historico__item historico__item--atual' : 'historico__item'}>
                  <span className="historico__ponto" aria-hidden="true" />
                  <div>
                    <strong>{ev.rotulo}</strong>
                    {ev.em && <small>{dataHora(ev.em)}</small>}
                  </div>
                </li>
              ))}
            </ol>
          </section>
        )}
        {erro && (
          <p className="alerta alerta--erro" role="alert">
            {erro}
          </p>
        )}
      </div>
    </>
  );
}
