<?php

declare(strict_types=1);

// Servidor embutido do PHP (desenvolvimento): entrega arquivos estáticos direto.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

App\Core\App::run();
