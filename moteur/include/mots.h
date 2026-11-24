#ifndef MOTS_H
#define MOTS_H

#include <stddef.h>

#define MOT_MAX 64

/*
 * Normalise un mot : majuscules, sans accents (UTF-8 : é -> E, ç -> C, œ -> OE...).
 * Renvoie 1 si le mot n'est fait que de lettres, 0 sinon (tiret, apostrophe, espace,
 * chiffre, mot vide ou trop long) : ces mots ne sont pas jouables au Boggle.
 */
int normalise(const char *entree, char *sortie, size_t taille);

#endif
