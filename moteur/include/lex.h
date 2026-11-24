#ifndef LEX_H
#define LEX_H

#include <stdint.h>

/*
 * Arbre lexicographique « fils gauche / frère droit », implémentation statique : les cellules sont
 * rangées dans un tableau et désignées par leur indice (-1 = aucune).
 * La cellule 0 est la racine. Chaque mot se termine par une cellule '\0' ; les frères sont triés
 * par lettre, donc le '\0' (s'il existe) est toujours le premier fils.
 */
typedef struct {
    char    element;
    int32_t fils;
    int32_t frere;
} Noeud;

typedef struct {
    Noeud  *noeuds;
    int32_t taille;     /* nombre de cellules utilisées */
    int32_t capacite;
    int32_t nb_mots;
} Lex;

/*
 * En-tête du fichier .lex (interface du moteur) : taille de l'en-tête, nombre de mots,
 * nombre de cellules et taille d'une cellule, puis le tableau des cellules.
 */
typedef struct {
    int32_t taille_entete;
    int32_t nb_mots;
    int32_t nb_cellules;
    int32_t taille_cellule;
} EnteteLex;

#define LEX_RACINE 0
#define LEX_AUCUN  (-1)

/* Résultats de lex_cherche (= valeurs de sortie de dictionnary_lookup). */
#define LEX_PRESENT 0
#define LEX_PREFIXE 1
#define LEX_ABSENT  2

Lex  *lex_nouveau(void);
void  lex_libere(Lex *lex);

/* Ajoute un mot (déjà normalisé). Renvoie 1 s'il était absent, 0 sinon. */
int   lex_ajoute(Lex *lex, const char *mot);
int   lex_contient(const Lex *lex, const char *mot);
/* LEX_PRESENT, LEX_PREFIXE (début d'un mot présent) ou LEX_ABSENT. */
int   lex_cherche(const Lex *lex, const char *mot);

/* Fils de « noeud » portant la lettre c, ou LEX_AUCUN. */
int32_t lex_fils(const Lex *lex, int32_t noeud, char c);
/* Le chemin de la racine à « noeud » forme-t-il un mot ? */
int   lex_est_mot(const Lex *lex, int32_t noeud);

int   lex_sauve(const Lex *lex, const char *chemin);
Lex  *lex_charge(const char *chemin);

/*
 * Construit l'arbre à partir d'un dictionnaire texte : un mot par ligne, éventuellement suivi d'un
 * espace et d'un commentaire ; une ligne commençant par un espace est ignorée.
 */
Lex  *lex_depuis_texte(const char *chemin);

#endif
