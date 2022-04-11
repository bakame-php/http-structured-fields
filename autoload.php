<?php

declare(strict_types=1);

spl_autoload_register(function (string $className): void {

    $prefix = 'Bakame\Http\StructuredFields\\';
    if (!str_starts_with($className, $prefix)) {
        return;
    }

    $file = __DIR__.'/src/'.str_replace('\\', '/', substr($className, strlen($prefix))).'.php';
    if (is_readable($file)) {
        require $file;
    }
});
