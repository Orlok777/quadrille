<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$utilisateur = utilisateur_courant();
if (!$utilisateur) {
    rediriger('index.php');
}

// Données fictives, en attendant le module de gestion de parties.
$parties = [
    ['nom' => 'Salon des Lève-tôt',  'grille' => '4 × 4', 'joueurs' => '2 / 4', 'etat' => 'attente',  'libelle' => 'En attente',       'score' => '—'],
    ['nom' => 'Duel du midi',        'grille' => '4 × 4', 'joueurs' => '2 / 2', 'etat' => 'en-cours', 'libelle' => 'En cours',         'score' => '37 pts'],
    ['nom' => 'Grande Grille BUT2',  'grille' => '5 × 5', 'joueurs' => '6 / 8', 'etat' => 'attente',  'libelle' => 'En attente',       'score' => '—'],
    ['nom' => 'Revanche de mardi',   'grille' => '4 × 4', 'joueurs' => '3 / 3', 'etat' => 'finie',    'libelle' => 'Terminée · 1er',   'score' => '82 pts'],
    ['nom' => 'Les Mots Fléchés',    'grille' => '5 × 5', 'joueurs' => '4 / 4', 'etat' => 'finie',    'libelle' => 'Terminée · 3e',    'score' => '54 pts'],
];

$nouvelle = isset($_GET['nouvelle']);

page_debut('Mes parties', 'interieur');
?>
<header class="barre">
    <?php logo(); ?>
    <div class="barre-compte">
        <span>Bonjour, <b><?= e($utilisateur['pseudo']) ?></b></span>
        <form method="post" action="deconnexion.php">
            <?php champ_csrf(); ?>
            <button type="submit" class="discret">Se déconnecter</button>
        </form>
    </div>
</header>

<main class="contenu">
    <div class="titre-ligne">
        <h1>Mes parties</h1>
        <a class="bouton" href="parties.php?nouvelle=1">Nouvelle partie</a>
    </div>

    <?php if ($nouvelle): ?>
        <?php alerte(null, 'Démonstration : la création de partie arrivera avec le projet SAE.'); ?>
    <?php endif; ?>

    <ul class="parties">
        <?php foreach ($parties as $p): ?>
            <li>
                <span class="partie-nom"><?= e($p['nom']) ?></span>
                <span class="partie-info">Grille <?= e($p['grille']) ?></span>
                <span class="partie-info"><?= e($p['joueurs']) ?> joueurs</span>
                <span class="partie-score"><?= e($p['score']) ?></span>
                <span class="etat etat-<?= e($p['etat']) ?>"><?= e($p['libelle']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="mention">Parties fictives, affichées pour la démonstration.</p>
</main>
<?php
page_fin();
