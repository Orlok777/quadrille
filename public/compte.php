<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$u = utilisateur_courant();
if (!$u) {
    rediriger('index.php');
}

$erreur = null;
$section = null;   // Section où afficher l'erreur.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = champ('action');
    if (!csrf_valide()) {
        http_response_code(400);
        $erreur = MSG_CSRF;
    } else {
        $succes = null;
        switch ($section) {
            case 'mot_de_passe':
                $erreur = changer_mot_de_passe($u, champ('actuel'), champ('nouveau'), champ('confirmation'));
                $succes = 'Mot de passe modifié. Vos autres sessions ont été fermées.';
                break;
            case 'sessions':
                deconnecter_autres_sessions($u);
                $succes = 'Toutes vos autres sessions ont été fermées.';
                break;
            case 'mfa_preparer':
                $erreur = preparer_double_auth($u, champ('mot_de_passe'));
                $section = 'mfa';
                break;
            case 'mfa_activer':
                $erreur = activer_double_auth($u, champ('code'));
                $succes = 'Double authentification activée.';
                $section = 'mfa';
                break;
            case 'mfa_annuler':
                unset($_SESSION['mfa_nouveau']);
                break;
            case 'mfa_desactiver':
                $erreur = desactiver_double_auth($u, champ('mot_de_passe'));
                $succes = 'Double authentification désactivée.';
                $section = 'mfa';
                break;
            case 'exporter':
                $erreur = exporter_donnees($u, champ('mot_de_passe'));   // Termine la requête si le mot de passe est bon.
                break;
            case 'supprimer':
                $erreur = supprimer_compte($u, champ('mot_de_passe'), champ('confirmer') === 'oui');
                if ($erreur === null) {
                    fermer_session();
                    flash('Votre compte et vos données ont été supprimés.');
                    rediriger('index.php');
                }
                break;
        }
        if ($erreur === null) {
            if ($succes !== null) {
                flash($succes);
            }
            rediriger('compte.php');
        }
    }
}

$info = flash();
$codes = $_SESSION['codes_secours'] ?? null;
unset($_SESSION['codes_secours']);
$mfaActive = $u['totp_secret'] !== null;
$mfaNouveau = !$mfaActive && isset($_SESSION['mfa_nouveau']) ? base64_decode($_SESSION['mfa_nouveau']) : null;
$date = fn ($t) => $t === null ? '—' : date('d/m/Y', (int) $t);

/** Affiche l'erreur uniquement dans la section concernée. */
$erreurDe = fn (string ...$noms) => in_array($section, $noms, true) ? $erreur : null;

