#include <stdio.h>
#include <stdlib.h>
#include <time.h>
#include "boggle.h"
#include "grille.h"

/* Usage : grid_build LIGNES COLONNES [frequences.txt] [graine]
 * Tire une grille au hasard selon la table de fréquences (« LETTRES poids » par ligne),
 * ou selon les fréquences du français si aucun fichier n'est donné (ou « - »).
 * Une graine fixe redonne toujours la même grille. Sortie : une ligne par rangée. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc < 3 || argc > 5) {
        fprintf(stderr, "Usage : %s LIGNES COLONNES [frequences.txt|-] [graine]\n", argv[0]);
        return 1;
    }
    Grille *g = grille_nouvelle(atoi(argv[1]), atoi(argv[2]));
    if (!g) {
        fprintf(stderr, "Dimensions invalides (1 à %d)\n", GRILLE_MAX);
        return 1;
    }
    TableFrequences t;
    if (argc >= 4 && argv[3][0] != '-') {
        if (!frequences_charge(argv[3], &t)) {
            fprintf(stderr, "Table de fréquences illisible ou vide : %s\n", argv[3]);
            grille_libere(g);
            return 1;
        }
    } else {
        frequences_defaut(&t);
    }
    uint64_t graine = argc == 5 ? strtoull(argv[4], NULL, 10)
                                : (uint64_t) time(NULL) ^ ((uint64_t) clock() << 32);
    grille_aleatoire(g, &t, graine);
    grille_affiche(g, stdout);
    frequences_libere(&t);
    grille_libere(g);
    return 0;
}
