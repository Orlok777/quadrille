#include <stdio.h>
#include <string.h>
#include "boggle.h"
#include "grille.h"
#include "lex.h"
#include "mots.h"

/* Usage : word_check dico.lex LIGNES COLONNES CASE... -- MOT1 [MOT2 ...]
 * Validité d'un mot proposé : il doit être assez long, présent dans la grille ET dans le dictionnaire.
 * Une ligne par mot : « MOT ok SCORE » ou « MOT trop_court | hors_grille | hors_dico 0 ». */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    int sep = 0;
    for (int i = 1; i < argc; i++) {
        if (strcmp(argv[i], "--") == 0) {
            sep = i;
            break;
        }
    }
    if (argc < 5 || sep == 0) {
        fprintf(stderr, "Usage : %s dico.lex LIGNES COLONNES CASE... -- MOT...\n", argv[0]);
        return 2;
    }
    Lex *dico = lex_charge(argv[1]);
    if (!dico) {
        fprintf(stderr, "Impossible de lire le dictionnaire %s\n", argv[1]);
        return 2;
    }
    Grille *g = grille_depuis_args(sep - 2, argv + 2);
    if (!g) {
        lex_libere(dico);
        return 2;
    }
    for (int i = sep + 1; i < argc; i++) {
        char mot[MOT_MAX];
        const char *verdict = "ok";
        if (!normalise(argv[i], mot, sizeof mot) || strlen(mot) < LONGUEUR_MIN) {
            verdict = "trop_court";
        } else if (!grille_contient(g, mot)) {
            verdict = "hors_grille";
        } else if (!lex_contient(dico, mot)) {
            verdict = "hors_dico";
        }
        printf("%s %s %d\n", argv[i], verdict, strcmp(verdict, "ok") == 0 ? score_mot(mot) : 0);
    }
    grille_libere(g);
    lex_libere(dico);
    return 0;
}
