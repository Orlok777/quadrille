package fr.univ_eiffel.jdict.store;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.text.WordNormalizer;
import fr.univ_eiffel.jdict.text.WordOrder;

import java.io.ByteArrayOutputStream;
import java.io.Closeable;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Comparator;
import java.util.List;
import java.util.Optional;
import java.util.function.Consumer;

/**
 * Dictionnaire ouvert en lecture : recherche par dichotomie dans l'index, lecture des entrées par
 * accès aléatoire (RandomAccessFile) dans le fichier de définitions.
 */
public final class Dictionary implements Closeable {

    private final RandomAccessFile definitions;
    private final IndexFile index;
    private final Header header;
    private final Codec codec;

    private Dictionary(RandomAccessFile definitions, IndexFile index, Header header, Codec codec) {
        this.definitions = definitions;
        this.index = index;
        this.header = header;
        this.codec = codec;
    }

    public static Dictionary open(DictionaryLocation location) throws IOException {
        var file = new RandomAccessFile(location.definitions().toFile(), "r");
        try {
            Header header = Json.headerFromJson(readHeaderLine(file));
            // Le codec vient de l'en-tête du fichier : pas besoin de redire « compressed: » à la lecture.
            Codec codec = Codecs.byName(header.compression())
                    .orElseThrow(() -> new IOException("Compression inconnue : " + header.compression()));
            return new Dictionary(file, IndexFile.open(location.index()), header, codec);
        } catch (IOException | RuntimeException e) {
            file.close();
            throw e;
        }
    }

    private static String readHeaderLine(RandomAccessFile file) throws IOException {
        var line = new ByteArrayOutputStream();
        int b;
        while ((b = file.read()) != -1 && b != '\n') {
            line.write(b);
        }
        return line.toString(StandardCharsets.UTF_8);
    }

    public Header header() {
        return header;
    }

    public int size() {
        return index.size();
    }

    /** Entrée de rang i dans l'ordre de l'index. */
    public Entry entry(int i) throws IOException {
        return read(index.span(i));
    }

    private Entry read(IndexFile.Span span) throws IOException {
        byte[] data = new byte[span.length()];
        definitions.seek(span.start());
        definitions.readFully(data);
        return Json.entryFromJson(new String(codec.decode(data), StandardCharsets.UTF_8));
    }

    /**
     * Recherche d'un mot. Écrit en majuscules sans accents (forme normalisée), il renvoie toutes les
     * entrées de même forme (CONGRES -> Congrès, congres, congrès) ; sinon, l'entrée exacte.
     */
    public List<Entry> lookup(String word) throws IOException {
        if (WordNormalizer.isNormalized(word)) {
            return findNormalized(word);
        }
        return findExact(word).map(List::of).orElse(List.of());
    }

    /** Dichotomie classique : on compare au mot médian et on ne garde que la moitié utile. */
    public Optional<Entry> findExact(String word) throws IOException {
        int lo = 0;
        int hi = size() - 1;
        while (lo <= hi) {
            int mid = (lo + hi) >>> 1;
            Entry e = entry(mid);
            int c = WordOrder.INSTANCE.compare(word, e.title());
            if (c == 0) {
                return Optional.of(e);
            }
            if (c < 0) {
                hi = mid - 1;
            } else {
                lo = mid + 1;
            }
        }
        return Optional.empty();
    }

    /** Toutes les entrées de forme normalisée donnée : elles sont contiguës dans l'index. */
    public List<Entry> findNormalized(String normalized) throws IOException {
        int lo = 0;
        int hi = size();
        while (lo < hi) {   // première position dont la forme normalisée est >= celle cherchée
            int mid = (lo + hi) >>> 1;
            if (WordNormalizer.normalize(entry(mid).title()).compareTo(normalized) < 0) {
                lo = mid + 1;
            } else {
                hi = mid;
            }
        }
        List<Entry> found = new ArrayList<>();
        for (int i = lo; i < size(); i++) {
            Entry e = entry(i);
            if (!WordNormalizer.normalize(e.title()).equals(normalized)) {
                break;
            }
            found.add(e);
        }
        return found;
    }

    /** Parcourt toutes les entrées dans l'ordre du fichier (lecture séquentielle, plus rapide que l'ordre de l'index). */
    public void forEachEntry(Consumer<Entry> action) throws IOException {
        IndexFile.Span[] spans = new IndexFile.Span[size()];
        for (int i = 0; i < spans.length; i++) {
            spans[i] = index.span(i);
        }
        Arrays.sort(spans, Comparator.comparingLong(IndexFile.Span::start));
        for (IndexFile.Span span : spans) {
            action.accept(read(span));
        }
    }

    @Override
    public void close() throws IOException {
        try (definitions; index) {
            // fermeture des deux fichiers, même si l'une échoue
        }
    }
}
