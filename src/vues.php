<?php
declare(strict_types=1);

function page_debut(string $titre, string $classe): void
{
    ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titre) ?> · <?= e(conf('app_name')) ?></title>
<meta name="description" content="Jeu de lettres en ligne : tracez des mots sur une grille et affrontez vos amis.">
<link rel="stylesheet" href="style.css">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body class="<?= e($classe) ?>">
    <?php
}

function page_fin(): void
{
    echo "</body>\n</html>\n";
}

function logo(): void
{
    ?>
    <span class="logo">
        <img src="favicon.svg" alt="" width="34" height="34">
        <span><?= e(conf('app_name')) ?></span>
    </span>
    <?php
}

function champ_csrf(): void
{
    echo '<input type="hidden" name="csrf" value="' . e(jeton_csrf()) . '">';
}

function alerte(?string $erreur, ?string $info = null): void
{
    if ($erreur !== null) {
        echo '<p class="alerte alerte-erreur" role="alert">' . e($erreur) . '</p>';
    }
    if ($info !== null) {
        echo '<p class="alerte alerte-info" role="status">' . e($info) . '</p>';
    }
}

/** Grille de démonstration : le nom du jeu y est tracé case après case. */
function grille_vitrine(): void
{
    $lettres = ['Q','U','A','D', 'S','O','I','R', 'M','T','L','L', 'E','N','E','S'];
    // Ordre de passage du tracé pour chaque case (0 = hors du tracé).
    $ordre   = [ 1,  2,  3,  4,   0,  0,  6,  5,   0,  0,  8,  7,   0,  0,  9,  0];
    ?>
    <div class="grille" aria-hidden="true">
        <?php foreach ($lettres as $i => $lettre): ?>
            <span class="case<?= $ordre[$i] ? ' trace t' . $ordre[$i] : '' ?>"><b><?= $lettre ?></b></span>
        <?php endforeach; ?>
        <svg class="fil" viewBox="0 0 400 400">
            <polyline pathLength="1" points="50,50 150,50 250,50 350,50 350,150 250,150 350,250 250,250 250,350"/>
        </svg>
    </div>
    <?php
}
