<?php
declare(strict_types=1);

const MDP_MIN            = 12;
const MDP_MAX_OCTETS     = 72;      // Au-delà, bcrypt tronque silencieusement.
const SESSION_INACTIVITE = 1800;    // 30 min
const SESSION_MAX        = 28800;   // 8 h
const RESET_DUREE        = 1800;    // 30 min

const MSG_TROP_ESSAIS = 'Trop de tentatives. Merci de réessayer dans quelques minutes.';
const MSG_CSRF        = 'Votre session a expiré. Merci de renvoyer le formulaire.';

const MDP_COURANTS = [
    '123456789012', '1234567890123', 'azertyuiop12', 'azertyuiop123', 'azertyuiopqs',
    'qwertyuiop12', 'qwertyuiop123', 'motdepasse12', 'motdepasse123', 'motdepasse1234',
    'password1234', 'password12345', 'passwordpassword', 'bonjour12345', 'soleil123456',
    'quadrille123', 'quadrille1234', 'iloveyou1234', 'adminadmin12', 'administrateur',
];

// --- Utilitaires ------------------------------------------------------------

function e(string $texte): string
{
    return htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Lit un champ POST. Un tableau envoyé par un client malveillant devient une chaîne vide. */
function champ(string $nom): string
{
    $valeur = $_POST[$nom] ?? '';
    return is_string($valeur) ? $valeur : '';
}

function rediriger(string $page): never
{
    header('Location: ' . $page, true, 303);
    exit;
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $courant = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $courant;
}

function algo_hachage(): string
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

// --- CSRF -------------------------------------------------------------------

function jeton_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_valide(): bool
{
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], champ('csrf'));
}

// --- Limitation de débit ----------------------------------------------------
// Une règle : [nom du compteur, nombre maximal, fenêtre en secondes].

function compteur(string $nom): string
{
    return hash_hmac('sha256', $nom, conf('secret'));
}

function limite_atteinte(array $regles): bool
{
    $q = db()->prepare('SELECT COUNT(*) FROM attempts WHERE bucket = ? AND created_at > ?');
    foreach ($regles as [$nom, $max, $fenetre]) {
        $q->execute([compteur($nom), time() - $fenetre]);
        if ((int) $q->fetchColumn() >= $max) {
            return true;
        }
    }
    return false;
}

function compter_essai(array $regles): void
{
    $q = db()->prepare('INSERT INTO attempts (bucket, created_at) VALUES (?, ?)');
    foreach ($regles as [$nom]) {
        $q->execute([compteur($nom), time()]);
    }
    if (random_int(1, 50) === 1) {
        db()->prepare('DELETE FROM attempts WHERE created_at < ?')->execute([time() - 86400]);
        db()->prepare('DELETE FROM password_resets WHERE expires_at < ?')->execute([time()]);
    }
}

function ip_client(): string
{
    // Volontairement pas X-Forwarded-For : cet en-tête est falsifiable par le client.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue');
}

// --- Validation -------------------------------------------------------------

function erreur_mot_de_passe(string $mdp, string $pseudo): ?string
{
    if (mb_strlen($mdp) < MDP_MIN) {
        return 'Le mot de passe doit contenir au moins ' . MDP_MIN . ' caractères.';
    }
    if (strlen($mdp) > MDP_MAX_OCTETS) {
        return 'Le mot de passe est trop long (' . MDP_MAX_OCTETS . ' octets maximum).';
    }
    $minuscule = mb_strtolower($mdp);
    if (in_array($minuscule, MDP_COURANTS, true) || count(array_unique(mb_str_split($mdp))) < 5) {
        return 'Ce mot de passe est trop facile à deviner.';
    }
    if ($pseudo !== '' && str_contains($minuscule, mb_strtolower($pseudo))) {
        return 'Le mot de passe ne doit pas contenir votre pseudo.';
    }
    return null;
}

// --- Session utilisateur ----------------------------------------------------

function ouvrir_session(array $utilisateur): void
{
    session_regenerate_id(true);    // Contre la fixation de session.
    unset($_SESSION['csrf']);
    $_SESSION['uid']       = (int) $utilisateur['id'];
    $_SESSION['epoch']     = (int) $utilisateur['session_epoch'];
    $_SESSION['ouverte']   = time();
    $_SESSION['activite']  = time();
}

function fermer_session(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function utilisateur_courant(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $maintenant = time();
    if ($maintenant - $_SESSION['activite'] > SESSION_INACTIVITE
        || $maintenant - $_SESSION['ouverte'] > SESSION_MAX) {
        fermer_session();
        return null;
    }
    $q = db()->prepare('SELECT id, pseudo, email, session_epoch FROM users WHERE id = ?');
    $q->execute([$_SESSION['uid']]);
    $utilisateur = $q->fetch();
    if (!$utilisateur || (int) $utilisateur['session_epoch'] !== $_SESSION['epoch']) {
        fermer_session();
        return null;
    }
    $_SESSION['activite'] = $maintenant;
    return $utilisateur;
}

// --- Actions (chaque fonction renvoie un message d'erreur, ou null si succès) ----

function creer_compte(string $pseudo, string $email, string $mdp, string $confirmation): ?string
{
    $regles = [['inscription:ip:' . ip_client(), 10, 3600]];
    if (limite_atteinte($regles)) {
        http_response_code(429);
        return MSG_TROP_ESSAIS;
    }
    if (!preg_match('/^[A-Za-z0-9_-]{3,20}$/D', $pseudo)) {
        return 'Le pseudo doit faire 3 à 20 caractères : lettres, chiffres, tiret ou tiret bas.';
    }
    $email = mb_strtolower($email);
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "L'adresse e-mail n'est pas valide.";
    }
    if ($mdp !== $confirmation) {
        return 'Les deux mots de passe ne sont pas identiques.';
    }
    $erreur = erreur_mot_de_passe($mdp, $pseudo);
    if ($erreur !== null) {
        return $erreur;
    }

    compter_essai($regles);
    $q = db()->prepare('INSERT INTO users (pseudo, email, password_hash, created_at) VALUES (?, ?, ?, ?)');
    try {
        $q->execute([$pseudo, $email, password_hash($mdp, algo_hachage()), time()]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            // Message commun au pseudo et à l'e-mail, pour ne pas confirmer quelle adresse est inscrite.
            return "Ce pseudo ou cette adresse n'est pas disponible.";
        }
        throw $e;
    }
    ouvrir_session([
        'id'            => (int) db()->lastInsertId(),
        'session_epoch' => 0,
    ]);
    return null;
}

