# jdict : gestionnaire de dictionnaire (module L1 bis, Java)

Extrait les définitions d'un dump Wiktionary (français ou anglais) vers un fichier JSONL indexé,
puis permet de les rechercher. Les mots extraits et la table de fréquences des lettres alimentent
le moteur C ([../moteur](../moteur/README.md)).

Java 17, Maven. Bibliothèques : Apache Commons Compress (bzip2), Gson (JSON), StAX et
`RandomAccessFile`, `Deflater`/`Inflater` du JDK.

## Compiler

**Eclipse** : *File > Import > Maven > Existing Maven Projects*, choisir le dossier `jdict`.
Puis clic droit sur le projet > *Run As > Maven build…*, objectif `package`.

**En ligne de commande** : `mvn package`.

Le résultat est le jar lourd `target/jdict.jar`, avec toutes les dépendances. On l'ajoute au CLASSPATH :

```sh
export CLASSPATH=$PWD/target/jdict.jar          # Linux
$env:CLASSPATH = "$PWD\target\jdict.jar"        # PowerShell
sh tests/test.sh                                # 14 tests de bout en bout
```

## Commandes

```sh
# Dump -> definitions.dict + definitions.index (préfixe compressed: pour la compression zlib)
cat frwiktionary-latest-pages-articles.xml.bz2 | java fr.univ_eiffel.DictionaryMaker fr definitions

java fr.univ_eiffel.jdict.DictionarySearcher definitions congres        # entrée exacte
java fr.univ_eiffel.jdict.DictionarySearcher definitions CONGRES        # congres, congrès, Congrès
java fr.univ_eiffel.jdict.DictionarySearcher definitions yaml:CONGRES   # sortie YAML

java fr.univ_eiffel.jdict.NormalizedExtractor definitions > words.txt
java fr.univ_eiffel.jdict.NormalizedExtractor definitions "nom propre,adjectif" > words.txt
java fr.univ_eiffel.jdict.CharTableMaker definitions > chartable.txt

java fr.univ_eiffel.jdict.InteractiveUI definitions    # mode interactif (help pour l'aide)
```

Enchaînement avec le moteur C :

```sh
../moteur/bin/dictionary_build words.txt fr.lex
../moteur/bin/solve fr.lex 4 4 $(../moteur/bin/grid_build 4 4 chartable.txt)
```

## Formats de fichiers

- **Définitions (`.dict`)** : une première ligne JSON de métadonnées
  (`description`, `created_on`, `language`, et `compression` si elle est activée), puis une entrée par ligne :
  `{"title": …, "definitions": {"nom": […], "verbe": […]}}`. En mode compressé, chaque entrée est
  compressée séparément avec zlib, ce qui permet de la relire seule.
- **Index (`.index`)** : `DICTINDX`, puis pour chaque mot la position du premier et du dernier octet de son
  entrée (2 entiers de 4 octets en grand-boutiste). Les mots sont triés par forme normalisée
  (majuscules, sans diacritiques), puis par ordre Unicode : `Congrès < congres < congrès`.
- **Recherche** : dichotomie dans l'index, lecture par `RandomAccessFile`. Une requête en majuscules
  renvoie toutes les entrées de même forme normalisée, qui sont contiguës dans l'index.

## Architecture

| Paquet | Rôle | Point d'extension |
|---|---|---|
| `model` | `Entry`, `WikiPage` (records) | |
| `wiki` | Lecture StAX du dump (bzip2 détecté automatiquement), extraction des définitions | `DefinitionExtractor` : une classe par Wiktionary, enregistrée dans `Extractors` (fr, en) |
| `text` | Normalisation et ordre de tri | |
| `store` | Écriture et lecture du dictionnaire et de l'index | `Codec` : un algorithme de compression, enregistré dans `Codecs` (aucun, zlib). `DictionaryLocation` isole les noms et formats de fichiers |
| `format` | Présentation des résultats | `OutputFormat` : enregistré dans `Formats` (json, yaml), choisi par préfixe |
| `stats` | Formes normalisées, table de fréquences (digrammes détectés automatiquement, ex. QU) | |

Les programmes (`DictionaryMaker`, `DictionarySearcher`…) ne font que lire les arguments et appeler ces paquets.
`InteractiveUI` réutilise les mêmes classes.

**Sécurité** : le parseur XML refuse les DTD et les entités externes (attaques XXE).

## Limites connues

- Testé sur des extraits de dump (`src/test/resources`), pas encore sur le dump complet. Le dump français
  ne pourra être indexé que si le fichier de définitions reste sous 4 Go (positions sur 4 octets).
- Interface JavaFX (facultative) non réalisée.
