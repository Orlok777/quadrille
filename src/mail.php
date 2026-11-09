<?php
declare(strict_types=1);

function envoyer_mail(string $destinataire, string $sujet, string $corps): void
{
    if (conf('mail_mode') === 'log') {
        $ligne = sprintf("[%s] À: %s | %s\n%s\n\n", date('c'), $destinataire, $sujet, $corps);
        file_put_contents(RACINE . '/data/mails.log', $ligne, FILE_APPEND | LOCK_EX);
        return;
    }

    $entetes = [
        'From'                      => conf('app_name') . ' <' . conf('mail_from') . '>',
        'Content-Type'              => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    $sujet = mb_encode_mimeheader($sujet, 'UTF-8', 'B');
    // L'échec n'est jamais montré au visiteur : cela révélerait si le compte existe.
    if (!mail($destinataire, $sujet, $corps, $entetes)) {
        error_log('Échec envoi e-mail : ' . $sujet);
    }
}
