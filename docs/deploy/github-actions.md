# Deploy automático via GitHub Actions

O workflow [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml) faz deploy pro droplet de produção a cada push na `main` (ou manualmente, via aba **Actions → Deploy → Run workflow**). Ele assume que o servidor já está provisionado (Nginx, PHP-FPM, MySQL, Composer, e o worker de fila via Supervisor — ver [queue-worker.md](queue-worker.md)). No droplet criado a partir da imagem Marketplace "LaraSail" da DigitalOcean, os arquivos vêm inicialmente com dono `larasail`, mas o **PHP-FPM roda como `www-data`** (`user`/`group` em `/etc/php/8.4/fpm/pool.d/*.conf`) — por isso o workflow corrige a permissão pra `www-data` no final de cada deploy (ver abaixo). Sem isso, qualquer rota que precise escrever em `storage/` (logs, views compiladas, etc.) retorna 500 sem nada no log, porque o processo que atende a requisição não tem permissão de escrita.

## O que o workflow faz

1. Builda os assets do Vite no runner do GitHub (`npm ci && npm run build`) — o droplet não precisa ter Node instalado.
2. Via SSH, sincroniza o código com a `main` em `DEPLOY_PATH`:
   - Se `DEPLOY_PATH` já é um clone do repositório: `git fetch` + `git reset --hard` (deploy normal).
   - Se **não** é (primeiro deploy, ou a pasta ainda tem só a instalação de demonstração da imagem Marketplace): move o conteúdo atual para `DEPLOY_PATH-backup-<timestamp>` (não apaga nada), clona o repositório do zero em `DEPLOY_PATH` e, se o backup tinha um `.env`, copia ele pro clone novo — assim as credenciais de banco que a imagem já tinha configurado não se perdem.
3. Envia a pasta `public/build` (gitignored, gerada no passo 1) pro servidor via rsync.
4. Via SSH: `composer install --no-dev`. Daqui em diante o comportamento depende de ter sido bootstrap ou não:
   - **Bootstrap (primeiro deploy):** gera `APP_KEY` se estiver vazio, roda `storage:link` e `migrate:fresh --force` (schema limpo, sem herdar as migrations que a instalação de demonstração já tinha rodado).
   - **Deploy normal:** coloca o site em modo de manutenção e roda `migrate --force` (incremental, preserva dados).
   - Em ambos os casos: recria os caches de config/rotas/views/eventos, reinicia o worker de fila (`queue:restart`), tira do modo de manutenção e corrige o dono dos arquivos (`chown -R www-data:www-data`, o mesmo usuário que roda o PHP-FPM — já que o deploy em si roda como `DEPLOY_USER`, normalmente `root`).

## Antes do primeiro push

O workflow cuida do clone, build e migrations sozinho, mas **não mexe em segredos de produção**. Antes do primeiro deploy, entre no servidor (Web Console ou SSH) e edite o `.env` que já existe na pasta atual (a da instalação de demonstração) com os valores reais — ele será reaproveitado automaticamente no clone novo:

```bash
nano /var/www/laravel/.env
```

Ajuste pelo menos `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://doingclub.com.br`, além de qualquer credencial de e-mail/webhook de pagamento específica do projeto. As credenciais de banco (`DB_*`) já vêm certas da instalação da imagem Marketplace.

## Secrets necessários

Cadastre em **Settings → Secrets and variables → Actions** do repositório no GitHub:

| Secret | Descrição |
|---|---|
| `DEPLOY_SSH_KEY` | Chave privada SSH dedicada ao deploy (não reutilize sua chave pessoal) |
| `DEPLOY_HOST` | IP ou domínio do droplet |
| `DEPLOY_USER` | Usuário SSH usado no deploy (ex: `root`) |
| `DEPLOY_PATH` | Caminho absoluto do projeto no servidor (ex: `/var/www/doug-club`) |

A chave pública correspondente a `DEPLOY_SSH_KEY` precisa estar em `~/.ssh/authorized_keys` do `DEPLOY_USER` no droplet (ou cadastrada nas SSH Keys da conta DigitalOcean antes de criar/anexar ao Droplet).

## Por que uma chave dedicada

A chave usada pelo GitHub Actions fica armazenada como secret no repositório. Se ela vazar, o dano fica limitado ao acesso que essa chave específica tem — por isso ela é separada da chave pessoal usada para acesso manual ao servidor, e deve ter só o necessário para rodar os comandos do deploy.

## Cuidado com o modo de manutenção

Se o step de deploy falhar **depois** de `php artisan down` e **antes** de `php artisan up` (ex: uma migration quebrada), o site fica em manutenção até alguém rodar `php artisan up` manualmente no servidor. Verifique o log do workflow no GitHub Actions se o site aparecer fora do ar depois de um deploy.
