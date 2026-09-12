<?php

/*
 * A faithful-enough stand-in for composer's own vendor/composer/ClassLoader.php,
 * for the hand-unpacked vendor tree (see /tmp/wp/gen-autoload.php). Laravel reads
 * ClassLoader::getRegisteredLoaders() to infer the app's base path, so the class
 * has to exist and register itself exactly like composer's does.
 */

namespace Composer\Autoload;

class ClassLoader
{
    private $vendorDir;

    private $prefixLengthsPsr4 = [];

    private $prefixDirsPsr4 = [];

    private $fallbackDirsPsr4 = [];

    private $prefixesPsr0 = [];

    private $fallbackDirsPsr0 = [];

    private $useIncludePath = false;

    private $classMap = [];

    private $classMapAuthoritative = false;

    private $missingClasses = [];

    private $apcuPrefix;

    private static $registeredLoaders = [];

    public function __construct($vendorDir = null)
    {
        $this->vendorDir = $vendorDir;
    }

    public function getPrefixes()
    {
        return $this->prefixesPsr0;
    }

    public function getPrefixesPsr4()
    {
        return $this->prefixDirsPsr4;
    }

    public function getFallbackDirs()
    {
        return $this->fallbackDirsPsr0;
    }

    public function getFallbackDirsPsr4()
    {
        return $this->fallbackDirsPsr4;
    }

    public function getClassMap()
    {
        return $this->classMap;
    }

    public function addClassMap(array $classMap)
    {
        if ($this->classMap) {
            $this->classMap = array_merge($this->classMap, $classMap);
        } else {
            $this->classMap = $classMap;
        }
    }

    public function add($prefix, $paths, $prepend = false)
    {
        $paths = (array) $paths;
        $firstChars = $prefix ? $prefix[0] : "\0";
        if (isset($this->prefixesPsr0[$firstChars])) {
            $this->prefixesPsr0[$firstChars] = $prepend
                ? array_merge([$prefix => $paths], $this->prefixesPsr0[$firstChars])
                : array_merge($this->prefixesPsr0[$firstChars], [$prefix => $paths]);
        } else {
            $this->prefixesPsr0[$firstChars] = [$prefix => $paths];
        }
    }

    public function addPsr4($prefix, $paths, $prepend = false)
    {
        $paths = (array) $paths;

        if (! $prefix) {
            $this->fallbackDirsPsr4 = $prepend
                ? array_merge($paths, $this->fallbackDirsPsr4)
                : array_merge($this->fallbackDirsPsr4, $paths);

            return;
        }

        if ($prepend) {
            $this->prefixDirsPsr4[$prefix] = array_merge($paths, $this->prefixDirsPsr4[$prefix] ?? []);
        } else {
            $this->prefixDirsPsr4[$prefix] = array_merge($this->prefixDirsPsr4[$prefix] ?? [], $paths);
        }

        $this->prefixLengthsPsr4[$prefix[0]][substr($prefix, 0, strrpos($prefix, '\\') ?: 0)] = strlen($prefix);
    }

    public function set($prefix, $paths)
    {
        if (! $prefix) {
            $this->fallbackDirsPsr0 = (array) $paths;

            return;
        }

        $this->prefixesPsr0[$prefix[0]][$prefix] = (array) $paths;
    }

    public function setPsr4($prefix, $paths)
    {
        if (! $prefix) {
            $this->fallbackDirsPsr4 = (array) $paths;

            return;
        }

        $length = strlen($prefix);

        if ($prefix[$length - 1] !== '\\') {
            throw new \InvalidArgumentException('A non-empty PSR-4 prefix must end with a namespace separator.');
        }

        $this->prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
        $this->prefixDirsPsr4[$prefix] = (array) $paths;
    }

    public function setUseIncludePath($useIncludePath)
    {
        $this->useIncludePath = $useIncludePath;
    }

    public function getUseIncludePath()
    {
        return $this->useIncludePath;
    }

