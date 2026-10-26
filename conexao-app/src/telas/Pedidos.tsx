import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../api';
import { mostrarAvisosDemo, semLinksDeCompra } from '../vitrine';
import type { ListaPedidos, Pedido } from '../api/tipos';
import { CONFIG } from '../config';
import { useAviso } from '../componentes/Feedback';
import { Icone } from '../componentes/Icone';
import { PuxarParaAtualizar } from '../componentes/PuxarParaAtualizar';
import { LinhaEtapas, SeloSituacao } from '../componentes/Situacao';
import { Botao, Cabecalho, Carregando, Erro, Vazio } from '../componentes/ui';
import { useOnline } from '../estado/rede';
import { gravarCache, lerCache } from '../plataforma/armazenamento';
import { abrirExterno } from '../plataforma/nativo';
import { dataCurta, dinheiro } from '../util/formato';

export function Pedidos() {
  const online = useOnline();
  const [dados, setDados] = useState<ListaPedidos | null>(() => lerCache<ListaPedidos>('pedidos'));
  const [erro, setErro] = useState<string | null>(null);
  const [carregando, setCarregando] = useState(false);

  const carregar = useCallback(async () => {
    setCarregando(true);
    setErro(null);
    try {
      const r = await api.pedidos();
      setDados(r);
      gravarCache('pedidos', r);
    } catch (e) {
      if (!(e instanceof ErroApi && e.status === 401)) setErro(mensagemDeErro(e));
    } finally {
      setCarregando(false);
    }
  }, []);

  useEffect(() => {
    if (online) void carregar();
  }, [online, carregar]);

  return (
    <PuxarParaAtualizar aoAtualizar={carregar} desativado={!online}>
      <Cabecalho titulo="Meus pedidos" grande />
      <div className="pagina">
        {mostrarAvisosDemo && <p className="selo-demo">Dados de exemplo · modo de demonstração</p>}
        {!online && dados && <p className="alerta alerta--info">Sem internet: mostrando a última lista carregada.</p>}
        {dados && dados.email_verificado === false && <CartaoConfirmarEmail />}

        {!dados && carregando && <Carregando texto="Carregando seus pedidos…" />}
        {!dados && erro && <Erro mensagem={erro} aoTentar={carregar} />}
        {!dados && !online && !carregando && <Vazio icone="sem-internet" titulo="Sem internet" texto="Conecte-se para ver seus pedidos." />}
        {dados && erro && (
          <p className="alerta alerta--erro" role="alert">
            {erro}
          </p>
        )}

        {dados && dados.pedidos.length === 0 && (
          <Vazio icone="pedidos" titulo="Nenhum pedido por aqui" texto="Seus pedidos de livros impressos e digitais aparecem aqui, com a situação de cada um, do pagamento até a entrega.">
            {!semLinksDeCompra && (
              <button type="button" className="botao botao--primario" onClick={() => void abrirExterno(CONFIG.lojaUrl)}>
                <Icone nome="loja" tamanho={20} />
                <span>Ir para a loja</span>
              </button>
            )}
          </Vazio>
        )}

        {dados && dados.pedidos.length > 0 && (
          <ul className="lista-pedidos">
            {dados.pedidos.map((p) => (
              <CartaoPedido key={p.ref} pedido={p} />
            ))}
          </ul>
        )}
        {dados?.fonte === 'loja' && dados.email_verificado !== false && dados.pedidos.length > 0 && (
          <p className="texto-suave texto-pequeno">Mostrando os pedidos feitos no site. Pedidos de outros canais podem demorar a aparecer.</p>
        )}
      </div>
    </PuxarParaAtualizar>
  );
}

function CartaoPedido({ pedido }: { pedido: Pedido }) {
  const primeiro = pedido.itens?.[0];
  const mais = (pedido.itens?.length ?? 0) - 1;
  return (
    <li>
      <Link to={`/pedidos/${encodeURIComponent(pedido.ref)}`} className="cartao pedido">
        <div className="pedido__topo">
          <div>
            <strong className="pedido__numero">Pedido {pedido.numero}</strong>
            <span className="pedido__meta">
              {dataCurta(pedido.feito_em)} · {pedido.canal}
            </span>
          </div>
          <SeloSituacao pedido={pedido} />
        </div>
        {primeiro && (
          <p className="pedido__itens">
            {primeiro.quantidade > 1 ? `${primeiro.quantidade}× ` : ''}
            {primeiro.nome}
            {mais > 0 ? ` e mais ${mais} ${mais === 1 ? 'item' : 'itens'}` : ''}
          </p>
        )}
        {!pedido.digital && pedido.situacao !== 'cancelado' && pedido.situacao !== 'problema' && <LinhaEtapas situacao={pedido.situacao} compacta />}
        <div className="pedido__rodape">
          <span className="pedido__tipo">
            <Icone nome={pedido.digital ? 'livro' : 'caminhao'} tamanho={16} />
            {pedido.digital ? 'E-book' : 'Impresso'}
          </span>
          <span className="pedido__total">{dinheiro(pedido.total_centavos)}</span>
          <Icone nome="avancar" tamanho={18} className="pedido__seta" />
        </div>
      </Link>
    </li>
  );
}

function CartaoConfirmarEmail() {
  const navegar = useNavigate();
  const avisar = useAviso();
  const online = useOnline();
  const [enviando, setEnviando] = useState(false);
  return (
    <section className="cartao cartao--destaque confirmar-email" aria-labelledby="titulo-confirmar-email">
      <span className="confirmar-email__icone">
        <Icone nome="escudo" />
      </span>
      <div>
        <h2 id="titulo-confirmar-email">Confirme seu e-mail</h2>
        <p>Confirme seu e-mail para ver também os pedidos feitos em outros canais (páginas de venda, Amazon).</p>
        <Botao
          pequeno
          carregando={enviando}
          disabled={!online}
          onClick={async () => {
            setEnviando(true);
            try {
              await api.enviarCodigoEmail();
              navegar('/pedidos/confirmar-email');
            } catch (e) {
              avisar(mensagemDeErro(e), 'erro');
            } finally {
              setEnviando(false);
            }
          }}
        >
          Enviar código
        </Botao>
      </div>
    </section>
  );
}
