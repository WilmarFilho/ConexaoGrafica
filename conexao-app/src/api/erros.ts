import type { Aparelho } from './tipos';

export type CodigoErro =
  | 'nao_autenticado'
  | 'sem_acesso'
  | 'nao_encontrado'
  | 'limite_aparelhos'
  | 'muitas_tentativas'
  | 'invalido'
  | 'indisponivel'
  | 'sem_conexao'
  | 'erro'
  | (string & {});

const MENSAGENS: Record<string, string> = {
  nao_autenticado: 'Sua sessão terminou. Entre de novo.',
  sem_acesso: 'Você não tem acesso a este conteúdo.',
  nao_encontrado: 'Não encontramos o que você procurou.',
  limite_aparelhos: 'Você atingiu o limite de aparelhos conectados.',
  muitas_tentativas: 'Muitas tentativas seguidas. Aguarde alguns minutos e tente de novo.',
  invalido: 'Confira os dados informados.',
  indisponivel: 'O serviço está indisponível agora. Tente de novo em instantes.',
  sem_conexao: 'Sem conexão com a internet. Verifique sua rede e tente de novo.',
  erro: 'Algo deu errado. Tente de novo em instantes.',
};

const POR_STATUS: Record<number, CodigoErro> = {
  401: 'nao_autenticado',
  403: 'sem_acesso',
  404: 'nao_encontrado',
  409: 'limite_aparelhos',
  422: 'invalido',
  429: 'muitas_tentativas',
  503: 'indisponivel',
};

export class ErroApi extends Error {
  readonly status: number;
  readonly codigo: CodigoErro;
  /** 422: mensagens por campo ({ email: "..." }) */
  readonly campos: Record<string, string>;
  /** 409 limite_aparelhos: aparelhos ativos */
  readonly aparelhos: Aparelho[];

  constructor(status: number, codigo: CodigoErro, mensagem?: string, corpo?: unknown) {
    super(mensagem || MENSAGENS[codigo] || MENSAGENS.erro);
    this.name = 'ErroApi';
    this.status = status;
    this.codigo = codigo;
    const c = (corpo ?? {}) as Record<string, unknown>;
    this.campos = normalizarCampos(c.campos);
    this.aparelhos = Array.isArray(c.aparelhos) ? (c.aparelhos as Aparelho[]) : [];
  }

  static deResposta(status: number, corpo: unknown): ErroApi {
    const c = (corpo ?? {}) as Record<string, unknown>;
    // Aceita o formato do contrato ({codigo, mensagem}) e o padrão do WordPress ({code, message}).
    const codigo = (typeof c.codigo === 'string' && c.codigo) || POR_STATUS[status] || (status >= 500 ? 'indisponivel' : 'erro');
    const mensagem = (typeof c.mensagem === 'string' && c.mensagem) || (typeof c.message === 'string' && c.code !== undefined && !String(c.code).startsWith('rest_') ? c.message : '') || MENSAGENS[codigo];
    return new ErroApi(status, codigo, mensagem as string, corpo);
  }

  static semConexao() {
    return new ErroApi(0, 'sem_conexao');
  }
}

/** `campos` pode vir como objeto {campo: mensagem} ou lista [{campo, mensagem}] / ["campo"]. */
function normalizarCampos(bruto: unknown): Record<string, string> {
  const saida: Record<string, string> = {};
  if (!bruto) return saida;
  if (Array.isArray(bruto)) {
    for (const item of bruto) {
      if (typeof item === 'string') saida[item] = 'Confira este campo.';
      else if (item && typeof item === 'object') {
        const i = item as Record<string, unknown>;
        const nome = String(i.campo ?? i.nome ?? '');
        if (nome) saida[nome] = String(i.mensagem ?? 'Confira este campo.');
      }
    }
  } else if (typeof bruto === 'object') {
    for (const [k, v] of Object.entries(bruto as Record<string, unknown>)) {
      saida[k] = Array.isArray(v) ? String(v[0]) : String(v);
    }
  }
  return saida;
}

export function mensagemDeErro(erro: unknown): string {
  if (erro instanceof ErroApi) return erro.message;
  if (erro instanceof DOMException && erro.name === 'AbortError') return 'Operação cancelada.';
  return MENSAGENS.erro;
}

export function ehAbort(erro: unknown) {
  return (erro instanceof DOMException && erro.name === 'AbortError') || (erro as { name?: string })?.name === 'AbortError';
}
