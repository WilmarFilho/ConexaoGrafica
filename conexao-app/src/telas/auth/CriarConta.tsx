import { useState, type FormEvent } from 'react';
import { Link } from 'react-router';
import { api, ErroApi, mensagemDeErro } from '../../api';
import { Botao, Campo, CampoSenha } from '../../componentes/ui';
import { useSessao } from '../../estado/sessao';
import { dadosDoAparelho } from '../../plataforma/aparelho';
import { CONFIG } from '../../config';
import { AvisoDemonstracao, TelaAuth } from './TelaAuth';

type Erros = Partial<Record<'nome' | 'email' | 'cpf' | 'senha', string>>;

/** 123.456.789-09 enquanto digita. */
function mascaraCpf(valor: string): string {
  const d = valor.replace(/\D/g, '').slice(0, 11);
  return d.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
}

/** Dígitos verificadores (a confirmação final é do servidor, que também barra CPF repetido). */
function cpfValido(valor: string): boolean {
  const d = valor.replace(/\D/g, '');
  if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) return false;
  for (let t = 9; t < 11; t++) {
    let soma = 0;
    for (let i = 0; i < t; i++) soma += Number(d[i]) * (t + 1 - i);
    if (Number(d[t]) !== ((10 * soma) % 11) % 10) return false;
  }
  return true;
}

export function CriarConta() {
  const { entrar } = useSessao();
  const [nome, setNome] = useState('');
  const [email, setEmail] = useState('');
  const [cpf, setCpf] = useState('');
  const [senha, setSenha] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [erros, setErros] = useState<Erros>({});

  async function enviar(e: FormEvent) {
    e.preventDefault();
    const novos: Erros = {};
    if (nome.trim().length < 2) novos.nome = 'Informe seu nome.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) novos.email = 'Informe um e-mail válido.';
    if (!cpfValido(cpf)) novos.cpf = cpf.trim() ? 'Este CPF não é válido. Confira os números.' : 'Informe o seu CPF.';
    if (senha.length < 8) novos.senha = 'A senha precisa ter pelo menos 8 caracteres.';
    setErros(novos);
    if (Object.keys(novos).length) return;
    setErro(null);
    setEnviando(true);
    try {
      const r = await api.cadastro({ nome: nome.trim(), email: email.trim(), cpf, senha, aparelho: await dadosDoAparelho() });
      await entrar(r);
    } catch (falha) {
      if (falha instanceof ErroApi && Object.keys(falha.campos).length) {
        setErros(falha.campos as Erros);
      }
      setErro(mensagemDeErro(falha));
    } finally {
      setEnviando(false);
    }
  }

  return (
    <TelaAuth
      titulo="Criar conta"
      subtitulo="Sua conta vale para o app e para o site da Conexão Editora."
      voltar="/entrar"
      rodape={
        <p>
          Já tem conta? <Link to="/entrar">Entrar</Link>
        </p>
      }
    >
      <AvisoDemonstracao>A conta criada aqui é de exemplo e começa com a estante vazia. E-mail começando com “existe” simula conta já cadastrada.</AvisoDemonstracao>
      <form className="formulario" onSubmit={enviar} noValidate>
        <Campo rotulo="Nome completo" icone="conta" autoComplete="name" value={nome} onChange={(e) => setNome(e.target.value)} erro={erros.nome} />
        <Campo
          rotulo="E-mail"
          icone="email"
          type="email"
          inputMode="email"
          autoComplete="email"
          autoCapitalize="none"
          spellCheck={false}
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          erro={erros.email}
        />
        <Campo
          rotulo="CPF"
          icone="conta"
          inputMode="numeric"
          autoComplete="off"
          value={cpf}
          onChange={(e) => setCpf(mascaraCpf(e.target.value))}
          erro={erros.cpf}
          ajuda="Um CPF por conta. Ele aparece, parcialmente, na marca d'água dos seus e-books."
        />
        <CampoSenha rotulo="Senha" autoComplete="new-password" value={senha} onChange={(e) => setSenha(e.target.value)} erro={erros.senha} ajuda="Mínimo de 8 caracteres." />
        {erro && (
          <p className="alerta alerta--erro" role="alert">
            {erro}
          </p>
        )}
        <Botao type="submit" bloco carregando={enviando}>
          Criar conta
        </Botao>
        <p className="formulario__nota">
          Ao criar a conta você concorda com os{' '}
          <a className="link" href={`${CONFIG.siteUrl}/termos-de-uso/`} target="_blank" rel="noreferrer">
            termos de uso
          </a>{' '}
          e a{' '}
          <a className="link" href={`${CONFIG.siteUrl}/politica-de-privacidade/`} target="_blank" rel="noreferrer">
            política de privacidade
          </a>
          .
        </p>
      </form>
    </TelaAuth>
  );
}
