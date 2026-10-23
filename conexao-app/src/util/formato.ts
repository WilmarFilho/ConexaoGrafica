const fmtDinheiro = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtData = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
const fmtDataCurta = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' });
const fmtDataHora = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

function data(iso: string | null | undefined) {
  if (!iso) return null;
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? null : d;
}

export function dinheiro(centavos: number) {
  return fmtDinheiro.format((centavos || 0) / 100);
}

export function dataLonga(iso: string | null | undefined) {
  const d = data(iso);
  return d ? fmtData.format(d).replace(/\./g, '') : '';
}

export function dataCurta(iso: string | null | undefined) {
  const d = data(iso);
  if (!d) return '';
  return (d.getFullYear() === new Date().getFullYear() ? fmtDataCurta : fmtData).format(d).replace(/\./g, '');
}

export function dataHora(iso: string | null | undefined) {
  const d = data(iso);
  return d ? fmtDataHora.format(d).replace(/\./g, '') : '';
}

export function relativo(iso: string | null | undefined) {
  const d = data(iso);
  if (!d) return '';
  const seg = (Date.now() - d.getTime()) / 1000;
  if (seg < 90) return 'agora há pouco';
  const min = Math.round(seg / 60);
  if (min < 60) return `há ${min} min`;
  const h = Math.round(min / 60);
  if (h < 24) return `há ${h} h`;
  const dias = Math.round(h / 24);
  if (dias === 1) return 'ontem';
  if (dias < 30) return `há ${dias} dias`;
  return `em ${dataLonga(iso)}`;
}

export function tamanhoArquivo(bytes: number | null | undefined) {
  if (!bytes) return '0 MB';
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  if (bytes < 1024 ** 3) return `${(bytes / 1024 / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
  return `${(bytes / 1024 ** 3).toLocaleString('pt-BR', { maximumFractionDigits: 2 })} GB`;
}

export function autores(lista: string[] | undefined, max = 2) {
  if (!lista?.length) return '';
  if (lista.length <= max) return lista.join(', ').replace(/, ([^,]*)$/, ' e $1');
  return `${lista.slice(0, max).join(', ')} e mais ${lista.length - max}`;
}

/** Remove acentos e passa para minúsculas (busca). */
export function normalizar(texto: string) {
  return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}
