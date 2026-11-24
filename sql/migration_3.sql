-- Version 3 : parties de Boggle (module L2, R3.07). PostgreSQL uniquement.
--
-- Les données d'une partie sont stockées dans une colonne JSONB, par exemple :
-- {
--   "nom": "Duel du midi",
--   "langue": "fr",
--   "duree": 180,                                   -- en secondes
--   "grille": {"lignes": 4, "colonnes": 4, "cases": ["E","U","E","S", ...]},
--   "debut": "2026-11-24T12:00:00Z", "fin": "2026-11-24T12:03:00Z",
--   "joueurs": [
--     {"id": 1, "pseudo": "alice", "score": 7,
--      "mots": [{"mot": "JOIE", "points": 1}, {"mot": "LIENS", "points": 2}],
--      "refuses": ["SIENS"]}
--   ]
-- }
-- Seuls l'état, la date de création et le créateur sont en colonnes classiques :
-- ce sont eux qui servent à trier et à filtrer la liste des parties.

CREATE TABLE parties (
    id          BIGINT      GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    createur_id BIGINT      REFERENCES users(id) ON DELETE SET NULL,
    etat        TEXT        NOT NULL DEFAULT 'attente' CHECK (etat IN ('attente', 'en_cours', 'finie')),
    cree_le     TIMESTAMPTZ NOT NULL DEFAULT now(),
    donnees     JSONB       NOT NULL,
    -- Structure minimale garantie par la base, quel que soit le code qui écrit.
    CONSTRAINT donnees_grille CHECK (
        jsonb_typeof(donnees -> 'grille' -> 'cases') = 'array'
        AND jsonb_array_length(donnees -> 'grille' -> 'cases')
            = (donnees -> 'grille' ->> 'lignes')::int * (donnees -> 'grille' ->> 'colonnes')::int
    ),
    CONSTRAINT donnees_joueurs CHECK (jsonb_typeof(donnees -> 'joueurs') = 'array')
);

-- Index GIN sur tout le document : accélère les recherches par contenu avec @>
-- (ex. « les parties où joue l'utilisateur 5 » : donnees @> '{"joueurs":[{"id":5}]}').
-- jsonb_path_ops : index plus petit et plus rapide, limité à @> et aux jsonpath (@?, @@).
CREATE INDEX idx_parties_donnees ON parties USING GIN (donnees jsonb_path_ops);

-- Index B-tree sur une expression JSON : filtre par langue du dictionnaire.
CREATE INDEX idx_parties_langue ON parties ((donnees ->> 'langue'));

-- Liste des parties récentes, éventuellement filtrée par état.
CREATE INDEX idx_parties_etat ON parties (etat, cree_le DESC);


-- Grille JSON -> texte lisible, une rangée par segment : « E U E S / O I N I / ... ».
CREATE FUNCTION grille_texte(g JSONB) RETURNS TEXT
LANGUAGE sql IMMUTABLE AS $$
    SELECT string_agg(rangee, ' / ' ORDER BY r)
    FROM (
        SELECT (i - 1) / (g ->> 'colonnes')::int AS r,
               string_agg(c, ' ' ORDER BY i)     AS rangee
        FROM jsonb_array_elements_text(g -> 'cases') WITH ORDINALITY AS t(c, i)
        GROUP BY 1
    ) AS rangees
$$;


-- Affichage complet d'une partie par une simple requête :  SELECT * FROM afficher_partie(1);
-- Une ligne par joueur, classée par score.
CREATE FUNCTION afficher_partie(p_id BIGINT)
RETURNS TABLE (
    partie   BIGINT,
    nom      TEXT,
    etat     TEXT,
    creee_le TIMESTAMPTZ,
    grille   TEXT,
    rang     BIGINT,
    joueur   TEXT,
    score    INT,
    nb_mots  INT,
    mots     TEXT
)
LANGUAGE sql STABLE AS $$
    SELECT p.id,
           p.donnees ->> 'nom',
           p.etat,
           p.cree_le,
           grille_texte(p.donnees -> 'grille'),
           rank() OVER (ORDER BY (j ->> 'score')::int DESC NULLS LAST),
           j ->> 'pseudo',
           (j ->> 'score')::int,
           jsonb_array_length(coalesce(j -> 'mots', '[]')),
           (SELECT string_agg(m ->> 'mot', ', ' ORDER BY m ->> 'mot')
              FROM jsonb_array_elements(j -> 'mots') AS m)
    FROM parties AS p
    LEFT JOIN LATERAL jsonb_array_elements(p.donnees -> 'joueurs') AS j ON true
    WHERE p.id = p_id
    ORDER BY 6, 7
$$;


-- Historique d'un joueur :  SELECT * FROM historique_joueur(1);
-- Le filtre @> utilise l'index GIN ; jsonb_path_query_first extrait l'entrée du joueur.
CREATE FUNCTION historique_joueur(p_user BIGINT)
RETURNS TABLE (
    partie     BIGINT,
    nom        TEXT,
    etat       TEXT,
    creee_le   TIMESTAMPTZ,
    dimensions TEXT,
    nb_joueurs INT,
    score      INT,
    rang       BIGINT
)
LANGUAGE sql STABLE AS $$
    SELECT p.id,
           p.donnees ->> 'nom',
           p.etat,
           p.cree_le,
           (p.donnees -> 'grille' ->> 'lignes') || ' × ' || (p.donnees -> 'grille' ->> 'colonnes'),
           jsonb_array_length(p.donnees -> 'joueurs'),
           (moi ->> 'score')::int,
           1 + (SELECT count(*)
                  FROM jsonb_array_elements(p.donnees -> 'joueurs') AS autre
                 WHERE (autre ->> 'score')::int > (moi ->> 'score')::int)
    FROM parties AS p
    CROSS JOIN LATERAL jsonb_path_query_first(
        p.donnees, '$.joueurs[*] ? (@.id == $u)', jsonb_build_object('u', p_user)
    ) AS moi
    WHERE p.donnees @> jsonb_build_object('joueurs', jsonb_build_array(jsonb_build_object('id', p_user)))
    ORDER BY p.cree_le DESC, p.id DESC
$$;


-- RGPD : quand un compte est supprimé, il disparaît des parties des autres joueurs
-- (identifiant retiré, pseudo remplacé), mais les scores restent pour garder l'historique cohérent.
CREATE FUNCTION anonymiser_joueur() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE parties
       SET donnees = jsonb_set(donnees, '{joueurs}', (
               SELECT jsonb_agg(
                          CASE WHEN (j ->> 'id')::bigint = OLD.id
                               THEN (j - 'id') || '{"pseudo": "Joueur supprimé"}'
                               ELSE j END
                          ORDER BY n)
                 FROM jsonb_array_elements(donnees -> 'joueurs') WITH ORDINALITY AS t(j, n)))
     WHERE donnees @> jsonb_build_object('joueurs', jsonb_build_array(jsonb_build_object('id', OLD.id)));
    RETURN OLD;
END
$$;

CREATE TRIGGER trg_anonymiser_joueur
    BEFORE DELETE ON users
    FOR EACH ROW EXECUTE FUNCTION anonymiser_joueur();
