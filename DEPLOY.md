# Deploy automático

Todo push na `main` publica **apenas** `wp-content/plugins/conexao-core` e
`wp-content/themes/conexao` em `graficaconexao.com.br`.

Banco de dados, uploads e o core do WordPress **não** são tocados. Isso é
proposital: código vai por Git, conteúdo vive no servidor.

## 1. Criar a chave de deploy

Rode **na sua máquina** (não no servidor). Quando pedir passphrase, deixe vazio —
o GitHub Actions não tem como digitá-la.

```bash
ssh-keygen -t ed25519 -C "deploy-github-conexao" -f ~/.ssh/conexao_deploy -N ""
```

Isso gera dois arquivos: `conexao_deploy` (privada) e `conexao_deploy.pub` (pública).

## 2. Autorizar a chave no servidor

Instale a **pública** na conta do cPanel. Use o usuário `clientescx`, não o root:
um deploy não precisa de acesso administrativo, e limitar o alcance da chave
limita o estrago se ela vazar.

```bash
ssh-copy-id -i ~/.ssh/conexao_deploy.pub clientescx@187.127.42.20
```

Se a hospedagem bloquear `ssh-copy-id`, cole o conteúdo de `conexao_deploy.pub`
em **cPanel → SSH Access → Manage SSH Keys → Import**, e depois clique em
**Authorize**.

Teste antes de seguir:

```bash
ssh -i ~/.ssh/conexao_deploy clientescx@187.127.42.20 "pwd && ls -d public_html"
```

## 3. Descobrir o caminho do site

Ainda na sessão SSH, confirme onde o WordPress mora:

```bash
ssh -i ~/.ssh/conexao_deploy clientescx@187.127.42.20 "ls -d ~/public_html/wp-content"
```

Normalmente é `/home/clientescx/public_html`. Se o domínio for um addon domain,
pode ser `/home/clientescx/graficaconexao.com.br`. O que valer é o `REMOTE_PATH`.

## 4. Capturar a impressão digital do servidor

Sem isso o workflow aceitaria qualquer servidor que responda naquele IP.

```bash
ssh-keyscan -H 187.127.42.20
```

Copie **todas** as linhas da saída.

## 5. Cadastrar os segredos no GitHub

Em **Settings → Secrets and variables → Actions → New repository secret**:

| Nome               | Valor                                                      |
|--------------------|------------------------------------------------------------|
| `SSH_HOST`         | `187.127.42.20`                                            |
| `SSH_USER`         | `clientescx`                                               |
| `SSH_PORT`         | `22` (só se a hospedagem usar outra porta)                 |
| `REMOTE_PATH`      | `/home/clientescx/public_html`                             |
| `SSH_KEY`          | conteúdo **inteiro** de `~/.ssh/conexao_deploy`            |
| `SSH_KNOWN_HOSTS`  | saída do `ssh-keyscan` do passo 4                          |

`SSH_KEY` inclui as linhas `-----BEGIN...` e `-----END...`. É a chave
**privada** — ela nunca aparece nos logs e nunca deve ser commitada.

## 6. Publicar

```bash
git remote add origin https://github.com/WilmarFilho/ConexaoGrafica.git
git branch -M main
git push -u origin main
```

O push dispara o workflow. Acompanhe em **Actions** no GitHub.

## O que o workflow faz

1. Roda `php -l` em todo arquivo PHP — um parse error derruba o site, e a
   checagem custa segundos.
2. Envia as duas pastas por `rsync`, com `--delete` **dentro de cada uma**.
   Nunca sobre `wp-content` inteiro: isso apagaria os uploads.
3. Roda `wp rewrite flush` se houver WP-CLI no servidor. Falha nesse passo não
   quebra o deploy.

## Primeiro deploy: cuidado

O `--delete` remove, no servidor, arquivos que não existem no repositório. Se já
houver uma versão do tema ou do plugin em produção com ajustes feitos direto no
File Manager, **eles serão perdidos**. Antes do primeiro push:

```bash
ssh clientescx@187.127.42.20 "cd ~/public_html/wp-content && tar czf ~/backup-antes-do-deploy.tar.gz plugins themes"
```

## Reverter

Como cada deploy é um commit, voltar é voltar o commit:

```bash
git revert HEAD
git push
```

O workflow roda de novo e restaura o estado anterior.

## Segurança

- O repositório é **público**: nunca commite `wp-config.php`, chaves ou senhas.
  O `.gitignore` já bloqueia os arquivos do core e o `wp-config.php`.
- A chave de deploy dá acesso de escrita ao site. Se vazar, remova-a em
  **cPanel → SSH Access** e gere outra.
- Prefira o usuário `clientescx` a `root`. Um comprometimento da chave de deploy
  não deve entregar a máquina inteira.
