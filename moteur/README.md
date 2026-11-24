# Moteur de jeu (module L1, langage C)

Conforme à `interfaceMoteur.pdf` : 6 exécutables, requêtes en ligne de commande, résultats dans la
valeur de sortie ou sur la sortie standard. Structure du squelette fourni : `apps/` (un exécutable par
fichier), `src/` + `include/` (bibliothèques partagées), `Makefile` → `bin/`.

```sh
make          # compile tout dans bin/
make test     # 28 tests, dont les exemples de l'interface
```

Sous Windows avec MSYS2, installer `make` une fois : `pacman -S make`.

## Exécutables

| Commande | Résultat |
|---|---|
| `dictionnary_build dico.txt dico.lex` | Construit l'arbre lexicographique et le sauvegarde. Aucune sortie. |
| `dictionnary_lookup dico.lex BONJOUR` | Valeur de sortie : 0 mot présent, 1 préfixe valide (BONJ), 2 sinon. Aucune sortie. |
| `grid_build frequences.txt 4 4` | Affiche une grille aléatoire sur une ligne : `G A I R R U V E QU E O T A S M J`. |
| `grid_path OUI 4 4 G A I R …` | Affiche le chemin (`10 5 2`, indices depuis 0) et sort avec 0 ; sort avec 1 sans rien afficher si le mot est absent. |
| `solve dico.lex 3 4 4 G A I R …` | Tous les mots de la grille d'au moins 3 lettres, séparés par des espaces, sans doublon. |
| `score OUI VIE` | Score total : `2`. |

Tous affichent l'auteur (et sortent avec 255) quand on les appelle avec le seul paramètre `author`.

**Valeurs de sortie** : 0 succès ; 1, 2 cas courants (mot absent, préfixe) ; **255** paramètres
manquants ou incorrects ; **254** fichier introuvable, illisible ou invalide.

**Extensions** (facultatives, compatibles avec l'interface) : `grid_build - 4 4` utilise les fréquences
du français intégrées ; `grid_build frequences.txt 4 4 42` fixe la graine (même grille à chaque fois).

## Formats de fichiers

- **Dictionnaire texte** : un mot par ligne (A-Z), éventuellement suivi d'un espace et d'un commentaire ;
  une ligne commençant par un espace est ignorée. Les accents sont aussi acceptés et normalisés (é → E).
- **Fréquences** : une lettre ou une paire (`QU`) par ligne, suivie d'un entier positif.
  Fichier du français : [data/frequences_fr.txt](data/frequences_fr.txt). Le module Java en produit un
  à partir du Wiktionary (`CharTableMaker`).
- **Dictionnaire binaire (.lex)** : un en-tête `EnteteLex` de 4 entiers (taille de l'en-tête, nombre de
  mots, nombre de cellules, taille d'une cellule, comme donnés par `sizeof`), puis le tableau des
  cellules `Noeud` (élément, fils, frère). La cellule 0 est la racine, `-1` signifie « aucun ».
  Le chargement vérifie les tailles et que chaque indice reste dans le tableau.

## Bibliothèques

| Fichier | Rôle |
|---|---|
| `mots` | Normalisation : majuscules, sans accents (UTF-8 et Latin-1). Les mots avec tiret, apostrophe ou chiffre sont refusés. |
| `lex` | Arbre lexicographique « fils gauche / frère droit » en tableau, frères triés, `'\0'` en fin de mot ; recherche (présent / préfixe / absent) ; fichier `.lex`. |
| `grille` | Grille L × C (une case peut valoir `QU`), tirage pondéré, recherche d'un mot avec son chemin (8-connexité, chaque case au plus une fois), solveur. |
| `boggle` | Règles : score, longueur minimale, valeurs de sortie, paramètre `author`. |

## Choix techniques

- **Score** (règle officielle du Boggle) : 3 et 4 lettres = 1 point, 5 = 2, 6 = 3, 7 = 5, 8 et plus = 11.
- **Solveur** : parcours simultané de la grille et de l'arbre ; une branche s'arrête dès que le préfixe ne
  correspond à aucun mot. Les doublons sont filtrés par un second arbre des mots déjà trouvés.
- **Tirage** : générateur xorshift64\* initialisé par `/dev/urandom` (ou, à défaut, heure, horloge et
  adresse de pile) : deux parties créées dans la même seconde n'ont pas la même grille.
- **Performances** mesurées : 260 000 mots → 1,1 million de cellules en 0,3 s, grille 10 × 10 résolue en 0,4 s.

## Reste à faire

- Essai avec le dictionnaire français complet (`words.txt` produit par `NormalizedExtractor` en Java).
