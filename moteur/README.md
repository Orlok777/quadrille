# Moteur de jeu (module L1, langage C)

Structure du squelette fourni : `apps/` (un exécutable par fichier, avec `main`),
`src/` + `include/` (bibliothèques partagées), `Makefile` → `bin/`.

```sh
make          # compile tout dans bin/
make test     # 16 tests sur la grille d'exemple du sujet
```

Sous Windows avec MSYS2, installer `make` une fois : `pacman -S make`.

## Bibliothèques

| Fichier | Rôle |
|---|---|
| `mots` | Normalisation : majuscules, sans accents (UTF-8 et Latin-1). Les mots avec tiret, apostrophe ou chiffre sont refusés. |
| `lex` | Arbre lexicographique « fils gauche / frère droit » dans un tableau, frères triés, `'\0'` en fin de mot. Sauvegarde et chargement du fichier `.lex`. |
| `grille` | Grille L × C (une case peut valoir `QU`), tirage pondéré, recherche d'un mot (8-connexité, chaque case au plus une fois), solveur. |
| `boggle` | Règles : score, longueur minimale (3), paramètre `author`. |

## Exécutables

Tous affichent l'auteur et renvoient 255 si on les appelle avec le seul paramètre `author`.
Une grille se passe sous la forme `LIGNES COLONNES CASE1 CASE2 …` (rangée par rangée).

| Commande | Résultat |
|---|---|
| `dictionary_build mots.txt dico.lex` | Construit l'arbre à partir d'une liste de mots (un par ligne). |
| `grid_build L C [freq.txt\|-] [graine]` | Tire une grille. La table contient des lignes `LETTRES poids` ; sans table, les fréquences du français sont utilisées. |
| `dictionary_lookup dico.lex MOT…` | `MOT 1` si le mot est dans le dictionnaire, `MOT 0` sinon. |
| `grid_path MOT L C CASES…` | `1` si le mot peut être formé dans la grille, `0` sinon. |
| `word_check dico.lex L C CASES… -- MOT…` | Validité d'un mot proposé : `ok SCORE`, `trop_court`, `hors_grille` ou `hors_dico`. |
| `score MOT…` | Score total (doublons comptés une fois). |
| `solve dico.lex L C CASES…` | Toutes les solutions, sans doublon. |

Exemple complet :

```sh
bin/dictionary_build tests/mots.txt tests/mots.lex
bin/solve tests/mots.lex 4 4 $(bin/grid_build 4 4)
bin/word_check tests/mots.lex 4 4 E U E S O I N I I J E L M I A E -- MIEL SIENS
```

## Choix techniques

- **Score** (règle officielle du Boggle) : 3 et 4 lettres = 1 point, 5 = 2, 6 = 3, 7 = 5, 8 et plus = 11.
- **Solveur** : parcours simultané de la grille et de l'arbre. Une branche s'arrête dès que le préfixe ne
  correspond à aucun mot. Les doublons sont filtrés par un second arbre des mots déjà trouvés.
- **Fichier `.lex`** : en-tête `LEX1`, nombre de nœuds, puis chaque nœud (lettre, fils, frère) avec
  des entiers sur 4 octets en grand-boutiste. Le fichier est donc portable entre machines et vérifié au chargement.
- **Tirage** : générateur xorshift64\*. Une même graine redonne la même grille sur toutes les machines.
- **Performances** mesurées : 260 000 mots → 1,1 million de nœuds (10 Mo) en 0,3 s, grille 10 × 10 résolue en 0,4 s.

## Reste à faire

- Vérifier les noms et paramètres exacts des exécutables avec `interfaceMoteur.pdf`, non fourni pour l'instant.
- Utiliser la vraie liste de mots (`words.txt`, produite par `NormalizedExtractor` en Java) et la table de fréquences (`CharTableMaker`).
