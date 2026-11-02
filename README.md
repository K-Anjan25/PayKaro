# PayKaro

Invoice & receivables tracker for Indian B2B (MSME) businesses: one pipeline for
what is owed, what is overdue and what could be financed today.

Laravel 12 · PHP 8.3 · Blade · SQLite or MySQL/Postgres · no build step.

## Run it locally

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/paykaro.sqlite
php artisan migrate --seed
php artisan serve
```

Sign in at <http://localhost:8000/login> with `sunita@shreeprecision.in` / `demo1234`.

## Deploy to Render

`render.yaml` is the whole deployment. Push this repo, then in Render:
**New → Blueprint** → pick the repo → apply.

Set three values when it asks:

| Key | Value |
|-----|-------|
| `APP_KEY` | output of `php artisan key:generate --show` |
| `APP_URL` | your `https://<service>.onrender.com` URL |
| `DB_URL` | Postgres/MySQL connection string (Neon, Supabase, Render Postgres…) |

Everything else has a production default. Details: [`docs/DEPLOY.md`](docs/DEPLOY.md).

## Tests

```bash
php artisan test
```

## Docs

Everything else lives in [`docs/`](docs/README.md) — deployment, local development,
the spec, the brand book and the design notes.

MIT — see [`LICENSE`](LICENSE).
