# Queue worker em produção (Supervisor)

Este projeto usa fila de e-mails (notificações de sessão 1:1, issue #27) via `QUEUE_CONNECTION=database`. Sem um worker de fila rodando continuamente em produção, nenhum e-mail enfileirado é enviado — eles ficam acumulados na tabela `jobs`, aguardando um worker.

## Passo a passo (Ubuntu/Debian, ajuste para sua distro)

1. Instalar o Supervisor:
   ```bash
   sudo apt update
   sudo apt install supervisor
   ```

2. Criar o arquivo de configuração `/etc/supervisor/conf.d/doing-club-worker.conf`:
   ```ini
   [program:doing-club-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /caminho/completo/para/doug-club/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/caminho/completo/para/doug-club/storage/logs/worker.log
   stopwaitsecs=3600
   ```

   Substitua `/caminho/completo/para/doug-club` pelo caminho real do projeto no VPS, e `user=www-data` pelo usuário que roda o deploy (o mesmo dono dos arquivos do projeto).

3. Registrar e iniciar:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start doing-club-worker:*
   ```

4. Verificar que está rodando:
   ```bash
   sudo supervisorctl status
   ```
   Deve mostrar `doing-club-worker:doing-club-worker_00   RUNNING`.

## Teste de fumaça pós-deploy

Depois de configurar o worker, confirme que ele está processando jobs de verdade:

**Antes de tudo**, confirme que `APP_URL` no `.env` de produção é o domínio real do projeto. Os links dos e-mails enfileirados (ex: o botão "Ver minha agenda") são gerados pelo worker, que roda em contexto de linha de comando — sem uma requisição HTTP real para herdar o host, o Laravel usa exatamente o que está em `config('app.url')`. Se `APP_URL` estiver errado, todo link nos e-mails de confirmação e lembrete aponta pro lugar errado, sem nenhum erro visível.

1. No servidor, rode `php artisan tinker`.
2. Marque uma sessão de teste (ou use o próprio fluxo da Agenda como membro/mentor de teste) para gerar uma notificação real na fila.
3. Rode `tail -f storage/logs/worker.log` e confirme que o job aparece como processado (`Processed: App\Jobs\SendSessionReminderJob` ou `App\Notifications\...`) em poucos segundos.
4. Se nada aparecer no log, confira `sudo supervisorctl status` — se o processo não estiver `RUNNING`, olhe `sudo supervisorctl tail doing-club-worker stderr` para o erro.

## Por que não precisa de cron

O `->delay()` usado pelo lembrete de sessão (`SendSessionReminderJob`) é resolvido inteiramente pela fila (`available_at` na tabela `jobs`) — o worker já verifica isso continuamente. Não é necessário configurar `php artisan schedule:run` via crontab para este recurso.

## Se o worker cair

O Supervisor reinicia o processo automaticamente (`autorestart=true`). Se o VPS inteiro reiniciar, o Supervisor também precisa estar configurado para iniciar no boot — isso já é o comportamento padrão de uma instalação padrão do Supervisor via `apt`, mas confirme com:
```bash
sudo systemctl is-enabled supervisor
```
Se não estiver `enabled`, rode `sudo systemctl enable supervisor`.

## Depois de cada deploy

O worker mantém o código da aplicação carregado em memória enquanto roda — ele não percebe sozinho que o código mudou. Depois de todo deploy, rode:
```bash
php artisan queue:restart
```
Isso sinaliza o worker pra terminar o job atual e reiniciar (o Supervisor sobe um processo novo automaticamente, graças a `autorestart=true`). Sem esse passo, o worker continua rodando o código antigo até ser reiniciado manualmente.

## Jobs que falharam

Com `--tries=3`, um job que falha (ex: erro de SMTP, ou uma sessão apagada antes do lembrete rodar) esgota as tentativas e cai na tabela `failed_jobs`, sem gerar nenhum alerta automático (fora de escopo desta issue). Pra checar manualmente:
```bash
php artisan queue:failed
```
Pra reprocessar todos os jobs falhados depois de corrigir a causa:
```bash
php artisan queue:retry all
```
