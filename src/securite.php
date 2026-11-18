<?php
declare(strict_types=1);

// Briques de sécurité réutilisables : tâches différées, journal, chiffrement, TOTP, mots de passe compromis.

// --- Tâches exécutées après l'envoi de la réponse ------------------------------
// Les e-mails partent après la réponse : le temps de réponse est le même que le compte existe ou non.

function plus_tard(callable $tache): void
{
    if (empty($GLOBALS['taches_differees'])) {
        // Pour les pages affichées sans redirection : exécution en fin de requête.
        register_shutdown_function('executer_taches_differees');
    }
    $GLOBALS['taches_differees'][] = $tache;
}

function executer_taches_differees(bool $reponseVide = false): void
{
    $taches = $GLOBALS['taches_differees'] ?? [];
    if ($taches === []) {
        return;
    }
    $GLOBALS['taches_differees'] = [];
    session_write_close();
    ignore_user_abort(true);
    if ($reponseVide && !headers_sent()) {   // Redirection : la réponse, vide, est complète dès maintenant.
        header('Content-Length: 0');
        header('Connection: close');
    }
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    foreach ($taches as $tache) {
        try {
            $tache();
        } catch (Throwable $e) {
            error_log((string) $e);
        }
    }
}

// --- Journal de sécurité -------------------------------------------------------
// Une ligne JSON par événement dans les journaux du serveur. Pas d'e-mail, pas de mot de passe,
// et l'IP n'y figure que sous forme d'empreinte.

function journal_securite(string $evenement, int|string|null $uid = null): void
{
    error_log(json_encode([
        'securite' => $evenement,
        'uid'      => $uid === null ? null : (int) $uid,
        'ip'       => substr(hash_hmac('sha256', ip_client(), conf('secret')), 0, 16),
    ]));
}

// --- Chiffrement des secrets stockés en base -----------------------------------
// Une copie volée de la base ne suffit pas : la clé dérive du secret de l'application.

function cle_chiffrement(): string
{
    return hash_hkdf('sha256', conf('secret'), 32, 'quadrille-totp');
}

function chiffrer(string $clair): string
{
    $iv = random_bytes(12);
    $chiffre = openssl_encrypt($clair, 'aes-256-gcm', cle_chiffrement(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $chiffre);
}

function dechiffrer(string $paquet): string
{
    $brut = base64_decode($paquet, true);
    $clair = $brut === false ? false : openssl_decrypt(
        substr($brut, 28), 'aes-256-gcm', cle_chiffrement(), OPENSSL_RAW_DATA, substr($brut, 0, 12), substr($brut, 12, 16)
    );
    if ($clair === false) {
        throw new RuntimeException('Secret chiffré illisible.');
    }
    return $clair;
}

// --- Double authentification (TOTP, RFC 6238) ------------------------------------

function base32(string $binaire): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($binaire) as $octet) {
        $bits .= str_pad(decbin(ord($octet)), 8, '0', STR_PAD_LEFT);
    }
    $sortie = '';
    foreach (str_split($bits, 5) as $groupe) {
        $sortie .= $alphabet[bindec(str_pad($groupe, 5, '0'))];
    }
    return $sortie;
}

function totp(string $secret, int $pas): string
{
    $hmac = hash_hmac('sha1', pack('J', $pas), $secret, true);
    $decalage = ord($hmac[19]) & 0x0f;
    $valeur = unpack('N', substr($hmac, $decalage, 4))[1] & 0x7fffffff;
    return str_pad((string) ($valeur % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Renvoie le pas de temps accepté, ou null. Un pas déjà utilisé est refusé (anti-rejeu). */
function totp_verifier(string $secret, string $code, int $dernierPas): ?int
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/D', $code)) {
        return null;
    }
    $maintenant = intdiv(time(), 30);
    foreach ([-1, 0, 1] as $ecart) {     // Tolère 30 s de décalage d'horloge.
        $pas = $maintenant + $ecart;
        if ($pas > $dernierPas && hash_equals(totp($secret, $pas), $code)) {
            return $pas;
        }
    }
    return null;
}

function generer_codes_secours(): array
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // Sans caractères ambigus (0/O, 1/I/L).
    $codes = [];
    for ($i = 0; $i < 8; $i++) {
        $code = '';
        for ($j = 0; $j < 10; $j++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
    }
    return $codes;
}

function empreinte_code_secours(string $code): string
{
    $normalise = preg_replace('/[^A-Z0-9]/', '', strtoupper($code));
    return hash_hmac('sha256', $normalise, conf('secret'));
}

// --- Mots de passe compromis (Have I Been Pwned, k-anonymat) -------------------------
// Seuls les 5 premiers caractères de l'empreinte SHA-1 partent : le mot de passe ne quitte jamais le serveur.

function mot_de_passe_compromis(string $mdp): bool
{
    $empreinte = strtoupper(sha1($mdp));
    $contexte = stream_context_create(['http' => [
        'timeout' => 3,
        'header'  => "Add-Padding: true\r\nUser-Agent: Quadrille-SAE\r\n",
    ]]);
    $reponse = @file_get_contents('https://api.pwnedpasswords.com/range/' . substr($empreinte, 0, 5), false, $contexte);
    if ($reponse === false) {
        // Service injoignable : on n'empêche pas l'inscription, mais on le trace.
        journal_securite('hibp_injoignable');
        return false;
    }
    $suffixe = substr($empreinte, 5);
    foreach (explode("\n", $reponse) as $ligne) {
        [$candidat, $nombre] = array_pad(explode(':', trim($ligne)), 2, '0');
        if ((int) $nombre > 0 && hash_equals($suffixe, $candidat)) {
            return true;
        }
    }
    return false;
}
