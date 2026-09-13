<?php

/**
 * Sandbox bridge — generate a composer-compatible autoloader.
 *
 * `composer dump-autoload` is what normally writes `vendor/autoload.php` and the
 * maps beside it. The bridge has to do it by hand because the sandboxes it exists
 * for have no Composer; `bootstrap.mjs` unpacks package code from the lock, and
 * this file turns those unpacked directories into the loader Laravel expects.
 *
 * It reads each package's own `composer.json` (psr-4, psr-0, classmap, files) —
 * the same input Composer's dumper reads — and writes:
 *
 *     vendor/composer/autoload_psr4.php · autoload_namespaces.php
 *     vendor/composer/autoload_classmap.php · autoload_files.php
 *     vendor/composer/installed.json      (Laravel's package discovery)
 *     vendor/autoload.php                 (returns the registered ClassLoader)
 *
 * Run through the bridge (php-wasm) rather than directly:
 *
 *     node bridge/bootstrap.mjs
 */
$root = dirname(__DIR__, 2);
$vendor = $root.'/vendor';

if (! is_dir($vendor)) {
    fwrite(STDERR, "no vendor/ under {$root} — run `node bridge/bootstrap.mjs`\n");
    exit(1);
}

// Composer creates this directory; nothing here runs before us, so do it.
if (! is_dir($composer = $vendor.'/composer') && ! mkdir($composer, 0755, true) && ! is_dir($composer)) {
    fwrite(STDERR, "could not create {$composer}\n");
    exit(1);
}

// Laravel asks Composer's loader where the app lives (Application::inferBasePath),
// so the stand-in loader has to be the real class shape.
copy(__DIR__.'/ClassLoader.php', $composer.'/ClassLoader.php');

$lock = json_decode(file_get_contents($root.'/composer.lock'), true);
$locked = [];

foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
    $locked[$package['name']] = $package;
}

$psr4 = [];
$psr0 = [];
$files = [];
$classmapDirs = [];
$classmapExclude = [];
$installed = [];
$names = [];

// The root package's own autoload section (App\, Database\Factories, helpers.php)
// lives outside vendor/ — Composer still dumps it, so include it.
$manifests = array_merge(glob($vendor.'/*/*/composer.json') ?: [], [$root.'/composer.json']);

foreach ($manifests as $manifestPath) {
    $base = dirname($manifestPath);
    $isRoot = $base === $root;
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
    $name = $manifest['name'] ?? basename(dirname($base)).'/'.basename($base);
    $autoload = $manifest['autoload'] ?? [];

    if ($isRoot) {
        foreach ($manifest['autoload-dev'] ?? [] as $section => $entries) {
            foreach ($entries as $key => $value) {
                $autoload[$section][$key] = isset($autoload[$section][$key])
                    ? array_merge((array) $autoload[$section][$key], (array) $value)
                    : $value;
            }
        }
    }

    foreach ($autoload['psr-4'] ?? [] as $prefix => $paths) {
        foreach ((array) $paths as $path) {
            $psr4[$prefix][] = rtrim($base.'/'.trim($path, '/'), '/');
        }
    }

    foreach ($autoload['psr-0'] ?? [] as $prefix => $paths) {
        foreach ((array) $paths as $path) {
            $psr0[$prefix][] = rtrim($base.'/'.trim($path, '/'), '/');
        }
    }

    foreach ((array) ($autoload['classmap'] ?? []) as $path) {
        $classmapDirs[] = rtrim($base.'/'.trim($path, '/'), '/');
    }

    foreach ((array) ($autoload['exclude-from-classmap'] ?? []) as $path) {
        $classmapExclude[] = $base.'/'.ltrim(str_replace('/**', '', $path), '/');
    }

    foreach ((array) ($autoload['files'] ?? []) as $file) {
        $files[] = $base.'/'.ltrim($file, '/');
    }

    $installed[] = [
        'name' => $name,
        'version' => $locked[$name]['version'] ?? ($manifest['version'] ?? 'dev-main'),
        'version_normalized' => $locked[$name]['version_normalized'] ?? null,
        'type' => $manifest['type'] ?? 'library',
        'autoload' => $autoload,
        'extra' => $manifest['extra'] ?? [],
    ];
    $names[] = $name;
}

