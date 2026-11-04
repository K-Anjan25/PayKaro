<p align="center"><img src="public/assets/img/icon-512.png" width="88" alt="PayKaro logo"></p>

# PayKaro

**MSME invoice & receivables tracker** — one pipeline for what is owed, what is
overdue and what could be financed today, with the dated evidence and statutory
interest that make a delayed-payment claim stand.

Laravel 12 · PHP 8.3 · Blade · SQLite or MySQL/Postgres · no build step.

## Run it locally

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/paykaro.sqlite
php artisan migrate --seed
php artisan serve
```

Sign in at <http://localhost:8000/login>. The seed command creates two sample
workspaces; their logins are printed by the seeder and are not published here.

## Deploy to Render

`render.yaml` is the whole deployment — it also creates the Postgres database and
wires it in, so there is no connection string to copy around. Push this repo, then
in Render: **New → Blueprint** → pick the repo → apply.

Set two values when it asks:

| Key | Value |
|-----|-------|
| `APP_KEY` | output of `php artisan key:generate --show` |
| `APP_URL` | your `https://<service>.onrender.com` URL |

Render's *free* Postgres is deleted 30 days after creation, so for anything real
point `DB_URL` at Neon or Supabase, or put the database on a paid plan.

Everything else has a production default. Details: [`docs/DEPLOY.md`](docs/DEPLOY.md).

## Tests

```bash
php artisan test
```

## Docs

Everything else lives in [`docs/`](docs/README.md) — deployment, local development,
the spec, the brand book and the design notes.

MIT — see [`LICENSE`](LICENSE).
