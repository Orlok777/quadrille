package fr.univ_eiffel.jdict;

import fr.univ_eiffel.jdict.format.Formats;
import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.store.Dictionary;
import fr.univ_eiffel.jdict.store.DictionaryLocation;

import java.io.IOException;
import java.util.List;

/**
 * Recherche des définitions d'un mot :
 * <pre>
 * java fr.univ_eiffel.jdict.DictionarySearcher definitions congres       (entrée exacte)
 * java fr.univ_eiffel.jdict.DictionarySearcher definitions CONGRES       (toutes les formes : congres, congrès, Congrès)
 * java fr.univ_eiffel.jdict.DictionarySearcher definitions yaml:CONGRES  (sortie YAML)
 * </pre>
 */
public final class DictionarySearcher {

    private DictionarySearcher() {
    }

    public static void main(String[] args) throws IOException {
        if (args.length != 2) {
            Cli.fail("Usage : DictionarySearcher DICTIONNAIRE [yaml:]MOT");
        }
        Formats.Query query = Formats.parse(args[1]);
        try (Dictionary dictionary = Dictionary.open(DictionaryLocation.parse(args[0]))) {
            List<Entry> found = dictionary.lookup(query.word());
            if (found.isEmpty()) {
                Cli.fail("Aucune définition pour « " + query.word() + " »");
            }
            Cli.OUT.print(Formats.format(query.format(), found));
            Cli.OUT.flush();
        }
    }
}
