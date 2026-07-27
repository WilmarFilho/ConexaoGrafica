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

## 2. Liberar SSH para a conta `clientescx`

Hoje o shell dessa conta é `/usr/local/cpanel/bin/noshell` — ou seja, **SSH está
desativado** para ela. Sem liberar, a chave de deploy não entra.

Em **WHM → Account Functions → Manage Shell Access → clientescx**, escolha
**Jailed Shell**. Jailed prende a sessão ao diretório da conta: é o suficiente
para o `rsync` e evita dar um shell completo à automação.

Depois instale a chave pública:

```bash
ssh-copy-id -i ~/.ssh/conexao_deploy.pub clientescx@187.127.42.20
```

Ou cole `conexao_deploy.pub` em **cPanel → SSH Access → Manage SSH Keys →
Import** e clique em **Authorize**.

Teste antes de seguir:

```bash
ssh -i ~/.ssh/conexao_deploy clientescx@187.127.42.20 "pwd && ls -d public_html/graficaconexao.com.br"
```

### Por que não deployar como root

O root já tem SSH liberado, e seria mais rápido. Mas o `rsync` rodando como root
cria arquivos com dono `root`, enquanto o WordPress escreve como `clientescx` —
plugins e uploads passariam a falhar. E daria ao GitHub Actions acesso total à
VPS, que hospeda outros vinte e poucos sites.

Se por algum motivo o Jailed Shell não for possível, a alternativa é conectar
como root e forçar o dono no envio (o rsync do servidor é 3.2.5, suporta):

```
rsync -az --delete --chown=clientescx:clientescx ...
```

Funciona, mas mantém a chave de root no GitHub. É a segunda melhor opção.

## 3. Caminho do site — já validado

```
/home/clientescx/public_html/graficaconexao.com.br
```

Confirmado por SSH em 27/07/2026. **Não é** `/home/clientescx/public_html`: esse
caminho é outro WordPress, de outro cliente (roda hello-elementor). A conta
`clientescx` é revenda e hospeda mais de vinte sites como addon domains — apontar
o deploy para a raiz sobrescreveria o site errado.

Para reconferir a qualquer momento:

```bash
ssh root@187.127.42.20 "find /home/clientescx -maxdepth 3 -name wp-config.php"
```

## 4. Impressão digital do servidor

Sem isso o workflow aceitaria qualquer servidor que responda naquele IP.

```bash
ssh-keyscan -t rsa,ecdsa,ed25519 187.127.42.20
```

Copie todas as linhas de chave (as que começam com o IP; as iniciadas por `#`
podem ficar de fora).

Confira que batem com o que foi verificado em 27/07/2026 — estas vieram de uma
conexão SSH real ao servidor, não só do scan:

```
ED25519  SHA256:vZxYag/OG72tdRf18Vr6BwCtBYO8YaNFaJPk3ttn3PQ
RSA      SHA256:rSL8Eb1ziLvpkpVJGsPYq03PXpsBXuD2x/nOOkgTeNA
ECDSA    SHA256:vNeV3RRb19mcnsR5jx/KCTBiRLzVxf7zD3X5/R9h2Kg
```

Para conferir uma saída de scan contra essa lista:

```bash
ssh-keyscan -t rsa,ecdsa,ed25519 187.127.42.20 2>/dev/null | ssh-keygen -lf -
```

Se alguma impressão digital divergir, **pare**: ou o servidor foi reinstalado, ou
alguém está no meio do caminho.

## 5. Cadastrar os segredos no GitHub

Em **Settings → Secrets and variables → Actions → New repository secret**:

| Nome               | Valor                                                      |
|--------------------|------------------------------------------------------------|
| `SSH_HOST`         | `187.127.42.20`                                            |
| `SSH_USER`         | `clientescx`                                               |
| `SSH_PORT`         | `22`                                                       |
| `REMOTE_PATH`      | `/home/clientescx/public_html/graficaconexao.com.br`       |
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

## Primeiro deploy

O destino é uma instalação limpa — verificado por SSH. Em
`wp-content` só existem os temas padrão (`twentytwentythree`, `twentytwentyfour`,
`twentytwentyfive`) e `akismet`/`hello.php`. Não há tema nem plugin customizado
para o `--delete` destruir.

Ainda assim, `--delete` remove no servidor o que não existe no repositório. Se um
dia isso mudar, faça o backup antes:

```bash
ssh clientescx@187.127.42.20 "cd ~/public_html/graficaconexao.com.br/wp-content && tar czf ~/backup-antes-do-deploy.tar.gz plugins themes"
```

Depois do primeiro deploy, ative o tema e o plugin em produção — o `rsync` copia
os arquivos, mas não ativa nada:

**Plugins → Conexão Core → Ativar** e **Aparência → Temas → Conexão Gráfica →
Ativar**. Em seguida, **Configurações → Links permanentes → Salvar**.

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
