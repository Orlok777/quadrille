# Quadrille

Portail de compte d'un jeu de lettres en ligne (préparation SAE S3, R3.01).
PHP 8.2, SQLite, sans dépendance ni JavaScript.

## Lancer en local

```bash
cp config.example.php config.php   # puis renseigner "secret"
php -S localhost:8000 -t public
```

En local (`mail_mode` = `log`), les e-mails sont écrits dans `data/mails.log`.

## Mettre en ligne

1. Déposer le projet sur un hébergement PHP avec `pdo_sqlite` (par exemple via `git clone`).
2. Pointer la racine web sur `public/`. Si c'est impossible, laisser la racine du projet : le `.htaccess` redirige tout vers `public/`.
3. Créer `config.php` sur le serveur, avec `app_url` en `https://`, `mail_mode` à `mail`, `mail_from` et un `secret` généré :
   `php -r "echo bin2hex(random_bytes(32));"`
4. Forcer HTTPS dans les réglages de l'hébergeur.
5. Vérifier que `data/` est accessible en écriture par PHP, et que `/data/quadrille.sqlite` répond 403 ou 404.

## Sécurité en place

- Mots de passe : Argon2id (bcrypt à défaut), 12 caractères minimum, liste de mots de passe courants.
- Connexion : message et temps de réponse identiques que le compte existe ou non.
- Limitation de débit par IP, par compte et par couple IP/compte (connexion, inscription, oubli).
- Réinitialisation : jeton aléatoire de 256 bits stocké haché, valable 30 min, à usage unique. Le changement ferme toutes les sessions et déclenche un e-mail d'alerte.
- Sessions : cookie HttpOnly, SameSite=Lax, Secure en HTTPS, identifiant régénéré à la connexion, expiration après inactivité et durée maximale.
- CSRF sur tous les formulaires, requêtes préparées, échappement des sorties.
- En-têtes : CSP stricte sans script, HSTS, anti-framing, `no-store`, `no-referrer`.
- Secrets et base hors du dépôt, et inaccessibles depuis le web.
