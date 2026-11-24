#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <time.h>
#include "boggle.h"
#include "grille.h"

/* Graine imprévisible : /dev/urandom (Linux) ; à défaut, heure, horloge du processus et adresse de pile
 * (différente à chaque lancement grâce à l'ASLR). L'heure seule donnerait la même grille à deux joueurs
 * qui créent une partie dans la même seconde. */
static uint64_t graine_aleatoire(void)
{
    uint64_t graine = 0;
    FILE *f = fopen("/dev/urandom", "rb");
    if (f) {
        if (fread(&graine, sizeof graine, 1, f) != 1) {
            graine = 0;
        }
        fclose(f);
    }
    int pile;
    graine ^= (uint64_t) time(NULL) ^ ((uint64_t) clock() << 20) ^ ((uint64_t) (uintptr_t) &pile << 7);
    return graine;
}

/* $ grid_build frequences.txt 4 4
 * G A I R R U V E QU E O T A S M J
 * Crée et affiche une grille aléatoire (4 lignes, 4 colonnes) à partir d'un fichier de fréquences.
 * Extensions : « - » à la place du fichier = fréquences du français ; une graine en 4e paramètre
 * redonne toujours la même grille. */
int main(int argc, char *argv[])
{
    if (demande_auteur(argc, argv)) {
        return 255;
    }
    if (argc < 4 || argc > 5) {
        fprintf(stderr, "Usage : %s frequences.txt LIGNES COLONNES [graine]\n", argv[0]);
        return SORTIE_PARAMETRES;
    }
    Grille *g = grille_nouvelle(atoi(argv[2]), atoi(argv[3]));
    if (!g) {
        fprintf(stderr, "Dimensions invalides (1 à %d)\n", GRILLE_MAX);
        return SORTIE_PARAMETRES;
    }
    TableFrequences t;
    if (argv[1][0] == '-' && argv[1][1] == '\0') {
        frequences_defaut(&t);
    } else if (!frequences_charge(argv[1], &t)) {
        fprintf(stderr, "Fichier de fréquences introuvable ou vide : %s\n", argv[1]);
        grille_libere(g);
        return SORTIE_FICHIER;
    }
    uint64_t graine = argc == 5 ? strtoull(argv[4], NULL, 10) : graine_aleatoire();
    grille_aleatoire(g, &t, graine);
    grille_affiche(g, stdout);
    frequences_libere(&t);
    grille_libere(g);
    return 0;
}
