#include "grille.h"
#include "mots.h"

#include <stdlib.h>
#include <string.h>

Grille *grille_nouvelle(int lignes, int colonnes)
{
    if (lignes < 1 || colonnes < 1 || lignes > GRILLE_MAX || colonnes > GRILLE_MAX) {
        return NULL;
    }
    Grille *g = malloc(sizeof *g);
    if (!g) {
        return NULL;
    }
    g->lignes = lignes;
    g->colonnes = colonnes;
    g->cases = calloc((size_t) lignes * colonnes, sizeof *g->cases);
    if (!g->cases) {
        free(g);
        return NULL;
    }
    return g;
}

void grille_libere(Grille *g)
{
    if (g) {
        free(g->cases);
        free(g);
    }
}

Grille *grille_depuis_args(int argc, char **argv)
{
    if (argc < 2) {
        fprintf(stderr, "Grille attendue : LIGNES COLONNES puis les cases\n");
        return NULL;
    }
    int l = atoi(argv[0]), c = atoi(argv[1]);
    Grille *g = grille_nouvelle(l, c);
    if (!g) {
        fprintf(stderr, "Dimensions invalides : %s x %s (1 à %d)\n", argv[0], argv[1], GRILLE_MAX);
        return NULL;
    }
    if (argc - 2 != l * c) {
        fprintf(stderr, "Grille %d x %d : %d cases attendues, %d reçues\n", l, c, l * c, argc - 2);
        grille_libere(g);
        return NULL;
    }
    for (int i = 0; i < l * c; i++) {
        char brut[16];
        snprintf(brut, sizeof brut, "%s", argv[2 + i]);
        brut[strcspn(brut, "\r\n")] = '\0';   /* sortie de grid_build sous Windows (CRLF) */
        if (!normalise(brut, g->cases[i], CASE_MAX + 1)) {
            fprintf(stderr, "Case invalide : \"%s\"\n", argv[2 + i]);
            grille_libere(g);
            return NULL;
        }
    }
    return g;
}

void grille_affiche(const Grille *g, FILE *f)
{
    for (int i = 0; i < g->lignes; i++) {
        for (int j = 0; j < g->colonnes; j++) {
            fprintf(f, j ? " %s" : "%s", g->cases[i * g->colonnes + j]);
        }
        fputc('\n', f);
    }
}

/* ---------- Fréquences et tirage ---------- */

/* Fréquences approximatives des lettres en français (en %). Q est remplacé par la case "QU". */
static const Frequence FRANCAIS[] = {
    {"E", 14.7}, {"S", 7.9}, {"A", 7.6}, {"I", 7.5}, {"T", 7.2}, {"N", 7.1}, {"R", 6.6},
    {"U", 6.3},  {"O", 5.8}, {"L", 5.5}, {"D", 3.7}, {"C", 3.3}, {"M", 3.0}, {"P", 3.0},
    {"V", 1.6},  {"F", 1.1}, {"G", 1.0}, {"QU", 1.0}, {"B", 0.9}, {"H", 0.7}, {"J", 0.5},
    {"X", 0.4},  {"Y", 0.3}, {"Z", 0.1}, {"K", 0.05}, {"W", 0.05},
};

void frequences_defaut(TableFrequences *t)
{
    t->nb = (int) (sizeof FRANCAIS / sizeof FRANCAIS[0]);
    t->entrees = malloc(sizeof FRANCAIS);
    memcpy(t->entrees, FRANCAIS, sizeof FRANCAIS);
}

int frequences_charge(const char *chemin, TableFrequences *t)
{
    FILE *f = fopen(chemin, "r");
    if (!f) {
        return 0;
    }
    int cap = 32;
    t->nb = 0;
    t->entrees = malloc((size_t) cap * sizeof *t->entrees);
    char ligne[256], lettres[64];
    double poids;
    while (fgets(ligne, sizeof ligne, f)) {
        if (ligne[0] == '#' || sscanf(ligne, "%63s %lf", lettres, &poids) != 2 || poids <= 0) {
            continue;
        }
        if (t->nb == cap) {
            cap *= 2;
            t->entrees = realloc(t->entrees, (size_t) cap * sizeof *t->entrees);
        }
        if (normalise(lettres, t->entrees[t->nb].lettres, CASE_MAX + 1)) {
            t->entrees[t->nb++].poids = poids;
        }
    }
    fclose(f);
    return t->nb > 0;
}

void frequences_libere(TableFrequences *t)
{
    free(t->entrees);
    t->entrees = NULL;
    t->nb = 0;
}

/* Générateur xorshift64* : même résultat sur toutes les machines pour une même graine. */
static uint64_t suivant(uint64_t *etat)
{
    *etat ^= *etat >> 12;
    *etat ^= *etat << 25;
    *etat ^= *etat >> 27;
    return *etat * 0x2545F4914F6CDD1DULL;
}

