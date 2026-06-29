# Deploy na VPS com Docker

Este guia descreve como publicar a aplicação em uma VPS Linux com Docker e Docker Compose.

## Pré-requisitos na VPS

- Ubuntu 22.04+ (ou Debian 12+)
- Docker Engine 24+ e Docker Compose v2
- Domínio apontando para o IP da VPS (opcional, recomendado)
- Portas **80** e **443** liberadas no firewall

### Instalar Docker (Ubuntu)

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker
```

## 1. Enviar o projeto

Na VPS, clone o repositório ou envie os arquivos:

```bash
git clone <seu-repositorio> anamnese
cd anamnese
```

## 2. Configurar ambiente

```bash
cp .env.docker.example .env
nano .env
```

Ajuste obrigatoriamente:

| Variável | Exemplo |
|----------|---------|
| `APP_KEY` | Gere com: `docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));"` |
| `APP_URL` | `https://clinica.seudominio.com.br` |
| `DB_PASSWORD` | Senha forte (mesma em `MYSQL_ROOT_PASSWORD` implicitamente via compose) |
| `DB_DATABASE` / `DB_USERNAME` | Nomes do banco e usuário |
| `WHATSAPP_*` | Credenciais Z-API |
| `MAIL_*` | SMTP para e-mails |

## 3. Build e subir os containers

```bash
docker compose build
docker compose up -d
```

Serviços:

| Serviço | Função |
|---------|--------|
| `app` | Nginx + PHP 8.3 (porta 80) |
| `mysql` | Banco de dados MySQL 8.4 |
| `queue` | Fila de jobs (`queue:work`) |
| `scheduler` | Lembretes diários (`schedule:work`) |

## 4. Migrations e primeiro acesso

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force   # se usar seeders
```

A aplicação ficará em `http://IP_DA_VPS` (ou na porta definida em `APP_PORT`).

## 5. HTTPS (recomendado)

O Docker expõe apenas HTTP **dentro** do container. O SSL (Certbot) fica no **Nginx do host**, que faz proxy para o container.

### Passo a passo com Certbot (`certbot --nginx`)

1. **Libere a porta 80 no host** para o Nginx do Ubuntu (não o container):
   - No `.env` da VPS: `APP_PORT=8080`
   - `docker compose up -d` (o app passa a escutar em `127.0.0.1:8080`)

2. **Configure o proxy no host** antes ou depois do Certbot. Exemplo completo em
   [`docker/nginx/host-reverse-proxy.example.conf`](../docker/nginx/host-reverse-proxy.example.conf).

3. **Certbot:**

```bash
sudo certbot --nginx -d clinicos.com.br -d www.clinicos.com.br
```

4. **Ajuste o `.env` da aplicação** (obrigatório após ativar HTTPS):

```env
APP_URL=https://clinicos.com.br
SESSION_SECURE_COOKIE=true
```

5. **Limpe o cache** (o `config:cache` grava o `APP_URL` antigo):

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose restart app
```

6. **Atualize o código** (`git pull` + rebuild) para o Nginx do container repassar HTTPS ao PHP e forçar URLs com `APP_URL` em produção.

O proxy **precisa** repassar estes headers (senão CSS/JS ficam em `http://` e o login pode retornar **419**):

```nginx
proxy_set_header Host $host;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
```

**Opção A – Caddy no host** — proxy para `127.0.0.1:8080` com os mesmos headers.

**Opção B – Cloudflare** — proxy laranja no DNS com SSL “Full” para o IP da VPS.

### Acesso pelo IP (`http://2.x.x.x`) retorna 404

Depois do Certbot, o Nginx do host só atende os `server_name` do domínio (`clinicos.com.br`). Requisições pelo **IP** caem no `default_server` do Ubuntu (página 404 do nginx), não no Docker. Isso é esperado: use sempre `https://clinicos.com.br`.

## Comandos úteis

```bash
# Ver logs
docker compose logs -f app

# Shell no container
docker compose exec app bash

# Reiniciar após alterar .env
docker compose up -d --force-recreate app queue scheduler

# Limpar cache Laravel
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache

# Parar tudo
docker compose down

# Parar e remover volumes (CUIDADO: apaga o banco)
docker compose down -v
```

