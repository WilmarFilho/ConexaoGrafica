# Plano de Resposta a Incidentes de Segurança — Hub Editora

Versão 1.0 · 16/09/2026 · Revisão a cada 6 meses (próxima: 16/03/2027)
Responsável técnico (IMPOC): Wilmar José Alves Ferreira Filho · comercial@conexaopro.com.br
Responsável pela conta Amazon: administrador da conta Conexão Editora

## 1. O que é um incidente
Qualquer evento que comprometa, ou possa comprometer, a confidencialidade, integridade ou disponibilidade dos dados tratados pelo Hub Editora: acesso não autorizado ao painel ou ao servidor, vazamento de dados de compradores, credencial de integração exposta, malware no servidor, perda de dados sem backup.

## 2. Papéis
- **Responsável técnico**: detecta, contém, investiga, corrige, documenta.
- **Administrador da conta**: decide comunicações a clientes e parceiros, autoriza medidas de impacto (desligar integrações, bloquear usuários).

## 3. Etapas e prazos
| Etapa | Prazo | O que fazer |
|---|---|---|
| Detecção e registro | imediato | Anotar data/hora, como foi percebido, sistemas envolvidos. Preservar logs e auditoria. |
| Contenção | até 4 h | Revogar tokens e chaves afetados (Integrações → trocar chaves; revogar no Bling, Amazon, Melhor Envio, Pagar.me, WooCommerce); desativar usuários suspeitos; se necessário, tirar o painel do ar. |
| Notificação à Amazon | até 24 h da detecção | E-mail para security@amazon.com com: o que aconteceu, quando, quais dados, quantos pedidos, medidas tomadas, contato. Não esperar a investigação terminar. |
| Investigação | até 72 h | Reconstituir a linha do tempo pela Auditoria do hub e pelos logs do servidor (Apache, Exim, SSH). Identificar causa raiz e extensão. |
| Erradicação e recuperação | até 7 dias | Corrigir a causa (atualização, patch, troca de senhas), restaurar de backup íntegro se houver perda, revalidar integrações. |
| Relatório e lições | até 15 dias | Documento com causa raiz, impacto, correções e mudanças de processo. Atualizar este plano se preciso. |

## 4. Comunicação
- Amazon: security@amazon.com (obrigatório em 24 h quando envolver dados de compradores da Amazon).
- Titulares afetados e ANPD: conforme LGPD, quando houver risco relevante, coordenado pelo administrador da conta.
- Demais parceiros (Melhor Envio, Bling, Pagar.me): quando credenciais deles estiverem envolvidas.

## 5. Contatos e acessos de emergência
- Servidor: acesso root por chave SSH (responsável técnico). Provedor: Hostinger (suporte via hPanel).
- Painel do hub: hub.pubcon.com.br/admin · Auditoria em /admin/logs · Integrações em /admin/integracoes.

## 6. Exercício e revisão
Simulação anual (ex.: "chave do Melhor Envio vazou") com registro do tempo de contenção. Revisão do plano a cada 6 meses ou após qualquer incidente real.

Assinaturas: ______________________ (responsável técnico) · ______________________ (administrador da conta)
