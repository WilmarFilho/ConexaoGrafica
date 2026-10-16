# Formulário SP-API — textos com até 500 caracteres

Versões curtas para colar no Perfil de Provedor de Soluções (16/10/2026). Os textos que citam 15/14 dias e o banco cifrado só valem depois de ajustar a retenção no `.env` e ativar a criptografia do MariaDB.

## Atividade na Amazon e uso da SP-API

Somos uma editora brasileira que vende livros na Amazon.com.br pela própria conta de vendedor, com envio próprio. Nosso sistema interno de expedição usará a SP-API só na nossa conta para ler os pedidos (endereço via Restricted Data Token, apenas para a etiqueta) e confirmar o envio com o código de rastreio. A SP-API é a única fonte de dados da Amazon; não há terceiros nem revenda. Dados pessoais são apagados 15 dias após o envio. Acesso individual com 2FA e perfis de menor privilégio.

## Casos de uso 1 (aplicativo)

Somos uma editora que vende livros na Amazon.com.br com envio próprio (FBM). Nosso sistema interno de expedição, Hub Editora, usado só pela nossa equipe, centraliza os pedidos do site, landing pages e Amazon. Com a SP-API ele vai importar os pedidos da nossa conta (Orders API), gerar a etiqueta da transportadora e confirmar o envio na Amazon com o código de rastreio. A SP-API é a única fonte de dados da Amazon no sistema.

## Casos de uso 2 (funções restritas)

Envio direto para o consumidor: enviamos com logística própria (FBM) e precisamos do nome e endereço do destinatário, obtidos com Restricted Data Token, para gerar a etiqueta; depois confirmamos o envio (confirmShipment). Os dados são usados só para a expedição, não são compartilhados nem usados para marketing e são apagados 15 dias após o envio. Logs não contêm dados do comprador. Acesso individual com 2FA, perfis de menor privilégio e TLS 1.2+.

## Partes externas

Só o Melhor Envio (plataforma de frete): para cada envio recebe por API, via HTTPS, nome, endereço, telefone, e-mail e, se houver, CPF/CNPJ do destinatário, com itens e peso, para emitir a etiqueta; a transportadora recebe só os dados da etiqueta. O backup diário vai ao Supabase Storage cifrado com AES-256-GCM antes de sair do servidor; o provedor não tem a chave. Nenhuma outra parte: sem exportação manual, planilhas ou marketing.

## Fontes externas

Nenhuma. Todas as Informações da Amazon vêm exclusivamente da Selling Partner API (Orders API, com Restricted Data Token para endereço e comprador), autorizada pela nossa conta de vendedor. O ERP Bling, citado antes, foi removido do sistema e não obtém nem envia dados da Amazon. O Melhor Envio só devolve o rastreio das etiquetas que nós geramos.

## Controles de rede

VPS com firewall de rede que recusa toda entrada exceto as portas necessárias, WAF (ModSecurity com OWASP CRS), bloqueio de força bruta (cPHulk) e antimalware (ImunifyAV). Cada aplicação em conta isolada. O banco não é acessível pela internet: nosso usuário só conecta via localhost e a porta fica fechada no firewall. Sem servidor de arquivos exposto. App só por HTTPS (TLS 1.2+); SSH restrito ao responsável técnico. Nada em computadores pessoais; dev usa dados fictícios.

## Identificação individual

Cada pessoa tem usuário próprio, com senha e 2FA obrigatórios; não há contas compartilhadas. Só administradores criam usuários. Perfis: Expedição vê só pedidos, fila de envio e catálogo; Administrador é o único que gerencia usuários, integrações e auditoria. Hoje 2 pessoas. Revisão trimestral registrada; contas de Expedição sem uso há 90 dias são desativadas; desligamento remove o acesso em até 24 h. Toda ação em pedido fica registrada com autor e data.

## Dispositivos pessoais

Sistema web sem exportação, download em massa ou relatórios com dados pessoais; a única saída é a etiqueta de cada pedido. Sem app, sincronização ou arquivos locais. A política proíbe copiar dados da Amazon para dispositivos pessoais, planilhas ou mensagens. Logins, falhas, bloqueios e ações em pedidos ficam na auditoria, revisada a cada 15 dias. Alertas por e-mail para falhas de login e bloqueio de conta. Suspeita de cópia indevida segue o plano de incidentes.

