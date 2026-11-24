#ifndef LEX_H
#define LEX_H

#include <stdint.h>

/*
 * Arbre lexicographique « fils gauche / frère droit ».
 * Les nœuds sont rangés dans un tableau et désignés par leur indice (-1 = aucun).
 * Le nœud 0 est la racine. Chaque mot se termine par un nœud '\0' ; les frères
 * sont triés par lettre, donc le '\0' (s'il existe) est toujours le premier fils.
 */
typedef struct {
    char    c;
    int32_t fils;
    int32_t frere;
} Noeud;

typedef struct {
    Noeud  *noeuds;
    int32_t taille;
    int32_t capacite;
} Lex;

#define LEX_RACINE 0
#define LEX_AUCUN  (-1)

Lex  *lex_nouveau(void);
void  lex_libere(Lex *lex);

/* Ajoute un mot (déjà normalisé). Renvoie 1 s'il était absent, 0 sinon. */
int   lex_ajoute(Lex *lex, const char *mot);
int   lex_contient(const Lex *lex, const char *mot);

/* Fils de « noeud » portant la lettre c, ou LEX_AUCUN. */
int32_t lex_fils(const Lex *lex, int32_t noeud, char c);
/* Le chemin de la racine à « noeud » forme-t-il un mot ? */
int   lex_est_mot(const Lex *lex, int32_t noeud);

/* Fichier .lex : en-tête "LEX1", nombre de nœuds, puis les nœuds (c, fils, frère). */
int   lex_sauve(const Lex *lex, const char *chemin);
Lex  *lex_charge(const char *chemin);

/* Construit l'arbre à partir d'une liste de mots (un par ligne). nb_mots peut être NULL. */
Lex  *lex_depuis_texte(const char *chemin, long *nb_mots);

#endif
