-- Schéma SQLite, appliqué automatiquement à la première connexion (voir db() dans src/bootstrap.php).

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    pseudo        TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    email         TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT    NOT NULL,
    -- Incrémenté à chaque changement de mot de passe : invalide toutes les sessions ouvertes.
    session_epoch INTEGER NOT NULL DEFAULT 0,
    created_at    INTEGER NOT NULL
);

-- On ne stocke que l'empreinte du jeton : une fuite de la base ne donne aucun lien utilisable.
CREATE TABLE IF NOT EXISTS password_resets (
    token_hash TEXT    PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires_at INTEGER NOT NULL
);

-- Limitation de débit. "bucket" est un HMAC : ni l'IP ni l'identifiant n'y figurent en clair.
CREATE TABLE IF NOT EXISTS attempts (
    bucket     TEXT    NOT NULL,
    created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attempts ON attempts (bucket, created_at);
