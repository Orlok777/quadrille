<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$utilisateur = utilisateur_courant();
if (!$utilisateur) {
    rediriger('index.php');
}

$erreur = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $regles = [['partie:' . $utilisateur['id'], 20, 3600]];   // 20 créations par heure et par compte
    if (!csrf_valide()) {
        http_response_code(400);
        $erreur = MSG_CSRF;
    } elseif (!in_array((int) champ('taille'), TAILLES_GRILLE, true)) {
        $erreur = 'Taille de grille invalide.';
    } elseif (limite_atteinte($regles)) {
        http_response_code(429);
        $erreur = 'Trop de parties créées. Réessayez plus tard.';
    } else {
        compter_essai($regles);
        try {
            $id = creer_partie($utilisateur, (int) champ('taille'));
            rediriger('partie.php?id=' . $id);
        } catch (RuntimeException $e) {
            error_log((string) $e);   // détail technique dans le journal, pas à l'écran
            $erreur = 'Le moteur de jeu est indisponible : impossible de créer la partie.';
        }
    }
}

$parties = parties_du_joueur((int) $utilisateur['id']);

page_debut('Mes parties', 'interieur');
?>
<?php barre($utilisateur, 'parties.php'); ?>

<main class="contenu">
    <div class="titre-ligne">
        <h1>Mes parties</h1>
        <form method="post" class="nouvelle-partie">
            <?php champ_csrf(); ?>
            <label for="taille">Grille</label>
            <select id="taille" name="taille">
                <?php foreach (TAILLES_GRILLE as $t): ?>
                    <option value="<?= $t ?>"><?= $t ?> × <?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Nouvelle partie</button>
        </form>
    </div>

    <?php alerte($erreur); ?>

    <?php if (!$parties): ?>
        <p class="mention">Aucune partie pour l'instant. Créez-en une pour commencer.</p>
    <?php else: ?>
        <ul class="parties">
            <?php foreach ($parties as $p): ?>
                <li>
                    <a class="partie-nom" href="partie.php?id=<?= (int) $p['partie'] ?>"><?= e($p['nom']) ?></a>
                    <span class="partie-info">Grille <?= e($p['dimensions']) ?></span>
                    <span class="partie-info"><?= (int) $p['nb_joueurs'] ?> joueur<?= $p['nb_joueurs'] > 1 ? 's' : '' ?></span>
                    <span class="partie-score"><?= (int) $p['score'] ?> pts</span>
                    <span class="etat etat-<?= e(str_replace('_', '-', $p['etat'])) ?>">
                        <?= e(LIBELLES_ETAT[$p['etat']] ?? $p['etat']) ?><?= $p['etat'] === 'finie' ? ' · ' . (int) $p['rang'] . ($p['rang'] == 1 ? 'er' : 'e') : '' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
<?php
page_fin();
