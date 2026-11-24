#!/bin/sh
# Tests de bout en bout sur les extraits de dump de src/test/resources.
# Prérequis : le fat jar target/jdict.jar (Maven : package), ou CLASSPATH déjà renseigné.
cd "$(dirname "$0")/.." || exit 1
RACINE=$(pwd)
CLASSPATH=${CLASSPATH:-$RACINE/target/jdict.jar}
export CLASSPATH
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
cd "$TMP" || exit 1
ECHECS=0

# Sous Windows, java écrit des fins de ligne CRLF : on retire les CR pour comparer.
run() { java "$@" | tr -d '\r'; }

verifie() {   # verifie "description" "attendu" "obtenu"
    if [ "$2" = "$3" ]; then
        echo "ok    $1"
    else
        printf 'ECHEC %s\n  attendu : %s\n  obtenu  : %s\n' "$1" "$2" "$3"
        ECHECS=$((ECHECS + 1))
    fi
}

java fr.univ_eiffel.DictionaryMaker fr definitions < "$RACINE/src/test/resources/extrait-frwiktionary.xml" 2>/dev/null
java fr.univ_eiffel.DictionaryMaker fr compressed:comp < "$RACINE/src/test/resources/extrait-frwiktionary.xml" 2>/dev/null
java fr.univ_eiffel.DictionaryMaker en en < "$RACINE/src/test/resources/extrait-enwiktionary.xml" 2>/dev/null

verifie "en-tête de l'index" "DICTINDX" "$(head -c 8 definitions.index)"
verifie "6 mots retenus (discussion et allemand exclus)" "56" "$(wc -c < definitions.index | tr -d ' ')"
verifie "recherche exacte" '{"title":"congres","definitions":{"nom":["'"''"'Pluriel de'"''"' [[congre#fr|congre]]."]}}' \
    "$(run fr.univ_eiffel.jdict.DictionarySearcher definitions congres)"
verifie "forme normalisée : 3 entrées triées" "Congrès congres congrès" \
    "$(run fr.univ_eiffel.jdict.DictionarySearcher definitions CONGRES | sed 's/^{"title":"\([^"]*\)".*/\1/' | tr '\n' ' ' | sed 's/ $//')"
verifie "sortie YAML" 'title: "quai"' "$(run fr.univ_eiffel.jdict.DictionarySearcher definitions yaml:quai | sed -n 2p)"
verifie "étymologie, exemples et allemand ignorés : 3 noms, 2 verbes" "nom:3 verbe:2" \
    "$(run fr.univ_eiffel.jdict.DictionarySearcher definitions yaml:plage \
        | awk '/^  "/ { if (n) printf "%s:%d ", n, c; n = $1; gsub(/[":]/, "", n); c = 0 } /^    - / { c++ } END { printf "%s:%d", n, c }')"
verifie "mot absent : code 1" "1" "$(java fr.univ_eiffel.jdict.DictionarySearcher definitions maison 2>/dev/null; echo $?)"
verifie "formes normalisées" "BEAU CONGRES PLAGE QUAI" "$(run fr.univ_eiffel.jdict.NormalizedExtractor definitions | tr '\n' ' ' | sed 's/ $//')"
verifie "filtre par nature" "BEAU CONGRES" "$(run fr.univ_eiffel.jdict.NormalizedExtractor definitions 'nom propre,adjectif' | tr '\n' ' ' | sed 's/ $//')"
verifie "digramme QU" "1" "$(run fr.univ_eiffel.jdict.CharTableMaker definitions | grep -c '^QU ')"
verifie "compression : en-tête" "zlib" "$(head -1 comp.dict | sed 's/.*"compression":"\([^"]*\)".*/\1/')"
verifie "compression : même résultat" "$(run fr.univ_eiffel.jdict.DictionarySearcher definitions CONGRES)" \
    "$(run fr.univ_eiffel.jdict.DictionarySearcher comp CONGRES)"
verifie "Wiktionary anglais" "noun verb" \
    "$(run fr.univ_eiffel.jdict.DictionarySearcher en beach | grep -o '"[a-z]*":\[' | tr -d '":[' | tr '\n' ' ' | sed 's/ $//')"
verifie "mode interactif" "BEAU" "$(printf 'words adjectif\nquit\n' | run fr.univ_eiffel.jdict.InteractiveUI definitions | grep -o 'BEAU')"

echo
if [ "$ECHECS" -eq 0 ]; then echo "Tous les tests passent."; else echo "$ECHECS test(s) en échec."; fi
exit "$ECHECS"
