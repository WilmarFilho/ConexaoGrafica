// Tipos do contrato (conexao-editora/docs/app-leitor-contrato.md, versão 1).

export type Plataforma = 'ios' | 'android' | 'web';

export interface Usuario {
  id: number;
  nome: string;
  email: string;
  iniciais: string;
  /** GET /eu: se o e-mail da conta já foi confirmado (libera pedidos de outros canais). */
  email_verificado?: boolean;
}

export interface Aparelho {
  id: number;
  nome: string;
  plataforma: Plataforma;
  usado_em: string | null;
  atual?: boolean;
  criado_em?: string | null;
}

export interface DadosAparelho {
  nome: string;
  plataforma: Plataforma;
  id_local: string;
}

export interface RespostaLogin {
  token: string;
  usuario: Usuario;
  aparelho: Aparelho;
}

export interface Eu {
  usuario: Usuario;
  aparelho_atual_id: number;
}

export interface ListaAparelhos {
  limite: number;
  aparelhos: Aparelho[];
}

export interface Progresso {
  pagina: number;
  total_paginas: number;
  percentual: number;
  atualizado_em: string | null;
}

export interface Ficha {
  paginas?: number | null;
  idioma?: string | null;
  editora?: string | null;
  ano?: number | null;
  isbn?: string | null;
  doi?: string | null;
  dimensoes?: string | null;
  pagina_doi?: string | null;
}

/** `pdf` lê no app; `flipbook` abre o leitor do site; `""` ainda sem arquivo ("em breve"). */
export type ModeloLivro = 'pdf' | 'flipbook' | '';

export interface Livro {
  id: number;
  titulo: string;
  subtitulo?: string | null;
  autores: string[];
  capa: string | null;
  modelo: ModeloLivro;
  disponivel: boolean;
  tamanho_bytes: number | null;
  atualizado_em: string | null;
  progresso: Progresso | null;
  descricao?: string | null;
  ficha?: Ficha | null;
  /** Sem avaliações: {media: 0, quantidade: 0} (o app esconde as estrelas). */
  avaliacao?: { media: number; quantidade: number } | null;
  /**
   * Livros `flipbook`: leitor do site. O app NÃO abre direto: pede um link de acesso
   * de uso único em POST /livros/{id}/acesso-web e abre esse link.
   */
  leitor_url?: string | null;
}

export interface Estante {
  livros: Livro[];
  continuar: Livro | null;
}

export interface Marcador {
  id: number | string;
  pagina: number;
  criado_em: string;
}

/** Sem progresso ainda: pagina, total_paginas e atualizado_em vêm null. */
export interface Leitura {
  pagina: number | null;
  total_paginas: number | null;
  atualizado_em: string | null;
  marcadores: Marcador[];
}

export type Situacao =
  | 'aguardando_pagamento'
  | 'pago'
  | 'em_separacao'
  | 'a_caminho'
  | 'entregue'
  | 'cancelado'
  | 'problema'
  | 'concluido_digital';

export interface ItemPedido {
  nome: string;
  quantidade: number;
  formato: string | null;
  livro_id: number | null;
}

export interface Envio {
  transportadora: string | null;
  servico: string | null;
  codigo: string | null;
  url_rastreio: string | null;
  postado_em: string | null;
  entregue_em: string | null;
  previsao: string | null;
}

export interface EventoPedido {
  situacao: string;
  rotulo: string;
  /** Pedidos da loja (fallback) podem vir sem data. */
  em: string | null;
}

export interface Pedido {
  ref: string;
  numero: string;
  canal: string;
  feito_em: string;
  total_centavos: number;
  situacao: Situacao;
  situacao_rotulo: string;
  pagamento: string;
  digital: boolean;
  itens?: ItemPedido[];
  envio?: Envio | null;
  eventos?: EventoPedido[];
}

export interface ListaPedidos {
  pedidos: Pedido[];
  fonte: 'hub' | 'loja';
  /** false: só aparecem pedidos da conta da loja; confirmar o e-mail libera os outros canais. */
  email_verificado?: boolean;
}

/** Abertura do PDF por partes (Range), sem baixar tudo antes. */
export interface FontePdfRemota {
  /** endereço do PDF e cabeçalhos para os pedidos de Range */
  url: string;
  cabecalhos: Record<string, string>;
  /** tamanho total (Content-Range) */
  total: number;
  /** primeiro bloco já recebido */
  inicial: Uint8Array;
  marca: string | null;
}

export interface ArquivoPdf {
  dados: Uint8Array;
  /** Texto da marca d'água (cabeçalho X-Conexao-Marca), quando o servidor expõe. */
  marca: string | null;
}

export interface OpcoesDownload {
  signal?: AbortSignal;
  /** total pode ser null quando o servidor não manda Content-Length */
  aoProgredir?: (recebido: number, total: number | null) => void;
  tamanhoEsperado?: number | null;
}

export interface Api {
  login(dados: { email: string; senha: string; aparelho: DadosAparelho; substituir?: number }): Promise<RespostaLogin>;
  cadastro(dados: { nome: string; email: string; senha: string; aparelho: DadosAparelho }): Promise<RespostaLogin>;
  senha(email: string): Promise<void>;
  sair(): Promise<void>;
  eu(): Promise<Eu>;
  aparelhos(): Promise<ListaAparelhos>;
  desconectarAparelho(id: number): Promise<void>;
  estante(): Promise<Estante>;
  livro(id: number): Promise<Livro>;
  pdf(id: number, opcoes?: OpcoesDownload): Promise<ArquivoPdf>;
  /** Primeiro bloco do PDF por Range. null se o servidor não aceitar Range (aí o app baixa inteiro). */
  pdfRemoto(id: number, tamanho: number | null, signal?: AbortSignal): Promise<FontePdfRemota | null>;
  /** Link de acesso de uso único ao leitor do site (livros flipbook). */
  acessoWeb(id: number): Promise<{ url: string }>;
  leitura(id: number): Promise<Leitura>;
  salvarLeitura(id: number, dados: { pagina: number; total_paginas: number }, opcoes?: { keepalive?: boolean }): Promise<Leitura>;
  criarMarcador(id: number, pagina: number): Promise<Marcador>;
  removerMarcador(id: number, marcador: number | string): Promise<void>;
  pedidos(): Promise<ListaPedidos>;
  pedido(ref: string): Promise<Pedido>;
  enviarCodigoEmail(): Promise<void>;
  /** Exclui a conta (exige a senha). 204; 422 senha errada; 403 conta da equipe; 429 muitas tentativas. */
  excluirConta(senha: string): Promise<void>;
  confirmarEmail(codigo: string): Promise<void>;
}
