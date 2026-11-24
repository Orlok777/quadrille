# --- Configuration du compilateur ---
CC       := gcc
# -MMD génère automatiquement les fichiers de dépendances (.d) pour les .h
CFLAGS   := -Wall -Wextra -Werror -Iinclude -O2 -MMD
LDFLAGS  := 
LDLIBS   := -lm

# --- Dossiers du projet ---
SRC_DIR   := src
APPS_DIR  := apps
BIN_DIR   := bin
OBJ_DIR   := obj

# --- Détection automatique des fichiers ---
# Sources des bibliothèques et leurs objets dans obj/
LIB_SRCS  := $(wildcard $(SRC_DIR)/*.c)
LIB_OBJS  := $(LIB_SRCS:$(SRC_DIR)/%.c=$(OBJ_DIR)/%.o)

# Sources des applications et leurs exécutables cibles dans bin/
APP_SRCS  := $(wildcard $(APPS_DIR)/*.c)
TARGETS   := $(APP_SRCS:$(APPS_DIR)/%.c=$(BIN_DIR)/%)

# Fichiers de dépendances (.d) pour suivre les modifications des .h
DEPS      := $(wildcard $(OBJ_DIR)/*.d)

# --- Règles principales ---
.PHONY: all clean fclean re

# Règle par défaut
all: $(TARGETS)

# Règle pour créer un exécutable dans bin/
$(BIN_DIR)/%: $(OBJ_DIR)/%.o $(LIB_OBJS) | $(BIN_DIR)
	$(CC) $(LDFLAGS) $^ $(LDLIBS) -o $@

# Règle pour les objets des applications dans obj/
$(OBJ_DIR)/%.o: $(APPS_DIR)/%.c | $(OBJ_DIR)
	$(CC) $(CFLAGS) -c $< -o $@

# Règle pour les objets des bibliothèques dans obj/
$(OBJ_DIR)/%.o: $(SRC_DIR)/%.c | $(OBJ_DIR)
	$(CC) $(CFLAGS) -c $< -o $@

# Création des dossiers s'ils n'existent pas
$(BIN_DIR) $(OBJ_DIR):
	mkdir -p $@

# Inclusion des fichiers de dépendances pour les .h (si existants)
-include $(DEPS)

# --- Nettoyage ---
clean:
	rm -rf $(OBJ_DIR)

fclean: clean
	rm -rf $(BIN_DIR) tests/*.lex

re: fclean all

# --- Tests (ajout) ---
.PHONY: test
test: all
	sh tests/test.sh
