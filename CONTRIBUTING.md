# Contributing to PayKaro

Thanks for taking a look. This is a Laravel 12 application with no build step — the
design system is a committed stylesheet (`public/assets/app.css`), so a clone plus a
SQLite file is a complete development environment.

## Set up

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/paykaro.sqlite
php artisan migrate --seed        # two demo businesses, 18 invoices, 6 buyers
php artisan serve                 # http://localhost:8000/login
```

Demo logins: `sunita@shreeprecision.in` or `farhan@metrowceramics.in`, password
`demo1234`. The seeder is idempotent — it skips itself once any user exists, so
re-running it never doubles the book. `migrate:fresh --seed` starts over.

No `php`/`composer` binary? The repo runs inside `@php-wasm/node` — see `RUN.md`
→ *Sandbox*.

## Before you open a pull request

```bash
php artisan test                  # the whole suite
./vendor/bin/pint --test          # style; `composer format` applies it
```

If you touched `public/assets/app.css` or a view's grid:

```bash
node bridge/overflow-sim.mjs      # the grid/overflow guards
node bridge/test.mjs              # the JS side of the harness (cd bridge && npm ci once)
```

CI (`.github/workflows/ci.yml`) runs lint and the suite on every push; both must be
green before merge.

## Rules the codebase already enforces

These are not preferences — tests fail without them.

- **Money and dates are derived, never typed.** The 45-day MSMED window, the
  `bank_rate × 3` compound interest with monthly rests, the ageing buckets and the
  readiness score all come from `config/paykaro.php` and `App\Services\Receivables`.
  Do not hard-code a rate, a day count or a threshold in a controller or a view.
  `tests/Unit/ReceivablesTest.php` is the contract: change the formula there first.
- **Tenant isolation is fail-closed.** Every model that belongs to a business goes
  through `App\Tenancy\TenantScope`. Never bypass it, never accept a `business_id`
  from a request, and answer another tenant's record with **404, not 403** — that
  is what `TenantIsolationTest` pins.
- **Roles are enforced by policies, not by hiding buttons.** A viewer who posts
  anyway must get a 403 from the server (`App\Policies`), not just a missing button.
- **Colours are tokens.** A new colour belongs in the stylesheet variables and in
  `App\Brand\Palette` with its contrast floor; `tests/Unit/BrandPaletteTest.php`
  fails otherwise. No hex codes in a Blade template.
- **Nothing a customer reads is retyped.** Figures come from `App\Support\Proof` or
  from config, because invented traction numbers are the failure
  `tests/Feature/AuthenticityTest.php` exists to prevent.
- **Business copy has one source.** The brand line is `config('paykaro.headline')` —
  it appears in the hero, the `<title>`, the sign-in shell and the share card, and
  `BrandCopyTest` checks they agree.

## Pull requests

Fill in `.github/pull_request_template.md`. In short:

- one PR, one concern, with a sentence on **what a user sees differently**;
- screenshots for any layout, colour or type change — light **and** dark, plus the
  printed claim packet if the `@media print` block changed;
- say which route and which role you tested with;
- a migration must be additive and have a `down()`, and it also has to run in
  production: `php artisan migrate --force` before the new code takes traffic
  (`DEPLOY.md` §5).

## Commit messages

Imperative subject, and the body explains **why**:

```text
Fix the overdue badge losing its border in dark mode

The tint and border came from a literal hex pair while the text used a token, so
dark mode kept the light border. All three now read the token.
```

## Reporting bugs

Include the route, the role you were signed in as, what you expected and what
happened, and the tail of `storage/logs/laravel.log` if there is an error.

**Do not paste real invoice, GSTIN, PAN or bank data into an issue.** Reproduce with
the seeded demo tenants instead.

## Security

Please do not open a public issue for a vulnerability. Report it privately to the
repository owner (`K-Anjan25` on GitHub) with reproduction steps, and allow a
reasonable window to fix it before disclosure.

Worth knowing about the product's scope: it stores **no uploaded documents** (the
evidence checklist records that a document exists, it does not store the file) and
no card data. `/security` states exactly what the app does and does not do — keep
that page honest if your change alters any of it.

## Deployment changes

Deployment lives in `DEPLOY.md` (hosts, proxies, the `PAYKARO_*` numbers),
`DEPLOY-ORACLE.md`, `DEPLOY-VERCEL.md` and the container files (`Dockerfile`,
`Caddyfile`, `docker-compose.yml`). If you change how the app is served, update the
guide for the target you changed — a stale runbook is worse than none.

## License

Contributions are accepted under the MIT License in [`LICENSE`](LICENSE).
