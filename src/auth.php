<?php
declare(strict_types=1);

const MDP_MIN            = 12;
const MDP_MAX_OCTETS     = 72;        // Au-delà, bcrypt tronque silencieusement.
const SESSION_INACTIVITE = 1800;      // 30 min
const SESSION_MAX        = 28800;     // 8 h
const RESET_DUREE        = 1800;      // 30 min
const VERIF_DUREE        = 86400;     // 24 h pour confirmer son adresse
const MFA_DUREE          = 300;       // 5 min pour saisir le code de double authentification
const INACTIVITE_MAX     = 94608000;  // 3 ans sans connexion : compte supprimé (RGPD)

const MSG_TROP_ESSAIS = 'Trop de tentatives. Merci de réessayer dans quelques minutes.';
const MSG_CSRF        = 'Votre session a expiré. Merci de renvoyer le formulaire.';
const MSG_INSCRIPTION = 'Si cette adresse peut être utilisée, un lien de confirmation vient d\'y être envoyé (valable 24 h). Pensez à regarder vos courriers indésirables.';

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
    executer_taches_differees(true);
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

function jeton_aleatoire(): string
{
    return bin2hex(random_bytes(32));
}

function jeton_bien_forme(string $jeton): bool
{
    return strlen($jeton) === 64 && ctype_xdigit($jeton);
}

// --- CSRF -------------------------------------------------------------------

function jeton_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_valide(): bool
{
    $valide = isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], champ('csrf'));
    if (!$valide) {
        journal_securite('csrf_refuse');
    }
    return $valide;
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
            journal_securite('limite_atteinte:' . strtok($nom, ':'));
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
        entretien();
    }
}

function ip_client(): string
{
    // Derrière un proxy, REMOTE_ADDR est l'adresse du proxy. Le client peut écrire ce qu'il veut au
    // début de X-Forwarded-For : on compte donc depuis la fin, le nombre de proxys de confiance.
    $sauts = (int) conf('xff_hops');
    if ($sauts > 0 && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chaine = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $ip = $chaine[count($chaine) - $sauts] ?? '';
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue');
}

/** Purge des données dont on n'a plus besoin (minimisation RGPD). */
function entretien(): void
{
    $t = time();
    $pdo = db();
    $pdo->prepare('DELETE FROM attempts WHERE created_at < ?')->execute([$t - 86400]);
    $pdo->prepare('DELETE FROM password_resets WHERE expires_at < ?')->execute([$t]);
    $pdo->prepare('DELETE FROM email_verifications WHERE expires_at < ?')->execute([$t]);
    $pdo->prepare('DELETE FROM users WHERE verified_at IS NULL AND created_at < ?')->execute([$t - VERIF_DUREE]);
    $pdo->prepare('DELETE FROM users WHERE last_login_at < ?')->execute([$t - INACTIVITE_MAX]);
}

// --- Mots de passe ----------------------------------------------------------

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

/** Contrôle complet d'un nouveau mot de passe, fuites de données comprises. */
function erreur_nouveau_mot_de_passe(string $mdp, string $confirmation, string $pseudo): ?string
{
    if ($mdp !== $confirmation) {
        return 'Les deux mots de passe ne sont pas identiques.';
    }
    $erreur = erreur_mot_de_passe($mdp, $pseudo);
    if ($erreur === null && mot_de_passe_compromis($mdp)) {
        return 'Ce mot de passe figure dans des fuites de données connues. Choisissez-en un autre.';
    }
    return $erreur;
}

// --- Session utilisateur ----------------------------------------------------

function ouvrir_session(array $utilisateur): void
{
    session_regenerate_id(true);    // Contre la fixation de session.
    unset($_SESSION['csrf'], $_SESSION['mfa_attente']);
    $_SESSION['uid']      = (int) $utilisateur['id'];
    $_SESSION['epoch']    = (int) $utilisateur['session_epoch'];
    $_SESSION['ouverte']  = time();
    $_SESSION['activite'] = time();
    db()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([time(), $utilisateur['id']]);
    journal_securite('connexion_reussie', $utilisateur['id']);
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
    $q = db()->prepare(
        'SELECT id, pseudo, email, password_hash, session_epoch, created_at, verified_at, last_login_at, totp_secret
         FROM users WHERE id = ?'
    );
    $q->execute([$_SESSION['uid']]);
    $utilisateur = $q->fetch();
    if (!$utilisateur || (int) $utilisateur['session_epoch'] !== $_SESSION['epoch']) {
        fermer_session();
        return null;
    }
    $_SESSION['activite'] = $maintenant;
    return $utilisateur;
}

// --- Inscription et confirmation de l'adresse -------------------------------

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
    $erreur = erreur_nouveau_mot_de_passe($mdp, $confirmation, $pseudo);
    if ($erreur !== null) {
        return $erreur;
    }
    compter_essai($regles);

    // Un compte jamais confirmé ne bloque pas une adresse ou un pseudo plus de 24 h.
    db()->prepare('DELETE FROM users WHERE verified_at IS NULL AND created_at < ?')->execute([time() - VERIF_DUREE]);

    // Les pseudos sont publics : dire qu'il est pris ne révèle rien.
    $q = db()->prepare('SELECT 1 FROM users WHERE lower(pseudo) = ?');
    $q->execute([strtolower($pseudo)]);
    if ($q->fetchColumn()) {
        return 'Ce pseudo est déjà pris.';
    }

    $q = db()->prepare('SELECT id FROM users WHERE email = ?');
    $q->execute([$email]);
    $existant = $q->fetchColumn();

    if ($existant === false) {
        try {
            db()->prepare('INSERT INTO users (pseudo, email, password_hash, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$pseudo, $email, password_hash($mdp, algo_hachage()), time()]);
        } catch (PDOException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) {   // Créé entre-temps par une requête concurrente.
                return 'Ce pseudo est déjà pris.';
            }
            throw $e;
        }
        $id = (int) db()->lastInsertId();
        journal_securite('inscription', $id);
    }

    // La réponse est identique dans les deux cas, et l'e-mail part après : l'adresse n'est pas révélée.
    $limiteEmail = [["inscription:email:$email", 3, 3600]];
    if (!limite_atteinte($limiteEmail)) {
        compter_essai($limiteEmail);
        plus_tard($existant === false
            ? fn () => envoyer_lien_verification($id, $email)
            : fn () => envoyer_mail(
                $email,
                conf('app_name') . ' : vous avez déjà un compte',
                "Bonjour,\n\nQuelqu'un a voulu créer un compte avec votre adresse, mais elle est déjà inscrite.\n\nSi c'est vous, connectez-vous ou utilisez « Mot de passe oublié » :\n" . conf('app_url') . "/index.php?vue=oubli\n\nSinon, ignorez ce message : votre compte n'est pas modifié.\n"
            ));
    }
    return null;
}

