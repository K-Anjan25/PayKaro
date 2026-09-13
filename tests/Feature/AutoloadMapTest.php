<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Autoloader map guard.
 *
 * The seeder is resolved by Laravel as the bare class `DatabaseSeeder`, so if
 * composer.json forgets the `Database\Seeders\` PSR-4 entry, nothing fails
 * until `php artisan migrate --seed` dies with "Target class [DatabaseSeeder]
 * does not exist" — on a *fresh install*, which is exactly the moment the app
 * is meant to be most trustworthy. (laravel/laravel maps those two namespaces in
 * `autoload`; a hand-written composer.json can silently drop them.)
 *
 * This asserts every namespace declared under app/ and database/ is resolvable
 * from the composer classmap, directory by directory.
 */
final class AutoloadMapTest extends TestCase
{
    public function test_every_app_and_database_namespace_is_known_to_the_autoloader(): void
    {
        $loader = require __DIR__.'/../../vendor/autoload.php';

        $checked = 0;
        foreach (['app', 'database'] as $dir) {
            $base = dirname(__DIR__, 2).'/'.$dir;

            foreach ($this->phpFiles($base) as $file) {
                $src = file_get_contents($file);

                if (! preg_match('/^namespace\s+([^;]+);/m', $src, $m)) {
                    continue; // migrations: loaded by path, deliberately global
                }

                $namespace = trim($m[1]);
                $class = $namespace.'\\'.basename($file, '.php');

                $this->assertNotNull(
                    $loader->findFile($class),
                    "{$class} ({$file}) is not resolvable — add its namespace to composer.json autoload.psr-4."
                );
                $checked++;
            }
        }

        // Guards against the sweep silently checking nothing (a wrong path,
        // or a regex that stopped matching) rather than a specific file count.
        $this->assertGreaterThan(40, $checked, 'the sweep should have covered the whole app, not nothing');
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $out = [];

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;

            if (is_dir($path)) {
                $out = array_merge($out, $this->phpFiles($path));
            } elseif (str_ends_with($path, '.php')) {
                $out[] = $path;
            }
        }

        return $out;
    }
}
