package fr.univ_eiffel.jdict.store;

import java.nio.file.Path;

/**
 * Désignation d'un dictionnaire en ligne de commande : « definitions » désigne definitions.dict et
 * definitions.index ; le préfixe « compressed: » demande la compression zlib des entrées.
 */
public record DictionaryLocation(Path definitions, Path index, Codec codec) {

    public static final String DEFINITIONS_EXTENSION = ".dict";
    public static final String INDEX_EXTENSION = ".index";
    private static final String COMPRESSED_PREFIX = "compressed:";

    public static DictionaryLocation parse(String argument) {
        Codec codec = Codecs.NONE;
        String base = argument;
        if (base.startsWith(COMPRESSED_PREFIX)) {
            codec = Codecs.ZLIB;
            base = base.substring(COMPRESSED_PREFIX.length());
        }
        return new DictionaryLocation(Path.of(base + DEFINITIONS_EXTENSION), Path.of(base + INDEX_EXTENSION), codec);
    }
}
