#include <stdio.h>
#include "boggle.h"
#include "grille.h"
#include "lex.h"

static void affiche(const char *mot, void *donnees)
{
    (void) donnees;
    puts(mot);
}

/* Usage : solve dico.lex LIGNES COLONNES CASE1 CASE2 ...
 * Énumère toutes les solutions de la grille (une par ligne, sans doublon),
 * par un parcours simultané de la grille et de l'arbre lexicographique. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc < 4) {
        fprintf(stderr, "Usage : %s dico.lex LIGNES COLONNES CASE...\n", argv[0]);
        return 1;
    }
    Lex *dico = lex_charge(argv[1]);
    if (!dico) {
        fprintf(stderr, "Impossible de lire le dictionnaire %s\n", argv[1]);
        return 1;
    }
    Grille *g = grille_depuis_args(argc - 2, argv + 2);
    if (!g) {
        lex_libere(dico);
        return 1;
    }
    long nb = grille_resout(g, dico, LONGUEUR_MIN, affiche, NULL);
    fprintf(stderr, "%ld solution(s)\n", nb);
    grille_libere(g);
    lex_libere(dico);
    return nb < 0;
}
