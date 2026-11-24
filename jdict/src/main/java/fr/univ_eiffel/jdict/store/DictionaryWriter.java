package fr.univ_eiffel.jdict.store;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.text.WordOrder;

import java.io.BufferedOutputStream;
import java.io.Closeable;
import java.io.IOException;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;

/**
 * Écrit le fichier de définitions (en-tête JSON puis une entrée par ligne) et, à la fermeture,
 * l'index trié. Chaque entrée est transformée par le codec (identité ou zlib).
 */
public final class DictionaryWriter implements Closeable {

    private record Pending(String title, IndexFile.Span span) {
    }

    private final DictionaryLocation location;
    private final OutputStream out;
    private final List<Pending> written = new ArrayList<>();
    private long position;

    public DictionaryWriter(DictionaryLocation location, String language) throws IOException {
        this.location = location;
        this.out = new BufferedOutputStream(Files.newOutputStream(location.definitions()), 1 << 16);
        byte[] header = Json.toJson(Header.now(language, location.codec())).getBytes(StandardCharsets.UTF_8);
        out.write(header);
        out.write('\n');
        position = header.length + 1;
    }

    public void add(Entry entry) throws IOException {
        byte[] data = location.codec().encode(Json.toJson(entry).getBytes(StandardCharsets.UTF_8));
        written.add(new Pending(entry.title(), new IndexFile.Span(position, position + data.length - 1)));
        out.write(data);
        out.write('\n');   // séparateur : garde le fichier lisible ligne à ligne quand il n'est pas compressé
        position += data.length + 1;
    }

    public int count() {
        return written.size();
    }

    @Override
    public void close() throws IOException {
        out.close();
        written.sort(Comparator.comparing(Pending::title, WordOrder.INSTANCE));
        IndexFile.write(location.index(), written.stream().map(Pending::span).toList());
    }
}
