<?php
declare(strict_types=1);

// Création et mise à jour automatiques du schéma PostgreSQL, à la première connexion.

const SCHEMA_VERSION = 3;

function table_existe(PDO $pdo, string $table): bool
{
    $q = $pdo->prepare('SELECT to_regclass(?) IS NOT NULL');
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
    // Verrou : deux requêtes simultanées après un déploiement ne migrent pas deux fois.
    // En PostgreSQL, le DDL est transactionnel : une migration échoue entièrement ou pas du tout.
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT pg_advisory_xact_lock(4242)');
        $version = version_schema($pdo);
        $etapes = [1 => 'schema.pgsql.sql', 2 => 'migration_2.sql', 3 => 'migration_3.sql'];
        foreach ($etapes as $numero => $fichier) {
            if ($version < $numero) {
                $pdo->exec((string) file_get_contents(RACINE . '/sql/' . $fichier));
                if ($numero >= 2) {
                    $pdo->exec('INSERT INTO schema_version (version) VALUES (' . $numero . ')');
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
