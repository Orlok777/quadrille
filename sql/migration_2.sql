-- Version 2 : vérification d'e-mail, double authentification, suivi d'activité (RGPD).
-- SQL compatible SQLite et PostgreSQL.

ALTER TABLE users ADD COLUMN verified_at BIGINT;
ALTER TABLE users ADD COLUMN last_login_at BIGINT;
ALTER TABLE users ADD COLUMN totp_secret TEXT;          -- Chiffré (AES-256-GCM), jamais en clair.
ALTER TABLE users ADD COLUMN totp_last_step BIGINT;     -- Dernier code accepté : anti-rejeu.

-- Les comptes créés avant cette version sont considérés comme confirmés.
UPDATE users SET verified_at = created_at, last_login_at = created_at;

CREATE TABLE email_verifications (
    token_hash TEXT   PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires_at BIGINT NOT NULL
);

-- Codes de secours de la double authentification, stockés sous forme d'empreinte HMAC.
CREATE TABLE recovery_codes (
    user_id   BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash TEXT   NOT NULL,
    PRIMARY KEY (user_id, code_hash)
);

CREATE TABLE schema_version (version INTEGER NOT NULL);
