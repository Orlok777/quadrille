#include "mots.h"

#include <string.h>

/* Lettres de base pour les caractères U+00C0 à U+00FF (2e octet UTF-8 après 0xC3). 0 = refusé. */
static const char *latin1(unsigned char o)
{
    static const char *table[64] = {
        "A", "A", "A", "A", "A", "A", "AE", "C", "E", "E", "E", "E", "I", "I", "I", "I",
        0,   "N", "O", "O", "O", "O", "O",  0,   "O", "U", "U", "U", "U", "Y", 0,   0,
        "A", "A", "A", "A", "A", "A", "AE", "C", "E", "E", "E", "E", "I", "I", "I", "I",
        0,   "N", "O", "O", "O", "O", "O",  0,   "O", "U", "U", "U", "U", "Y", 0,   "Y",
    };
    return (o >= 0x80 && o <= 0xBF) ? table[o - 0x80] : 0;
}

int normalise(const char *entree, char *sortie, size_t taille)
{
    const unsigned char *p = (const unsigned char *) entree;
    size_t n = 0;
    while (*p) {
        const char *ajout;
        char lettre[2] = {0, 0};
        if (*p >= 'a' && *p <= 'z') {
            lettre[0] = (char) (*p - 'a' + 'A');
            ajout = lettre;
            p++;
        } else if (*p >= 'A' && *p <= 'Z') {
            lettre[0] = (char) *p;
            ajout = lettre;
            p++;
        } else if (p[0] == 0xC3 && p[1] >= 0x80 && p[1] <= 0xBF) {   /* UTF-8 */
            ajout = latin1(p[1]);
            p += 2;
        } else if (p[0] == 0xC5 && (p[1] == 0x92 || p[1] == 0x93)) {   /* Œ œ en UTF-8 */
            ajout = "OE";
            p += 2;
        } else if (p[0] >= 0xC0) {   /* Latin-1 / Windows-1252 (arguments sous Windows) */
            ajout = latin1((unsigned char) (p[0] - 0x40));
            p++;
        } else if (p[0] == 0x8C || p[0] == 0x9C) {   /* Œ œ en Windows-1252 */
            ajout = "OE";
            p++;
        } else {
            ajout = 0;
        }
        if (!ajout) {
            return 0;
        }
        size_t l = strlen(ajout);
        if (n + l >= taille) {
            return 0;
        }
        memcpy(sortie + n, ajout, l);
        n += l;
    }
    sortie[n] = '\0';
    return n > 0;
}
