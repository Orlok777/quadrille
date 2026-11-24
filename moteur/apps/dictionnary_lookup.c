#include <stdio.h>
#include "boggle.h"
#include "lex.h"
#include "mots.h"

/* $ dictionnary_lookup dico.lex BONJOUR
 * Valeur de sortie : 0 si le mot est dans le dictionnaire, 1 si c'est un préfixe valide d'un mot
 * présent (BONJ), 2 sinon. Aucune sortie. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc != 3) {
        fprintf(stderr, "Usage : %s dico.lex MOT\n", argv[0]);
        return SORTIE_PARAMETRES;
    }
    Lex *dico = lex_charge(argv[1]);
    if (!dico) {
        fprintf(stderr, "Dictionnaire introuvable ou invalide : %s\n", argv[1]);
        return SORTIE_FICHIER;
    }
    char mot[MOT_MAX];
    int resultat = normalise(argv[2], mot, sizeof mot) ? lex_cherche(dico, mot) : LEX_ABSENT;
    lex_libere(dico);
    return resultat;
}
