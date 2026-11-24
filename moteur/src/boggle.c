#include "boggle.h"
#include "mots.h"

#include <stdio.h>
#include <string.h>

int score_mot(const char *mot)
{
    char norm[MOT_MAX];
    if (!normalise(mot, norm, sizeof norm)) {
        return 0;
    }
    size_t n = strlen(norm);
    if (n < LONGUEUR_MIN) return 0;
    if (n <= 4) return 1;
    if (n == 5) return 2;
    if (n == 6) return 3;
    if (n == 7) return 5;
    return 11;
}

int score(char *mots[], int nombre)
{
    int total = 0;
    for (int i = 0; i < nombre; i++) {
        char a[MOT_MAX];
        int doublon = 0;
        if (!normalise(mots[i], a, sizeof a)) {
            continue;
        }
        for (int j = 0; j < i && !doublon; j++) {
            char b[MOT_MAX];
            doublon = normalise(mots[j], b, sizeof b) && strcmp(a, b) == 0;
        }
        if (!doublon) {
            total += score_mot(a);
        }
    }
    return total;
}

int demande_auteur(int argc, char *argv[])
{
    if (argc == 2 && strcmp("author", argv[1]) == 0) {
        printf("Auteur: %s\n", AUTEURS);
        return 1;
    }
    return 0;
}
