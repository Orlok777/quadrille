#include <stdio.h>
#include "boggle.h"
#include "lex.h"

/* $ dictionnary_build dico.txt dico.lex
 * Construit un arbre lexicographique à partir d'un dictionnaire texte et le sauvegarde dans un
 * fichier binaire. Aucune sortie. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc != 3) {
        fprintf(stderr, "Usage : %s dico.txt dico.lex\n", argv[0]);
        return SORTIE_PARAMETRES;
    }
    Lex *lex = lex_depuis_texte(argv[1]);
    if (!lex) {
        perror(argv[1]);
        return SORTIE_FICHIER;
    }
    int ok = lex_sauve(lex, argv[2]);
    lex_libere(lex);
    if (!ok) {
        perror(argv[2]);
        return SORTIE_FICHIER;
    }
    return 0;
}
