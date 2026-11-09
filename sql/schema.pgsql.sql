-- Schéma PostgreSQL, appliqué automatiquement à la première connexion (voir db() dans src/bootstrap.php).

CREATE TABLE IF NOT EXISTS users (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pseudo        TEXT   NOT NULL,
    email         TEXT   NOT NULL UNIQUE,
    password_hash TEXT   NOT NULL,
    -- Incrémenté à chaque changement de mot de passe : invalide toutes les sessions ouvertes.
    session_epoch BIGINT NOT NULL DEFAULT 0,
    created_at    BIGINT NOT NULL
);
-- Unicité sans tenir compte de la casse : "Alice" et "alice" sont le même pseudo.
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_pseudo ON users (lower(pseudo));

-- On ne stocke que l'empreinte du jeton : une fuite de la base ne donne aucun lien utilisable.
CREATE TABLE IF NOT EXISTS password_resets (
    token_hash TEXT   PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires_at BIGINT NOT NULL
);

-- Limitation de débit. "bucket" est un HMAC : ni l'IP ni l'identifiant n'y figurent en clair.
CREATE TABLE IF NOT EXISTS attempts (
    bucket     TEXT   NOT NULL,
    created_at BIGINT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attempts ON attempts (bucket, created_at);
