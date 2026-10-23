import logo from '../assets/marca/logo-vertical-branco.png';

export function Abertura() {
  return (
    <div className="abertura" role="status" aria-label="Abrindo o app da Conexão Editora">
      <img src={logo} alt="Conexão Editora" className="abertura__logo" width={180} />
      <span className="abertura__pontos" aria-hidden="true">
        <i />
        <i />
        <i />
      </span>
    </div>
  );
}
