package fr.univ_eiffel.jdict.wiki;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.model.WikiPage;

import java.util.Optional;

/**
 * Extraction des définitions d'une page, propre à chaque Wiktionary (les titres de sections diffèrent
 * d'une langue à l'autre). Ajouter une langue = écrire une implémentation et l'enregistrer dans {@link Extractors}.
 */
public interface DefinitionExtractor {

    /** Code de la langue traitée (fr, en...), écrit dans l'en-tête du fichier de définitions. */
    String language();

    /** L'entrée du mot dans cette langue, ou vide si la page n'en contient aucune définition. */
    Optional<Entry> extract(WikiPage page);

    /** Une ligne de définition commence par # ; #* (exemple) et #: (note) sont ignorées. */
    static boolean isDefinitionLine(String line) {
        if (!line.startsWith("#")) {
            return false;
        }
        int i = 0;
        while (i < line.length() && line.charAt(i) == '#') {
            i++;
        }
        return i == line.length() || (line.charAt(i) != '*' && line.charAt(i) != ':');
    }

    /** Texte de la définition, sans les # de tête. */
    static String definitionText(String line) {
        int i = 0;
        while (i < line.length() && line.charAt(i) == '#') {
            i++;
        }
        return line.substring(i).strip();
    }
}
