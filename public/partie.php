<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$utilisateur = utilisateur_courant();
if (!$utilisateur) {
    rediriger('index.php');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$partie = $id ? partie_pour($id, (int) $utilisateur['id']) : null;
if ($partie === null) {
    // Même réponse que la partie n'existe pas ou que l'utilisateur n'y joue pas.
    http_response_code(404);
}

page_debut($partie ? $partie['infos']['nom'] : 'Partie introuvable', 'interieur');
?>
<?php barre($utilisateur, 'parties.php'); ?>

<main class="contenu">
    <p><a href="parties.php">← Mes parties</a></p>

    <?php if ($partie === null): ?>
        <h1>Partie introuvable</h1>
        <p class="mention">Cette partie n'existe pas, ou vous n'y participez pas.</p>
    <?php else: ?>
        <?php $g = $partie['grille']; ?>
        <div class="titre-ligne">
            <h1><?= e($partie['infos']['nom']) ?></h1>
            <span class="etat etat-<?= e(str_replace('_', '-', $partie['infos']['etat'])) ?>"><?= e(LIBELLES_ETAT[$partie['infos']['etat']] ?? $partie['infos']['etat']) ?></span>
        </div>

        <div class="partie-detail">
            <div class="grille grille-jeu grille-<?= (int) $g['colonnes'] ?>" role="img"
                 aria-label="Grille <?= (int) $g['lignes'] ?> × <?= (int) $g['colonnes'] ?> : <?= e(implode(' ', $g['cases'])) ?>">
                <?php foreach ($g['cases'] as $lettre): ?>
                    <span class="case"><b><?= e(ucfirst(strtolower($lettre))) ?></b></span>
                <?php endforeach; ?>
            </div>

            <table class="classement">
                <thead><tr><th>Rang</th><th>Joueur</th><th>Score</th><th>Mots trouvés</th></tr></thead>
                <tbody>
                    <?php foreach ($partie['joueurs'] as $j): ?>
                        <tr>
                            <td><?= (int) $j['rang'] ?></td>
                            <td><?= e((string) $j['joueur']) ?></td>
                            <td><?= (int) $j['score'] ?></td>
                            <td><?= $j['nb_mots'] ? e((string) $j['mots']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mention">Créée le <?= e(date('d/m/Y à H:i', strtotime($partie['infos']['creee_le']))) ?>.
            La saisie des mots arrivera avec la gestion des parties en temps réel.</p>
    <?php endif; ?>
</main>
<?php
page_fin();
