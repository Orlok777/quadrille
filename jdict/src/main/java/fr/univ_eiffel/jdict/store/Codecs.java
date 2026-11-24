package fr.univ_eiffel.jdict.store;

import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.util.Map;
import java.util.Optional;
import java.util.zip.Deflater;
import java.util.zip.DeflaterOutputStream;
import java.util.zip.InflaterInputStream;

/** Registre des codecs disponibles. */
public final class Codecs {

    /** Aucune transformation : les entrées restent des lignes JSON lisibles. */
    public static final Codec NONE = new Codec() {
        @Override
        public String name() {
            return null;
        }

        @Override
        public byte[] encode(byte[] data) {
            return data;
        }

        @Override
        public byte[] decode(byte[] data) {
            return data;
        }
    };

    /** Compression zlib (classes Deflater / Inflater du JDK), entrée par entrée. */
    public static final Codec ZLIB = new Codec() {
        @Override
        public String name() {
            return "zlib";
        }

        @Override
        public byte[] encode(byte[] data) throws IOException {
            var out = new ByteArrayOutputStream(data.length / 2 + 16);
            Deflater deflater = new Deflater(Deflater.BEST_COMPRESSION);
            try (var z = new DeflaterOutputStream(out, deflater)) {
                z.write(data);
            } finally {
                deflater.end();
            }
            return out.toByteArray();
        }

        @Override
        public byte[] decode(byte[] data) throws IOException {
            try (var z = new InflaterInputStream(new ByteArrayInputStream(data))) {
                return z.readAllBytes();
            }
        }
    };

    private static final Map<String, Codec> BY_NAME = Map.of("zlib", ZLIB);

    private Codecs() {
    }

    /** Codec désigné par l'en-tête d'un fichier (null ou absent = aucun). */
    public static Optional<Codec> byName(String name) {
        return name == null ? Optional.of(NONE) : Optional.ofNullable(BY_NAME.get(name));
    }
}
