# Sécurité du projet Quadrille

Site PHP 8.2 sans framework ni JavaScript, base PostgreSQL, hébergé sur Render (HTTPS). Moteur de jeu en C appelé par le serveur.

## Comptes et authentification
- **Mots de passe** : hachage Argon2id (`password_hash`), 12 caractères minimum, refus des mots de passe courants et de ceux qui ont fuité (Have I Been Pwned par k-anonymat : seuls 5 caractères de l'empreinte SHA-1 quittent le serveur).
- **Inscription** : adresse e-mail confirmée avant la première connexion. Même réponse que l'adresse soit libre ou déjà prise : impossible de savoir qui est inscrit.
- **Connexion** : message et temps de réponse identiques que le compte existe ou non (hachage factice). Double authentification TOTP facultative, avec clé chiffrée en AES-256-GCM, anti-rejeu et 8 codes de secours hachés à usage unique.
- **Mot de passe oublié** : jeton aléatoire de 256 bits, stocké haché (SHA-256), valable 30 min, à usage unique. Le changement ferme toutes les sessions et déclenche un e-mail d'alerte.
- **Actions sensibles** (changer le mot de passe, gérer la 2FA, exporter ou supprimer le compte) : le mot de passe est redemandé.

## Attaques web
- **Force brute** : limitation de débit par IP, par compte et par couple IP/compte. Les compteurs sont des HMAC, sans IP ni identifiant en clair. L'IP réelle est lue depuis la fin de `X-Forwarded-For` (proxys de confiance comptés).
- **Sessions** : cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS. Identifiant régénéré à la connexion, mode strict, expiration après 30 min d'inactivité et 8 h au maximum. Toutes les sessions sont invalidées au changement de mot de passe.
- **CSRF** : jeton de session vérifié avec `hash_equals` sur tous les formulaires POST, déconnexion comprise.
- **Injection SQL** : uniquement des requêtes préparées, y compris pour les données JSON (`?::jsonb`).
- **XSS** : toutes les sorties échappées (`htmlspecialchars`). CSP stricte `default-src 'none'` : aucun script, aucune ressource externe.
- **Contrôle d'accès (IDOR)** : une partie n'est visible que par ses joueurs, et la vérification est faite en base à chaque requête. Une partie inexistante et une partie d'un autre joueur donnent la même 404.
- **Injection de commande** : le moteur C est lancé par `proc_open` avec un tableau d'arguments, donc sans shell. Sa sortie est validée avant d'être stockée.
- **En-têtes** : HSTS, `X-Frame-Options: DENY` et `frame-ancestors 'none'` (anti-clickjacking), `nosniff`, `Referrer-Policy: no-referrer` (le jeton de réinitialisation ne fuit pas), `Cache-Control: no-store`. Versions de PHP et d'Apache masquées.
- **Création de parties** : limitée à 20 par heure et par compte.

## Serveur et données
- Seul `public/` est servi. Le code, la configuration, le SQL, le moteur et les outils sont hors de la racine web.
- Les secrets (clé de l'application, base de données, API d'e-mail) sont dans des variables d'environnement, jamais dans le dépôt (`config.php` est ignoré par git).
- La base PostgreSQL n'est joignable que depuis les services Render. Le schéma vérifie lui-même la structure des parties (contraintes `CHECK` sur le JSON).
- Les erreurs détaillées vont dans le journal du serveur. L'utilisateur ne voit qu'un message générique.
- **Journal de sécurité** : une ligne JSON par événement (connexion, échec, CSRF refusé, limite atteinte…), sans e-mail ni IP en clair.

## RGPD
Page de confidentialité, export des données en JSON (parties comprises), suppression immédiate du compte. Un trigger anonymise le joueur supprimé dans les parties des autres. Purge automatique des comptes non confirmés (24 h) ou inactifs (3 ans). Détails dans `RGPD.md`.

## Reste à traiter
Profils publics (images : type réel, taille, ré-encodage), discussion (XSS, anti-flood, modération), parties en temps réel (scores validés uniquement côté serveur), sauvegardes de la base.
