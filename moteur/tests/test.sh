#!/bin/sh
# Tests du moteur, d'après les exemples de interfaceMoteur.pdf et la grille du sujet.
cd "$(dirname "$0")/.." || exit 1
B=bin
# Sous Windows, les programmes écrivent des fins de ligne CRLF : on retire les CR pour comparer.
run() { "$@" | tr -d '\r'; }
code() { "$@" >/dev/null 2>&1; echo $?; }
INTERFACE="4 4 G A I R R U V E QU E O T A S M J"
SUJET="4 4 E U E S O I N I I J E L M I A E"
ECHECS=0

verifie() {   # verifie "description" "attendu" "obtenu"
    if [ "$2" = "$3" ]; then
        echo "ok    $1"
    else
        echo "ECHEC $1 : attendu [$2], obtenu [$3]"
        ECHECS=$((ECHECS + 1))
    fi
}

verifie "dictionnary_build : aucune sortie"   ""  "$(run $B/dictionnary_build tests/mots.txt tests/mots.lex 2>&1)"
verifie "dictionnary_build : fichier absent"  "254" "$(code $B/dictionnary_build tests/absent.txt tests/x.lex)"
verifie "dictionnary_build : paramètres"      "255" "$(code $B/dictionnary_build tests/mots.txt)"
verifie "author"                              "Auteur: Joseph Malet" "$(run $B/solve author)"

verifie "lookup : mot présent -> 0"           "0" "$(code $B/dictionnary_lookup tests/mots.lex BONJOUR)"
verifie "lookup : préfixe -> 1"               "1" "$(code $B/dictionnary_lookup tests/mots.lex BONJ)"
verifie "lookup : absent -> 2"                "2" "$(code $B/dictionnary_lookup tests/mots.lex BONSOIR)"
verifie "lookup : aucune sortie"              ""  "$(run $B/dictionnary_lookup tests/mots.lex BONJOUR)"
verifie "lookup : commentaire ignoré"         "0" "$(code $B/dictionnary_lookup tests/mots.lex CHAT)"
verifie "lookup : ligne avec espace ignorée"  "2" "$(code $B/dictionnary_lookup tests/mots.lex IGNORE)"
verifie "lookup : accents normalisés"         "0" "$(code $B/dictionnary_lookup tests/mots.lex ELEVE)"
verifie "lookup : dictionnaire absent -> 254" "254" "$(code $B/dictionnary_lookup tests/absent.lex OUI)"

verifie "en-tête .lex : taille de l'en-tête"  "16" "$(od -A n -t d4 -N 4 tests/mots.lex | tr -d ' ')"
verifie "en-tête .lex : taille d'une cellule" "12" "$(od -A n -t d4 -j 12 -N 4 tests/mots.lex | tr -d ' ')"

verifie "grid_path OUI (exemple officiel)"    "10 5 2" "$(run $B/grid_path OUI $INTERFACE)"
verifie "grid_path OUI -> 0"                  "0" "$(code $B/grid_path OUI $INTERFACE)"
verifie "grid_path TOIT -> 1, sans sortie"    "1:" "$(code $B/grid_path TOIT $INTERFACE):$(run $B/grid_path TOIT $INTERFACE)"
verifie "grid_path SIENS refusé (sujet)"      "1" "$(code $B/grid_path SIENS $SUJET)"
verifie "grid_path : grille incomplète"       "255" "$(code $B/grid_path OUI 4 4 A B C)"

verifie "solve (exemple officiel)"            "JOUE OUI VIE VIRE" "$(run $B/solve tests/mots.lex 3 $INTERFACE | tr ' ' '\n' | sort | tr '\n' ' ' | sed 's/ $//')"
verifie "solve (grille du sujet)"             "JAI JOIE JOIES JOUE LIE LIEN LIENS MIE MIEL NIE NIES OUI UNE" \
    "$(run $B/solve tests/mots.lex 3 $SUJET | tr ' ' '\n' | sort | tr '\n' ' ' | sed 's/ $//')"
verifie "solve : taille minimale 5"           "JOIES LIENS" "$(run $B/solve tests/mots.lex 5 $SUJET | tr ' ' '\n' | sort | tr '\n' ' ' | sed 's/ $//')"
verifie "solve : case QU"                     "QUAI" "$(run $B/solve tests/mots.lex 3 2 2 QU A I X)"

verifie "score OUI VIE (exemple officiel)"    "2"  "$(run $B/score OUI VIE)"
verifie "score 8 lettres"                     "11" "$(run $B/score ABCDEFGH)"

verifie "grid_build : 16 cases sur une ligne" "16:1" "$(run $B/grid_build data/frequences_fr.txt 4 4 | wc -w | tr -d ' '):$(run $B/grid_build data/frequences_fr.txt 4 4 | wc -l | tr -d ' ')"
verifie "grid_build : graine reproductible"   "$(run $B/grid_build data/frequences_fr.txt 5 5 42)" "$(run $B/grid_build data/frequences_fr.txt 5 5 42)"
verifie "grid_build : fichier absent -> 254"  "254" "$(code $B/grid_build tests/absent.txt 4 4)"

echo
if [ "$ECHECS" -eq 0 ]; then echo "Tous les tests passent."; else echo "$ECHECS test(s) en échec."; fi
exit "$ECHECS"
