#include <stdio.h>
#include "boggle.h"
#include "lex.h"
#include "mots.h"

/* Usage : dictionary_lookup dico.lex MOT1 [MOT2 ...]
 * Affiche « MOT 1 » si le mot est dans le dictionnaire, « MOT 0 » sinon.
 * Code de retour : 0 si tous les mots sont présents, 1 sinon, 2 en cas d'erreur. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc < 3) {
        fprintf(stderr, "Usage : %s dico.lex MOT [MOT...]\n", argv[0]);
        return 2;
    }
    Lex *dico = lex_charge(argv[1]);
    if (!dico) {
        fprintf(stderr, "Impossible de lire le dictionnaire %s\n", argv[1]);
        return 2;
    }
    int tous = 1;
    for (int i = 2; i < argc; i++) {
        char mot[MOT_MAX];
        int present = normalise(argv[i], mot, sizeof mot) && lex_contient(dico, mot);
        printf("%s %d\n", argv[i], present);
        tous &= present;
    }
    lex_libere(dico);
    return tous ? 0 : 1;
}