page_debut('Mon compte', 'interieur');
barre($u, 'compte.php');
?>
<main class="contenu etroit">
    <h1>Mon compte</h1>
    <?php alerte($erreur === MSG_CSRF ? $erreur : null, $info); ?>

    <?php if ($codes): ?>
        <section class="bloc bloc-important">
            <h2>Vos codes de secours</h2>
            <p>Notez-les maintenant : ils ne seront plus jamais affichés. Chacun permet une seule connexion si vous perdez votre téléphone.</p>
            <ul class="codes">
                <?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="bloc">
        <h2>Informations</h2>
        <dl class="infos">
            <dt>Pseudo</dt><dd><?= e($u['pseudo']) ?></dd>
            <dt>Adresse e-mail</dt><dd><?= e($u['email']) ?></dd>
            <dt>Membre depuis</dt><dd><?= $date($u['created_at']) ?></dd>
            <dt>Adresse IP de cette connexion</dt><dd><?= e(ip_client()) ?></dd>
        </dl>
    </section>

    <section class="bloc">
        <h2>Mot de passe</h2>
        <?php alerte($erreurDe('mot_de_passe')); ?>
        <form method="post" action="compte.php">
            <?php champ_csrf(); ?>
            <input type="hidden" name="action" value="mot_de_passe">
            <input type="text" name="username" value="<?= e($u['pseudo']) ?>" autocomplete="username" hidden>
            <label for="actuel">Mot de passe actuel</label>
            <input id="actuel" name="actuel" type="password" autocomplete="current-password" required>
            <label for="nouveau">Nouveau mot de passe</label>
            <input id="nouveau" name="nouveau" type="password" autocomplete="new-password" required minlength="<?= MDP_MIN ?>">
            <label for="confirmation">Confirmez le nouveau mot de passe</label>
            <input id="confirmation" name="confirmation" type="password" autocomplete="new-password" required minlength="<?= MDP_MIN ?>">
            <button type="submit">Changer le mot de passe</button>
        </form>
    </section>

    <section class="bloc" id="double-auth">
        <h2>Double authentification</h2>
        <?php alerte($erreurDe('mfa')); ?>
        <?php if ($mfaActive): ?>
            <p class="statut statut-on">Activée. Un code de votre application est demandé à chaque connexion.</p>
            <form method="post" action="compte.php">
                <?php champ_csrf(); ?>
                <input type="hidden" name="action" value="mfa_desactiver">
                <label for="mdp-mfa-off">Mot de passe actuel</label>
                <input id="mdp-mfa-off" name="mot_de_passe" type="password" autocomplete="current-password" required>
                <button type="submit" class="secondaire">Désactiver</button>
            </form>
        <?php elseif ($mfaNouveau !== null): ?>
            <ol class="etapes">
                <li>Dans votre application d'authentification (Google Authenticator, Aegis, FreeOTP…), choisissez « Saisir une clé de configuration ».</li>
                <li>Saisissez cette clé, en mode « basé sur l'heure » :
                    <code class="cle"><?= e(trim(chunk_split(base32($mfaNouveau), 4, ' '))) ?></code>
                    <small>Ou utilisez ce lien de configuration :</small>
                    <code class="uri"><?= e('otpauth://totp/' . rawurlencode(conf('app_name') . ':' . $u['pseudo']) . '?secret=' . base32($mfaNouveau) . '&issuer=' . rawurlencode(conf('app_name'))) ?></code>
                </li>
                <li>Entrez le code affiché par l'application pour terminer.</li>
            </ol>
            <form method="post" action="compte.php">
                <?php champ_csrf(); ?>
                <input type="hidden" name="action" value="mfa_activer">
                <label for="code">Code à 6 chiffres</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required maxlength="7">
                <button type="submit">Activer</button>
            </form>
            <form method="post" action="compte.php" class="annexe">
                <?php champ_csrf(); ?>
                <input type="hidden" name="action" value="mfa_annuler">
                <button type="submit" class="lien">Annuler</button>
            </form>
        <?php else: ?>
            <p class="statut">Désactivée. Avec la double authentification, votre mot de passe seul ne suffit plus pour accéder au compte.</p>
            <form method="post" action="compte.php">
                <?php champ_csrf(); ?>
                <input type="hidden" name="action" value="mfa_preparer">
                <label for="mdp-mfa">Mot de passe actuel</label>
                <input id="mdp-mfa" name="mot_de_passe" type="password" autocomplete="current-password" required>
                <button type="submit">Activer la double authentification</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="bloc">
        <h2>Sessions</h2>
        <p>Vous avez oublié de vous déconnecter sur un autre appareil ? Fermez toutes les autres sessions d'un coup.</p>
        <form method="post" action="compte.php">
            <?php champ_csrf(); ?>
            <input type="hidden" name="action" value="sessions">
            <button type="submit" class="secondaire">Déconnecter mes autres sessions</button>
        </form>
    </section>

    <section class="bloc">
        <h2>Mes données</h2>
        <p>Téléchargez toutes les données que nous avons sur vous, dans un fichier JSON.</p>
        <?php alerte($erreurDe('exporter')); ?>
        <form method="post" action="compte.php">
            <?php champ_csrf(); ?>
            <input type="hidden" name="action" value="exporter">
            <label for="mdp-export">Mot de passe actuel</label>
            <input id="mdp-export" name="mot_de_passe" type="password" autocomplete="current-password" required>
            <button type="submit" class="secondaire">Télécharger mes données</button>
        </form>
    </section>

    <section class="bloc bloc-danger">
        <h2>Supprimer mon compte</h2>
        <p>Votre compte et toutes vos données seront effacés définitivement. Cette action est irréversible.</p>
        <?php alerte($erreurDe('supprimer')); ?>
        <form method="post" action="compte.php">
            <?php champ_csrf(); ?>
            <input type="hidden" name="action" value="supprimer">
            <label for="mdp-suppr">Mot de passe actuel</label>
            <input id="mdp-suppr" name="mot_de_passe" type="password" autocomplete="current-password" required>
            <label class="case-a-cocher"><input type="checkbox" name="confirmer" value="oui" required> Je comprends que la suppression est définitive.</label>
            <button type="submit" class="danger">Supprimer définitivement</button>
        </form>
    </section>
</main>
<?php
page_fin();
