# Clinicos

Sistema de gestão para clínicas de estética. Centraliza clientes, agenda, tratamentos, orçamentos, anamnese e comunicação com pacientes via WhatsApp e e-mail.

## Funcionalidades

- **Dashboard** — visão geral de agendamentos e indicadores da clínica
- **Clientes** — cadastro, histórico de tratamentos comprados e anamnese
- **Agenda** — agendamentos com profissionais, horários disponíveis e múltiplos tratamentos por sessão
- **Concluir agendas** — registro em lote de atendimentos realizados
- **Tratamentos** — catálogo com preços, combos, pacotes e duração
- **Orçamentos** — simulação de valores para clientes
- **Relatórios** — análises com exportação em PDF
- **Anamnese** — perguntas personalizáveis, preenchimento interno ou por link público enviado ao cliente
- **WhatsApp (Z-API)** — lembretes, confirmação de agendamento, orientações pré-procedimento e webhook de mensagens
- **Administração** — gestão de profissionais (`admin` / `staff`) e configurações da clínica

## Stack

| Camada      | Tecnologias |
|-------------|-------------|
| Backend     | PHP 8.3+, Laravel 13, Fortify |
| Frontend    | React 19, Inertia.js, TypeScript, Tailwind CSS 4 |
| Banco       | SQLite (desenvolvimento) / MySQL 8.4 (produção) |
| Filas       | Laravel Queue (driver `database`) |
| PDF         | DomPDF |
| Deploy      | Docker + Docker Compose |

## Requisitos

- PHP **8.3+** com extensões comuns do Laravel (`pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`)
- Composer 2.x
- Node.js **20+** e npm
- SQLite (padrão local) ou MySQL (produção)

## Instalação local

```bash
git clone git@github.com:Leoamaaral/clinicos.git clinicos
cd clinicos
composer setup
```

O script `composer setup` executa: `composer install`, cria o `.env`, gera a `APP_KEY`, roda as migrations, instala dependências npm e faz o build do frontend.

### Configuração manual

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite   # se usar SQLite
php artisan migrate
php artisan db:seed              # opcional: usuário admin e dados iniciais
npm install
npm run build
```

### Variáveis de ambiente

Copie `.env.example` para `.env` e ajuste conforme necessário:

| Variável | Descrição |
|----------|-----------|
| `APP_URL` | URL base da aplicação |
| `DB_CONNECTION` | `sqlite` (local) ou `mysql` (produção) |
| `WHATSAPP_API_URL` | Endpoint Z-API para envio de mensagens |
| `WHATSAPP_CLIENT_TOKEN` | Token de segurança da conta Z-API |
| `MAIL_*` | Configuração SMTP para lembretes por e-mail |

Configure o webhook de mensagens recebidas no painel Z-API:

```
{APP_URL}/webhooks/whatsapp
```

## Executando em desenvolvimento

Inicia servidor PHP, fila, logs e Vite em paralelo:

```bash
composer dev
```

A aplicação ficará disponível em `http://localhost:8000`.

Para rodar os serviços separadamente:

```bash
php artisan serve
php artisan queue:listen
npm run dev
```

O scheduler (lembretes de agendamento) roda via `php artisan schedule:work` ou cron em produção.

## Primeiro acesso

Após `php artisan db:seed`, use as credenciais padrão:

| Campo    | Valor |
|----------|-------|
| E-mail   | `clinicos@gmail.com` |
| Senha    | `@password123` |

Altere a senha após o primeiro login em produção.

## Papéis de usuário

| Papel   | Permissões |
|---------|------------|
| `admin` | Acesso total, incluindo profissionais e configurações da clínica |
| `staff` | Profissional: pode ser vinculado a agendamentos |

## Deploy em produção

Para publicar em VPS com Docker (Nginx, PHP, MySQL, fila e scheduler), siga o guia:

**[docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md)**

Resumo:

```bash
cp .env.docker.example .env
# edite APP_KEY, APP_URL, senhas e credenciais Z-API
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force   # opcional
```

## Testes e qualidade

```bash
composer test          # Pint + PHPUnit
composer ci:check      # lint PHP/TS, formatação e testes
npm run lint           # ESLint
npm run types:check    # TypeScript
```

## Estrutura do projeto

```
app/                 # Controllers, Models, Services, Jobs
database/            # Migrations e seeders
resources/js/        # Páginas e componentes React (Inertia)
routes/web.php       # Rotas da aplicação
docker-compose.yml   # Stack de produção
docs/                # Documentação adicional
```

## Licença

MIT
