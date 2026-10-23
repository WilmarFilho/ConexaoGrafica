// Ícones em traço (24×24, currentColor), no mesmo desenho dos ícones do site.

const ICONES = {
  estante: '<path d="M4 4.5h3.5v15H4zM9.5 4.5H13v15H9.5z"/><path d="M15.2 5.6l3.3-.9 3 14.4-3.3.8z"/><path d="M3 19.5h18"/>',
  pedidos: '<path d="M3.5 7.5L12 3.5l8.5 4v9L12 20.5l-8.5-4z"/><path d="M3.5 7.5L12 11.5l8.5-4M12 11.5v9"/><path d="M7.7 5.5l8.6 4"/>',
  conta: '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.6 3.1-5.5 7-5.5s7 1.9 7 5.5"/>',
  voltar: '<path d="M14.5 5.5L8 12l6.5 6.5"/>',
  avancar: '<path d="M9.5 5.5L16 12l-6.5 6.5"/>',
  busca: '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
  sumario: '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="4.5" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="4.5" cy="18" r="1.2" fill="currentColor" stroke="none"/>',
  marcador: '<path d="M6.5 3.5h11v17L12 16.6l-5.5 3.9z"/>',
  'marcador-cheio': '<path d="M6.5 3.5h11v17L12 16.6l-5.5 3.9z" fill="currentColor"/>',
  ajustes: '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
  fechar: '<path d="M6 6l12 12M18 6L6 18"/>',
  download: '<path d="M12 4v11M7.5 10.5L12 15l4.5-4.5"/><path d="M4.5 16.5v2a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-2"/>',
  check: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
  'check-circulo': '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.2l2.4 2.4 4.6-4.9"/>',
  lixeira: '<path d="M4 6.5h16M9.5 6.5V4.8c0-.7.6-1.3 1.3-1.3h2.4c.7 0 1.3.6 1.3 1.3v1.7"/><path d="M6.5 6.5l.9 12.2c.1 1 .9 1.8 1.9 1.8h5.4c1 0 1.8-.8 1.9-1.8l.9-12.2"/><path d="M10.5 10.5v6M13.5 10.5v6"/>',
  olho: '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
  'olho-fechado': '<path d="M3 3l18 18"/><path d="M10.6 5.6A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.8M6.6 6.6C3.9 8.3 2.5 12 2.5 12S6 18.5 12 18.5c1.8 0 3.3-.5 4.6-1.3"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
  'sem-internet': '<path d="M3 3l18 18"/><path d="M8.5 16.4a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 4.2-2.6M12.9 10.1A10 10 0 0 1 19 12.9M1.8 9.4a15 15 0 0 1 3.7-2.5M10 5.1a15 15 0 0 1 12.2 4.3"/><circle cx="12" cy="19.5" r=".9" fill="currentColor" stroke="none"/>',
  copiar: '<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5"/>',
  externo: '<path d="M13.5 4.5h6v6M19.5 4.5L11 13"/><path d="M17.5 13.5v4.5a1.5 1.5 0 0 1-1.5 1.5H6A1.5 1.5 0 0 1 4.5 18V8A1.5 1.5 0 0 1 6 6.5h4.5"/>',
  celular: '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
  tablet: '<rect x="4" y="2.5" width="16" height="19" rx="2.5"/><path d="M10.5 18.5h3"/>',
  computador: '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20"/>',
  sair: '<path d="M14 4.5h4.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H14"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><circle cx="12" cy="7.8" r=".9" fill="currentColor" stroke="none"/>',
  ajuda: '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 0 1 4.8 1c0 1.7-2.4 2.1-2.4 3.6"/><circle cx="12" cy="17.2" r=".9" fill="currentColor" stroke="none"/>',
  whatsapp: '<path d="M12 2.8a9.2 9.2 0 0 0-7.9 13.9L2.8 21.2l4.6-1.2A9.2 9.2 0 1 0 12 2.8z"/><path d="M8.8 8.4c.2-.5.4-.5.6-.5h.5c.2 0 .4 0 .6.4l.8 1.9c0 .2 0 .3-.1.4l-.4.5c-.1.2-.2.3-.1.5.5 1 1.6 1.9 2.8 2.4.2.1.4.1.5-.1l.6-.7c.2-.2.3-.2.5-.1l1.7.8c.2.1.3.2.3.3v.5c-.1.4-.5.9-1.2 1.1-.6.2-1.3.1-2-.1a9.6 9.6 0 0 1-5.5-5.1c-.3-.7-.4-1.4-.2-2.1z"/>',
  email: '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M3 7l9 6.5L21 7"/>',
  sol: '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
  lua: '<path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10z"/>',
  sistema: '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20"/><path d="M12 7.5v6" />',
  estrela: '<path d="M12 3.6l2.5 5.3 5.8.7-4.3 4 1.1 5.7L12 16.5l-5.1 2.8L8 13.6l-4.3-4 5.8-.7z" fill="currentColor" stroke="none"/>',
  'estrela-vazia': '<path d="M12 3.6l2.5 5.3 5.8.7-4.3 4 1.1 5.7L12 16.5l-5.1 2.8L8 13.6l-4.3-4 5.8-.7z"/>',
  caminhao: '<path d="M3 6h11v9H3z"/><path d="M14 9h3.5L21 12v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
  relogio: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  alerta: '<path d="M12 3.5l9.5 16.5h-19z"/><path d="M12 10v4.5"/><circle cx="12" cy="17.3" r=".9" fill="currentColor" stroke="none"/>',
  livro: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 5.5v15"/>',
  'livro-aberto': '<path d="M12 6.5C10.3 5 7.8 4.5 3.5 4.5v13c4.3 0 6.8.5 8.5 2 1.7-1.5 4.2-2 8.5-2v-13c-4.3 0-6.8.5-8.5 2z"/><path d="M12 6.5v13"/>',
  atualizar: '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4.5v4h-4"/>',
  mais: '<path d="M12 5.5v13M5.5 12h13"/>',
  menos: '<path d="M5.5 12h13"/>',
  compartilhar: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
  cadeado: '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.2 10.5V8a3.8 3.8 0 0 1 7.6 0v2.5"/>',
  baixado: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v7M9 11.5l3 3 3-3M8.5 17h7"/>',
  pagina: '<rect x="6" y="3.5" width="12" height="17" rx="1.5"/>',
  'paginas-duplas': '<rect x="2.5" y="4.5" width="9" height="15" rx="1.2"/><rect x="12.5" y="4.5" width="9" height="15" rx="1.2"/>',
  girar: '<path d="M4.5 12a7.5 7.5 0 0 1 12.8-5.3L19.5 9"/><path d="M19.5 4.5V9H15"/><path d="M19.5 12a7.5 7.5 0 0 1-12.8 5.3L4.5 15"/><path d="M4.5 19.5V15H9"/>',
  toque: '<path d="M9 11.5V5.5a1.5 1.5 0 0 1 3 0v5l3.7.9a2 2 0 0 1 1.5 2.2l-.6 4.4a2 2 0 0 1-2 1.7H11a2 2 0 0 1-1.6-.8L6.3 14.6a1.4 1.4 0 0 1 2.1-1.8z"/>',
  loja: '<path d="M4 9.5V19a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19V9.5"/><path d="M3 9.5L4.8 4h14.4L21 9.5z"/><path d="M9.5 20.5v-5h5v5"/>',
  pacote: '<path d="M3.5 7.5L12 3.5l8.5 4v9L12 20.5l-8.5-4z"/><path d="M3.5 7.5L12 11.5l8.5-4M12 11.5v9"/>',
  escudo: '<path d="M12 3l7.5 3v5.5c0 4.6-3.2 8.3-7.5 9.5-4.3-1.2-7.5-4.9-7.5-9.5V6z"/><path d="M8.8 12l2.2 2.2 4.2-4.4"/>',
} as const;

export type NomeIcone = keyof typeof ICONES;

export function Icone({ nome, tamanho = 22, className, titulo }: { nome: NomeIcone; tamanho?: number; className?: string; titulo?: string }) {
  return (
    <svg
      className={`icone${className ? ' ' + className : ''}`}
      width={tamanho}
      height={tamanho}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.8}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden={titulo ? undefined : true}
      role={titulo ? 'img' : undefined}
      aria-label={titulo}
      focusable="false"
      dangerouslySetInnerHTML={{ __html: ICONES[nome] }}
    />
  );
}
