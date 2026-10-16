# Recurso da avaliação SP-API — caso 22092972891

Avaliação recusada em 14/10/2026 por seis pontos. Respostas revisadas em 16/10/2026, depois das mudanças no Hub.

**Como recorrer:** não reabrir o caso. Atualizar as respostas do Perfil de Provedor de Soluções em https://solutionproviderportal.amazon.com/developer/register e abrir um **caso novo** citando o 22092972891.

## 1. O que mudou no Hub (16/10/2026)

| Ponto da recusa | O que o Hub faz agora |
|---|---|
| **AUP 4.3 — fonte externa (Bling)** | Pedidos da Amazon entram **só pela SP-API** (Orders API, com token de dados restritos para endereço e comprador); o "Confirmar envio" também vai direto pela SP-API. **O Bling foi removido do Hub** (cliente, importador, autorização, tela e rotas). |
| **1.2 / 1.3 — acesso e menor privilégio** | Perfis **Administrador** e **Expedição**; Usuários, Integrações e Auditoria só para administradores. **Revisão trimestral automática** (lista aos administradores + confirmação registrada no painel); contas de Expedição sem uso há 90 dias são desativadas; desativação imediata pelo painel. |
| **2.6 — logs e PII** | Já não havia PII de comprador nos registros; agora também o "raw" dos pedidos da SP-API é guardado **sem** endereço e comprador. Bloqueio de conta e varreduras entram na auditoria e no resumo quinzenal. |
| **1.4 — senhas** | Senha sem partes do nome/e-mail, **histórico das 10 últimas**, **idade mínima de 1 dia**, **bloqueio após 10 falhas** (30 min, aviso aos administradores, desbloqueio pelo painel). Já existiam: 12+ caracteres com maiúsculas, minúsculas, número e símbolo, checagem de vazamentos, MFA obrigatório e expiração em 365 dias. |
| **2.7 — vulnerabilidades** | **Varredura mensal automática** das dependências (dia 5, registrada na auditoria, alerta se houver achado), além da verificação obrigatória antes de cada publicação (`deploy/check.sh`). Prazos de correção na política: crítica 7 dias, alta 30 dias. **Primeiro teste de invasão feito em 16/10/2026** (OWASP ZAP + verificações manuais, 0 falhas, avisos corrigidos e retestados; `docs/pentest-2026.md`). **Backup diário cifrado (AES-256-GCM) para armazenamento S3 externo** (`hub:backup`), com conferência automática semanal (`hub:backup-restore --conferir`). |

Testes automatizados cobrindo tudo isso: `AmazonImportTest`, `AccessControlTest`, `BackupTest` (56 testes no total).

## 2. Antes de reenviar — pendências fora do código

1. ~~**Teste de invasão anual**~~ — **feito em 16/10/2026**, relatório em `docs/pentest-2026.md`. Sobram dois ajustes de servidor recomendados no relatório, a fazer pelo administrador do VPS: bloquear a porta 3306 no firewall e desativar o FTP (21) se não for usado.
2. ~~**Cópia de backup fora do servidor**~~ — **ativada em 16/10/2026**: Supabase Storage (projeto `hub-backups`, organização Conexão, região eu-central-1/Frankfurt, bucket privado `hub-backups`). Primeiro backup enviado e conferido (212 KB cifrados, 19 tabelas). Chave de cifra e chaves S3 no cofre de senhas. Atenção: no plano gratuito, o Supabase pausa projetos sem atividade; se o projeto for pausado, o backup falha e o Hub alerta os administradores.
3. **Servidor (achados de 16/10/2026)** — decisões e ações:
   - firewall da Hostinger: **decidido não ativar** (16/10/2026). As respostas não citam firewall de rede;
   - ImunifyAV fica **mensal**, e as respostas 2.6 e 2.7 já dizem mensal;
   - flipbook `~/biblioteca/flipbooks/1897` **limpo em 16/10/2026**: as 360 páginas `basic-html` tinham sido trocadas por um redirecionamento de spam (`rxcheapplace.shop`). Foram para a quarentena `/root/quarentena/flipbook-1897-infectado-20261016.tgz` e removidas; os JS do leitor estavam íntegros. A pasta não era publicada pelo Hub. Falta conferir o original no acervo da Pubcon.
4. **Criptografia em repouso do banco (MariaDB):** a política lista como controle; está pendente desde setembro (precisa reiniciar o banco). Não é um dos seis pontos recusados, mas a Amazon cobra na Data Protection Policy (2.4) e pode aparecer na próxima avaliação.
5. **Bling ↔ Amazon fora do Hub:** se a integração de marketplace Bling↔Amazon continuar ligada no Bling para nota fiscal, ela é do ERP da editora e não alimenta o Hub. Se não for mais necessária, desligar no Bling e revogar o app "Hub" lá (Bling → Configurações → Aplicativos).
6. **Assinar** a política (`docs/politica-tratamento-dados.md`, versão 1.1) e o plano de incidentes.

## 3. Respostas para colar no Perfil de Provedor de Soluções

