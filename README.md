# Quadrille

Plate-forme de Boggle en ligne (SAE S3). PHP 8.2 sans dépendance ni JavaScript, base PostgreSQL,
moteur de jeu en C ([moteur/](moteur/README.md)).

- Sécurité du site : [doc/securite.md](doc/securite.md)
- Base de données (module L2) : [sql/migration_3.sql](sql/migration_3.sql), démonstration dans [sql/demo_bdd.sql](sql/demo_bdd.sql)

## Lancer en local

Prérequis : PHP 8.2 avec `pdo_pgsql`, un serveur PostgreSQL, gcc et make.

```bash
cp config.example.php config.php   # puis renseigner "secret" et "database_url"
make -C moteur                     # compile le moteur de jeu (grilles des parties)
php -S localhost:8000 -t public
php outils/demo.php                # facultatif : compte « demo » et parties d'exemple
```

Le schéma est créé et mis à jour automatiquement à la première requête (`src/schema.php`).

En local (`mail_mode` = `log`), les e-mails sont écrits dans `data/mails.log`.

## Mettre en ligne

Sur Render : New > Blueprint, puis choisir ce dépôt. `render.yaml` crée le site et la base PostgreSQL.
Seules `MAIL_FROM` (expéditeur validé chez Brevo) et `BREVO_API_KEY` sont à saisir.

`XFF_HOPS` indique combien d'adresses lire depuis la fin de `X-Forwarded-For` pour trouver l'IP réelle du client. Une mauvaise valeur rend la limitation de débit inefficace. Sur Render, la chaîne est « IP réelle, Cloudflare, proxy interne » : la valeur est donc 3.

## Sécurité en place

**Comptes**
- Mots de passe : Argon2id, 12 caractères minimum, refus des mots de passe courants et de ceux qui ont fuité (Have I Been Pwned, k-anonymat : seuls 5 caractères de l'empreinte SHA-1 sont envoyés).
- Adresse e-mail confirmée avant la première connexion. L'inscription répond de la même façon que l'adresse soit libre ou déjà prise : seul le propriétaire de l'adresse est prévenu.
- Double authentification TOTP facultative, avec clé chiffrée (AES-256-GCM), anti-rejeu et 8 codes de secours à usage unique.
- Réinitialisation : jeton de 256 bits stocké haché, valable 30 min, à usage unique. Le changement ferme toutes les sessions et déclenche un e-mail d'alerte.
- Page « Mon compte » : changement de mot de passe, déconnexion des autres sessions, 2FA. Chaque action sensible redemande le mot de passe.

**Attaques**
- Message et temps de réponse identiques que le compte existe ou non. Les e-mails partent après la réponse.
- Limitation de débit par IP, par compte et par couple IP/compte (connexion, inscription, oubli, 2FA, ré-authentification).
- Sessions : cookie HttpOnly, SameSite=Lax, Secure en HTTPS, identifiant régénéré à la connexion, expiration après 30 min d'inactivité et 8 h au maximum.
- CSRF sur tous les formulaires, requêtes préparées, échappement des sorties.
- En-têtes : CSP stricte sans aucun script, HSTS, anti-framing, `no-store`, `no-referrer`.
- Journal de sécurité (une ligne JSON par événement, sans e-mail ni IP en clair) dans les journaux du serveur.

**RGPD** (détails dans [RGPD.md](RGPD.md))
- Page de confidentialité, export des données en JSON, suppression immédiate du compte.
- Purge automatique : comptes non confirmés après 24 h, comptes inactifs après 3 ans, jetons et compteurs expirés.

## Reste à faire pour la SAE

- Profils public/privé (image, coordonnées).
- Saisie des mots pendant une partie (validation par `moteur/bin/word_check`), salons d'attente, discussion.
- Images de profil : vérification du type réel, taille limitée, ré-encodage, stockage hors de la racine web.
- Discussion : protection XSS, anti-flood, signalement et modération.
- Parties en temps réel : authentification des WebSockets, validation des mots et des scores côté serveur uniquement.
- Alertes automatiques sur le journal de sécurité, et sauvegardes (absentes de l'offre gratuite de Render).
- Changement d'adresse e-mail et de pseudo (rectification).