## Políticas de privacidade

Política: https://hub.pubcon.com.br/privacidade. Coletamos da Amazon, só pela SP-API, o necessário para expedir: número, itens, nome, endereço, telefone e CPF/CNPJ quando houver. Uso: fila de expedição, etiqueta e confirmação de envio; nada de marketing. Banco cifrado em repouso (AES-256), acesso por perfil com 2FA. Compartilhamos só com o Melhor Envio, para a etiqueta. Dados do comprador apagados 15 dias após o envio; backups cifrados guardados até 14 dias.

## Armazenamento em repouso

(a) MariaDB com criptografia de tabelas em repouso (AES-256) em todas as tabelas do sistema. Credenciais da SP-API cifradas pela aplicação (AES-256-CBC). Backups cifrados com AES-256-GCM antes de sair do servidor. (b) A chave do banco fica em arquivo fora do diretório do banco, legível só pelo administrador do servidor, com rotação anual; a chave da aplicação fica fora da pasta pública e do repositório; a chave de backup fica fora do servidor, com o responsável técnico.

## Backups

(a) Backup diário do banco, cifrado com AES-256-GCM antes de sair do servidor, enviado a armazenamento externo em outro provedor e região (Frankfurt, Alemanha), retido por 14 dias; a chave fica fora do servidor e do armazenamento. O código está em git e pode ser reimplantado do zero. (b) RPO 24 h e RTO 4 h. Toda semana o sistema decifra e confere o backup mais recente, com alerta se falhar; restauração completa exercitada a cada 6 meses.

## Registro e monitoramento

Trilha de auditoria no banco, retida 12 meses e sem edição: logins e falhas, bloqueios, mudanças de usuários e perfis, credenciais, chamadas às APIs, mudanças de status, apagamento de dados, backups e varreduras. Logs do servidor (web, WAF, SSH) completam. Alertas por e-mail: 5 falhas de login em 15 min, conta bloqueada, integração com falhas, falha de backup e vulnerabilidade. Resumo de segurança a cada 15 dias. Investigação pelo plano de incidentes, com linha do tempo e evidências.

## Senhas

No sistema: mínimo de 12 caracteres com maiúsculas, minúsculas, números e símbolos; sem partes do nome ou e-mail; checagem contra senhas vazadas; sem repetir as 10 últimas; idade mínima de 1 dia e expiração em 365 dias; 2FA obrigatório; bloqueio de 30 min após 10 falhas. Hash bcrypt. Servidor: SSH restrito ao responsável técnico, com bloqueio de força bruta. Credenciais da SP-API cifradas e rotacionadas anualmente.

## Exposição de credenciais

Credenciais nunca vão para o código ou o repositório: ficam em arquivo de configuração fora da pasta pública, legível só pelo usuário da aplicação, ou cifradas no banco (AES-256). A tela de configuração não exibe segredos salvos e só administradores com 2FA alteram, com registro na auditoria. Nada de envio por e-mail ou mensagem; guarda em cofre do responsável técnico. SSH restrito. Rotação anual da SP-API ou imediata se houver suspeita.

## Remediação de achados

Cada achado é registrado com severidade, responsável e prazo: varreduras mensais de dependências na trilha de auditoria (com alerta) e achados de pentest no relatório versionado no repositório. Prazos: críticas em 7 dias, altas em 30, médias e baixas no ciclo seguinte. Revisão quinzenal pelo responsável técnico. Um item só fecha quando a correção é publicada e uma nova verificação confirma. No pentest de out/2026, tudo foi corrigido e retestado no mesmo dia.

## Vulnerabilidades no código

Antes de cada publicação, em ambiente de teste com dados fictícios: auditoria de dependências (composer audit, CVEs e GitHub Advisories), verificação de sintaxe e suíte de testes, incluindo controle de acesso e cabeçalhos de segurança; qualquer falha bloqueia. Varredura automática das dependências a cada 30 dias. Em execução: WAF com OWASP CRS, firewall, antimalware, CSP e HSTS, logs e alertas por e-mail. Achado em produção segue o plano de incidentes; pentest anual.
