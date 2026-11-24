package fr.univ_eiffel.jdict.store;

import java.io.BufferedOutputStream;
import java.io.Closeable;
import java.io.DataOutputStream;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Arrays;
import java.util.List;

/**
 * Fichier d'index : en-tête "DICTINDX", puis pour chaque mot (dans l'ordre {@code WordOrder}) la position
 * du premier et du dernier octet de son entrée dans le fichier de définitions, chacune sur 4 octets en
 * grand-boutiste. Lu par accès aléatoire : l'index n'est jamais chargé entièrement en mémoire.
 */
public final class IndexFile implements Closeable {

    static final byte[] MAGIC = "DICTINDX".getBytes(StandardCharsets.US_ASCII);
    private static final long MAX_POSITION = 0xFFFF_FFFFL;   // 4 octets non signés

    /** Position d'une entrée : premier et dernier octet (inclus). */
    public record Span(long start, long end) {
        public int length() {
            return Math.toIntExact(end - start + 1);
        }
    }

    private final RandomAccessFile file;
    private final int size;

    private IndexFile(RandomAccessFile file, int size) {
        this.file = file;
        this.size = size;
    }

    public static void write(Path path, List<Span> spans) throws IOException {
        try (var out = new DataOutputStream(new BufferedOutputStream(Files.newOutputStream(path), 1 << 16))) {
            out.write(MAGIC);
            for (Span s : spans) {
                if (s.end() > MAX_POSITION) {
                    throw new IOException("Fichier de définitions trop gros pour des positions sur 4 octets");
                }
                out.writeInt((int) s.start());   // DataOutputStream écrit en grand-boutiste
                out.writeInt((int) s.end());
            }
        }
    }

    public static IndexFile open(Path path) throws IOException {
        var file = new RandomAccessFile(path.toFile(), "r");
        try {
            byte[] magic = new byte[MAGIC.length];
            file.readFully(magic);
            long body = file.length() - MAGIC.length;
            if (!Arrays.equals(magic, MAGIC) || body % 8 != 0) {
                throw new IOException(path + " n'est pas un fichier d'index valide");
            }
            return new IndexFile(file, Math.toIntExact(body / 8));
        } catch (IOException | RuntimeException e) {
            file.close();
            throw e;
        }
    }

    public int size() {
        return size;
    }

    public Span span(int i) throws IOException {
        file.seek(MAGIC.length + 8L * i);
        long start = file.readInt() & MAX_POSITION;
        long end = file.readInt() & MAX_POSITION;
        return new Span(start, end);
    }

    @Override
    public void close() throws IOException {
        file.close();
    }
}
