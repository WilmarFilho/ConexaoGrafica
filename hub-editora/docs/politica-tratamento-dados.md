# Política Interna de Tratamento, Classificação e Privacidade de Dados — Hub Editora

Versão 1.1 · 16/10/2026 · Revisão anual
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
- **Necessidade de saber e menor privilégio**: usuário individual para cada pessoa, com dois perfis — **Expedição** (pedidos, expedição e catálogo) e **Administrador** (também usuários, integrações e auditoria).
- **Fonte única**: Informações da Amazon vêm exclusivamente da Selling Partner API. Nenhum ERP, planilha ou intermediário é usado como fonte de dados da Amazon.

## 3. Controles técnicos em vigor
- Painel só por HTTPS; login individual; senha com 12+ caracteres, letras maiúsculas e minúsculas, número e símbolo, sem partes do nome ou do e-mail, verificada contra vazamentos; as 10 últimas senhas não podem ser repetidas; idade mínima de 1 dia; troca obrigatória a cada 365 dias; autenticação em dois fatores obrigatória.
- Bloqueio da conta por 30 minutos após 10 tentativas de login falhas seguidas, com aviso aos administradores; desbloqueio antecipado só por administrador ou redefinição pelo link de e-mail.
- Credenciais de integração cifradas (AES-256) no banco e backups cifrados (AES-256-GCM). Criptografia em repouso de todo o banco (MariaDB, chave fora do diretório do banco e acessível só ao root) em implantação: exige reiniciar o banco em janela de manutenção.
- Servidor com firewall de rede (só as portas necessárias abertas), bloqueio automático de força bruta (cPHulk), antimalware (ImunifyAV) com varredura mensal e acesso administrativo só por SSH, restrito ao responsável técnico.
- Auditoria de todas as ações sobre pedidos, integrações, usuários e logins, com alerta automático para tentativas de login repetidas, bloqueio de conta, quedas de integração e criação de usuários. Os registros não contêm dados pessoais de compradores (só o número do pedido); os únicos dados pessoais neles são e-mail e IP dos operadores do sistema.
- Varredura de vulnerabilidades das dependências a cada 30 dias (automática, registrada na auditoria) e antes de cada publicação; teste de invasão anual da aplicação e da infraestrutura exposta, com reteste após as correções. Prazos de correção: crítica em até 7 dias, alta em até 30 dias.
- Apagamento automático de dados pessoais de compradores da Amazon 30 dias após o envio.
- Backup diário do banco cifrado com AES-256-GCM antes de sair do servidor, enviado a armazenamento externo em outro provedor e região, retenção de 30 dias; chave de cifra guardada fora do servidor. Conferência automática semanal (decifra e valida o mais recente, com alerta se falhar) e restauração completa exercitada semestralmente (RPO 24 h, RTO 4 h).

## 4. Registros de processamento
A trilha de auditoria do hub (menu Auditoria) é o registro de operações: importação de pedidos, geração de etiqueta, envio de rastreio, alterações manuais com autor e motivo, alterações de integrações, logins e apagamentos de dados. Exportável sob demanda para auditoria.

## 5. Gestão de mudanças
Todo código é versionado em git com histórico. Antes de publicar: auditoria automática de vulnerabilidades nas dependências, verificação de sintaxe e suíte de testes, em ambiente de teste sem dados reais. Publicação em produção restrita ao responsável técnico. Ambientes de teste usam dados fictícios.

## 6. Pessoas
- Entrada: usuário criado por administrador, com o perfil mínimo necessário; a pessoa define a própria senha por link e configura o segundo fator no primeiro acesso.
- Revisão trimestral: a cada três meses o sistema envia aos administradores a lista de usuários, perfis, segundo fator e último acesso; o administrador confirma a revisão no painel (registrada na auditoria). Contas de Expedição sem acesso há 90 dias são desativadas automaticamente.
- Saída ou mudança de função: acesso desativado ou perfil ajustado em até 24 horas; credenciais compartilhadas que a pessoa conhecia são rotacionadas.
- Uso aceitável: não copiar dados de compradores para fora do sistema; não compartilhar login; não usar dispositivos sem bloqueio de tela.

## 7. Direitos dos titulares e contato
Pedidos de acesso, correção ou eliminação: comercial@conexaopro.com.br, resposta em até 15 dias.

Assinaturas: ______________________ (responsável técnico) · ______________________ (administrador da conta)
