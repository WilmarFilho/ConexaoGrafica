/**
 * MODO DE DEMONSTRAÇÃO (VITE_MOCK=1)
 * Dados de EXEMPLO para usar e testar o app sem o servidor. Nada aqui é real:
 * livros, autores, pedidos e aparelhos são fictícios. O PDF é public/mock/amostra.pdf
 * (no modo de capturas de tela, ?capturas=ios|ipad|android|android-tablet, usa public/mock/vitrine.pdf
 * e esconde as marcas de "amostra" para as imagens das lojas).
 *
 * Atalhos:
 *  - qualquer e-mail e senha entram;
 *  - senha "errada" → "E-mail ou senha incorretos.";
 *  - e-mail começando com "limite" (ex.: limite@exemplo.com) → tela de limite de aparelhos;
 *  - confirmação de e-mail (Pedidos): o código é 123456;
 *  - excluir conta: senha "errada" → senha incorreta; e-mail começando com "equipe" → conta da equipe (403).
 */
import { CONFIG } from '../config';
import { abrirPorPartes, lerComProgresso, tokenAtual, notificar401 } from './cliente';
import { ErroApi, ehAbort } from './erros';
import { modoCapturas } from '../vitrine';
import type { Api, Aparelho, DadosAparelho, Leitura, Livro, Marcador, Pedido, RespostaLogin, Situacao, Usuario } from './tipos';

const CHAVE = 'cx-mock-estado';
const TOTAL_PAGINAS_AMOSTRA = 34;

interface Estado {
  usuario: Usuario | null;
  aparelhos: (Aparelho & { token?: string })[];
  leituras: Record<number, { pagina: number; total_paginas: number; atualizado_em: string | null; marcadores: Marcador[] }>;
  proximoId: number;
  emailVerificado: boolean;
}

function agora(deslocMin = 0) {
  return new Date(Date.now() - deslocMin * 60000).toISOString();
}
function diasAtras(dias: number, hora = 10) {
  const d = new Date();
  d.setDate(d.getDate() - dias);
  d.setHours(hora, 12, 0, 0);
  return d.toISOString();
}

function estadoInicial(): Estado {
  return {
    usuario: null,
    aparelhos: [],
    leituras: {
      101: { pagina: 12, total_paginas: TOTAL_PAGINAS_AMOSTRA, atualizado_em: agora(90), marcadores: [{ id: 1, pagina: 5, criado_em: diasAtras(3) }, { id: 2, pagina: 9, criado_em: diasAtras(2) }] },
      102: { pagina: 30, total_paginas: TOTAL_PAGINAS_AMOSTRA, atualizado_em: diasAtras(6), marcadores: [] },
      104: { pagina: 34, total_paginas: TOTAL_PAGINAS_AMOSTRA, atualizado_em: diasAtras(20), marcadores: [] },
    },
    proximoId: 100,
    emailVerificado: !!modoCapturas,
  };
}

let estado: Estado = carregar();

function carregar(): Estado {
  try {
    const bruto = localStorage.getItem(CHAVE);
    if (bruto) return JSON.parse(bruto) as Estado;
  } catch {
    /* ignora */
  }
  return estadoInicial();
}
function salvar() {
  try {
    localStorage.setItem(CHAVE, JSON.stringify(estado));
  } catch {
    /* ignora */
  }
}

function esperar(ms = 350 + Math.random() * 300, signal?: AbortSignal) {
  return new Promise<void>((ok, falha) => {
    const t = setTimeout(ok, ms);
    signal?.addEventListener('abort', () => {
      clearTimeout(t);
      falha(new DOMException('Cancelado', 'AbortError'));
    });
  });
}

function offline() {
  return typeof navigator !== 'undefined' && navigator.onLine === false;
}

async function chamada<T>(fn: () => T, opcoes: { publica?: boolean } = {}): Promise<T> {
  await esperar();
  if (offline()) throw ErroApi.semConexao();
  if (!opcoes.publica) {
    const tk = tokenAtual();
    const ap = estado.aparelhos.find((a) => a.token === tk);
    if (!tk || !ap || !estado.usuario) {
      notificar401();
      throw new ErroApi(401, 'nao_autenticado');
    }
    ap.usado_em = agora();
  }
  const r = fn();
  salvar();
  return r;
}

function iniciais(nome: string) {
  const partes = nome.trim().split(/\s+/);
  return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase();
}

