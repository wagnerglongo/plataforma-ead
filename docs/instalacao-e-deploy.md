# Instalação e deploy

## Requisitos

- PHP 8.1 ou superior com as extensões `pdo_mysql`, `gd` (com FreeType), `mbstring`, `intl`, `fileinfo`, `zlib`
- MySQL 5.7+/8 ou MariaDB 10.4+
- Apache ou LiteSpeed com `mod_rewrite` (ou Nginx, ver abaixo)

Confira com `php bin/ead check`.

## Local

```bash
cp .env.example .env
php bin/ead key                    # gera APP_KEY
# crie o banco e preencha DB_* no .env
php bin/ead demo --force           # tabelas + dados de demonstração
php -S 127.0.0.1:8000 -t public public/index.php
```

## Hostinger (hospedagem compartilhada)

A raiz do site (`public_html` ou a pasta do subdomínio) recebe **o projeto inteiro**. O `.htaccess` da raiz encaminha tudo para `public/` e bloqueia `app/`, `storage/`, `.env` e as demais pastas internas.

1. **Banco:** hPanel › Bancos de dados › MySQL. Crie o banco e o usuário e anote os dados.
2. **Arquivos:** envie o projeto para a pasta do domínio (Gerenciador de arquivos, FTP ou Git em hPanel › Avançado › Git). Não envie `.env` local, `node_modules` nem `tests/screenshots`.
3. **PHP:** hPanel › Avançado › Configuração do PHP: versão 8.1 ou superior. Para upload de vídeos grandes, aumente `upload_max_filesize` e `post_max_size`.
4. **.env:** crie o arquivo na raiz a partir do `.env.example`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://seu-dominio.com.br
   APP_KEY=                 # em branco: gerada sozinha em storage/app.key
   DB_HOST=localhost
   DB_DATABASE=u123456789_ead
   DB_USERNAME=u123456789_ead
   DB_PASSWORD=...
   MAIL_DRIVER=smtp
   MAIL_HOST=smtp.hostinger.com
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USERNAME=nao-responda@seu-dominio.com.br
   MAIL_PASSWORD=...
   MAIL_FROM=nao-responda@seu-dominio.com.br
   ```
5. **Instalar:**
   - Com SSH (hPanel › Avançado › Acesso SSH): `php bin/ead install` (pede e-mail e nome do administrador e mostra uma senha provisória).
   - Sem SSH: no gerenciador de arquivos, crie o arquivo vazio `storage/instalar.liberado` e acesse `https://seu-dominio.com.br/instalar`. Ao concluir, o arquivo é apagado e o instalador volta a responder 404.
6. **Permissões:** `storage/` e `public/uploads/` precisam ser graváveis pelo PHP (755 na Hostinger já funciona).
7. **HTTPS:** ative o SSL gratuito no hPanel. A plataforma envia HSTS automaticamente quando acessada por HTTPS.

### Tarefas agendadas (hPanel › Avançado › Cron Jobs)

| Frequência | Comando | Para quê |
|---|---|---|
| Diário, 3h | `php /home/USUARIO/domains/DOMINIO/public_html/bin/ead backup` | backup do banco (guarda os 14 últimos) |
| Diário, 4h (só na demonstração) | `php /home/USUARIO/domains/DOMINIO/public_html/bin/ead demo` | volta os dados de demonstração ao original |

O caminho exato da pasta aparece no Gerenciador de arquivos.

## Atualizações

1. Faça um backup (`php bin/ead backup` ou pelo painel).
2. Envie os arquivos novos (ou `git pull`).
3. Rode `php bin/ead migrate` para aplicar alterações de banco (arquivos em `database/migrations/`).

## Nginx

Aponte a raiz para `public/` e use:

```nginx
location / { try_files $uri /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location ^~ /uploads/ { location ~ \.php$ { deny all; } }
```

## Recuperação

```bash
php bin/ead restore storage/backups/backup-AAAAMMDD-HHMMSS.sql.gz
```

Vídeos, materiais e imagens enviados ficam em `storage/app/` e `public/uploads/`; inclua essas pastas no backup de arquivos da hospedagem.
