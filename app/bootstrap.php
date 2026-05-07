<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
   $prefixes = [
    'App\\'       => __DIR__ . '/',
    'Src\\'       => __DIR__ . '/../src/',
    'PhpOffice\\' => __DIR__ . '/../lib/phpspreadsheet/src/PhpOffice/',
    'Composer\\'  => __DIR__ . '/../lib/composer-pcre/src/Composer/',
    'Psr\\'       => __DIR__ . '/../lib/psr-simple-cache/src/Psr/',
    'ZipStream\\' => __DIR__ . '/../lib/zipstream/src/ZipStream/',
];


    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});