<?php
declare(strict_types=1);

namespace App\Support;

final class Logger
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    public function write(string $file, string $line): void
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . $file;
        @file_put_contents($path, $line, FILE_APPEND);
    }
}