function envoyer_lien_verification(int $id, string $email): void
{
    $jeton = jeton_aleatoire();
    db()->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$id]);
    db()->prepare('INSERT INTO email_verifications (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
        ->execute([hash('sha256', $jeton), $id, time() + VERIF_DUREE]);
    envoyer_mail(
        $email,
        conf('app_name') . ' : confirmez votre adresse',
        "Bienvenue !\n\nPour activer votre compte, confirmez votre adresse avec ce lien (valable 24 heures) :\n\n" . conf('app_url') . "/verifier.php?token=$jeton\n\nSans confirmation, le compte et ses données sont supprimés automatiquement après 24 heures.\nSi vous n'avez rien demandé, ignorez ce message.\n"
    );
}

function verification_valide(string $jeton): bool
{
    if (!jeton_bien_forme($jeton)) {
        return false;
    }
    $q = db()->prepare('SELECT 1 FROM email_verifications WHERE token_hash = ? AND expires_at > ?');
    $q->execute([hash('sha256', $jeton), time()]);
    return (bool) $q->fetchColumn();
}

function confirmer_email(string $jeton): bool
{
    $pdo = db();
    $q = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token_hash = ? AND expires_at > ?');
    $q->execute([hash('sha256', $jeton), time()]);
    $uid = $q->fetchColumn();
    if ($uid === false) {
        return false;
    }
    $pdo->beginTransaction();
    $suppression = $pdo->prepare('DELETE FROM email_verifications WHERE token_hash = ?');
    $suppression->execute([hash('sha256', $jeton)]);
    if ($suppression->rowCount() !== 1) {
        $pdo->rollBack();
        return false;
    }
    $pdo->prepare('UPDATE users SET verified_at = ? WHERE id = ? AND verified_at IS NULL')->execute([time(), $uid]);
    $pdo->commit();
    journal_securite('email_confirme', $uid);
    return true;
}

// --- Connexion --------------------------------------------------------------

