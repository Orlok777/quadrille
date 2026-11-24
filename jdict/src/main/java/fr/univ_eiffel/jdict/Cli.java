package fr.univ_eiffel.jdict;

import java.io.FileDescriptor;
import java.io.FileOutputStream;
import java.io.PrintStream;
import java.nio.charset.StandardCharsets;

/** Outils communs aux programmes en ligne de commande. */
final class Cli {

    /** Sortie standard en UTF-8, quel que soit l'encodage par défaut du système (Windows notamment). */
    static final PrintStream OUT = new PrintStream(new FileOutputStream(FileDescriptor.out), false, StandardCharsets.UTF_8);

    private Cli() {
    }

    static void fail(String message) {
        System.err.println(message);
        System.exit(1);
    }
}
