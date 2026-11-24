#ifndef GRILLE_H
#define GRILLE_H

#include <stdint.h>
#include <stdio.h>

#include "lex.h"

/* Une case peut contenir plusieurs lettres (ex. "QU"). */
#define CASE_MAX 4
#define GRILLE_MAX 32   /* lignes et colonnes */

typedef struct {
    int lignes, colonnes;
    char (*cases)[CASE_MAX + 1];   /* lignes * colonnes cases, rangées ligne par ligne */
} Grille;

typedef struct {
    char   lettres[CASE_MAX + 1];
    double poids;
} Frequence;

typedef struct {
    Frequence *entrees;
    int        nb;
} TableFrequences;

Grille *grille_nouvelle(int lignes, int colonnes);
void    grille_libere(Grille *g);

/* Lit « L C case1 case2 ... » depuis argv (L*C cases). Renvoie NULL et écrit l'erreur sinon. */
Grille *grille_depuis_args(int argc, char **argv);
/* Toutes les cases sur une ligne, séparées par des espaces. */
void    grille_affiche(const Grille *g, FILE *f);

/* Table de fréquences : fichier « LETTRES entier » par ligne (# = commentaire), ou table française par défaut. */
int  frequences_charge(const char *chemin, TableFrequences *t);
void frequences_defaut(TableFrequences *t);
void frequences_libere(TableFrequences *t);

/* Tire chaque case au hasard en respectant les poids. */
void grille_aleatoire(Grille *g, const TableFrequences *t, uint64_t graine);

/* Le mot (normalisé) peut-il être formé en suivant des cases voisines, chacune au plus une fois ? */
int grille_contient(const Grille *g, const char *mot);
/* Idem, et trace[0..*longueur-1] reçoit les indices des cases utilisées (lignes après lignes, depuis 0). */
int grille_chemin(const Grille *g, const char *mot, int *trace, int *longueur);

/* Appelle « rappel » une fois par mot du dictionnaire présent dans la grille (longueur >= min). Renvoie le nombre de mots. */
long grille_resout(const Grille *g, const Lex *dico, int longueur_min,
                   void (*rappel)(const char *mot, void *donnees), void *donnees);

#endif