/* ------------------------------------------------ classmap directories */

/**
 * The classes a file declares, keyed by their fully-qualified name. Composer
 * scans classmap entries with PHP's tokenizer for the same reason: a file's
 * class name cannot be guessed from its path.
 *
 * @return array<string, string>
 */
function classesIn(string $file): array
{
    $source = @file_get_contents($file);
    $tokens = $source === false ? null : @token_get_all($source);

    if (! is_array($tokens)) {
        return [];
    }

    $found = [];
    $namespace = '';
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $namespace = '';
            for ($j = $i + 1; $j < $count; $j++) {
                $next = $tokens[$j];
                if ($next === ';' || $next === '{') {
                    break;
                }
                if (is_array($next) && in_array($next[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                    $namespace .= $next[1];
                }
            }
            $namespace = trim($namespace, '\\');
        }

        if (! in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            continue;
        }

        // `::class` and anonymous classes declare nothing we can autoload.
        for ($k = $i - 1; $k >= 0; $k--) {
            if (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($tokens[$k]) && $tokens[$k][0] === T_DOUBLE_COLON) {
                continue 2;
            }
            break;
        }

        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $found[($namespace ? $namespace.'\\' : '').$tokens[$j][1]] = $file;
                break;
            }
            if ($tokens[$j] === '{' || $tokens[$j] === '(') {
                break;
            }
        }
    }

    return $found;
}

$classmap = [];

foreach ($classmapDirs as $dir) {
    if (is_file($dir)) {
        $classmap += classesIn($dir);

        continue;
    }

    if (! is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $entry) {
        /** @var SplFileInfo $entry */
        if ($entry->getExtension() !== 'php') {
            continue;
        }

        $file = $entry->getPathname();

        foreach ($classmapExclude as $excluded) {
            if ($excluded !== '' && str_starts_with($file, $excluded)) {
                continue 2;
            }
        }

        $classmap += classesIn($file);
    }
}

/* ---------------------------------------------------------------- write */

function dump(string $file, array $value): void
{
    if (file_put_contents($file, "<?php\n\nreturn ".var_export($value, true).";\n") === false) {
        fwrite(STDERR, "could not write {$file}\n");
        exit(1);
    }
}

// The same order Composer dumps in, longest prefix first, so the two loaders
// resolve nested namespaces identically.
uksort($psr4, fn ($a, $b) => strlen($b) <=> strlen($a));

dump($vendor.'/composer/autoload_psr4.php', $psr4);
dump($vendor.'/composer/autoload_namespaces.php', $psr0);
dump($vendor.'/composer/autoload_classmap.php', $classmap);
dump($vendor.'/composer/autoload_files.php', $files);

file_put_contents(
    $vendor.'/composer/installed.json',
    json_encode(
        ['packages' => $installed, 'dev' => true, 'dev-package-names' => $names],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    )
);

file_put_contents($vendor.'/autoload.php', <<<'PHP'
<?php

/**
 * Generated by bridge/php/gen-autoload.php (see that file's header). Behaviourally
 * the same contract as Composer's loader: the dumped classmap first, then psr-4
 * and psr-0 lookups, then the `files` includes. It returns the loader, because
 * that is how Composer's own autoload.php ends and how callers use it.
 */

require_once __DIR__.'/composer/ClassLoader.php';

$loader = new Composer\Autoload\ClassLoader(dirname(__DIR__).'/vendor');

foreach (require __DIR__.'/composer/autoload_psr4.php' as $prefix => $paths) {
    $loader->setPsr4($prefix, $paths);
}

foreach (require __DIR__.'/composer/autoload_namespaces.php' as $prefix => $paths) {
    $loader->set($prefix, $paths);
}

$loader->addClassMap(require __DIR__.'/composer/autoload_classmap.php');
$loader->register(true);

foreach (require __DIR__.'/composer/autoload_files.php' as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

return $loader;
PHP);

printf(
    "%d packages · %d psr-4 prefixes · %d namespace prefixes · %d classmap entries · %d file includes\n",
    count($installed),
    count($psr4),
    count($psr0),
    count($classmap),
    count($files),
);