function emailParcial(email: string) {
  const [u, d] = email.split('@');
  return `${u.slice(0, 2)}***@${d ?? ''}`;
}

const APARELHOS_CAPTURAS: Record<string, { atual: [string, Aparelho['plataforma']]; outros: [string, Aparelho['plataforma']][] }> = {
  ios: { atual: ['iPhone de Ana', 'ios'], outros: [['iPad de Ana', 'ios'], ['Chrome · Windows', 'web']] },
  ipad: { atual: ['iPad de Ana', 'ios'], outros: [['iPhone de Ana', 'ios'], ['Chrome · Windows', 'web']] },
  android: { atual: ['Galaxy S24', 'android'], outros: [['Galaxy Tab S9', 'android'], ['Chrome · Windows', 'web']] },
  'android-tablet': { atual: ['Galaxy Tab S9', 'android'], outros: [['Galaxy S24', 'android'], ['Chrome · Windows', 'web']] },
};

function aparelhosDeExemplo(): Aparelho[] {
  const cap = APARELHOS_CAPTURAS[modoCapturas];
  if (cap) return cap.outros.map(([nome, plataforma], i) => ({ id: 21 + i, nome, plataforma, usado_em: diasAtras(1 + i * 3, 20), criado_em: diasAtras(90) }));
  return [
    { id: 11, nome: 'iPhone de Ana', plataforma: 'ios', usado_em: diasAtras(1, 21), criado_em: diasAtras(120) },
    { id: 12, nome: 'Chrome · Windows', plataforma: 'web', usado_em: diasAtras(4, 9), criado_em: diasAtras(60) },
    { id: 13, nome: 'Galaxy Tab S9', plataforma: 'android', usado_em: diasAtras(15, 18), criado_em: diasAtras(200) },
  ];
}

