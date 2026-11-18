<?php
declare(strict_types=1);

// Actions de la page « Mon compte ». Chacune renvoie un message d'erreur, ou null si elle a réussi.
// Les actions sensibles redemandent le mot de passe : une session volée ne suffit pas.

function reauthentifier(array $u, string $mdp): ?string
{
    $regles = [['reauth:' . $u['id'], 5, 900]];
    if (limite_atteinte($regles)) {
        http_response_code(429);
        return MSG_TROP_ESSAIS;
    }
    if (strlen($mdp) > MDP_MAX_OCTETS || !password_verify($mdp, $u['password_hash'])) {
        compter_essai($regles);
        journal_securite('reauthentification_echouee', $u['id']);
        return 'Mot de passe actuel incorrect.';
    }
    return null;
}

/** Invalide toutes les autres sessions et garde la session courante ouverte. */
function changer_epoch(array $u): void
{
    db()->prepare('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?')->execute([$u['id']]);
    $_SESSION['epoch'] = (int) $u['session_epoch'] + 1;
    session_regenerate_id(true);
}

function notifier(array $u, string $sujet, string $texte): void
{
    plus_tard(fn () => envoyer_mail(
        $u['email'],
        conf('app_name') . ' : ' . $sujet,
        "Bonjour {$u['pseudo']},\n\n$texte\n\nSi vous n'êtes pas à l'origine de cette action, changez immédiatement votre mot de passe via « Mot de passe oublié ».\n"
    ));
}

function changer_mot_de_passe(array $u, string $actuel, string $nouveau, string $confirmation): ?string
{
    $erreur = reauthentifier($u, $actuel) ?? erreur_nouveau_mot_de_passe($nouveau, $confirmation, $u['pseudo']);
    if ($erreur !== null) {
        return $erreur;
    }
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($nouveau, algo_hachage()), $u['id']]);
    changer_epoch($u);
    journal_securite('mot_de_passe_change', $u['id']);
    notifier($u, 'mot de passe modifié', 'Le mot de passe de votre compte vient d\'être modifié. Vos autres sessions ont été fermées.');
    return null;
}

function deconnecter_autres_sessions(array $u): void
{
    changer_epoch($u);
    journal_securite('autres_sessions_fermees', $u['id']);
}

function preparer_double_auth(array $u, string $mdp): ?string
{
    $erreur = reauthentifier($u, $mdp);
    if ($erreur === null) {
        $_SESSION['mfa_nouveau'] = base64_encode(random_bytes(20));
    }
    return $erreur;
}

function activer_double_auth(array $u, string $code): ?string
{
    $secret = base64_decode($_SESSION['mfa_nouveau'] ?? '', true);
    if (!$secret) {
        return 'Merci de recommencer l\'activation.';
    }
    $pas = totp_verifier($secret, $code, 0);
    if ($pas === null) {
        return 'Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.';
    }
    $codes = generer_codes_secours();
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET totp_secret = ?, totp_last_step = ? WHERE id = ?')->execute([chiffrer($secret), $pas, $u['id']]);
    $pdo->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$u['id']]);
    $insertion = $pdo->prepare('INSERT INTO recovery_codes (user_id, code_hash) VALUES (?, ?)');
    foreach ($codes as $c) {
        $insertion->execute([$u['id'], empreinte_code_secours($c)]);
    }
    $pdo->commit();

    unset($_SESSION['mfa_nouveau']);
    $_SESSION['codes_secours'] = $codes;    // Affichés une seule fois.
    changer_epoch($u);
    journal_securite('double_auth_activee', $u['id']);
    notifier($u, 'double authentification activée', 'La double authentification vient d\'être activée sur votre compte.');
    return null;
}

function desactiver_double_auth(array $u, string $mdp): ?string
{
    $erreur = reauthentifier($u, $mdp);
    if ($erreur !== null) {
        return $erreur;
    }
    db()->prepare('UPDATE users SET totp_secret = NULL, totp_last_step = NULL WHERE id = ?')->execute([$u['id']]);
    db()->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$u['id']]);
    journal_securite('double_auth_desactivee', $u['id']);
    notifier($u, 'double authentification désactivée', 'La double authentification vient d\'être désactivée sur votre compte.');
    return null;
}

/** Droit d'accès et de portabilité : toutes les données du compte, sauf les secrets. */
function exporter_donnees(array $u, string $mdp): ?string
{
    $erreur = reauthentifier($u, $mdp);
    if ($erreur !== null) {
        return $erreur;
    }
    $q = db()->prepare('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ?');
    $q->execute([$u['id']]);
    $date = fn ($t) => $t === null ? null : date(DATE_ATOM, (int) $t);
    $donnees = [
        'pseudo'                 => $u['pseudo'],
        'email'                  => $u['email'],
        'compte_cree_le'         => $date($u['created_at']),
        'adresse_confirmee_le'   => $date($u['verified_at']),
        'derniere_connexion_le'  => $date($u['last_login_at']),
        'double_authentification' => $u['totp_secret'] !== null,
        'codes_de_secours_restants' => (int) $q->fetchColumn(),
        'parties'                => [],
        'non_inclus'             => 'Mot de passe et clé de double authentification : stockés uniquement sous forme hachée ou chiffrée.',
    ];
    journal_securite('donnees_exportees', $u['id']);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="quadrille-mes-donnees.json"');
    echo json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Droit à l'effacement : le compte et tout ce qui en dépend (suppression en cascade). */
function supprimer_compte(array $u, string $mdp, bool $confirme): ?string
{
    if (!$confirme) {
        return 'Cochez la case pour confirmer la suppression.';
    }
    $erreur = reauthentifier($u, $mdp);
    if ($erreur !== null) {
        return $erreur;
    }
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$u['id']]);
    journal_securite('compte_supprime', $u['id']);
    plus_tard(fn () => envoyer_mail(
        $u['email'],
        conf('app_name') . ' : compte supprimé',
        "Bonjour,\n\nVotre compte {$u['pseudo']} et toutes les données associées ont été supprimés.\n"
    ));
    return null;
}
