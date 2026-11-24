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

/** Lit config.php en local ; sur l'hébergeur, tout vient des variables d'environnement. */
function conf(string $cle): string
{
    static $config = null;
    $config ??= is_file(RACINE . '/config.php') ? require RACINE . '/config.php' : [
        'app_name'      => 'Quadrille',
        'app_url'       => getenv('APP_URL') ?: getenv('RENDER_EXTERNAL_URL'),
        'database_url'  => getenv('DATABASE_URL'),
        'mail_mode'     => getenv('MAIL_MODE'),
        'mail_from'     => getenv('MAIL_FROM'),
        'brevo_api_key' => getenv('BREVO_API_KEY'),
        'secret'        => getenv('SECRET'),
        'xff_hops'      => getenv('XFF_HOPS'),
        'contact_email' => getenv('CONTACT_EMAIL'),
    ];
    return (string) ($config[$cle] ?? '');
}

if (strlen(conf('secret')) < 32) {
    throw new RuntimeException('Le secret doit être généré (32 caractères minimum).');
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
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    // PostgreSQL partout (exigé par le module L2) : en local comme en ligne.
    // Format : postgresql://utilisateur:motdepasse@hote:port/base
    $url = parse_url(conf('database_url'));
    if (!$url || !isset($url['host'], $url['path'], $url['user'])) {
        throw new RuntimeException('database_url manquante ou invalide (postgresql://utilisateur:motdepasse@hote:port/base).');
    }
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $url['host'], $url['port'] ?? 5432, ltrim($url['path'], '/'));
    $pdo = new PDO($dsn, rawurldecode($url['user']), rawurldecode($url['pass'] ?? ''), $options);
    migrer($pdo);
    return $pdo;
}

require __DIR__ . '/schema.php';
require __DIR__ . '/securite.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/compte.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/parties.php';
require __DIR__ . '/vues.php';
