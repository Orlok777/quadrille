<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if (utilisateur_courant()) {
    rediriger('parties.php');
}
if (empty($_SESSION['mfa_attente'])) {
    rediriger('index.php');
}

$erreur = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valide()) {
        http_response_code(400);
        $erreur = MSG_CSRF;
    } else {
        $erreur = valider_double_auth(champ('code'));
        if ($erreur === null) {
            rediriger('parties.php');
        }
        if (empty($_SESSION['mfa_attente'])) {
            flash($erreur);
            rediriger('index.php');
        }
    }
}

page_debut('Double authentification', 'simple');
?>
<main class="carte carte-seule">
    <?php logo(); ?>
    <h2>Double authentification</h2>
    <?php alerte($erreur); ?>
    <form method="post" action="double-auth.php">
        <?php champ_csrf(); ?>
        <label for="code">Code à 6 chiffres de votre application</label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="11">
        <small>Téléphone perdu ? Saisissez l'un de vos codes de secours (format XXXXX-XXXXX).</small>
        <button type="submit">Valider</button>
    </form>
    <p class="annexe"><a href="index.php">Annuler</a></p>
</main>
<?php
page_fin();
