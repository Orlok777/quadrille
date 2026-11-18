<?php
declare(strict_types=1);

// Diagnostic temporaire pour régler XFF_HOPS. Inactif sauf si la variable DIAG_IP vaut 1.
require __DIR__ . '/../src/bootstrap.php';

if (getenv('DIAG_IP') !== '1') {
    http_response_code(404);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
echo 'REMOTE_ADDR: ', $_SERVER['REMOTE_ADDR'] ?? '', "\n";
foreach ($_SERVER as $cle => $valeur) {
    if (preg_match('/^HTTP_.*(FORWARD|IP|REAL|CLIENT)/', $cle)) {
        echo $cle, ': ', $valeur, "\n";
    }
}
echo 'ip_client(): ', ip_client(), "\n";