function connecter(string $identifiant, string $mdp): ?string
{
    $identifiant = mb_strtolower(trim($identifiant));
    $ip = ip_client();
    $regles = [
        ["connexion:paire:$ip:$identifiant", 5, 900],   // Un attaquant, un compte.
        ["connexion:ip:$ip", 30, 900],                  // Un attaquant, beaucoup de comptes.
        ["connexion:compte:$identifiant", 50, 3600],    // Attaque distribuée sur un compte.
    ];
    if (limite_atteinte($regles)) {
        http_response_code(429);
        return MSG_TROP_ESSAIS;
    }

    $q = db()->prepare('SELECT * FROM users WHERE pseudo = :i OR email = :i');
    $q->execute(['i' => $identifiant]);
    $utilisateur = $q->fetch();

    $borne = substr($mdp, 0, MDP_MAX_OCTETS);
    if ($utilisateur) {
        $valide = password_verify($borne, $utilisateur['password_hash']) && strlen($mdp) <= MDP_MAX_OCTETS;
    } else {
        // Même coût de calcul qu'un compte existant : le temps de réponse ne révèle rien.
        password_hash($borne, algo_hachage());
        $valide = false;
    }

    if (!$valide) {
        compter_essai($regles);
        return 'Identifiant ou mot de passe incorrect.';
    }

    if (password_needs_rehash($utilisateur['password_hash'], algo_hachage())) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($mdp, algo_hachage()), $utilisateur['id']]);
    }
    db()->prepare('DELETE FROM attempts WHERE bucket = ?')->execute([compteur($regles[0][0])]);
    ouvrir_session($utilisateur);
    return null;
}

/** Ne renvoie rien : la réponse doit être identique que le compte existe ou non. */
function demander_reinitialisation(string $email): void
{
    $email = mb_strtolower(trim($email));
    $regles = [
        ['oubli:ip:' . ip_client(), 10, 3600],
        ["oubli:email:$email", 3, 3600],    // Évite d'inonder la boîte d'une victime.
    ];
    if (limite_atteinte($regles)) {
        return;
    }
    compter_essai($regles);

    $q = db()->prepare('SELECT id FROM users WHERE email = ?');
    $q->execute([$email]);
    $id = $q->fetchColumn();
    if ($id === false) {
        return;
    }

    $jeton = bin2hex(random_bytes(32));
    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$id]);
    db()->prepare('INSERT INTO password_resets (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
        ->execute([hash('sha256', $jeton), $id, time() + RESET_DUREE]);

    $lien = conf('app_url') . '/reinitialiser.php?token=' . $jeton;
    envoyer_mail(
        $email,
        conf('app_name') . ' : réinitialisation de votre mot de passe',
        "Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 30 minutes, utilisable une seule fois) :\n\n$lien\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.\n"
    );
}

function reinitialisation_valide(string $jeton): ?array
{
    if (strlen($jeton) !== 64 || !ctype_xdigit($jeton)) {
        return null;
    }
    $q = db()->prepare(
        'SELECT u.id, u.pseudo, u.email FROM password_resets r
         JOIN users u ON u.id = r.user_id
         WHERE r.token_hash = ? AND r.expires_at > ?'
    );
    $q->execute([hash('sha256', $jeton), time()]);
    return $q->fetch() ?: null;
}

function appliquer_reinitialisation(string $jeton, array $utilisateur, string $mdp, string $confirmation): ?string
{
    if ($mdp !== $confirmation) {
        return 'Les deux mots de passe ne sont pas identiques.';
    }
    $erreur = erreur_mot_de_passe($mdp, $utilisateur['pseudo']);
    if ($erreur !== null) {
        return $erreur;
    }

    $hash = password_hash($mdp, algo_hachage());
    $pdo = db();
    $pdo->beginTransaction();
    $suppression = $pdo->prepare('DELETE FROM password_resets WHERE token_hash = ? AND expires_at > ?');
    $suppression->execute([hash('sha256', $jeton), time()]);
    if ($suppression->rowCount() !== 1) {   // Jeton déjà consommé par une requête concurrente.
        $pdo->rollBack();
        return 'Ce lien n\'est plus valable. Merci de refaire une demande.';
    }
    $pdo->prepare('UPDATE users SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?')
        ->execute([$hash, $utilisateur['id']]);
    $pdo->commit();

    envoyer_mail(
        $utilisateur['email'],
        conf('app_name') . ' : votre mot de passe a été modifié',
        "Bonjour,\n\nLe mot de passe de votre compte vient d'être modifié et toutes vos sessions ont été fermées.\n\nSi vous n'êtes pas à l'origine de ce changement, refaites immédiatement une demande de réinitialisation.\n"
    );
    return null;
}
