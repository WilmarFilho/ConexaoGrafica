/// <reference types="vite/client" />

declare const __VERSAO_APP__: string;

interface ImportMetaEnv {
  readonly VITE_API_BASE?: string;
  readonly VITE_SITE_URL?: string;
  readonly VITE_LOJA_URL?: string;
  readonly VITE_SUPORTE_WHATSAPP?: string;
  readonly VITE_SUPORTE_EMAIL?: string;
  readonly VITE_MOCK?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
