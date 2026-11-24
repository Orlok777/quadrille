#!/bin/sh
# Tests du moteur, sur la grille d'exemple du sujet :
#   E U E S
#   O I N I
#   I J E L
#   M I A E
cd "$(dirname "$0")/.." || exit 1
B=bin
# Sous Windows, les programmes écrivent des fins de ligne CRLF : on retire les CR pour comparer.
run() { "$@" | tr -d '\r'; }
GRILLE="4 4 E U E S O I N I I J E L M I A E"
ECHECS=0

verifie() {   # verifie "description" "attendu" "obtenu"
    if [ "$2" = "$3" ]; then
        echo "ok    $1"
    else
        echo "ECHEC $1 : attendu [$2], obtenu [$3]"
        ECHECS=$((ECHECS + 1))
    fi
}

$B/dictionary_build tests/mots.txt tests/mots.lex 2>/dev/null

verifie "author"                 "Auteur: Joseph Malet" "$(run $B/score author)"
verifie "score JOIE MIEL LIENS"  "4"   "$(run $B/score JOIE MIEL LIENS)"
verifie "score doublons"         "1"   "$(run $B/score joie JOIE)"
verifie "score 8 lettres"        "11"  "$(run $B/score ABCDEFGH)"
verifie "dico : élève normalisé" "ELEVE 1" "$(run $B/dictionary_lookup tests/mots.lex ELEVE)"
verifie "dico : CHAT"            "CHAT 1"  "$(run $B/dictionary_lookup tests/mots.lex CHAT)"
verifie "dico : CHA absent"      "CHA 0"   "$(run $B/dictionary_lookup tests/mots.lex CHA)"
verifie "dico : apostrophe"      "aujourd'hui 0" "$(run $B/dictionary_lookup tests/mots.lex "aujourd'hui")"
verifie "grille : JOIES"         "1"   "$(run $B/grid_path JOIES $GRILLE)"
verifie "grille : SIENS refusé"  "0"   "$(run $B/grid_path SIENS $GRILLE)"
verifie "grille : case QU"       "1"   "$(run $B/grid_path QUAI 2 2 QU A I X)"
verifie "validité"               "MIEL ok 1 SIENS hors_grille 0 CHAT hors_grille 0 NIE ok 1 EUE hors_dico 0 MI trop_court 0" \
    "$(run $B/word_check tests/mots.lex $GRILLE -- MIEL SIENS CHAT NIE EUE MI | tr '\n' ' ' | sed 's/ $//')"
verifie "solveur"                "JAI JOIE JOIES JOUE LIE LIEN LIENS MIE MIEL NIE NIES UNE" \
    "$(run $B/solve tests/mots.lex $GRILLE 2>/dev/null | sort | tr '\n' ' ' | sed 's/ $//')"
verifie "solveur : QU"           "QUAI" "$(run $B/solve tests/mots.lex 2 2 QU A I X 2>/dev/null)"
verifie "grille 5x5 avec graine" "$(run $B/grid_build 5 5 - 42)" "$(run $B/grid_build 5 5 - 42)"
verifie "grille 5x5 : 25 cases"  "25" "$(run $B/grid_build 5 5 | wc -w | tr -d ' ')"

echo
if [ "$ECHECS" -eq 0 ]; then echo "Tous les tests passent."; else echo "$ECHECS test(s) en échec."; fi
exit "$ECHECS"
