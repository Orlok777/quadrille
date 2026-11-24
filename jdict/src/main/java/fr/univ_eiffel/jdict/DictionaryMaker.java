package fr.univ_eiffel.jdict;

import fr.univ_eiffel.jdict.store.DictionaryLocation;
import fr.univ_eiffel.jdict.store.DictionaryWriter;
import fr.univ_eiffel.jdict.wiki.DefinitionExtractor;
import fr.univ_eiffel.jdict.wiki.DumpReader;
import fr.univ_eiffel.jdict.wiki.Extractors;

import java.io.IOException;
import java.io.UncheckedIOException;

/**
 * Crée le fichier de définitions et son index à partir d'un dump Wiktionary lu sur l'entrée standard :
 * <pre>cat frwiktionary.xml.bz2 | java fr.univ_eiffel.DictionaryMaker fr definitions</pre>
 * Préfixer la destination par « compressed: » compresse chaque entrée (zlib).
 */
public final class DictionaryMaker {

    private DictionaryMaker() {
    }

    public static void main(String[] args) throws IOException {
        if (args.length != 2) {
            Cli.fail("Usage : DictionaryMaker LANGUE [compressed:]DESTINATION < dump.xml.bz2\n"
                    + "Langues prises en charge : " + Extractors.languages());
        }
        DefinitionExtractor extractor = Extractors.forLanguage(args[0]).orElse(null);
        if (extractor == null) {
            Cli.fail("Langue non prise en charge : " + args[0] + " (disponibles : " + Extractors.languages() + ")");
            return;
        }
        DictionaryLocation location = DictionaryLocation.parse(args[1]);
        int[] pages = {0};
        try (var writer = new DictionaryWriter(location, extractor.language())) {
            new DumpReader(System.in).forEachArticle(page -> {
                if (++pages[0] % 100_000 == 0) {
                    System.err.printf("%d pages lues, %d mots retenus%n", pages[0], writer.count());
                }
                extractor.extract(page).ifPresent(entry -> {
                    try {
                        writer.add(entry);
                    } catch (IOException e) {
                        throw new UncheckedIOException(e);
                    }
                });
            });
            System.err.printf("Terminé : %d pages, %d mots -> %s et %s%n",
                    pages[0], writer.count(), location.definitions(), location.index());
        } catch (UncheckedIOException e) {
            throw e.getCause();
        }
    }
}
