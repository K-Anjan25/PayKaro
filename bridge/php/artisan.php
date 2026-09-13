<?php

/**
 * Sandbox bridge — run an artisan command.
 *
 * The bridge (js) cannot pass CLI arguments to a script, so the command is
 * handed over in `$_SERVER['PAYKARO_ARGV']` (JSON) and turned back into argv
 * here. One command per process: php-wasm resets its state between runs, which
 * is exactly what `artisan` expects.
 *
 * The two file-level prerequisites the repo's `composer run setup` makes are
 * made here too, because `migrate` fails on a checkout that has neither:
 * `.env` from `.env.example`, and the empty SQLite file the `sqlite` connection
 * points at.
 *
 * usage (from serve.mjs / bootstrap.mjs):
 *     $_SERVER['PAYKARO_ARGV'] = '["migrate","--seed","--force"]'
 */
$root = dirname(__DIR__, 2);

chdir($root);

if (! file_exists($root.'/.env') && file_exists($root.'/.env.example')) {
    copy($root.'/.env.example', $root.'/.env');
}

$database = $root.'/database/paykaro.sqlite';

if (! file_exists($database)) {
    touch($database);
}

$arguments = json_decode((string) ($_SERVER['PAYKARO_ARGV'] ?? '[]'), true);
$arguments = is_array($arguments) && $arguments !== [] ? $arguments : ['migrate', '--seed', '--force'];

$_SERVER['argv'] = array_merge(['artisan'], $arguments);
$_SERVER['argc'] = count($_SERVER['argv']);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/artisan';
$_SERVER['SCRIPT_FILENAME'] = $root.'/artisan';

require $root.'/artisan';
