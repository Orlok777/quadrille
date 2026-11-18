<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// La confirmation demande un clic : les antivirus de messagerie qui ouvrent les liens ne la déclenchent pas.
$estPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$brut = $estPost ? champ('token') : ($_GET['token'] ?? '');
$jeton = is_string($brut) ? $brut : '';

if ($estPost && csrf_valide() && confirmer_email($jeton)) {
    flash('Adresse confirmée. Vous pouvez maintenant vous connecter.');
    rediriger('index.php');
}
$valide = verification_valide($jeton);

page_debut('Confirmer mon adresse', 'simple');
?>
<main class="carte carte-seule">
    <?php logo(); ?>
    <?php if ($valide): ?>
        <h2>Confirmez votre adresse</h2>
        <p>Un dernier clic pour activer votre compte.</p>
        <form method="post" action="verifier.php">
            <?php champ_csrf(); ?>
            <input type="hidden" name="token" value="<?= e($jeton) ?>">
            <button type="submit">Confirmer mon adresse</button>
        </form>
    <?php else: ?>
        <h2>Ce lien n'est plus valable</h2>
        <p>Il a expiré ou a déjà servi. Connectez-vous : si votre adresse n'est pas confirmée, un nouveau lien vous sera envoyé.</p>
        <p class="annexe"><a href="index.php">Aller à la connexion</a></p>
    <?php endif; ?>
</main>
<?php
page_fin();
