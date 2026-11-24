#include <stdio.h>
#include "boggle.h"
#include "lex.h"

/* Usage : dictionary_build mots.txt dico.lex
 * Transforme une liste de mots (un par ligne, UTF-8) en arbre lexicographique.
 * Les mots sont normalisés (majuscules, sans accents) ; ceux qui contiennent
 * autre chose que des lettres sont ignorés. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc != 3) {
        fprintf(stderr, "Usage : %s mots.txt dico.lex\n", argv[0]);
        return 1;
    }
    long nb;
    Lex *lex = lex_depuis_texte(argv[1], &nb);
    if (!lex) {
        perror(argv[1]);
        return 1;
    }
    if (!lex_sauve(lex, argv[2])) {
        perror(argv[2]);
        lex_libere(lex);
        return 1;
    }
    fprintf(stderr, "%ld mots, %ld noeuds -> %s\n", nb, (long) lex->taille, argv[2]);
    lex_libere(lex);
    return 0;
}