/**
 * Renvoie null si le mot de passe est bon. La session est alors ouverte, ou bien une étape de
 * double authentification est mise en attente ($_SESSION['mfa_attente']).
 */
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

    $q = db()->prepare('SELECT * FROM users WHERE lower(pseudo) = ? OR email = ?');
    $q->execute([$identifiant, $identifiant]);
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
        journal_securite('connexion_echouee', $utilisateur['id'] ?? null);
        return 'Identifiant ou mot de passe incorrect.';
    }

    if (password_needs_rehash($utilisateur['password_hash'], algo_hachage())) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($mdp, algo_hachage()), $utilisateur['id']]);
    }
    db()->prepare('DELETE FROM attempts WHERE bucket = ?')->execute([compteur($regles[0][0])]);

    // Le mot de passe est bon : on peut dire que l'adresse n'est pas confirmée.
    if ($utilisateur['verified_at'] === null) {
        $limite = [['verification:' . $utilisateur['id'], 3, 3600]];
        if (!limite_atteinte($limite)) {
            compter_essai($limite);
            plus_tard(fn () => envoyer_lien_verification((int) $utilisateur['id'], $utilisateur['email']));
        }
        return "Votre adresse n'est pas encore confirmée. Un nouveau lien de confirmation vient de vous être envoyé.";
    }

    if ($utilisateur['totp_secret'] !== null) {
        session_regenerate_id(true);
        $_SESSION['mfa_attente'] = ['uid' => (int) $utilisateur['id'], 'expire' => time() + MFA_DUREE];
        return null;
    }

    ouvrir_session($utilisateur);
    return null;
}

function valider_double_auth(string $code): ?string
{
    $attente = $_SESSION['mfa_attente'] ?? null;
    if (!$attente || $attente['expire'] < time()) {
        unset($_SESSION['mfa_attente']);
        return 'Le délai est dépassé. Merci de vous reconnecter.';
    }
    $uid = $attente['uid'];
    $regles = [["mfa:$uid", 5, 900]];
    if (limite_atteinte($regles)) {
        http_response_code(429);
        return MSG_TROP_ESSAIS;
    }

    $q = db()->prepare('SELECT * FROM users WHERE id = ?');
    $q->execute([$uid]);
    $utilisateur = $q->fetch();
    if (!$utilisateur || $utilisateur['totp_secret'] === null) {
        unset($_SESSION['mfa_attente']);
        return 'Le délai est dépassé. Merci de vous reconnecter.';
    }

    $valide = false;
    $pas = totp_verifier(dechiffrer($utilisateur['totp_secret']), $code, (int) $utilisateur['totp_last_step']);
    if ($pas !== null) {
        // La condition sur le dernier pas empêche deux requêtes simultanées d'utiliser le même code.
        $maj = db()->prepare('UPDATE users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)');
        $maj->execute([$pas, $uid, $pas]);
        $valide = $maj->rowCount() === 1;
    } elseif (strlen(preg_replace('/[^A-Za-z0-9]/', '', $code)) === 10) {
        $suppression = db()->prepare('DELETE FROM recovery_codes WHERE user_id = ? AND code_hash = ?');
        $suppression->execute([$uid, empreinte_code_secours($code)]);
        $valide = $suppression->rowCount() === 1;
        if ($valide) {
            journal_securite('code_secours_utilise', $uid);
        }
    }

    if (!$valide) {
        compter_essai($regles);
        journal_securite('double_auth_echouee', $uid);
        return 'Code incorrect.';
    }
    ouvrir_session($utilisateur);
    return null;
}

// --- Mot de passe oublié ------------------------------------------------------

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
    plus_tard(function () use ($email): void {
        $q = db()->prepare('SELECT id FROM users WHERE email = ?');
        $q->execute([$email]);
        $id = $q->fetchColumn();
        if ($id === false) {
            return;
        }
        $jeton = jeton_aleatoire();
        db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$id]);
        db()->prepare('INSERT INTO password_resets (token_hash, user_id, expires_at) VALUES (?, ?, ?)')
            ->execute([hash('sha256', $jeton), $id, time() + RESET_DUREE]);
        journal_securite('reinitialisation_demandee', $id);
        envoyer_mail(
            $email,
            conf('app_name') . ' : réinitialisation de votre mot de passe',
            "Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 30 minutes, utilisable une seule fois) :\n\n" . conf('app_url') . "/reinitialiser.php?token=$jeton\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.\n"
        );
    });
}

function reinitialisation_valide(string $jeton): ?array
{
    if (!jeton_bien_forme($jeton)) {
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
    $erreur = erreur_nouveau_mot_de_passe($mdp, $confirmation, $utilisateur['pseudo']);
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
    // Recevoir le lien prouve la possession de l'adresse : elle est donc confirmée.
    $pdo->prepare(
        'UPDATE users SET password_hash = ?, session_epoch = session_epoch + 1, verified_at = COALESCE(verified_at, ?) WHERE id = ?'
    )->execute([$hash, time(), $utilisateur['id']]);
    $pdo->commit();
    journal_securite('mot_de_passe_reinitialise', $utilisateur['id']);

    plus_tard(fn () => envoyer_mail(
        $utilisateur['email'],
        conf('app_name') . ' : votre mot de passe a été modifié',
        "Bonjour,\n\nLe mot de passe de votre compte vient d'être modifié et toutes vos sessions ont été fermées.\n\nSi vous n'êtes pas à l'origine de ce changement, refaites immédiatement une demande de réinitialisation.\n"
    ));
    return null;
}
