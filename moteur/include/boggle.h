#pragma once
/* Règles du jeu de Boggle : score et informations communes aux exécutables. */

#define AUTEURS "Joseph Malet"

/* Longueur minimale d'un mot accepté. */
#define LONGUEUR_MIN 3

/* Valeurs de sortie (interface du moteur) : 0 = succès, 1, 2... = cas courants (mot absent...),
 * 255, 254... = anomalies. */
#define SORTIE_PARAMETRES 255   /* paramètres manquants ou incorrects */
#define SORTIE_FICHIER    254   /* fichier introuvable, illisible ou invalide */

/* Score d'un mot selon sa longueur (règle officielle du Boggle) :
 * 3-4 lettres : 1, 5 : 2, 6 : 3, 7 : 5, 8 et plus : 11, moins de 3 : 0. */
int score_mot(const char *mot);

/* Renvoie le score associé à une liste de mots (les doublons ne comptent qu'une fois). */
int score(char *mots[], int nombre);

/* Si l'unique paramètre est "author", affiche les auteurs et renvoie 1. */
int demande_auteur(int argc, char *argv[]);
