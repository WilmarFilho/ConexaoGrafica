import { Component, type ReactNode } from 'react';
import { Icone } from './Icone';

interface Estado {
  erro: boolean;
}

/** Mostra uma tela amigável se uma parte do app falhar (ex.: não conseguiu carregar o leitor sem internet). */
export class LimiteDeErro extends Component<{ children: ReactNode }, Estado> {
  state: Estado = { erro: false };

  static getDerivedStateFromError(): Estado {
    return { erro: true };
  }

  componentDidCatch(erro: unknown) {
    console.error(erro);
  }

  render() {
    if (!this.state.erro) return this.props.children;
    return (
      <div className="tela-erro" role="alert">
        <span className="vazio__icone">
          <Icone nome="alerta" tamanho={34} />
        </span>
        <h1 className="vazio__titulo">Algo deu errado</h1>
        <p className="vazio__texto">Não foi possível abrir esta tela. Verifique a internet e tente de novo.</p>
        <div className="tela-erro__acoes">
          <button type="button" className="botao botao--primario" onClick={() => window.location.reload()}>
            <span>Tentar de novo</span>
          </button>
          <button
            type="button"
            className="botao botao--texto"
            onClick={() => {
              this.setState({ erro: false });
              window.history.back();
            }}
          >
            <span>Voltar</span>
          </button>
        </div>
      </div>
    );
  }
}
