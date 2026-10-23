import { useEffect, useId, useRef, useState, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode } from 'react';
import { useNavigate } from 'react-router';
import { useVoltar } from '../estado/voltar';
import { Icone, type NomeIcone } from './Icone';

// ---------- Botões ----------

type Variante = 'primario' | 'secundario' | 'contorno' | 'texto' | 'perigo';

interface PropsBotao extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: Variante;
  icone?: NomeIcone;
  carregando?: boolean;
  bloco?: boolean;
  pequeno?: boolean;
}

export function Botao({ variante = 'primario', icone, carregando, bloco, pequeno, className, children, disabled, ...resto }: PropsBotao) {
  return (
    <button
      type="button"
      {...resto}
      disabled={disabled || carregando}
      aria-busy={carregando || undefined}
      className={['botao', `botao--${variante}`, bloco && 'botao--bloco', pequeno && 'botao--pequeno', className].filter(Boolean).join(' ')}
    >
      {carregando ? <Girando pequeno /> : icone ? <Icone nome={icone} tamanho={20} /> : null}
      <span>{children}</span>
    </button>
  );
}

interface PropsBotaoIcone extends ButtonHTMLAttributes<HTMLButtonElement> {
  icone: NomeIcone;
  rotulo: string;
  ativo?: boolean;
}

export function BotaoIcone({ icone, rotulo, ativo, className, ...resto }: PropsBotaoIcone) {
  return (
    <button type="button" aria-label={rotulo} title={rotulo} {...resto} className={['botao-icone', ativo && 'botao-icone--ativo', className].filter(Boolean).join(' ')}>
      <Icone nome={icone} />
    </button>
  );
}

// ---------- Campos ----------

interface PropsCampo extends InputHTMLAttributes<HTMLInputElement> {
  rotulo: string;
  erro?: string | null;
  ajuda?: string;
  icone?: NomeIcone;
}

export function Campo({ rotulo, erro, ajuda, icone, id, className, ...resto }: PropsCampo) {
  const gerado = useId();
  const idCampo = id ?? gerado;
  const idDesc = `${idCampo}-desc`;
  return (
    <div className={['campo', erro && 'campo--erro', className].filter(Boolean).join(' ')}>
      <label htmlFor={idCampo}>{rotulo}</label>
      <div className="campo__caixa">
        {icone && <Icone nome={icone} tamanho={20} className="campo__icone" />}
        <input id={idCampo} aria-invalid={erro ? true : undefined} aria-describedby={erro || ajuda ? idDesc : undefined} {...resto} />
      </div>
      {(erro || ajuda) && (
        <p id={idDesc} className={erro ? 'campo__erro' : 'campo__ajuda'} role={erro ? 'alert' : undefined}>
          {erro || ajuda}
        </p>
      )}
    </div>
  );
}

export function CampoSenha(props: Omit<PropsCampo, 'type'>) {
  const [visivel, setVisivel] = useState(false);
  const gerado = useId();
  const id = props.id ?? gerado;
  return (
    <div className={['campo', props.erro && 'campo--erro'].filter(Boolean).join(' ')}>
      <label htmlFor={id}>{props.rotulo}</label>
      <div className="campo__caixa">
        <Icone nome="cadeado" tamanho={20} className="campo__icone" />
        <input
          {...props}
          id={id}
          type={visivel ? 'text' : 'password'}
          aria-invalid={props.erro ? true : undefined}
          aria-describedby={props.erro || props.ajuda ? `${id}-desc` : undefined}
        />
        <button type="button" className="campo__acao" onClick={() => setVisivel((v) => !v)} aria-label={visivel ? 'Ocultar senha' : 'Mostrar senha'} aria-pressed={visivel}>
          <Icone nome={visivel ? 'olho-fechado' : 'olho'} tamanho={20} />
        </button>
      </div>
      {(props.erro || props.ajuda) && (
        <p id={`${id}-desc`} className={props.erro ? 'campo__erro' : 'campo__ajuda'} role={props.erro ? 'alert' : undefined}>
          {props.erro || props.ajuda}
        </p>
      )}
    </div>
  );
}

