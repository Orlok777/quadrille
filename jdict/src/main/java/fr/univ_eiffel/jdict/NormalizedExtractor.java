package fr.univ_eiffel.jdict;

import fr.univ_eiffel.jdict.stats.NormalizedWords;
import fr.univ_eiffel.jdict.store.Dictionary;
import fr.univ_eiffel.jdict.store.DictionaryLocation;

import java.io.IOException;
import java.util.Set;

/**
 * Formes normalisées (majuscules, sans diacritiques) triées et sans doublon, une par ligne :
 * <pre>
 * java fr.univ_eiffel.jdict.NormalizedExtractor definitions > words.txt
 * java fr.univ_eiffel.jdict.NormalizedExtractor definitions "nom propre,adjectif" > words.txt
 * </pre>
 * Le fichier words.txt sert ensuite à construire l'arbre lexicographique du moteur C (dictionnary_build).
 */
public final class NormalizedExtractor {

    private NormalizedExtractor() {
    }

    public static void main(String[] args) throws IOException {
        if (args.length < 1 || args.length > 2) {
            Cli.fail("Usage : NormalizedExtractor DICTIONNAIRE [\"nature1,nature2...\"]");
        }
        Set<String> natures = args.length == 2 ? NormalizedWords.parseNatures(args[1]) : Set.of();
        var words = new NormalizedWords(natures);
        try (Dictionary dictionary = Dictionary.open(DictionaryLocation.parse(args[0]))) {
            dictionary.forEachEntry(words::add);
        }
        words.words().forEach(Cli.OUT::println);
        Cli.OUT.flush();
    }
}
