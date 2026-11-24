#include "lex.h"
#include "mots.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

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
    memset(&lex->noeuds[i], 0, sizeof lex->noeuds[i]);   /* octets de remplissage à zéro dans le fichier */
    lex->noeuds[i].element = c;
    lex->noeuds[i].fils = LEX_AUCUN;
    lex->noeuds[i].frere = frere;
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
        if (lex->noeuds[f].element == c) {
            return f;
        }
        if (lex->noeuds[f].element > c) {
            break;   /* frères triés : inutile d'aller plus loin */
        }
    }
    return LEX_AUCUN;
}

int lex_est_mot(const Lex *lex, int32_t noeud)
{
    int32_t f = lex->noeuds[noeud].fils;
    return f != LEX_AUCUN && lex->noeuds[f].element == '\0';
}

/* Cherche le fils c de « parent », le crée à sa place (ordre trié) s'il manque. */
static int32_t fils_ou_cree(Lex *lex, int32_t parent, char c, int *cree)
{
    int32_t prec = LEX_AUCUN;
    int32_t cour = lex->noeuds[parent].fils;
    while (cour != LEX_AUCUN && lex->noeuds[cour].element < c) {
        prec = cour;
        cour = lex->noeuds[cour].frere;
    }
    if (cour != LEX_AUCUN && lex->noeuds[cour].element == c) {
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
            lex->nb_mots += cree;
            return cree;
        }
    }
}

int lex_cherche(const Lex *lex, const char *mot)
{
    if (*mot == '\0') {
        return LEX_ABSENT;
    }
    int32_t n = LEX_RACINE;
    for (const char *p = mot; *p; p++) {
        n = lex_fils(lex, n, *p);
        if (n == LEX_AUCUN) {
            return LEX_ABSENT;
        }
    }
    return lex_est_mot(lex, n) ? LEX_PRESENT : LEX_PREFIXE;
}

int lex_contient(const Lex *lex, const char *mot)
{
    return lex_cherche(lex, mot) == LEX_PRESENT;
}

int lex_sauve(const Lex *lex, const char *chemin)
{
    FILE *f = fopen(chemin, "wb");
    if (!f) {
        return 0;
    }
    EnteteLex entete = {
        .taille_entete  = (int32_t) sizeof(EnteteLex),
        .nb_mots        = lex->nb_mots,
        .nb_cellules    = lex->taille,
        .taille_cellule = (int32_t) sizeof(Noeud),
    };
    int ok = fwrite(&entete, sizeof entete, 1, f) == 1
          && fwrite(lex->noeuds, sizeof(Noeud), (size_t) lex->taille, f) == (size_t) lex->taille;
    return fclose(f) == 0 && ok;
}

Lex *lex_charge(const char *chemin)
{
    FILE *f = fopen(chemin, "rb");
    if (!f) {
        return NULL;
    }
    EnteteLex e;
    Lex *lex = NULL;
    /* L'en-tête peut être plus grand que le nôtre (champs ajoutés) : on saute le reste. */
    if (fread(&e, sizeof e, 1, f) != 1 || e.taille_entete < (int32_t) sizeof e
        || e.taille_cellule != (int32_t) sizeof(Noeud) || e.nb_cellules < 1 || e.nb_mots < 0
        || fseek(f, e.taille_entete, SEEK_SET) != 0) {
        goto fin;
    }
    lex = calloc(1, sizeof *lex);
    lex->noeuds = malloc((size_t) e.nb_cellules * sizeof(Noeud));
    if (!lex->noeuds || fread(lex->noeuds, sizeof(Noeud), (size_t) e.nb_cellules, f) != (size_t) e.nb_cellules) {
        goto erreur;   /* fichier tronqué */
    }
    lex->taille = lex->capacite = e.nb_cellules;
    lex->nb_mots = e.nb_mots;
    for (int32_t i = 0; i < lex->taille; i++) {   /* indices hors du tableau : fichier corrompu */
        const Noeud *n = &lex->noeuds[i];
        if (n->fils < LEX_AUCUN || n->fils >= lex->taille || n->frere < LEX_AUCUN || n->frere >= lex->taille) {
            goto erreur;
        }
    }
    goto fin;
erreur:
    lex_libere(lex);
    lex = NULL;
fin:
    fclose(f);
    return lex;
}

Lex *lex_depuis_texte(const char *chemin)
{
    FILE *f = fopen(chemin, "r");
    if (!f) {
        return NULL;
    }
    Lex *lex = lex_nouveau();
    char ligne[512], mot[MOT_MAX];
    while (fgets(ligne, sizeof ligne, f)) {
        if (ligne[0] == ' ') {
            continue;   /* ligne ignorée */
        }
        ligne[strcspn(ligne, " \t\r\n")] = '\0';   /* le mot s'arrête au premier espace (commentaire) */
        if (normalise(ligne, mot, sizeof mot)) {
            lex_ajoute(lex, mot);
        }
    }
    fclose(f);
    return lex;
}
