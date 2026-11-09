<?php
declare(strict_types=1);

// Chargé en tête de chaque page : configuration, en-têtes de sécurité, session, base.

const RACINE = __DIR__ . '/..';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Une erreur est survenue. Merci de réessayer plus tard.";
});

if (!is_file(RACINE . '/config.php')) {
    throw new RuntimeException('config.php manquant : copier config.example.php.');
}

function conf(string $cle): string
{
    static $config = null;
    $config ??= require RACINE . '/config.php';
    return (string) $config[$cle];
}

if (strlen(conf('secret')) < 32) {
    throw new RuntimeException('Le secret de config.php doit être généré (32 caractères minimum).');
}

$enHttps = str_starts_with(conf('app_url'), 'https://');

header_remove('X-Powered-By');
header('Content-Type: text/html; charset=utf-8');
// Aucune ressource externe, aucun JavaScript : la politique peut être très stricte.
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// Évite la fuite du jeton de réinitialisation via l'en-tête Referer.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
if ($enHttps) {
    header('Strict-Transport-Security: max-age=31536000');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('quadrille_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $enHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . conf('db_path'), null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() === 0) {
        $pdo->exec((string) file_get_contents(RACINE . '/sql/schema.sql'));
        $pdo->exec('PRAGMA user_version = 1');
    }
    return $pdo;
}

require __DIR__ . '/auth.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/vues.php';
