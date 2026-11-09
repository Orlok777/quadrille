<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$estPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$brut = $estPost ? champ('token') : ($_GET['token'] ?? '');
$jeton = is_string($brut) ? $brut : '';
$utilisateur = reinitialisation_valide($jeton);
$erreur = null;

if ($estPost && $utilisateur) {
    if (!csrf_valide()) {
        http_response_code(400);
        $erreur = MSG_CSRF;
    } else {
        $erreur = appliquer_reinitialisation($jeton, $utilisateur, champ('mot_de_passe'), champ('confirmation'));
        if ($erreur === null) {
            fermer_session();
            flash('Votre mot de passe a été modifié. Vous pouvez vous connecter.');
            rediriger('index.php');
        }
    }
}

page_debut('Nouveau mot de passe', 'simple');
?>
<main class="carte carte-seule">
    <?php logo(); ?>
    <?php if ($utilisateur): ?>
        <h2>Choisissez un nouveau mot de passe</h2>
        <?php alerte($erreur); ?>
        <form method="post" action="reinitialiser.php">
            <?php champ_csrf(); ?>
            <input type="hidden" name="token" value="<?= e($jeton) ?>">
            <label for="mot_de_passe">Nouveau mot de passe</label>
            <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="new-password" required minlength="<?= MDP_MIN ?>" autofocus>
            <small>Au moins <?= MDP_MIN ?> caractères.</small>
            <label for="confirmation">Confirmez le mot de passe</label>
            <input id="confirmation" name="confirmation" type="password" autocomplete="new-password" required minlength="<?= MDP_MIN ?>">
            <button type="submit">Enregistrer</button>
        </form>
    <?php else: ?>
        <h2>Ce lien n'est plus valable</h2>
        <p>Il a expiré ou a déjà été utilisé.</p>
        <p class="annexe"><a href="index.php?vue=oubli">Refaire une demande</a></p>
    <?php endif; ?>
</main>
<?php
page_fin();
