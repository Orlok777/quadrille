<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (utilisateur_courant()) {
    rediriger('parties.php');
}

$vues = ['connexion', 'inscription', 'oubli'];
$demande = $_GET['vue'] ?? 'connexion';
$vue = in_array($demande, $vues, true) ? $demande : 'connexion';
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = champ('action');
    $vue = in_array($action, $vues, true) ? $action : 'connexion';

    if (!csrf_valide()) {
        http_response_code(400);
        $erreur = MSG_CSRF;
    } elseif ($vue === 'connexion') {
        $erreur = connecter(champ('identifiant'), champ('mot_de_passe'));
        if ($erreur === null) {
            rediriger('parties.php');
        }
    } elseif ($vue === 'inscription') {
        $erreur = creer_compte(trim(champ('pseudo')), trim(champ('email')), champ('mot_de_passe'), champ('confirmation'));
        if ($erreur === null) {
            rediriger('parties.php');
        }
    } else {
        demander_reinitialisation(champ('email'));
        flash('Si un compte correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé.');
        rediriger('index.php');
    }
}

$info = flash();
$titres = ['connexion' => 'Connexion', 'inscription' => 'Créer un compte', 'oubli' => 'Mot de passe oublié'];

page_debut($titres[$vue], 'accueil');
?>
<main class="scene">
    <section class="vitrine">
        <?php logo(); ?>
        <h1>Tracez des mots.<br>Battez vos amis.</h1>
        <p class="accroche">Le jeu de lettres en ligne où chaque partie est une nouvelle grille : reliez les lettres voisines et trouvez plus de mots que vos adversaires avant la fin du temps.</p>
        <?php grille_vitrine(); ?>
        <ol class="regles">
            <li><b>Une grille</b> tirée au sort</li>
            <li><b>Des mots</b> en lettres voisines</li>
            <li><b>Un classement</b> en fin de partie</li>
        </ol>
    </section>

    <section class="guichet">
        <div class="carte">
            <?php if ($vue !== 'oubli'): ?>
                <nav class="onglets" aria-label="Accès au compte">
                    <a href="index.php"<?= $vue === 'connexion' ? ' aria-current="page"' : '' ?>>Connexion</a>
                    <a href="index.php?vue=inscription"<?= $vue === 'inscription' ? ' aria-current="page"' : '' ?>>Créer un compte</a>
                </nav>
            <?php endif; ?>

            <?php alerte($erreur, $info); ?>

            <?php if ($vue === 'connexion'): ?>
                <h2>Content de vous revoir</h2>
                <form method="post" action="index.php">
                    <?php champ_csrf(); ?>
                    <input type="hidden" name="action" value="connexion">
                    <label for="identifiant">Pseudo ou e-mail</label>
                    <input id="identifiant" name="identifiant" type="text" autocomplete="username" required value="<?= e(champ('identifiant')) ?>">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required>
                    <button type="submit">Se connecter</button>
                </form>
                <p class="annexe"><a href="index.php?vue=oubli">Mot de passe oublié ?</a></p>

            <?php elseif ($vue === 'inscription'): ?>
                <h2>Rejoignez la partie</h2>
                <form method="post" action="index.php">
                    <?php champ_csrf(); ?>
                    <input type="hidden" name="action" value="inscription">
                    <label for="pseudo">Pseudo</label>
                    <input id="pseudo" name="pseudo" type="text" autocomplete="username" required minlength="3" maxlength="20" pattern="[A-Za-z0-9_\-]+" value="<?= e(champ('pseudo')) ?>">
                    <label for="email">Adresse e-mail</label>
                    <input id="email" name="email" type="email" autocomplete="email" required maxlength="254" value="<?= e(champ('email')) ?>">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="new-password" required minlength="<?= MDP_MIN ?>" aria-describedby="aide-mdp">
                    <small id="aide-mdp">Au moins <?= MDP_MIN ?> caractères. Une phrase facile à retenir fonctionne très bien.</small>
                    <label for="confirmation">Confirmez le mot de passe</label>
                    <input id="confirmation" name="confirmation" type="password" autocomplete="new-password" required minlength="<?= MDP_MIN ?>">
                    <button type="submit">Créer mon compte</button>
                </form>

            <?php else: ?>
                <h2>Mot de passe oublié</h2>
                <p>Indiquez l'adresse de votre compte. Nous vous enverrons un lien valable 30 minutes.</p>
                <form method="post" action="index.php">
                    <?php champ_csrf(); ?>
                    <input type="hidden" name="action" value="oubli">
                    <label for="email">Adresse e-mail</label>
                    <input id="email" name="email" type="email" autocomplete="email" required maxlength="254">
                    <button type="submit">Recevoir le lien</button>
                </form>
                <p class="annexe"><a href="index.php">Retour à la connexion</a></p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
page_fin();