// ---------- Estados ----------

export function Girando({ pequeno, rotulo }: { pequeno?: boolean; rotulo?: string }) {
  return <span className={`girando${pequeno ? ' girando--pequeno' : ''}`} role={rotulo ? 'status' : undefined} aria-label={rotulo} />;
}

export function Carregando({ texto = 'Carregando…' }: { texto?: string }) {
  return (
    <div className="carregando" role="status">
      <Girando />
      <span>{texto}</span>
    </div>
  );
}

export function Vazio({ icone, titulo, texto, children }: { icone: NomeIcone; titulo: string; texto?: ReactNode; children?: ReactNode }) {
  return (
    <div className="vazio">
      <div className="vazio__icone">
        <Icone nome={icone} tamanho={34} />
      </div>
      <h2 className="vazio__titulo">{titulo}</h2>
      {texto && <p className="vazio__texto">{texto}</p>}
      {children && <div className="vazio__acoes">{children}</div>}
    </div>
  );
}

export function Erro({ mensagem, aoTentar }: { mensagem: string; aoTentar?: () => void }) {
  return (
    <Vazio icone="alerta" titulo="Não foi possível carregar" texto={mensagem}>
      {aoTentar && (
        <Botao variante="contorno" icone="atualizar" onClick={aoTentar}>
          Tentar de novo
        </Botao>
      )}
    </Vazio>
  );
}

export function BarraProgresso({ valor, rotulo, fina }: { valor: number; rotulo: string; fina?: boolean }) {
  const v = Math.max(0, Math.min(100, valor));
  return (
    <div className={`barra-progresso${fina ? ' barra-progresso--fina' : ''}`} role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(v)} aria-label={rotulo}>
      <div className="barra-progresso__valor" style={{ width: `${v}%` }} />
    </div>
  );
}

// ---------- Cabeçalho de tela ----------

export function Cabecalho({ titulo, voltar, acoes, grande }: { titulo: string; voltar?: boolean | string; acoes?: ReactNode; grande?: boolean }) {
  const navegar = useNavigate();
  return (
    <header className={`cabecalho${grande ? ' cabecalho--grande' : ''}`}>
      {voltar && (
        <BotaoIcone
          icone="voltar"
          rotulo="Voltar"
          onClick={() => (podeVoltar() ? navegar(-1) : navegar(typeof voltar === 'string' ? voltar : '/estante'))}
        />
      )}
      <h1 className="cabecalho__titulo">{titulo}</h1>
      {acoes && <div className="cabecalho__acoes">{acoes}</div>}
    </header>
  );
}

/** Há uma tela anterior do próprio app no histórico (o React Router guarda o índice em history.state.idx)? */
export function podeVoltar() {
  return ((window.history.state as { idx?: number } | null)?.idx ?? 0) > 0;
}

// ---------- Capa ----------

export function Capa({ src, titulo, className }: { src: string | null | undefined; titulo: string; className?: string }) {
  const [falhou, setFalhou] = useState(false);
  if (!src || falhou) {
    return (
      <div className={`capa capa--vazia ${className ?? ''}`} aria-hidden="true">
        <Icone nome="livro" tamanho={28} />
        <span>{titulo}</span>
      </div>
    );
  }
  return <img className={`capa ${className ?? ''}`} src={src} alt="" loading="lazy" decoding="async" onError={() => setFalhou(true)} />;
}

export function Estrelas({ media, quantidade }: { media: number; quantidade: number }) {
  if (!quantidade || media <= 0) return null;
  const cheias = Math.round(media);
  return (
    <div className="estrelas" aria-label={`Avaliação ${media.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} de 5, ${quantidade} ${quantidade === 1 ? 'avaliação' : 'avaliações'}`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <Icone key={i} nome={i <= cheias ? 'estrela' : 'estrela-vazia'} tamanho={16} />
      ))}
      <span>
        {media.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} ({quantidade})
      </span>
    </div>
  );
}