void grille_aleatoire(Grille *g, const TableFrequences *t, uint64_t graine)
{
    uint64_t etat = graine ? graine : 0x9E3779B97F4A7C15ULL;
    double total = 0;
    for (int k = 0; k < t->nb; k++) {
        total += t->entrees[k].poids;
    }
    for (int i = 0; i < g->lignes * g->colonnes; i++) {
        double r = (double) (suivant(&etat) >> 11) / 9007199254740992.0 * total;   /* dans [0, total[ */
        int k = 0;
        while (k < t->nb - 1 && r >= t->entrees[k].poids) {
            r -= t->entrees[k].poids;
            k++;
        }
        strcpy(g->cases[i], t->entrees[k].lettres);
    }
}

/* ---------- Recherche d'un mot ---------- */

static int chemin(const Grille *g, int i, const char *reste, char *utilise)
{
    size_t n = strlen(g->cases[i]);
    if (utilise[i] || strncmp(g->cases[i], reste, n) != 0) {
        return 0;
    }
    reste += n;
    if (*reste == '\0') {
        return 1;
    }
    utilise[i] = 1;
    int x = i / g->colonnes, y = i % g->colonnes, trouve = 0;
    for (int dx = -1; dx <= 1 && !trouve; dx++) {
        for (int dy = -1; dy <= 1 && !trouve; dy++) {
            int nx = x + dx, ny = y + dy;
            if ((dx || dy) && nx >= 0 && ny >= 0 && nx < g->lignes && ny < g->colonnes) {
                trouve = chemin(g, nx * g->colonnes + ny, reste, utilise);
            }
        }
    }
    utilise[i] = 0;
    return trouve;
}

int grille_contient(const Grille *g, const char *mot)
{
    char utilise[GRILLE_MAX * GRILLE_MAX] = {0};
    if (*mot == '\0') {
        return 0;
    }
    for (int i = 0; i < g->lignes * g->colonnes; i++) {
        if (chemin(g, i, mot, utilise)) {
            return 1;
        }
    }
    return 0;
}

/* ---------- Solveur : parcours commun de la grille et de l'arbre ---------- */

typedef struct {
    const Grille *g;
    const Lex    *dico;
    Lex          *deja;          /* mots déjà trouvés, pour éviter les doublons */
    char          prefixe[GRILLE_MAX * GRILLE_MAX * CASE_MAX + 1];
    char          utilise[GRILLE_MAX * GRILLE_MAX];
    int           longueur_min;
    long          nb;
    void        (*rappel)(const char *, void *);
    void         *donnees;
} Solveur;

static void explore(Solveur *s, int i, int32_t noeud, size_t l)
{
    if (s->utilise[i]) {
        return;
    }
    /* Avance dans l'arbre avec les lettres de la case (plusieurs pour "QU"). */
    for (const char *c = s->g->cases[i]; *c; c++) {
        noeud = lex_fils(s->dico, noeud, *c);
        if (noeud == LEX_AUCUN) {
            s->prefixe[l] = '\0';
            return;   /* aucun mot ne commence ainsi : on coupe la branche */
        }
        s->prefixe[l++] = *c;
    }
    s->prefixe[l] = '\0';

    if ((int) l >= s->longueur_min && lex_est_mot(s->dico, noeud) && lex_ajoute(s->deja, s->prefixe)) {
        s->nb++;
        s->rappel(s->prefixe, s->donnees);
    }

    s->utilise[i] = 1;
    int x = i / s->g->colonnes, y = i % s->g->colonnes;
    for (int dx = -1; dx <= 1; dx++) {
        for (int dy = -1; dy <= 1; dy++) {
            int nx = x + dx, ny = y + dy;
            if ((dx || dy) && nx >= 0 && ny >= 0 && nx < s->g->lignes && ny < s->g->colonnes) {
                explore(s, nx * s->g->colonnes + ny, noeud, l);
            }
        }
    }
    s->utilise[i] = 0;
    s->prefixe[l] = '\0';
}

long grille_resout(const Grille *g, const Lex *dico, int longueur_min,
                   void (*rappel)(const char *mot, void *donnees), void *donnees)
{
    Solveur *s = calloc(1, sizeof *s);
    if (!s) {
        return -1;
    }
    s->g = g;
    s->dico = dico;
    s->deja = lex_nouveau();
    s->longueur_min = longueur_min;
    s->rappel = rappel;
    s->donnees = donnees;
    for (int i = 0; i < g->lignes * g->colonnes; i++) {
        explore(s, i, LEX_RACINE, 0);
    }
    long nb = s->nb;
    lex_libere(s->deja);
    free(s);
    return nb;
}
