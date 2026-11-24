#include <stdio.h>
#include "boggle.h"
#include "grille.h"
#include "mots.h"

/* Usage : grid_path MOT LIGNES COLONNES CASE1 CASE2 ...
 * Affiche 1 si MOT peut être formé dans la grille (cases voisines, 8-connexité,
 * chaque case au plus une fois), 0 sinon. Le code de retour suit le résultat (0 = trouvé). */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc < 4) {
        fprintf(stderr, "Usage : %s MOT LIGNES COLONNES CASE...\n", argv[0]);
        return 2;
    }
    Grille *g = grille_depuis_args(argc - 2, argv + 2);
    if (!g) {
        return 2;
    }
    char mot[MOT_MAX];
    int trouve = normalise(argv[1], mot, sizeof mot) && grille_contient(g, mot);
    printf("%d\n", trouve);
    grille_libere(g);
    return trouve ? 0 : 1;
}
