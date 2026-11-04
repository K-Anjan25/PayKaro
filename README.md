<p align="center"><img src="public/assets/img/icon-512.png" width="88" alt="PayKaro logo"></p>

<h1 align="center">PayKaro</h1>

<p align="center">MSME invoice &amp; receivables tracker — one pipeline for what is owed, what is overdue and what could be financed today, with the dated evidence and statutory interest that make a delayed-payment claim stand.</p>

## Run it

```bash
docker compose up --build
```

Open http://localhost:8080. Migrations and the sample workspaces run on first
boot; the sample logins are printed to the container log.

## Run it without Docker

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/paykaro.sqlite
php artisan migrate --seed
php artisan serve
```

## Tests

```bash
php artisan test
```

Laravel 12 · PHP 8.3 · MIT
