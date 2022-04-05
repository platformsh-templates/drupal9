<?php

// https://github.com/cloudpanel-io/clp-opcache-preloader

error_reporting((E_ALL | E_STRICT) ^ E_NOTICE);
//ini_set('display_errors', 1);

class ClpPreloader
{
    /**
     * @var array
     */
    private $ignores = [];

    /**
     * @var array
     */
    private $paths = [];

    /**
     * @var array
     */
    private $loaded = [];

    /**
     * @var int
     */
    private $count = 0;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var array
     */
    private $included = [];

    /**
     * Constructor
     *
     * @param string $paths
     */
    public function __construct(string ...$paths)
    {
        $this->paths = $paths;
    }

    /**
     * Paths
     *
     * Path to load
     *
     * @param string $paths
     *
     * @return $this
     */
    public function paths(string ...$paths): void
    {
        $this->paths = \array_merge(
            $this->paths,
            $paths
        );
    }

    /**
     * Ignore
     *
     * Ignore a given path or file
     *
     * @param string $names
     *
     * @return $this
     */
    public function ignore(string ...$names): void
    {
        foreach ($names as $name) {
            if (true === is_readable($name)) {
                $this->ignores[] = $name;
            } else {
                if (true === $this->debug) {
                    echo sprintf('Preloader] Failed to ignore path %s', $name).PHP_EOL;
                }
            }
        }
    }

    /**
     * Preload
     */
    public function preload(): void
    {
        $this->loaded = get_included_files();
        foreach ($this->paths as $path) {
            $path = \rtrim($path, '/');
            $this->loadPath($path);
        }
        if (true === $this->debug) {
            echo sprintf('[Preloader] Preloaded: %s files', $this->count).PHP_EOL;
        }
    }

    /**
     * Get Count
     *
     * Get the total number of loaded files.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get List
     *
     * @return array
     */
    public function getList(): array
    {
        return $this->included;
    }

    /**
     * Set Debug
     *
     * @param bool $flag
     */
    public function setDebug(bool $flag = true): void
    {
        $this->debug = $flag;
    }

    /**
     * Load Path
     *
     * Load a specific file or folder and nested folders.
     *
     * @param string $path
     * @return void
     */
    private function loadPath(string $path): void
    {
        if (true === \is_dir($path)) {
            $this->loadDir($path);
            return;
        }
        $this->loadFile($path);
    }

    /**
     * Load Directory
     *
     * Load a specific folder and nested folders.
     *
     * @param string $path
     * @return void
     */
    private function loadDir(string $path): void
    {
        $handle = \opendir($path);
        while ($file = \readdir($handle)) {
            if (true === \in_array($file, ['.', '..'])) {
                continue;
            }
            $this->loadPath("{$path}/{$file}");
        }
        \closedir($handle);
    }

    /**
     * Load File
     *
     * Load a specific file.
     *
     * @param string $path
     * @return void
     */
    private function loadFile(string $path): void
    {
        if (true === $this->shouldIgnore($path)) {
            return;
        }
        if (true === \in_array(\realpath($path), $this->included)) {
            if (true === $this->debug) {
                echo sprintf('[Preloader] Skipped: %s', $path).PHP_EOL;
            }
            return;
        }
        if (true === \in_array(\realpath($path), $this->loaded)) {
            if (true === $this->debug) {
                echo sprintf('[Preloader] Skipped: %s', $path).PHP_EOL;
            }
            return;
        }
        try {
            require $path;
        } catch (\Throwable $th) {
            if (true === $this->debug) {
                echo sprintf('[[Preloader] Failed to load: %s, Error Message: %s', $path, $th->getMessage()).PHP_EOL;
            }
            return;
        }
        $this->loaded = get_included_files();
        $this->included[] = $path;
        $this->count++;
    }

    /**
     * Should Ignore
     *
     * Should a given path be ignored or not?
     *
     * @param string $path
     * @return bool
     */
    private function shouldIgnore(?string $path): bool
    {
        if ($path === null) {
            return true;
        }
        $pathExtension = \pathinfo($path, PATHINFO_EXTENSION);
        if (false === \in_array($pathExtension, ['php']) || 'html.php' == substr($path, -8) || 'tpl.php' == substr($path, -7)) {
            return true;
        }
        foreach ($this->ignores as $ignore) {
            if (\strpos($path, $ignore) === 0) {
                return true;
            }
        }
        return false;
    }
}

$vendorDirectory = __DIR__.'/vendor/';
$vendorAutoloadFile = sprintf('%s/autoload.php', \rtrim($vendorDirectory, '/'));
if (true === file_exists($vendorAutoloadFile)) {
    require $vendorAutoloadFile;
}

$clpPreloader = new ClpPreloader();
$clpPreloader->setDebug(false);
$clpPreloader->paths(realpath(__DIR__ . '/src'));
$clpPreloader->paths(realpath(__DIR__ . '/vendor'));
$clpPreloader->ignore(realpath(__DIR__ . '/vendor/twig/twig'));
$clpPreloader->preload();