function novoToken() {
  const bytes = new Uint8Array(32);
  crypto.getRandomValues(bytes);
  return 'cxa_' + btoa(String.fromCharCode(...bytes)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function entrar(email: string, nome: string, dados: DadosAparelho, substituir?: number, limite = false): RespostaLogin {
  const cap = APARELHOS_CAPTURAS[modoCapturas];
  const aparelho = cap ? { ...dados, nome: cap.atual[0], plataforma: cap.atual[1] } : dados;
  const mesmoUsuario = estado.usuario?.email === email;
  if (!mesmoUsuario) {
    estado.usuario = { id: 7, nome, email, iniciais: iniciais(nome) };
    // Conta "limite" começa com 3 aparelhos; as outras com 2 (sobra 1 vaga).
    estado.aparelhos = limite ? aparelhosDeExemplo() : aparelhosDeExemplo().slice(0, 2);
  }
  // O mesmo aparelho (id_local) entrando de novo substitui o registro antigo.
  estado.aparelhos = estado.aparelhos.filter((a) => (a as { id_local?: string }).id_local !== aparelho.id_local);
  if (substituir) estado.aparelhos = estado.aparelhos.filter((a) => a.id !== substituir);
  if (estado.aparelhos.length >= 3) {
    throw new ErroApi(409, 'limite_aparelhos', 'Você já está conectado em 3 aparelhos. Desconecte um para entrar neste.', {
      aparelhos: estado.aparelhos.map(({ token: _t, ...a }) => a),
    });
  }
  const token = novoToken();
  const novo = { id: estado.proximoId++, nome: aparelho.nome, plataforma: aparelho.plataforma, usado_em: agora(), criado_em: agora(), token, id_local: aparelho.id_local };
  estado.aparelhos.push(novo);
  const { token: _t, ...publico } = novo;
  return { token, usuario: estado.usuario!, aparelho: { ...publico, atual: true } };
}

// ---------- Livros de exemplo ----------

function capa(titulo: string, autor: string, cor1: string, cor2: string) {
  const linhas: string[] = [];
  let atual = '';
  for (const p of titulo.split(' ')) {
    if ((atual + ' ' + p).trim().length > 14) {
      linhas.push(atual.trim());
      atual = p;
    } else atual += ' ' + p;
  }
  if (atual.trim()) linhas.push(atual.trim());
  const texto = linhas
    .slice(0, 4)
    .map((l, i) => `<text x="52" y="${300 + i * 66}" font-family="Nunito, Arial, sans-serif" font-weight="800" font-size="56" fill="#fff">${l}</text>`)
    .join('');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 900" width="600" height="900">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${cor1}"/><stop offset="1" stop-color="${cor2}"/></linearGradient></defs>
<rect width="600" height="900" fill="url(#g)"/>
<circle cx="510" cy="120" r="150" fill="#fff" opacity=".08"/><circle cx="90" cy="820" r="220" fill="#000" opacity=".06"/>
<rect x="52" y="200" width="70" height="8" rx="4" fill="#fff" opacity=".85"/>
${texto}
<text x="52" y="780" font-family="Nunito, Arial, sans-serif" font-weight="700" font-size="30" fill="#fff" opacity=".92">${autor}</text>
<text x="52" y="840" font-family="Nunito, Arial, sans-serif" font-weight="700" font-size="22" fill="#fff" opacity=".7" letter-spacing="3">${modoCapturas ? 'CONEXÃO EDITORA' : 'AMOSTRA · CONEXÃO EDITORA'}</text>
</svg>`;
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}

const LIVROS_BASE: Livro[] = [
  {
    id: 101,
    titulo: 'Fundamentos de Ultrassonografia Clínica',
    subtitulo: 'Da propedêutica ao laudo',
    autores: ['Helena Duarte Campos', 'Marcos Vinícius Prado'],
    capa: capa('Fundamentos de Ultrassonografia Clínica', 'Helena D. Campos', '#0096D6', '#0B4F8A'),
    modelo: 'pdf',
    disponivel: true,
    tamanho_bytes: modoCapturas ? 18_874_368 : 115346,
    atualizado_em: diasAtras(30),
    progresso: null,
    descricao:
      'Um guia direto para quem está começando no ultrassom: apresenta os princípios físicos do método, a técnica de exame ' +
      'por regiões e um roteiro claro para a escrita do laudo, com casos comentados e mais de cem figuras.',
    ficha: { paginas: 34, idioma: 'Português', editora: 'Conexão Editora', ano: 2025, isbn: '978-00-00000-01-0', doi: '10.99999/ce.2025.101', dimensoes: '16 × 23 cm', pagina_doi: `${CONFIG.siteUrl}/` },
    avaliacao: { media: 4.6, quantidade: 18 },
  },
  {
    id: 102,
    titulo: 'Direito Digital e Sociedade',
    subtitulo: 'Privacidade, dados e cidadania',
    autores: ['Marina Albuquerque', 'Rafael Torres'],
    capa: capa('Direito Digital e Sociedade', 'Marina Albuquerque', '#3A4E8C', '#14213D'),
    modelo: 'pdf',
    disponivel: true,
    tamanho_bytes: modoCapturas ? 18_874_368 : 115346,
    atualizado_em: diasAtras(50),
    progresso: null,
    descricao: 'Proteção de dados pessoais, responsabilidade das plataformas e direitos fundamentais na rede, explicados com casos reais da jurisprudência brasileira.',
    ficha: { paginas: 34, idioma: 'Português', editora: 'Conexão Editora', ano: 2024, isbn: '978-00-00000-02-7', doi: '10.99999/ce.2025.102', dimensoes: '14 × 21 cm', pagina_doi: `${CONFIG.siteUrl}/` },
    avaliacao: { media: 4.2, quantidade: 7 },
  },
  {
    id: 103,
    titulo: 'Educação e Família no Século XXI',
    subtitulo: null,
    autores: ['Paulo Henrique Mendes'],
    capa: capa('Educação e Família no Século XXI', 'Paulo H. Mendes', '#E07A2E', '#B23A48'),
    modelo: 'pdf',
    disponivel: true,
    tamanho_bytes: modoCapturas ? 18_874_368 : 115346,
    atualizado_em: diasAtras(80),
    progresso: null,
    descricao: 'Reflexões sobre a parceria entre escola e família, com relatos de professores e propostas práticas para o dia a dia.',
    ficha: { paginas: 34, idioma: 'Português', editora: 'Conexão Editora', ano: 2023, isbn: '978-00-00000-03-4', doi: null, dimensoes: '14 × 21 cm', pagina_doi: null },
    avaliacao: { media: 0, quantidade: 0 },
  },
  {
    id: 104,
    titulo: 'Crônicas do Cerrado',
    subtitulo: 'Histórias de um interior que muda',
    autores: ['Luíza Ferraz'],
    capa: capa('Crônicas do Cerrado', 'Luíza Ferraz', '#5E8C31', '#2F4F1F'),
    modelo: 'pdf',
    disponivel: true,
    tamanho_bytes: modoCapturas ? 18_874_368 : 115346,
    atualizado_em: diasAtras(120),
    progresso: null,
    descricao: 'Crônicas curtas sobre a vida no Centro-Oeste, entre memórias de infância e a cidade que cresce.',
    ficha: { paginas: 34, idioma: 'Português', editora: 'Conexão Editora', ano: 2022, isbn: '978-00-00000-04-1', doi: '10.99999/ce.2025.104', dimensoes: '13 × 19 cm', pagina_doi: `${CONFIG.siteUrl}/` },
    avaliacao: { media: 4.9, quantidade: 31 },
  },
  {
    id: 105,
    titulo: 'História Oral e Memória Coletiva',
    subtitulo: 'Métodos e experiências',
    autores: ['Ana Beatriz Rocha', 'Tiago Nunes', 'Carla Menezes', 'Jorge Amaral'],
    capa: capa('História Oral e Memória Coletiva', 'Ana B. Rocha e outros', '#7A4FA0', '#3B2459'),
    modelo: 'flipbook',
    disponivel: true,
    tamanho_bytes: null,
    atualizado_em: diasAtras(300),
    progresso: null,
    descricao: 'Experiências de pesquisa com história oral em comunidades do interior, com métodos de entrevista, transcrição e arquivo.',
    ficha: { paginas: 210, idioma: 'Português', editora: 'Conexão Editora', ano: 2021, isbn: '978-00-00000-05-8', doi: null, dimensoes: '16 × 23 cm', pagina_doi: null },
    avaliacao: { media: 0, quantidade: 0 },
    leitor_url: `${CONFIG.siteUrl}/minha-conta/biblioteca/`,
  },
  {
    id: 106,
    titulo: 'Saúde Coletiva: Teoria e Prática',
    subtitulo: null,
    autores: ['Renata Lopes'],
    capa: capa('Saúde Coletiva: Teoria e Prática', 'Renata Lopes', '#1C8C83', '#0E4D49'),
    modelo: '',
    disponivel: true,
    tamanho_bytes: null,
    atualizado_em: diasAtras(2),
    progresso: null,
    descricao: 'Fundamentos de epidemiologia, planejamento e gestão em saúde coletiva, com exercícios comentados.',
    ficha: { paginas: null, idioma: 'Português', editora: 'Conexão Editora', ano: 2026, isbn: null, doi: null, dimensoes: null, pagina_doi: null },
    avaliacao: { media: 0, quantidade: 0 },
  },
];

function comProgresso(livro: Livro): Livro {
  // Fora do modo de capturas, a descrição deixa claro que é um livro de exemplo.
  const l = livro.descricao && !modoCapturas ? { ...livro, descricao: `Livro de exemplo (modo de demonstração). ${livro.descricao}` } : livro;
  const leitura = estado.leituras[l.id];
  if (!leitura || l.modelo !== 'pdf') return { ...l, progresso: null };
  return {
    ...l,
    progresso: {
      pagina: leitura.pagina,
      total_paginas: leitura.total_paginas,
      percentual: Math.round((leitura.pagina / leitura.total_paginas) * 1000) / 10,
      atualizado_em: leitura.atualizado_em,
    },
  };
}

function resumo(l: Livro): Livro {
  const { descricao: _d, ficha: _f, ...resto } = comProgresso(l);
  return resto;
}

// ---------- Pedidos de exemplo ----------

const RASTREIO = 'https://rastreamento.correios.com.br/app/index.php';

function eventos(lista: [Situacao | string, string, number][]) {
  return lista.map(([situacao, rotulo, dias]) => ({ situacao, rotulo, em: diasAtras(dias, 9 + (dias % 8)) }));
}

const PEDIDOS: Pedido[] = [
  {
    ref: 'hub-9001', numero: '#10482', canal: 'Loja Conexão Editora', feito_em: diasAtras(1, 14), total_centavos: 8990,
    situacao: 'a_caminho', situacao_rotulo: 'A caminho', pagamento: 'pago', digital: false,
    itens: [{ nome: 'Direito Digital e Sociedade', quantidade: 1, formato: 'Impresso', livro_id: null }],
    envio: { transportadora: 'Correios', servico: 'PAC', codigo: 'AA123456789BR', url_rastreio: RASTREIO, postado_em: diasAtras(0, 8), entregue_em: null, previsao: diasAtras(-5) },
    eventos: eventos([['aguardando_pagamento', 'Pedido recebido', 1], ['pago', 'Pagamento confirmado', 1], ['em_separacao', 'Em separação', 1], ['a_caminho', 'Postado nos Correios', 0]]),
  },
  {
    ref: 'hub-9002', numero: '#10475', canal: 'Loja Conexão Editora', feito_em: diasAtras(2, 10), total_centavos: 4990,
    situacao: 'aguardando_pagamento', situacao_rotulo: 'Aguardando pagamento', pagamento: 'pendente', digital: false,
    itens: [{ nome: 'Crônicas do Cerrado', quantidade: 1, formato: 'Impresso', livro_id: null }],
    envio: null, eventos: eventos([['aguardando_pagamento', 'Pedido recebido · aguardando boleto', 2]]),
  },
  {
    ref: 'hub-9003', numero: '#10466', canal: 'Mercado Livre', feito_em: diasAtras(3, 16), total_centavos: 13980,
    situacao: 'em_separacao', situacao_rotulo: 'Em separação', pagamento: 'pago', digital: false,
    itens: [
      { nome: 'Educação e Família no Século XXI', quantidade: 2, formato: 'Impresso', livro_id: null },
      { nome: 'Crônicas do Cerrado', quantidade: 1, formato: 'Impresso', livro_id: null },
    ],
    envio: { transportadora: 'Correios', servico: 'SEDEX', codigo: null, url_rastreio: null, postado_em: null, entregue_em: null, previsao: null },
    eventos: eventos([['aguardando_pagamento', 'Pedido recebido', 3], ['pago', 'Pagamento confirmado', 3], ['em_separacao', 'Em separação', 2]]),
  },
  {
    ref: 'loja-5288', numero: '#5288', canal: 'Loja Conexão Editora', feito_em: diasAtras(5, 11), total_centavos: 5990,
    situacao: 'pago', situacao_rotulo: 'Pagamento confirmado', pagamento: 'pago', digital: false,
    itens: [{ nome: 'Fundamentos de Ultrassonografia Clínica', quantidade: 1, formato: 'Impresso', livro_id: null }],
    envio: null, eventos: eventos([['aguardando_pagamento', 'Pedido recebido', 5], ['pago', 'Pagamento confirmado', 5]]),
  },
  {
    ref: 'loja-5301', numero: '#5301', canal: 'Loja Conexão Editora', feito_em: diasAtras(12, 9), total_centavos: 3990,
    situacao: 'concluido_digital', situacao_rotulo: 'Disponível na estante', pagamento: 'pago', digital: true,
    itens: [{ nome: 'Fundamentos de Ultrassonografia Clínica', quantidade: 1, formato: 'E-book', livro_id: 101 }],
    envio: null, eventos: eventos([['pago', 'Pagamento confirmado', 12], ['concluido_digital', 'Disponível na estante', 12]]),
  },
  {
    ref: 'hub-9006', numero: '#10310', canal: 'Shopee', feito_em: diasAtras(25, 15), total_centavos: 7480,
    situacao: 'entregue', situacao_rotulo: 'Entregue', pagamento: 'pago', digital: false,
    itens: [{ nome: 'Crônicas do Cerrado', quantidade: 1, formato: 'Impresso', livro_id: null }, { nome: 'Marcador de páginas', quantidade: 2, formato: null, livro_id: null }],
    envio: { transportadora: 'Correios', servico: 'PAC', codigo: 'BR987654321BR', url_rastreio: RASTREIO, postado_em: diasAtras(22), entregue_em: diasAtras(17), previsao: null },
    eventos: eventos([['aguardando_pagamento', 'Pedido recebido', 25], ['pago', 'Pagamento confirmado', 25], ['em_separacao', 'Em separação', 24], ['a_caminho', 'Postado nos Correios', 22], ['entregue', 'Entregue', 17]]),
  },
  {
    ref: 'hub-9007', numero: '#10288', canal: 'Loja Conexão Editora', feito_em: diasAtras(31, 13), total_centavos: 4990,
    situacao: 'problema', situacao_rotulo: 'Precisa de atenção', pagamento: 'pago', digital: false,
    itens: [{ nome: 'Educação e Família no Século XXI', quantidade: 1, formato: 'Impresso', livro_id: null }],
    envio: { transportadora: 'Correios', servico: 'PAC', codigo: 'AB555666777BR', url_rastreio: RASTREIO, postado_em: diasAtras(28), entregue_em: null, previsao: null },
    eventos: eventos([['pago', 'Pagamento confirmado', 31], ['a_caminho', 'Postado nos Correios', 28], ['problema', 'Precisa de atenção', 20]]),
  },
  {
    ref: 'loja-5120', numero: '#5120', canal: 'Loja Conexão Editora', feito_em: diasAtras(60, 19), total_centavos: 2990,
    situacao: 'cancelado', situacao_rotulo: 'Cancelado', pagamento: 'estornado', digital: true,
    itens: [{ nome: 'Direito Digital e Sociedade', quantidade: 1, formato: 'E-book', livro_id: 102 }],
    envio: null, eventos: [{ situacao: 'aguardando_pagamento', rotulo: 'Pedido recebido', em: null }, ...eventos([['cancelado', 'Cancelado a pedido do cliente', 59]])],
  },
];

// ---------- API ----------

export const apiMock: Api = {
  async login({ email, senha, aparelho, substituir }) {
    await esperar(700);
    if (offline()) throw ErroApi.semConexao();
    if (senha === 'errada') throw new ErroApi(401, 'nao_autenticado', 'E-mail ou senha incorretos.');
    const limite = email.trim().toLowerCase().startsWith('limite');
    const r = entrar(email.trim().toLowerCase(), 'Ana Souza', aparelho, substituir, limite);
    salvar();
    return r;
  },
  async cadastro({ nome, email, senha, aparelho }) {
    await esperar(800);
    if (offline()) throw ErroApi.semConexao();
    const campos: Record<string, string> = {};
    if (senha.length < 8) campos.senha = 'A senha precisa ter pelo menos 8 caracteres.';
    if (email.toLowerCase().startsWith('existe')) campos.email = 'Já existe uma conta com este e-mail.';
    if (Object.keys(campos).length) throw new ErroApi(422, 'invalido', 'Confira os dados informados.', { campos });
    estado.usuario = null;
    const r = entrar(email.trim().toLowerCase(), nome.trim(), aparelho);
    estado.aparelhos = estado.aparelhos.filter((a) => a.token === r.token);
    estado.leituras = {};
    salvar();
    return r;
  },
  async senha() {
    await esperar(600);
    if (offline()) throw ErroApi.semConexao();
  },
  sair: () =>
    chamada(() => {
      estado.aparelhos = estado.aparelhos.filter((a) => a.token !== tokenAtual());
    }),
  eu: () =>
    chamada(() => ({
      usuario: { ...estado.usuario!, email_verificado: estado.emailVerificado },
      aparelho_atual_id: estado.aparelhos.find((a) => a.token === tokenAtual())!.id,
    })),
  aparelhos: () =>
    chamada(() => ({
      limite: 3,
      aparelhos: estado.aparelhos
        .map(({ token, ...a }) => ({ ...a, atual: token === tokenAtual() }))
        .sort((a, b) => Number(b.atual) - Number(a.atual) || String(b.usado_em).localeCompare(String(a.usado_em))),
    })),
  desconectarAparelho: (id) =>
    chamada(() => {
      estado.aparelhos = estado.aparelhos.filter((a) => a.id !== id);
    }),
  estante: () =>
    chamada(() => {
      const livros = estado.usuario?.nome === 'Ana Souza' ? LIVROS_BASE.map(resumo) : [];
      const continuar = livros
        .filter((l) => l.progresso && l.progresso.pagina < l.progresso.total_paginas)
        .sort((a, b) => String(b.progresso!.atualizado_em).localeCompare(String(a.progresso!.atualizado_em)))[0];
      return { livros, continuar: continuar ?? null };
    }),
  livro: (id) =>
    chamada(() => {
      const l = LIVROS_BASE.find((x) => x.id === id);
      if (!l) throw new ErroApi(404, 'nao_encontrado', 'Livro não encontrado na sua estante.');
      return comProgresso(l);
    }),
  async pdf(id, opcoes = {}) {
    await chamada(() => null);
    const l = LIVROS_BASE.find((x) => x.id === id);
    if (!l || l.modelo !== 'pdf') throw new ErroApi(404, 'nao_encontrado');
    let resp: Response;
    try {
      resp = await fetch(`${CONFIG.base}mock/${modoCapturas ? 'vitrine' : 'amostra'}.pdf`, { signal: opcoes.signal });
    } catch (e) {
      if (ehAbort(e)) throw e;
      throw ErroApi.semConexao();
    }
    const dados = await lerComProgresso(resp, {});
    // Simula um download mais lento para mostrar a barra de progresso e o cancelar.
    const passos = 16;
    for (let i = 1; i <= passos; i++) {
      await esperar(90, opcoes.signal);
      opcoes.aoProgredir?.(Math.round((dados.byteLength * i) / passos), dados.byteLength);
    }
    const u = estado.usuario!;
    return { dados, marca: `${u.nome} · ${emailParcial(u.email)}` };
  },
  async pdfRemoto(id, tamanho, signal) {
    await chamada(() => null);
    const l = LIVROS_BASE.find((x) => x.id === id);
    if (!l || l.modelo !== 'pdf') throw new ErroApi(404, 'nao_encontrado');
    const fonte = await abrirPorPartes(`${CONFIG.base}mock/${modoCapturas ? 'vitrine' : 'amostra'}.pdf`, {}, tamanho, signal);
    const u = estado.usuario!;
    return fonte ? { ...fonte, marca: `${u.nome} · ${emailParcial(u.email)}` } : null;
  },
  acessoWeb: (id) =>
    chamada(() => {
      const l = LIVROS_BASE.find((x) => x.id === id);
      if (!l?.leitor_url) throw new ErroApi(404, 'nao_encontrado');
      return { url: l.leitor_url };
    }),
  leitura: (id) =>
    chamada((): Leitura => {
      const l = estado.leituras[id];
      return l ? { ...l } : { pagina: null, total_paginas: null, atualizado_em: null, marcadores: [] };
    }),
  salvarLeitura: (id, dados) =>
    chamada((): Leitura => {
      const atual = estado.leituras[id] ?? { pagina: 1, total_paginas: dados.total_paginas, atualizado_em: null, marcadores: [] };
      estado.leituras[id] = { ...atual, pagina: dados.pagina, total_paginas: dados.total_paginas, atualizado_em: agora() };
      return { ...estado.leituras[id] };
    }),
  criarMarcador: (id, pagina) =>
    chamada((): Marcador => {
      const atual = estado.leituras[id] ?? { pagina: 1, total_paginas: TOTAL_PAGINAS_AMOSTRA, atualizado_em: null, marcadores: [] };
      const existente = atual.marcadores.find((m) => m.pagina === pagina);
      if (existente) return existente;
      const m = { id: estado.proximoId++, pagina, criado_em: agora() };
      estado.leituras[id] = { ...atual, marcadores: [...atual.marcadores, m] };
      return m;
    }),
  removerMarcador: (id, marcador) =>
    chamada(() => {
      const atual = estado.leituras[id];
      if (atual) atual.marcadores = atual.marcadores.filter((m) => String(m.id) !== String(marcador));
    }),
  pedidos: () =>
    chamada(() => {
      const todos = estado.usuario?.nome === 'Ana Souza' ? PEDIDOS.map(({ eventos: _e, envio: _v, ...p }) => p) : [];
      // Sem e-mail confirmado só aparecem os pedidos da conta da loja.
      return estado.emailVerificado
        ? { pedidos: todos, fonte: 'hub' as const, email_verificado: true }
        : { pedidos: todos.filter((p) => p.ref.startsWith('loja-')), fonte: 'loja' as const, email_verificado: false };
    }),
  async excluirConta(senha) {
    await chamada(() => {
      if (senha === 'errada') throw new ErroApi(422, 'invalido', 'Senha incorreta. Confira e tente de novo.');
      if (estado.usuario?.email.startsWith('equipe')) throw new ErroApi(403, 'sem_acesso', 'Contas da equipe não podem ser excluídas pelo app.');
      estado = estadoInicial();
    });
  },
  async enviarCodigoEmail() {
    await chamada(() => null);
  },
  async confirmarEmail(codigo) {
    await chamada(() => {
      if (codigo.trim() !== '123456') throw new ErroApi(422, 'invalido', 'Código incorreto ou vencido. Confira o e-mail ou peça um novo código.');
      estado.emailVerificado = true;
    });
  },
  pedido: (ref) =>
    chamada(() => {
      const p = PEDIDOS.find((x) => x.ref === ref);
      if (!p) throw new ErroApi(404, 'nao_encontrado', 'Pedido não encontrado.');
      return p;
    }),
};
