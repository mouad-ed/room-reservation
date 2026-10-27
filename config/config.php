<?php
declare(strict_types=1);
// Copiez local.example.php vers local.php, puis adaptez les valeurs a votre installation XAMPP.
$local = __DIR__ . '/local.php';
if (!is_file($local)) {
    http_response_code(503);
    exit('Configuration manquante : copiez config/local.example.php vers config/local.php.');
}
$settings = require $local;
if (!is_array($settings)) {
    throw new RuntimeException('Configuration invalide.');
}
date_default_timezone_set($settings['timezone'] ?? 'Africa/Casablanca');
return $settings;
