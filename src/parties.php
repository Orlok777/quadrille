<?php
declare(strict_types=1);

// Parties de Boggle : stockage JSONB (PostgreSQL) et appel du moteur en C.

const TAILLES_GRILLE = [4, 5];
const DUREE_PARTIE = 180;   // secondes
const LIBELLES_ETAT = ['attente' => 'En attente', 'en_cours' => 'En cours', 'finie' => 'Terminée'];

/** Condition @> « l'utilisateur fait partie des joueurs » : elle utilise l'index GIN idx_parties_donnees. */
const SQL_EST_JOUEUR = "donnees @> jsonb_build_object('joueurs', jsonb_build_array(jsonb_build_object('id', ?::bigint)))";

/** Historique du joueur, calculé par la fonction stockée historique_joueur(). */
function parties_du_joueur(int $uid): array
{
    $q = db()->prepare('SELECT * FROM historique_joueur(?)');
    $q->execute([$uid]);
    return $q->fetchAll();
}

/**
 * Détail d'une partie, ou null si elle n'existe pas OU si l'utilisateur n'y joue pas :
 * les deux cas renvoient la même réponse, on ne peut pas sonder les identifiants des autres (IDOR).
 */
function partie_pour(int $id, int $uid): ?array
{
    $q = db()->prepare("SELECT donnees -> 'grille' AS grille FROM parties WHERE id = ? AND " . SQL_EST_JOUEUR);
    $q->execute([$id, $uid]);
    $grille = $q->fetchColumn();
    if ($grille === false) {
        return null;
    }
    $q = db()->prepare('SELECT * FROM afficher_partie(?)');
    $q->execute([$id]);
    $lignes = $q->fetchAll();
    return [
        'grille'  => json_decode($grille, true, 8, JSON_THROW_ON_ERROR),
        'infos'   => $lignes[0],
        'joueurs' => $lignes,
    ];
}

/** Crée une partie en attente dont l'utilisateur est le premier joueur. Renvoie son identifiant. */
function creer_partie(array $u, int $taille): int
{
    if (!in_array($taille, TAILLES_GRILLE, true)) {
        throw new InvalidArgumentException('Taille de grille non prise en charge.');
    }
    $donnees = [
        'nom'     => 'Partie de ' . $u['pseudo'],
        'langue'  => 'fr',
        'duree'   => DUREE_PARTIE,
        'grille'  => ['lignes' => $taille, 'colonnes' => $taille, 'cases' => tirer_grille($taille, $taille)],
        'joueurs' => [['id' => (int) $u['id'], 'pseudo' => $u['pseudo'], 'score' => 0, 'mots' => [], 'refuses' => []]],
    ];
    $q = db()->prepare('INSERT INTO parties (createur_id, donnees) VALUES (?, ?::jsonb) RETURNING id');
    $q->execute([$u['id'], json_encode($donnees, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    return (int) $q->fetchColumn();
}

/** Données de parties du joueur pour l'export RGPD : uniquement sa propre entrée, pas celles des autres. */
function parties_export(int $uid): array
{
    $q = db()->prepare(
        "SELECT id, cree_le, etat, donnees ->> 'nom' AS nom,
                jsonb_path_query_first(donnees, '$.joueurs[*] ? (@.id == \$u)', jsonb_build_object('u', ?::bigint)) AS moi
           FROM parties WHERE " . SQL_EST_JOUEUR . ' ORDER BY cree_le'
    );
    $q->execute([$uid, $uid]);
    return array_map(function (array $p): array {
        $p['moi'] = json_decode($p['moi'], true);
        return $p;
    }, $q->fetchAll());
}

// --- Moteur de jeu (module L1, en C) ----------------------------------------

/** Chemin d'un exécutable du moteur compilé dans moteur/bin/. */
function moteur(string $programme): string
{
    $chemin = RACINE . '/moteur/bin/' . $programme . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    if (!is_file($chemin)) {
        throw new RuntimeException("Moteur non compilé : $chemin introuvable (lancer make dans moteur/).");
    }
    return $chemin;
}

/**
 * Lance un programme du moteur et renvoie sa sortie standard.
 * Les arguments sont passés sous forme de tableau : aucun shell n'intervient, donc pas d'injection de commande.
 */
function lancer_moteur(string $programme, array $arguments): string
{
    $commande = array_merge([moteur($programme)], array_map('strval', $arguments));
    $proc = proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
    if (!is_resource($proc)) {
        throw new RuntimeException("Impossible de lancer $programme.");
    }
    $sortie = stream_get_contents($tubes[1]);
    $erreurs = stream_get_contents($tubes[2]);
    fclose($tubes[1]);
    fclose($tubes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        throw new RuntimeException("$programme a échoué ($code) : $erreurs");
    }
    return (string) $sortie;
}

/** Grille tirée par le moteur C (grid_build), en tenant compte des fréquences des lettres. */
function tirer_grille(int $lignes, int $colonnes): array
{
    $cases = preg_split('/\s+/', trim(lancer_moteur('grid_build', [$lignes, $colonnes])));
    if (count($cases) !== $lignes * $colonnes) {
        throw new RuntimeException('Grille inattendue renvoyée par le moteur.');
    }
    foreach ($cases as $c) {
        if (!preg_match('/^[A-Z]{1,4}$/', $c)) {
            throw new RuntimeException('Case invalide renvoyée par le moteur.');
        }
    }
    return $cases;
}
