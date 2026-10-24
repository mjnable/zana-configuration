<?php

declare(strict_types=1);

namespace Zana\Configuration\Tests;

/**
 * A throwaway directory for file-based tests.
 */
final class TempDirectory
{
    public readonly string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zana-config-' . bin2hex(random_bytes(6));
        mkdir($this->path, 0777, true);
    }

    public function file(string $name, string $contents): string
    {
        $path = $this->path . DIRECTORY_SEPARATOR . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);

        return $path;
    }

    public function remove(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->path);
    }
}
