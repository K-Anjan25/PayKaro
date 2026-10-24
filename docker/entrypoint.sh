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
#
# Everything else is left to the app: APP_KEY, DB_*, PAYKARO_*, TRUSTED_PROXIES.
set -eu

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
	echo "[paykaro] running migrations"
	php artisan migrate --force --no-interaction
fi

if [ "${BUILD_CACHES:-false}" = "true" ]; then
	echo "[paykaro] caching config, routes and views"
	php artisan optimize
fi

# `exec` so FrankenPHP becomes PID 1 and receives the platform's SIGTERM.
exec "$@"
