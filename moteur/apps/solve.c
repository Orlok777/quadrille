#include <stdio.h>
#include <stdlib.h>
#include "boggle.h"
#include "grille.h"
#include "lex.h"

static void affiche(const char *mot, void *donnees)
{
    int *premier = donnees;
    printf(*premier ? "%s" : " %s", mot);
    *premier = 0;
}

/* $ solve dico.lex 3 4 4 G A I R R U V E QU E O T A S M J
 * OUI VIE VIRE ...
 * Tous les mots du dictionnaire présents dans la grille, de taille au moins 3, séparés par des
 * espaces et sans doublon (parcours simultané de la grille et de l'arbre lexicographique). */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc < 5) {
        fprintf(stderr, "Usage : %s dico.lex TAILLE_MIN LIGNES COLONNES CASE...\n", argv[0]);
        return SORTIE_PARAMETRES;
    }
    char *fin;
    long taille_min = strtol(argv[2], &fin, 10);
    if (*fin != '\0' || taille_min < 1) {
        fprintf(stderr, "Taille minimale invalide : %s\n", argv[2]);
        return SORTIE_PARAMETRES;
    }
    Grille *g = grille_depuis_args(argc - 3, argv + 3);
    if (!g) {
        return SORTIE_PARAMETRES;
    }
    Lex *dico = lex_charge(argv[1]);
    if (!dico) {
        fprintf(stderr, "Dictionnaire introuvable ou invalide : %s\n", argv[1]);
        grille_libere(g);
        return SORTIE_FICHIER;
    }
    int premier = 1;
    grille_resout(g, dico, (int) taille_min, affiche, &premier);
    putchar('\n');
    grille_libere(g);
    lex_libere(dico);
    return 0;
}
