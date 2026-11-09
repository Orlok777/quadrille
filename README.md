# Quadrille

Portail de compte d'un jeu de lettres en ligne (préparation SAE S3, R3.01).
PHP 8.2, sans dépendance ni JavaScript. SQLite en local, PostgreSQL en ligne.

## Lancer en local

```bash
cp config.example.php config.php   # puis renseigner "secret"
php -S localhost:8000 -t public
```

En local (`mail_mode` = `log`), les e-mails sont écrits dans `data/mails.log`.

## Mettre en ligne

Sur Render : New > Blueprint, puis choisir ce dépôt. `render.yaml` crée le site et la base PostgreSQL.
Seules `MAIL_FROM` (expéditeur validé chez Brevo) et `BREVO_API_KEY` sont à saisir.

`XFF_HOPS` indique combien d'adresses lire depuis la fin de `X-Forwarded-For` pour trouver l'IP réelle du client. Une mauvaise valeur rend la limitation de débit inefficace.

## Sécurité en place

- Mots de passe : Argon2id (bcrypt à défaut), 12 caractères minimum, liste de mots de passe courants.
- Connexion : message et temps de réponse identiques que le compte existe ou non.
- Limitation de débit par IP, par compte et par couple IP/compte (connexion, inscription, oubli).
- Réinitialisation : jeton aléatoire de 256 bits stocké haché, valable 30 min, à usage unique. Le changement ferme toutes les sessions et déclenche un e-mail d'alerte.
- Sessions : cookie HttpOnly, SameSite=Lax, Secure en HTTPS, identifiant régénéré à la connexion, expiration après inactivité et durée maximale.
- CSRF sur tous les formulaires, requêtes préparées, échappement des sorties.
- En-têtes : CSP stricte sans script, HSTS, anti-framing, `no-store`, `no-referrer`.
- Secrets et base hors du dépôt, et inaccessibles depuis le web.
