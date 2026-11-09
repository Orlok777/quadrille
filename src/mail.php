<?php
declare(strict_types=1);

function envoyer_mail(string $destinataire, string $sujet, string $corps): void
{
    match (conf('mail_mode')) {
        'log'   => envoyer_mail_journal($destinataire, $sujet, $corps),
        'brevo' => envoyer_mail_brevo($destinataire, $sujet, $corps),
        default => envoyer_mail_php($destinataire, $sujet, $corps),
    };
}

/** Développement uniquement : le message, lien compris, est écrit dans un fichier. */
function envoyer_mail_journal(string $destinataire, string $sujet, string $corps): void
{
    $ligne = sprintf("[%s] À: %s | %s\n%s\n\n", date('c'), $destinataire, $sujet, $corps);
    file_put_contents(RACINE . '/data/mails.log', $ligne, FILE_APPEND | LOCK_EX);
}

/** API HTTPS de Brevo, pour les hébergeurs qui bloquent mail() et les ports SMTP. */
function envoyer_mail_brevo(string $destinataire, string $sujet, string $corps): void
{
    $contexte = stream_context_create(['http' => [
        'method'        => 'POST',
        'timeout'       => 10,
        'ignore_errors' => true,
        'header'        => implode("\r\n", [
            'api-key: ' . conf('brevo_api_key'),
            'Content-Type: application/json',
            'Accept: application/json',
        ]),
        'content'       => json_encode([
            'sender'      => ['name' => conf('app_name'), 'email' => conf('mail_from')],
            'to'          => [['email' => $destinataire]],
            'subject'     => $sujet,
            'textContent' => $corps,
        ], JSON_THROW_ON_ERROR),
    ]]);

    $reponse = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $contexte);
    $statut = $http_response_header[0] ?? 'aucune réponse';
    // L'échec n'est jamais montré au visiteur : cela révélerait si le compte existe.
    if ($reponse === false || !preg_match('/ 2\d\d( |$)/', $statut)) {
        error_log('Échec envoi e-mail Brevo : ' . $statut . ' ' . (string) $reponse);
    }
}

function envoyer_mail_php(string $destinataire, string $sujet, string $corps): void
{
    $entetes = [
        'From'                      => conf('app_name') . ' <' . conf('mail_from') . '>',
        'Content-Type'              => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    if (!mail($destinataire, mb_encode_mimeheader($sujet, 'UTF-8', 'B'), $corps, $entetes)) {
        error_log('Échec envoi e-mail : ' . $sujet);
    }
}
