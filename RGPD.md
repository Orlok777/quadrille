# Registre des traitements (RGPD)

Projet pédagogique du BUT Informatique, Université Gustave Eiffel.

| Traitement | Données | Finalité | Base légale | Durée de conservation | Destinataires |
|---|---|---|---|---|---|
| Comptes joueurs | Pseudo, e-mail, empreinte du mot de passe, dates de création, de confirmation et de dernière connexion | Fournir le jeu et l'accès au compte | Exécution du service | Jusqu'à la suppression du compte, ou 3 ans sans connexion. Compte non confirmé : 24 h | Hébergeur (Render, Francfort) |
| Historique des parties | Pseudo, identifiant du compte, grille, mots trouvés, score, dates | Déroulement et historique des parties | Exécution du service | Tant que la partie existe. À la suppression d'un compte, le joueur est anonymisé dans les parties des autres (trigger `anonymiser_joueur`) | Hébergeur, autres joueurs de la partie |
| Double authentification | Clé TOTP chiffrée, codes de secours hachés | Protéger le compte | Exécution du service, à la demande du joueur | Jusqu'à la désactivation ou la suppression du compte | Hébergeur |
| E-mails du service | Adresse e-mail, contenu du message | Confirmation d'adresse, réinitialisation, alertes de sécurité | Exécution du service | Selon Brevo (journal d'envoi) | Brevo (France) |
| Limitation des tentatives | Empreinte HMAC de l'IP et de l'identifiant | Bloquer les attaques par force brute | Intérêt légitime (sécurité) | 24 h | Hébergeur |
| Journal de sécurité | Type d'événement, numéro de compte, empreinte tronquée de l'IP | Détecter les attaques | Intérêt légitime (sécurité) | Selon la rétention des journaux de Render | Hébergeur |
| Mots de passe compromis | 5 premiers caractères de l'empreinte SHA-1 | Refuser les mots de passe ayant fuité | Intérêt légitime (sécurité) | Non conservé | Have I Been Pwned |

**Droits des personnes :** accès et portabilité par l'export JSON, effacement par la suppression du compte, toutes deux sur la page « Mon compte ». Pour la rectification et l'opposition, l'adresse de contact est définie par `CONTACT_EMAIL`.

**Cookies :** un seul cookie de session, strictement nécessaire, donc sans bandeau de consentement. Aucune ressource tierce n'est chargée.
