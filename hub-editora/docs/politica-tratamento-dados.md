# Política Interna de Tratamento, Classificação e Privacidade de Dados — Hub Editora

Versão 1.0 · 16/09/2026 · Revisão anual
Versão pública (para compradores e parceiros): https://hub.pubcon.com.br/privacidade

## 1. Classificação
| Classe | Exemplos | Regras |
|---|---|---|
| **Dado pessoal de comprador** | nome, endereço, telefone, CPF/CNPJ, e-mail | Só no hub e nos parceiros de entrega. Nunca em planilhas, mensagens ou arquivos locais. Amazon: apagado 30 dias após o envio. |
| **Dado do pedido** | número, itens, valores, cidade/UF, rastreio, datas | Mantido por prazo fiscal. Pode ser consultado pela equipe. |
| **Credencial** | chaves de API, tokens, senhas | Só na tela Integrações ou no `.env` do servidor. Nunca em chat, e-mail, repositório ou documento. Rotação anual ou imediata em suspeita. |
| **Registro de auditoria** | quem fez o quê e quando | Retido por no mínimo 12 meses; revisado a cada 15 dias. |

## 2. Princípios
- **Mínimo necessário**: coletar só o que a entrega exige. Nada de dados de pagamento.
- **Finalidade**: expedição, rastreio e atendimento do pedido. Nenhum uso comercial secundário.
- **Necessidade de saber**: acesso apenas para a equipe de expedição, com usuário individual.

## 3. Controles técnicos em vigor
- Painel só por HTTPS; login individual; senha com 12+ caracteres, letras maiúsculas e minúsculas, número e símbolo, verificada contra vazamentos; autenticação em dois fatores obrigatória; troca de senha a cada 365 dias.
- Credenciais de integração cifradas (AES-256) no banco; banco com criptografia em repouso; chave fora do diretório do banco, acessível só ao root.
- Servidor com firewall, proteção contra malware e acesso administrativo apenas por chave SSH.
- Auditoria de todas as ações sobre pedidos, integrações, usuários e logins, com alerta automático para tentativas de login repetidas, quedas de integração e criação de usuários.
- Apagamento automático de dados pessoais de compradores da Amazon 30 dias após o envio.
- Backups diários; cópia cifrada externa; restauração testada semestralmente (RPO 24 h, RTO 4 h).

## 4. Registros de processamento
A trilha de auditoria do hub (menu Auditoria) é o registro de operações: importação de pedidos, geração de etiqueta, envio de rastreio, alterações manuais com autor e motivo, alterações de integrações, logins e apagamentos de dados. Exportável sob demanda para auditoria.

## 5. Gestão de mudanças
Todo código é versionado em git com histórico. Antes de publicar: auditoria automática de vulnerabilidades nas dependências, verificação de sintaxe e suíte de testes, em ambiente de teste sem dados reais. Publicação em produção restrita ao responsável técnico. Ambientes de teste usam dados fictícios.

## 6. Pessoas
- Entrada: usuário criado por administrador; a pessoa define a própria senha por link e configura o segundo fator no primeiro acesso.
- Saída: usuário removido no desligamento; credenciais compartilhadas que a pessoa conhecia são rotacionadas.
- Uso aceitável: não copiar dados de compradores para fora do sistema; não compartilhar login; não usar dispositivos sem bloqueio de tela.

## 7. Direitos dos titulares e contato
Pedidos de acesso, correção ou eliminação: comercial@conexaopro.com.br, resposta em até 15 dias.

Assinaturas: ______________________ (responsável técnico) · ______________________ (administrador da conta)
