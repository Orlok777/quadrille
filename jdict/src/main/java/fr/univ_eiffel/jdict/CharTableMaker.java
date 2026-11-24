package fr.univ_eiffel.jdict;

import fr.univ_eiffel.jdict.stats.CharTable;
import fr.univ_eiffel.jdict.store.Dictionary;
import fr.univ_eiffel.jdict.store.DictionaryLocation;

import java.io.IOException;

/**
 * Table de fréquences des lettres (et digrammes comme QU) de la langue du dictionnaire :
 * <pre>java fr.univ_eiffel.jdict.CharTableMaker definitions > chartable.txt</pre>
 * Le format (« LETTRES poids » par ligne) est celui attendu par le moteur C (grid_build).
 */
public final class CharTableMaker {

    private CharTableMaker() {
    }

    public static void main(String[] args) throws IOException {
        if (args.length != 1) {
            Cli.fail("Usage : CharTableMaker DICTIONNAIRE");
        }
        var table = new CharTable();
        try (Dictionary dictionary = Dictionary.open(DictionaryLocation.parse(args[0]))) {
            dictionary.forEachEntry(table::add);
            Cli.OUT.print(table.format(dictionary.header().language()));
        }
        Cli.OUT.flush();
    }
}