    public function setClassMapAuthoritative($classMapAuthoritative)
    {
        $this->classMapAuthoritative = $classMapAuthoritative;
    }

    public function isClassMapAuthoritative()
    {
        return $this->classMapAuthoritative;
    }

    public function setApcuPrefix($apcuPrefix)
    {
        $this->apcuPrefix = function_exists('apcu_fetch') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN) ? $apcuPrefix : null;
    }

    public function getApcuPrefix()
    {
        return $this->apcuPrefix;
    }

    public function register($prepend = false)
    {
        spl_autoload_register([$this, 'loadClass'], true, $prepend);

        if ($this->vendorDir === null) {
            return;
        }

        if ($prepend) {
            self::$registeredLoaders = [$this->vendorDir => $this] + self::$registeredLoaders;
        } else {
            unset(self::$registeredLoaders[$this->vendorDir]);
            self::$registeredLoaders[$this->vendorDir] = $this;
        }
    }

    public function unregister()
    {
        spl_autoload_unregister([$this, 'loadClass']);

        if ($this->vendorDir !== null) {
            unset(self::$registeredLoaders[$this->vendorDir]);
        }
    }

    public function loadClass($class)
    {
        if ($file = $this->findFile($class)) {
            self::includeFile($file);

            return true;
        }

        return null;
    }

    public function findFile($class)
    {
        if (isset($this->classMap[$class])) {
            return $this->classMap[$class];
        }

        if ($this->classMapAuthoritative || isset($this->missingClasses[$class])) {
            return false;
        }

        $file = $this->findFileWithExtension($class);

        if ($file === false) {
            $this->missingClasses[$class] = true;
        }

        return $file;
    }

    private function findFileWithExtension($class, $ext = '.php')
    {
        $logicalPathPsr4 = strtr($class, '\\', DIRECTORY_SEPARATOR).$ext;

        $first = $class[0];
        if (isset($this->prefixLengthsPsr4[$first])) {
            $subPath = $class;
            while (false !== $lastPos = strrpos($subPath, '\\')) {
                $subPath = substr($subPath, 0, $lastPos);
                $search = $subPath.'\\';
                if (isset($this->prefixDirsPsr4[$search])) {
                    $pathEnd = DIRECTORY_SEPARATOR.substr($logicalPathPsr4, $lastPos + 1);
                    foreach ($this->prefixDirsPsr4[$search] as $dir) {
                        if (file_exists($file = $dir.$pathEnd)) {
                            return $file;
                        }
                    }
                }
            }
        }

        foreach ($this->fallbackDirsPsr4 as $dir) {
            if (file_exists($file = $dir.DIRECTORY_SEPARATOR.$logicalPathPsr4)) {
                return $file;
            }
        }

        if (false !== $pos = strrpos($class, '\\')) {
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                .strtr(substr($logicalPathPsr4, $pos + 1), '_', DIRECTORY_SEPARATOR);
        } else {
            $logicalPathPsr0 = strtr($class, '_', DIRECTORY_SEPARATOR).$ext;
        }

        if (isset($this->prefixesPsr0[$first])) {
            foreach ($this->prefixesPsr0[$first] as $prefix => $dirs) {
                if (strpos($class, $prefix) === 0) {
                    foreach ($dirs as $dir) {
                        if (file_exists($file = $dir.DIRECTORY_SEPARATOR.$logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }

        foreach ($this->fallbackDirsPsr0 as $dir) {
            if (file_exists($file = $dir.DIRECTORY_SEPARATOR.$logicalPathPsr0)) {
                return $file;
            }
        }

        if ($this->useIncludePath && $file = stream_resolve_include_path($logicalPathPsr0)) {
            return $file;
        }

        return false;
    }

    private static function includeFile($file)
    {
        include $file;
    }

    public static function getRegisteredLoaders()
    {
        return self::$registeredLoaders;
    }
}
