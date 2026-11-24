<?php
declare(strict_types=1);

// Données de démonstration (développement uniquement), en ligne de commande :
//   php outils/demo.php
// Crée le compte « demo » (mot de passe aléatoire écrit dans data/compte-demo.txt),
// deux adversaires sans mot de passe utilisable, et trois parties terminées.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

$pdo = db();

function compte_demo(PDO $pdo, string $pseudo, ?string $mdp): array
{
    $q = $pdo->prepare('SELECT id, pseudo FROM users WHERE lower(pseudo) = lower(?)');
    $q->execute([$pseudo]);
    if ($u = $q->fetch()) {
        return $u;
    }
    $hash = $mdp === null ? '!' : password_hash($mdp, algo_hachage());   // '!' : aucune connexion possible
    $q = $pdo->prepare(
        'INSERT INTO users (pseudo, email, password_hash, created_at, verified_at, last_login_at)
         VALUES (?, ?, ?, ?, ?, ?) RETURNING id, pseudo'
    );
    $t = time();
    $q->execute([$pseudo, $pseudo . '@demo.invalid', $hash, $t, $t, $t]);
    return $q->fetch();
}

$mdp = null;
$q = $pdo->prepare('SELECT 1 FROM users WHERE pseudo = ?');
$q->execute(['demo']);
if (!$q->fetchColumn()) {
    $mdp = bin2hex(random_bytes(9));
    file_put_contents(RACINE . '/data/compte-demo.txt', "pseudo : demo\nmot de passe : $mdp\n");
}
$demo  = compte_demo($pdo, 'demo', $mdp);
$adv   = [compte_demo($pdo, 'Margaux', null), compte_demo($pdo, 'Yanis', null)];

$q = $pdo->prepare('SELECT count(*) FROM parties WHERE ' . SQL_EST_JOUEUR);
$q->execute([$demo['id']]);
if ((int) $q->fetchColumn() > 0) {
    echo "Le compte demo a déjà des parties : rien à faire.\n";
    exit;
}

$joueur = fn (array $u, array $mots) => [
    'id'     => (int) $u['id'],
    'pseudo' => $u['pseudo'],
    'score'  => array_sum(array_map('score_boggle', $mots)),
    'mots'   => array_map(fn ($m) => ['mot' => $m, 'points' => score_boggle($m)], $mots),
];

function score_boggle(string $mot): int
{
    $n = strlen($mot);
    return match (true) { $n < 3 => 0, $n <= 4 => 1, $n === 5 => 2, $n === 6 => 3, $n === 7 => 5, default => 11 };
}

$exemple = ['E','U','E','S','O','I','N','I','I','J','E','L','M','I','A','E'];   // grille du sujet
$parties = [
    ['Revanche de mardi', 4, $exemple, [
        $joueur($demo, ['JOIE', 'JOIES', 'LIENS', 'MIEL']),
        $joueur($adv[0], ['LIEN', 'NIES', 'MIE']),
        $joueur($adv[1], ['JOUE', 'UNE']),
    ]],
    ['Duel du midi', 4, tirer_grille(4, 4), [
        $joueur($adv[0], ['ARBRE', 'TERRE', 'RIRE']),
        $joueur($demo, ['TERRE', 'ETRE']),
    ]],
    ['Grande grille', 5, tirer_grille(5, 5), [
        $joueur($demo, ['CHATEAU', 'TALENT', 'ANNEE']),
        $joueur($adv[1], ['TALENT', 'NATTE']),
    ]],
];

$insert = $pdo->prepare("INSERT INTO parties (createur_id, etat, cree_le, donnees) VALUES (?, 'finie', now() - ?::interval, ?::jsonb)");
foreach ($parties as $i => [$nom, $taille, $cases, $joueurs]) {
    $insert->execute([$joueurs[0]['id'], ($i + 1) . ' days', json_encode([
        'nom'     => $nom,
        'langue'  => 'fr',
        'duree'   => DUREE_PARTIE,
        'grille'  => ['lignes' => $taille, 'colonnes' => $taille, 'cases' => $cases],
        'joueurs' => $joueurs,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
}
echo count($parties) . " parties de démonstration créées.\n";
if ($mdp !== null) {
    echo "Identifiants du compte demo : data/compte-demo.txt\n";
}
