#include <stdio.h>
#include "boggle.h"
#include "grille.h"
#include "mots.h"

/* $ grid_path OUI 4 4 G A I R R U V E QU E O T A S M J
 * 10 5 2
 * Valeur de sortie 0 si le mot est dans la grille (cases voisines, chacune au plus une fois), avec
 * le chemin emprunté (indices des cases, depuis 0) ; 1 et aucune sortie sinon. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc < 4) {
        fprintf(stderr, "Usage : %s MOT LIGNES COLONNES CASE...\n", argv[0]);
        return SORTIE_PARAMETRES;
    }
    Grille *g = grille_depuis_args(argc - 2, argv + 2);
    if (!g) {
        return SORTIE_PARAMETRES;
    }
    char mot[MOT_MAX];
    int trace[GRILLE_MAX * GRILLE_MAX], longueur = 0;
    int trouve = normalise(argv[1], mot, sizeof mot) && grille_chemin(g, mot, trace, &longueur);
    if (trouve) {
        for (int i = 0; i < longueur; i++) {
            printf(i ? " %d" : "%d", trace[i]);
        }
        putchar('\n');
    }
    grille_libere(g);
    return trouve ? 0 : 1;
}