### AUP 4.3 — Fontes externas de Informações da Amazon

> Nossa organização **não obtém Informações da Amazon de nenhuma fonte externa à Amazon**. Todos os dados de pedidos da Amazon — itens, valores, endereço de entrega e dados do comprador — são obtidos **exclusivamente pela Selling Partner API** (Orders API), com autorização da própria conta de vendedor via Login with Amazon; endereço e comprador são lidos com Restricted Data Token. A confirmação de envio com código de rastreio também é feita diretamente pela SP-API (shipmentConfirmation). O ERP Bling, citado na submissão anterior, **foi removido da nossa aplicação** e não é usado para ler nem gravar nenhuma Informação da Amazon. Os dados obtidos são usados somente para a expedição dos pedidos e os dados pessoais são apagados automaticamente 30 dias após o envio.

### 1.2 / 1.3 — Identificação individual e necessidade de conhecimento

> Cada pessoa com acesso ao sistema tem um **usuário individual, com e-mail próprio e autenticação em dois fatores obrigatória** (aplicativo autenticador TOTP). Não existem contas genéricas, compartilhadas ou padrão; só um administrador cria usuários, e a própria pessoa define a senha por link de uso único enviado ao seu e-mail.
>
> O acesso segue o **princípio do menor privilégio**, com dois perfis: **Expedição**, que acessa apenas pedidos, expedição e catálogo (o necessário para separar e enviar); e **Administrador**, o único que gerencia usuários, credenciais de integração e consulta a trilha de auditoria. Hoje 2 pessoas têm acesso, ambas administradoras.
>
> **Revisão de acessos trimestral**: no primeiro dia de cada trimestre o sistema envia automaticamente aos administradores a lista de todos os usuários, com perfil, situação, uso do segundo fator e data do último acesso. O administrador confere, ajusta perfis ou desativa contas e registra a confirmação da revisão no sistema, que fica na trilha de auditoria. Contas de Expedição sem acesso há 90 dias são desativadas automaticamente nessa revisão. No desligamento ou mudança de função, o acesso é desativado ou ajustado **em até 24 horas** (desativação imediata pelo painel, que encerra também as sessões abertas) e as credenciais de integração que a pessoa conhecia são rotacionadas. É proibido armazenar Informações da Amazon em dispositivos pessoais, planilhas ou mensagens.

### 2.6 — Registro e monitoramento

> O sistema mantém uma **trilha de auditoria central, no banco de dados da aplicação**, que registra: logins, saídas e tentativas de login falhas (usuário, IP, agente); bloqueios e desbloqueios de conta; criação, alteração de perfil, desativação e reativação de usuários; alterações e testes de credenciais de integração; as chamadas às APIs externas (Amazon SP-API, transportadora, pagamento) com data, ação, resultado e identificador do pedido; alterações manuais de status de pedido com autor e motivo; apagamentos de dados pessoais; revisões de acesso e varreduras de vulnerabilidades. Os logs do servidor (web, SSH, e-mail) completam a trilha.
>
> **Os registros não contêm Informações de Identificação Pessoal de compradores.** As entradas referenciam apenas o número do pedido Amazon e identificadores internos; nome, endereço, telefone e e-mail de compradores nunca são escritos nos registros, e a cópia do pedido guardada para auditoria também exclui endereço e dados do comprador. Os únicos dados pessoais nos registros são o e-mail e o IP dos operadores do próprio sistema, necessários para identificar quem fez cada ação. Os registros são **retidos por no mínimo 12 meses**, só podem ser incluídos pela aplicação (não há edição nem exclusão pela interface) e só os administradores os consultam.
>
> **Monitoramento**: alertas automáticos por e-mail aos administradores quando há 5 tentativas de login falhas em 15 minutos para o mesmo usuário ou IP, quando uma conta é **bloqueada após 10 falhas**, quando qualquer integração falha repetidamente e quando a varredura mensal encontra vulnerabilidade. A cada 15 dias o sistema envia aos administradores um **resumo de segurança** (logins, falhas, bloqueios, usuários alterados, integrações alteradas, erros, apagamentos de dados pessoais, varreduras), revisado pelo responsável técnico. O servidor tem WAF (ModSecurity), bloqueio automático de força bruta (cPHulk) e antimalware (ImunifyAV) com varredura mensal de todos os arquivos.
>
> **Investigação de incidentes**: temos Plano de Resposta a Incidentes com Incident Management Point of Contact (IMPOC) designado (responsável técnico, comercial@conexaopro.com.br), com etapas e prazos: contenção em até 4 horas (revogação de tokens e chaves, bloqueio de usuários), **notificação à Amazon (security@amazon.com) em até 24 horas** da detecção, investigação em até 72 horas reconstituindo a linha do tempo pela trilha de auditoria e pelos logs do servidor, correção em até 7 dias e relatório em até 15 dias. O plano é revisado a cada 6 meses e exercitado anualmente.

### 1.4 — Gerenciamento de senhas

