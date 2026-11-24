-- Démonstration du module L2 (à lancer avec psql sur une base migrée) :
--   psql -d quadrille -f sql/demo_bdd.sql
-- Tout se passe dans une transaction annulée à la fin : la base n'est pas modifiée.

BEGIN;

INSERT INTO users (pseudo, email, password_hash, created_at) VALUES
    ('demo_alice', 'alice@demo.invalid', '!', 0),
    ('demo_bob',   'bob@demo.invalid',   '!', 0);

-- Une partie terminée sur la grille d'exemple du sujet.
INSERT INTO parties (createur_id, etat, donnees)
SELECT a.id, 'finie', jsonb_build_object(
    'nom', 'Duel de démonstration', 'langue', 'fr', 'duree', 180,
    'grille', '{"lignes": 4, "colonnes": 4, "cases": ["E","U","E","S","O","I","N","I","I","J","E","L","M","I","A","E"]}'::jsonb,
    'joueurs', jsonb_build_array(
        jsonb_build_object('id', a.id, 'pseudo', a.pseudo, 'score', 4,
            'mots', '[{"mot":"JOIE","points":1},{"mot":"LIENS","points":2},{"mot":"MIEL","points":1}]'::jsonb),
        jsonb_build_object('id', b.id, 'pseudo', b.pseudo, 'score', 6,
            'mots', '[{"mot":"JOIES","points":2},{"mot":"LIEN","points":1},{"mot":"NIES","points":1}]'::jsonb)))
FROM users a, users b WHERE a.pseudo = 'demo_alice' AND b.pseudo = 'demo_bob';

\echo '1. Affichage d''une partie par une simple SELECT (fonction stockée)'
SELECT * FROM afficher_partie((SELECT max(id) FROM parties));

\echo '2. Historique d''un joueur (filtre @> + jsonb_path_query_first)'
SELECT * FROM historique_joueur((SELECT id FROM users WHERE pseudo = 'demo_bob'));

\echo '3. Opérateurs JSON : tous les mots de plus de 4 lettres trouvés, avec leur auteur'
SELECT j ->> 'pseudo' AS joueur, m ->> 'mot' AS mot, (m ->> 'points')::int AS points
  FROM parties,
       jsonb_array_elements(donnees -> 'joueurs') AS j,
       jsonb_array_elements(j -> 'mots') AS m
 WHERE length(m ->> 'mot') > 4;

\echo '4. Utilisation de l''index GIN pour « les parties d''un joueur »'
SET LOCAL enable_seqscan = off;   -- la table est trop petite pour que le planificateur choisisse l'index seul
EXPLAIN (COSTS OFF)
SELECT id FROM parties WHERE donnees @> '{"joueurs": [{"id": 1}]}';
RESET enable_seqscan;

\echo '5. La base refuse une grille incohérente (16 cases annoncées, 1 fournie)'
SAVEPOINT avant_erreur;
INSERT INTO parties (donnees) VALUES ('{"grille": {"lignes": 4, "colonnes": 4, "cases": ["A"]}, "joueurs": []}');
ROLLBACK TO avant_erreur;

\echo '6. RGPD : suppression d''un compte -> joueur anonymisé dans les parties des autres'
DELETE FROM users WHERE pseudo = 'demo_alice';
SELECT rang, joueur, score FROM afficher_partie((SELECT max(id) FROM parties));

ROLLBACK;