Com Makefile:

```bash
make deploy    # build + up + migrate
make logs
make shell
make migrate
```

## Atualizar versão

```bash
git pull
docker compose build --no-cache app
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

## Uploads de imagens

Arquivos enviados ficam no volume `storage_data`. Faça backup periódico:

```bash
docker run --rm -v anamnese_storage_data:/data -v $(pwd):/backup alpine \
  tar czf /backup/storage-backup.tar.gz -C /data .
```

## Desenvolvimento local com Docker

```bash
cp .env.docker.example .env
# Ajuste APP_ENV=local e APP_DEBUG=true
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

Acesse `http://localhost:8080`.

## Solução de problemas

**Redirect HTTPS → HTTP (`Location: http://...` no Network)**

O Laravel dentro do Docker recebe HTTP do proxy e gera links/redirects em `http://` até:

1. `APP_URL=https://seu-dominio.com.br` no `.env` + `optimize:clear` + `config:cache`
2. Rebuild da imagem (`docker compose build app`) com a correção do Nginx no container

Teste após o deploy:

```bash
curl -sI https://clinicos.com.br/
# Deve ser HTTP/2 ou 302 — NÃO 500
curl -sI https://clinicos.com.br/ | grep -i '^location:'
# Deve ser: location: https://clinicos.com.br/login
```

Se `grep` não retornar nada, veja o status (`curl -sI ... | head -5`). **500** = erro no Laravel (veja logs abaixo).

**Página em branco / sem CSS após SSL (`blocked:mixed-content` no DevTools)**

O HTML abre em HTTPS, mas os assets (`.css`, `.js`, fontes) saem com URL `http://`. O navegador bloqueia.

1. Confirme `APP_URL=https://clinicos.com.br` (com `https`, sem barra no final).
2. Rode `optimize:clear` e `config:cache` (ver seção 5).
3. No Nginx do **host**, confira `proxy_set_header X-Forwarded-Proto $scheme;` no bloco `location /`.
4. Faça `git pull`, `docker compose build app` e `docker compose up -d` para pegar a correção do Nginx no container.

**419 Page Expired no login**

1. Confirme `APP_URL` igual à URL no navegador (ex.: `https://clinicos.com.br`).
2. Se acessa por **HTTP** (sem SSL), use `SESSION_SECURE_COOKIE=false` no `.env`.
3. Se usa **HTTPS** com Nginx/Caddy/Cloudflare na frente, repasse `X-Forwarded-Proto` (ver seção 5).
4. Limpe o cache após mudar o `.env`:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose restart app
```

5. Teste sessão no banco:

```bash
docker compose exec app php artisan db:show
docker compose exec app php artisan tinker --execute="DB::table('sessions')->count();"
```

**500 Internal Server Error após deploy**

Versão anterior enviava headers `X-Forwarded-*` vazios ao PHP e quebrava o app. Atualize o código e rebuild:

```bash
git pull
docker compose build --no-cache app
docker compose up -d
docker compose logs app --tail 30
```

**502 / página em branco**

```bash
docker compose logs app
docker compose exec app php artisan config:clear
```

**Erro de conexão com MySQL (`Access denied`)**

1. Confirme `DB_HOST=mysql` no `.env` (não use `127.0.0.1` dentro do Docker).
2. As credenciais em `DB_*` devem ser **as mesmas** usadas na primeira vez que o container MySQL subiu. O volume `mysql_data` não atualiza usuário/senha ao mudar o `.env`.

Para conferir o usuário existente:

```bash
docker compose exec mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT user FROM mysql.user;"
```

Se alterou `DB_PASSWORD` ou `DB_DATABASE` depois do primeiro `up`, ou recrie o volume (apaga o banco):

```bash
docker compose down -v
docker compose up -d
docker compose exec app php artisan migrate --force
```

Ou ajuste o `.env` para bater com o banco já criado e limpe o cache:

```bash
docker compose exec app php artisan config:clear
docker compose restart app queue scheduler
```

```bash
docker compose ps mysql
```

**Permissão em storage**

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

**APP_KEY ausente**

```bash
docker compose exec app php artisan key:generate
docker compose restart app
```
