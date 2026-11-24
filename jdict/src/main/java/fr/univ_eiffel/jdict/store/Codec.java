package fr.univ_eiffel.jdict.store;

import java.io.IOException;

/**
 * Transformation appliquée à chaque entrée avant écriture (compression, par exemple).
 * Ajouter un algorithme = écrire une implémentation et l'enregistrer dans {@link Codecs}.
 */
public interface Codec {

    /** Nom écrit dans l'en-tête du fichier de définitions ("compression"), null si aucune. */
    String name();

    byte[] encode(byte[] data) throws IOException;

    byte[] decode(byte[] data) throws IOException;
}
