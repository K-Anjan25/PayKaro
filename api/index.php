<?php

/**
 * Vercel serverless entrypoint.
 *
 * `public/index.php` stays the front controller for every other host (Nginx,
 * Apache, the php-wasm bridge in `bridge/`). This file exists because Vercel's
 * function filesystem is writable in exactly one place — `/tmp` — and Laravel
 * writes outside it on a normal boot:
 *
 *   storage/framework/views     compiled Blade templates
 *   storage/framework/sessions  sessions, when SESSION_DRIVER=file
 *   storage/framework/cache     the file cache
 *   storage/logs                the log channel
 *   bootstrap/cache             package/config/route caches
 *
 * So the storage path is relocated to /tmp before the framework boots, and the
 * bootstrap caches are redirected there too. Nothing else changes: the
 * application, routes, views and tests are the same code that runs on a
 * PHP-FPM host.
 *
 * Deploy notes, environment variables and the limits of this target are in
 * DEPLOY.md → "Vercel".
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
 * /tmp survives for the life of one function instance, so every directory the
 * framework may write to has to exist before it is asked for. `is_dir` first
 * because two concurrent cold starts on the same instance can race on mkdir.
 */
$storage = '/tmp/storage';

foreach ([
    'framework/views',
    'framework/cache/data',
    'framework/sessions',
    'logs',
    'app/public',
    'bootstrap',
] as $directory) {
    if (! is_dir($storage.'/'.$directory)) {
        @mkdir($storage.'/'.$directory, 0777, true);
    }
}

/*
 * Defaults for a read-only root. Each one is skipped when the Vercel project
 * already defines it, so the dashboard stays the place a deployment is
 * configured:
 *
 *   caches ....... Laravel writes these on boot when they are missing, and the
 *                  shipped bootstrap/cache is not writable.
 *   views ........ no config/view.php here, so the framework default is
 *                  storage/framework/views — hence the explicit redirect.
 *   logs ......... stderr is what Vercel's Runtime Logs reads.
 *   cache ........ `array` is per-invocation; nothing in this app needs a
 *                  shared cache, and `file` would be per-instance.
 *   sessions ..... `database` keeps sign-ins shared across instances and
 *                  revocable (the sessions table ships in the base migration).
 *                  `file` here would sign users out at random as instances
 *                  recycle; `cookie` is the stateless alternative.
 */
$defaults = [
    'APP_CONFIG_CACHE' => $storage.'/bootstrap/config.php',
    'APP_EVENTS_CACHE' => $storage.'/bootstrap/events.php',
    'APP_PACKAGES_CACHE' => $storage.'/bootstrap/packages.php',
    'APP_ROUTES_CACHE' => $storage.'/bootstrap/routes.php',
    'APP_SERVICES_CACHE' => $storage.'/bootstrap/services.php',
    'VIEW_COMPILED_PATH' => $storage.'/framework/views',
    'LOG_CHANNEL' => 'stderr',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'database',
    'SESSION_SECURE_COOKIE' => 'true',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false || getenv($key) === '') {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/*
 * There is no web server in front of this file, so nothing normalises the SAPI.
 * Vercel reports the script as /api/index.php, and Laravel would take that as
 * the base path and prefix every `route()` and `asset()` URL with it. Telling
 * the framework it is serving `/` is what the Nginx `root …/public` block does
 * implicitly on a normal host.
 */
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__).'/public/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require dirname(__DIR__).'/vendor/autoload.php';

/** @var Application $app */
$app = require_once dirname(__DIR__).'/bootstrap/app.php';

$app->useStoragePath($storage);

$app->handleRequest(Request::capture());
