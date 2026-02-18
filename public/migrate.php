<?php
// Segurança: proteja essa rota com IP/Token se necessário.
$cmd = $_GET['cmd'] ?? 'status';
$version = $_GET['v'] ?? null;
passthru(PHP_BINARY.' '.__DIR__.'/../scripts/migrate.php '.escapeshellarg($cmd).($version?' '.escapeshellarg($version):''));