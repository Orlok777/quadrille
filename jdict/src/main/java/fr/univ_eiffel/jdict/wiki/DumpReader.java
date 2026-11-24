package fr.univ_eiffel.jdict.wiki;

import fr.univ_eiffel.jdict.model.WikiPage;

import java.io.BufferedInputStream;
import java.io.IOException;
import java.io.InputStream;
import java.util.function.Consumer;
import javax.xml.stream.XMLInputFactory;
import javax.xml.stream.XMLStreamConstants;
import javax.xml.stream.XMLStreamException;
import javax.xml.stream.XMLStreamReader;

import org.apache.commons.compress.compressors.bzip2.BZip2CompressorInputStream;

/**
 * Lecture d'un dump Wiktionary avec l'API StAX (analyse « pull », sans charger le fichier en mémoire).
 * Le flux peut être compressé en bzip2 (détecté par sa signature « BZh ») ou être du XML brut.
 */
public final class DumpReader {

    private final InputStream input;

    public DumpReader(InputStream input) throws IOException {
        this.input = decompressIfNeeded(input);
    }

    private static InputStream decompressIfNeeded(InputStream in) throws IOException {
        var buffered = new BufferedInputStream(in, 1 << 16);
        buffered.mark(3);
        byte[] magic = buffered.readNBytes(3);
        buffered.reset();
        boolean bzip2 = magic.length == 3 && magic[0] == 'B' && magic[1] == 'Z' && magic[2] == 'h';
        return bzip2 ? new BZip2CompressorInputStream(buffered, true) : buffered;
    }

    private static XMLInputFactory factory() {
        XMLInputFactory f = XMLInputFactory.newInstance();
        // Sécurité : pas de DTD ni d'entités externes (attaques XXE).
        f.setProperty(XMLInputFactory.SUPPORT_DTD, false);
        f.setProperty(XMLInputFactory.IS_SUPPORTING_EXTERNAL_ENTITIES, false);
        // Le dump complet dépasse les limites par défaut du parseur du JDK sur le volume de texte.
        trySet(f, "jdk.xml.totalEntitySizeLimit", 0);
        trySet(f, "http://www.oracle.com/xml/jaxp/properties/totalEntitySizeLimit", 0);
        return f;
    }

    private static void trySet(XMLInputFactory f, String property, Object value) {
        try {
            f.setProperty(property, value);
        } catch (IllegalArgumentException ignored) {
            // propriété propre à une implémentation : absente ailleurs, sans conséquence
        }
    }

    /** Appelle {@code action} pour chaque article (espace de noms 0) du dump. */
    public void forEachArticle(Consumer<WikiPage> action) throws IOException {
        try {
            XMLStreamReader xml = factory().createXMLStreamReader(input, "UTF-8");
            String title = null;
            String text = null;
            int ns = -1;
            while (xml.hasNext()) {
                int event = xml.next();
                if (event == XMLStreamConstants.START_ELEMENT) {
                    switch (xml.getLocalName()) {
                        case "page" -> {
                            title = null;
                            text = null;
                            ns = -1;
                        }
                        case "title" -> title = xml.getElementText();
                        case "ns" -> ns = parseNamespace(xml.getElementText());
                        case "text" -> text = xml.getElementText();
                        default -> {
                        }
                    }
                } else if (event == XMLStreamConstants.END_ELEMENT && xml.getLocalName().equals("page")) {
                    if (title != null && text != null && ns == 0) {
                        action.accept(new WikiPage(title, ns, text));
                    }
                }
            }
            xml.close();
        } catch (XMLStreamException e) {
            throw new IOException("Dump XML invalide : " + e.getMessage(), e);
        }
    }

    private static int parseNamespace(String s) {
        try {
            return Integer.parseInt(s.trim());
        } catch (NumberFormatException e) {
            return -1;
        }
    }
}
