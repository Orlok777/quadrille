<?php
declare(strict_types=1);

// Création et mise à jour automatiques du schéma, pour SQLite (local) comme PostgreSQL (en ligne).

const SCHEMA_VERSION = 2;

function table_existe(PDO $pdo, string $table): bool
{
    $q = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? $pdo->prepare('SELECT to_regclass(?) IS NOT NULL')
        : $pdo->prepare("SELECT COUNT(*) > 0 FROM sqlite_master WHERE type = 'table' AND name = ?");
    $q->execute([$table]);
    return (bool) $q->fetchColumn();
}

function version_schema(PDO $pdo): int
{
    if (table_existe($pdo, 'schema_version')) {
        return (int) $pdo->query('SELECT MAX(version) FROM schema_version')->fetchColumn();
    }
    return table_existe($pdo, 'users') ? 1 : 0;   // 1 : base créée avant le suivi des versions.
}

function migrer(PDO $pdo): void
{
    if (version_schema($pdo) >= SCHEMA_VERSION) {
        return;
    }
    $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

    // Verrou : deux requêtes simultanées après un déploiement ne migrent pas deux fois.
    if ($pg) {
        $pdo->beginTransaction();
        $pdo->query('SELECT pg_advisory_xact_lock(4242)');
    } else {
        $pdo->exec('BEGIN IMMEDIATE');
    }
    try {
        $version = version_schema($pdo);
        if ($version < 1) {
            $pdo->exec((string) file_get_contents(RACINE . ($pg ? '/sql/schema.pgsql.sql' : '/sql/schema.sql')));
        }
        if ($version < 2) {
            $pdo->exec((string) file_get_contents(RACINE . '/sql/migration_2.sql'));
            $pdo->exec('INSERT INTO schema_version (version) VALUES (2)');
        }
        $pg ? $pdo->commit() : $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pg ? $pdo->rollBack() : $pdo->exec('ROLLBACK');
        throw $e;
    }
}
