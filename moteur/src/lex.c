#include "lex.h"
#include "mots.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static const char MAGIQUE[4] = {'L', 'E', 'X', '1'};

static int32_t nouveau_noeud(Lex *lex, char c, int32_t frere)
{
    if (lex->taille == lex->capacite) {
        int32_t cap = lex->capacite ? lex->capacite * 2 : 1024;
        Noeud *n = realloc(lex->noeuds, (size_t) cap * sizeof *n);
        if (!n) {
            perror("realloc");
            exit(EXIT_FAILURE);
        }
        lex->noeuds = n;
        lex->capacite = cap;
    }
    int32_t i = lex->taille++;
    lex->noeuds[i] = (Noeud) {c, LEX_AUCUN, frere};
    return i;
}

Lex *lex_nouveau(void)
{
    Lex *lex = calloc(1, sizeof *lex);
    if (!lex) {
        return NULL;
    }
    nouveau_noeud(lex, '\0', LEX_AUCUN);   /* racine */
    return lex;
}

void lex_libere(Lex *lex)
{
    if (lex) {
        free(lex->noeuds);
        free(lex);
    }
}

int32_t lex_fils(const Lex *lex, int32_t noeud, char c)
{
    for (int32_t f = lex->noeuds[noeud].fils; f != LEX_AUCUN; f = lex->noeuds[f].frere) {
        if (lex->noeuds[f].c == c) {
            return f;
        }
        if (lex->noeuds[f].c > c) {
            break;   /* frères triés : inutile d'aller plus loin */
        }
    }
    return LEX_AUCUN;
}

int lex_est_mot(const Lex *lex, int32_t noeud)
{
    int32_t f = lex->noeuds[noeud].fils;
    return f != LEX_AUCUN && lex->noeuds[f].c == '\0';
}

/* Cherche le fils c de « parent », le crée à sa place (ordre trié) s'il manque. */
static int32_t fils_ou_cree(Lex *lex, int32_t parent, char c, int *cree)
{
    int32_t prec = LEX_AUCUN;
    int32_t cour = lex->noeuds[parent].fils;
    while (cour != LEX_AUCUN && lex->noeuds[cour].c < c) {
        prec = cour;
        cour = lex->noeuds[cour].frere;
    }
    if (cour != LEX_AUCUN && lex->noeuds[cour].c == c) {
        *cree = 0;
        return cour;
    }
    int32_t n = nouveau_noeud(lex, c, cour);   /* peut déplacer le tableau : on n'a gardé que des indices */
    if (prec == LEX_AUCUN) {
        lex->noeuds[parent].fils = n;
    } else {
        lex->noeuds[prec].frere = n;
    }
    *cree = 1;
    return n;
}

int lex_ajoute(Lex *lex, const char *mot)
{
    int32_t n = LEX_RACINE;
    int cree = 0;
    for (const char *p = mot;; p++) {
        n = fils_ou_cree(lex, n, *p, &cree);
        if (*p == '\0') {
            return cree;
        }
    }
}

int lex_contient(const Lex *lex, const char *mot)
{
    int32_t n = LEX_RACINE;
    for (const char *p = mot; *p; p++) {
        n = lex_fils(lex, n, *p);
        if (n == LEX_AUCUN) {
            return 0;
        }
    }
    return lex_est_mot(lex, n);
}

/* Entiers écrits octet par octet (grand-boutiste) : le fichier est portable d'une machine à l'autre. */
static int ecrit_i32(FILE *f, int32_t v)
{
    uint32_t u = (uint32_t) v;
    unsigned char o[4] = {u >> 24, u >> 16, u >> 8, u};
    return fwrite(o, 1, 4, f) == 4;
}

static int lit_i32(FILE *f, int32_t *v)
{
    unsigned char o[4];
    if (fread(o, 1, 4, f) != 4) {
        return 0;
    }
    *v = (int32_t) ((uint32_t) o[0] << 24 | (uint32_t) o[1] << 16 | (uint32_t) o[2] << 8 | o[3]);
    return 1;
}

int lex_sauve(const Lex *lex, const char *chemin)
{
    FILE *f = fopen(chemin, "wb");
    if (!f) {
        return 0;
    }
    int ok = fwrite(MAGIQUE, 1, 4, f) == 4 && ecrit_i32(f, lex->taille);
    for (int32_t i = 0; ok && i < lex->taille; i++) {
        const Noeud *n = &lex->noeuds[i];
        ok = fputc(n->c, f) != EOF && ecrit_i32(f, n->fils) && ecrit_i32(f, n->frere);
    }
    return fclose(f) == 0 && ok;
}

Lex *lex_charge(const char *chemin)
{
    FILE *f = fopen(chemin, "rb");
    if (!f) {
        return NULL;
    }
    char magique[4];
    int32_t taille;
    Lex *lex = NULL;
    if (fread(magique, 1, 4, f) != 4 || memcmp(magique, MAGIQUE, 4) != 0 || !lit_i32(f, &taille) || taille < 1) {
        goto fin;
    }
    lex = calloc(1, sizeof *lex);
    lex->noeuds = malloc((size_t) taille * sizeof *lex->noeuds);
    if (!lex->noeuds) {
        goto erreur;
    }
    lex->taille = lex->capacite = taille;
    for (int32_t i = 0; i < taille; i++) {
        Noeud *n = &lex->noeuds[i];
        int c = fgetc(f);
        if (c == EOF || !lit_i32(f, &n->fils) || !lit_i32(f, &n->frere)
            || n->fils < LEX_AUCUN || n->fils >= taille || n->frere < LEX_AUCUN || n->frere >= taille) {
            goto erreur;   /* fichier tronqué ou corrompu */
        }
        n->c = (char) c;
    }
    goto fin;
erreur:
    lex_libere(lex);
    lex = NULL;
fin:
    fclose(f);
    return lex;
}

Lex *lex_depuis_texte(const char *chemin, long *nb_mots)
{
    FILE *f = fopen(chemin, "r");
    if (!f) {
        return NULL;
    }
    Lex *lex = lex_nouveau();
    char ligne[512], mot[MOT_MAX];
    long n = 0;
    while (fgets(ligne, sizeof ligne, f)) {
        ligne[strcspn(ligne, "\r\n")] = '\0';
        if (normalise(ligne, mot, sizeof mot) && lex_ajoute(lex, mot)) {
            n++;
        }
    }
    fclose(f);
    if (nb_mots) {
        *nb_mots = n;
    }
    return lex;
}
