import { apiReal } from './cliente';
import { apiMock } from './mock';
import type { Api } from './tipos';

export const modoDemonstracao = import.meta.env.VITE_MOCK === '1' || import.meta.env.VITE_MOCK === 'true';

export const api: Api = modoDemonstracao ? apiMock : apiReal;

export { definirToken, aoReceber401 } from './cliente';
export { ErroApi, mensagemDeErro, ehAbort } from './erros';
export type * from './tipos';