// ---------- Folha (painel que sobe de baixo; lateral em telas largas) ----------

export function Folha({ aberta, aoFechar, titulo, children, lateral, className }: { aberta: boolean; aoFechar: () => void; titulo: string; children: ReactNode; lateral?: boolean; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const idTitulo = useId();
  useVoltar(aberta, aoFechar);
  useEffect(() => {
    if (!aberta) return;
    const anterior = document.activeElement as HTMLElement | null;
    const t = setTimeout(() => {
      const raiz = ref.current;
      // Foco inicial: campo marcado, senão o primeiro controle do conteúdo (não o "Fechar").
      const alvo = raiz?.querySelector<HTMLElement>('[data-foco-inicial]') ?? raiz?.querySelector<HTMLElement>('.folha__corpo input, .folha__corpo button') ?? raiz;
      alvo?.focus();
    }, 30);
    return () => {
      clearTimeout(t);
      anterior?.focus?.();
    };
  }, [aberta]);
  if (!aberta) return null;
  return (
    <div className="folha-fundo" onClick={aoFechar}>
      <div
        ref={ref}
        className={['folha', lateral && 'folha--lateral', className].filter(Boolean).join(' ')}
        role="dialog"
        aria-modal="true"
        aria-labelledby={idTitulo}
        tabIndex={-1}
        onClick={(e) => e.stopPropagation()}
        onKeyDown={(e) => {
          if (e.key === 'Escape') {
            e.stopPropagation();
            aoFechar();
          }
          if (e.key === 'Tab') prenderFoco(e, ref.current);
        }}
      >
        <div className="folha__alca" aria-hidden="true" />
        <div className="folha__topo">
          <h2 id={idTitulo}>{titulo}</h2>
          <BotaoIcone icone="fechar" rotulo="Fechar" onClick={aoFechar} />
        </div>
        <div className="folha__corpo">{children}</div>
      </div>
    </div>
  );
}

export function prenderFoco(e: React.KeyboardEvent, raiz: HTMLElement | null) {
  if (!raiz) return;
  const focaveis = Array.from(raiz.querySelectorAll<HTMLElement>('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter((el) => !el.hasAttribute('disabled'));
  if (!focaveis.length) return;
  const primeiro = focaveis[0];
  const ultimo = focaveis[focaveis.length - 1];
  if (e.shiftKey && document.activeElement === primeiro) {
    e.preventDefault();
    ultimo.focus();
  } else if (!e.shiftKey && document.activeElement === ultimo) {
    e.preventDefault();
    primeiro.focus();
  }
}

// ---------- Seletor segmentado ----------

export function Segmentado<T extends string>({ rotulo, valor, opcoes, aoMudar }: { rotulo: string; valor: T; opcoes: { valor: T; texto: string; icone?: NomeIcone }[]; aoMudar: (v: T) => void }) {
  return (
    <div className="segmentado" role="radiogroup" aria-label={rotulo}>
      {opcoes.map((o) => (
        <button
          key={o.valor}
          type="button"
          role="radio"
          aria-checked={valor === o.valor}
          className={`segmentado__opcao${valor === o.valor ? ' segmentado__opcao--ativa' : ''}`}
          onClick={() => aoMudar(o.valor)}
        >
          {o.icone && <Icone nome={o.icone} tamanho={18} />}
          <span>{o.texto}</span>
        </button>
      ))}
    </div>
  );
}

export function Interruptor({ rotulo, descricao, ligado, aoMudar }: { rotulo: string; descricao?: string; ligado: boolean; aoMudar: (v: boolean) => void }) {
  const id = useId();
  return (
    <div className="interruptor">
      <div className="interruptor__texto">
        <span id={id}>{rotulo}</span>
        {descricao && <small>{descricao}</small>}
      </div>
      <button type="button" role="switch" aria-checked={ligado} aria-labelledby={id} className={`interruptor__botao${ligado ? ' interruptor__botao--ligado' : ''}`} onClick={() => aoMudar(!ligado)}>
        <span />
      </button>
    </div>
  );
}
