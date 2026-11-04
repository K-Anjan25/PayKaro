#!/bin/sh
#
# Container entrypoint. Keeps the start-up steps that depend on the runtime
# environment opt-in, so one image works on a single instance and on a fleet.
#
#   RUN_MIGRATIONS=true   migrate before serving. Correct for ONE instance and
#                         for a fresh environment. With more than one replica,
#                         leave it false and run `php artisan migrate --force`
#                         once from CI / a release step — two containers racing
#                         the same migration is how a deploy half-fails.
#   BUILD_CACHES=true     `php artisan optimize` (config + routes + views).
#                         Safe here, unlike in a Docker build: the environment
#                         variables are present at this point. Skip it when the
#                         platform rotates secrets without a restart.
#   PAYKARO_SEED_DEMO=true  seed the sample workspaces once. The seeder skips
#                         itself when any user exists, so this cannot duplicate
#                         or overwrite a real book. Logins are never displayed
#                         in the UI — read them from the deploy log if needed.
#
# Everything else is left to the app: APP_KEY, DB_*, PAYKARO_*, TRUSTED_PROXIES.
set -eu

# Render, Neon, Supabase, Railway and Heroku all call the connection string
# DATABASE_URL; Laravel reads DB_URL. `config/database.php` falls back to
# DATABASE_URL for the server drivers, so this is belt and braces — it also
# makes the two checks below, and any `artisan db:*` run by hand in the
# container, agree with what the framework will actually connect to.
if [ -z "${DB_URL:-}" ] && [ -n "${DATABASE_URL:-}" ]; then
	DB_URL="$DATABASE_URL"
	export DB_URL
	echo "[paykaro] using DATABASE_URL as DB_URL"
fi

# A server driver with no address anywhere is a missing environment variable,
# not a network fault. Naming it here costs one line; without it the deploy
# dies inside PDO with a stack trace and "connection to server at 127.0.0.1".
case "${DB_CONNECTION:-sqlite}" in
sqlite) ;;
*)
	if [ -z "${DB_URL:-}" ] && [ -z "${DB_HOST:-}" ]; then
		echo "[paykaro] DB_CONNECTION=${DB_CONNECTION} but neither DB_URL nor DB_HOST is set." >&2
		echo "[paykaro] Set DB_URL to your database's connection string — the value your provider calls DATABASE_URL." >&2
		exit 1
	fi
	;;
esac

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
	echo "[paykaro] running migrations against ${DB_CONNECTION:-sqlite}"
	php artisan migrate --force --no-interaction
fi

if [ "${PAYKARO_SEED_DEMO:-false}" = "true" ]; then
	echo "[paykaro] seeding the sample workspaces (skips itself if any user exists)"
	php artisan db:seed --force --no-interaction
fi

if [ "${BUILD_CACHES:-false}" = "true" ]; then
	echo "[paykaro] caching config, routes and views"
	php artisan optimize
fi

# `exec` so FrankenPHP becomes PID 1 and receives the platform's SIGTERM.
exec "$@"