> A política é aplicada tecnicamente pela aplicação em todos os acessos a sistemas que tratam dados da Amazon:
> - **Comprimento mínimo de 12 caracteres**, com letras maiúsculas e minúsculas, números e caracteres especiais obrigatórios.
> - A senha **não pode conter partes do nome nem do e-mail do usuário** (comparação sem acentos e sem diferenciar maiúsculas) e é verificada contra bases de senhas vazadas, sendo rejeitada se já exposta.
> - **Histórico**: a senha atual e as **10 senhas anteriores** não podem ser reutilizadas.
> - **Idade mínima de 1 dia** entre trocas voluntárias e **expiração máxima de 365 dias**, após a qual o sistema exige a troca antes de qualquer outra ação.
> - **Autenticação multifator obrigatória** para todas as contas (TOTP por aplicativo autenticador, com códigos de recuperação).
> - **Bloqueio de conta após 10 tentativas de login falhas** seguidas, por 30 minutos, com aviso imediato aos administradores; o desbloqueio antecipado só é feito por um administrador ou pela redefinição de senha via link de uso único enviado ao e-mail do usuário.
> - Senhas armazenadas apenas como hash (bcrypt). As credenciais da SP-API (client secret e refresh token) ficam fora do diretório público, cifradas em repouso com AES-256, nunca em código ou repositório, e são **rotacionadas anualmente** ou imediatamente em caso de suspeita. Acesso administrativo ao servidor somente por chave SSH; todo o tráfego usa TLS 1.2 ou superior.

### 2.7 — Gerenciamento de vulnerabilidades

> **Antes de cada publicação**, uma verificação obrigatória e automatizada roda em ambiente de teste, sem dados reais: varredura de vulnerabilidades conhecidas em todas as dependências (composer audit, base de CVEs e GitHub Security Advisories), verificação de sintaxe de todo o código e a suíte completa de testes automatizados. Qualquer falha bloqueia a publicação. Todo o código é versionado em git, e a publicação em produção é restrita ao responsável técnico.
>
> **Frequências**: varredura de vulnerabilidades das dependências **a cada 30 dias**, de forma automática e agendada, com o resultado registrado na trilha de auditoria e alerta aos administradores quando há achado; a mesma varredura roda após qualquer mudança relevante, pois toda publicação passa por ela. Varredura de malware no servidor **mensal** (ImunifyAV, todos os arquivos) e atualizações de sistema operacional, PHP e painel aplicadas automaticamente pelo WHM/cPanel. **Teste de invasão a cada 365 dias** da aplicação e da infraestrutura exposta, com metodologia OWASP, relatório com recomendações e reteste após as correções.
>
> **Prazos de correção**: vulnerabilidades **críticas em até 7 dias** e **altas em até 30 dias** a partir da descoberta; médias e baixas no ciclo de publicação seguinte. Vulnerabilidades em dependências são corrigidas por atualização da biblioteca, seguida de nova verificação completa antes da publicação.
>
> **Teste de invasão**: o primeiro foi realizado em **16/10/2026**: varredura completa com OWASP ZAP (rastreamento e ataque ativo: injeção de SQL, XSS, injeção de comando e de template, path traversal, entre outros) e verificações manuais de exposição de arquivos, TLS, autenticação dos webhooks, controle de acesso e portas do servidor. **Nenhuma vulnerabilidade explorável foi encontrada.** Os achados de configuração (cabeçalhos de segurança HTTP como HSTS e Content-Security-Policy, além de um serviço interno exposto no servidor) foram corrigidos no mesmo dia e confirmados em reteste. O relatório fica arquivado e o próximo teste está previsto para outubro de 2027.
>
> **Recuperação**: backup diário do banco de dados, **cifrado com AES-256-GCM antes de sair do servidor** e enviado a armazenamento de objetos externo, em outro provedor e região geográfica, com retenção de 30 dias; a chave de cifra fica fora do servidor e do armazenamento. Toda semana o sistema decifra automaticamente o backup mais recente e confere o conteúdo (teste de restauração), registrando o resultado na trilha de auditoria e alertando os administradores em caso de falha; uma restauração completa é exercitada a cada 6 meses. O código da aplicação é recuperável a partir do repositório git. RPO de 24 horas e RTO de 4 horas.

*(Backup externo ativo desde 16/10/2026.)*

## 4. Checklist do novo caso

- [x] Hub publicado com as mudanças de 16/10/2026.
- [x] Pendência 1 (teste de invasão) resolvida.
- [x] Pendência 2 (ativar o backup externo) resolvida.
- [x] Pendência 3 (flipbook 1897 limpo) resolvida.
- [ ] Perfil do Provedor de Soluções atualizado com os textos da seção 3.
- [ ] Caso novo aberto no Solution Provider Portal, citando o 22092972891 e informando que as respostas foram corrigidas.
- [ ] Após a aprovação: criar o app no portal, gerar Client ID, Client Secret e Refresh token, colar em Integrações → Amazon, testar e acompanhar a primeira varredura de pedidos.
