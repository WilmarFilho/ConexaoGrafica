const env = import.meta.env;

function semBarraFinal(url: string) {
  return url.replace(/\/+$/, '');
}

export const CONFIG = {
  apiBase: semBarraFinal(env.VITE_API_BASE || 'https://conexaoeditora.com.br/wp-json/conexao-app/v1'),
  siteUrl: semBarraFinal(env.VITE_SITE_URL || 'https://conexaoeditora.com.br'),
  lojaUrl: env.VITE_LOJA_URL || 'https://conexaoeditora.com.br/loja/',
  suporteWhatsapp: (env.VITE_SUPORTE_WHATSAPP || '').replace(/\D+/g, ''),
  suporteEmail: env.VITE_SUPORTE_EMAIL || '',
  mock: env.VITE_MOCK === '1' || env.VITE_MOCK === 'true',
  versao: __VERSAO_APP__,
  /** Caminho base do app (ex.: "/app/" no site, "/" no Capacitor). */
  base: import.meta.env.BASE_URL,
  /** Intervalo mínimo entre envios do progresso de leitura (contrato: no máximo a cada 5 s). */
  intervaloProgressoMs: 5000,
};
