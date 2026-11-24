#include <stdio.h>
#include "boggle.h"

/* Usage : score MOT1 MOT2 ... -> affiche le score total (doublons comptés une fois). */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    char **mots = &argv[1];   /* tous les paramètres passés sauf le premier */
    int nombre = argc - 1;    /* le nombre de mots en paramètre */
    printf("%d\n", score(mots, nombre));
    return 0;
}
