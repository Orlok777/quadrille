package fr.univ_eiffel.jdict.model;

import java.util.Collections;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Objects;

/**
 * Entrée du dictionnaire : un mot et ses définitions, regroupées par nature (nom, verbe, adjectif...).
 * L'ordre des natures est celui de la page Wiktionary.
 */
public record Entry(String title, Map<String, List<String>> definitions) {

    public Entry {
        Objects.requireNonNull(title, "title");
        var copy = new LinkedHashMap<String, List<String>>();
        definitions.forEach((nature, list) -> copy.put(nature, List.copyOf(list)));
        definitions = Collections.unmodifiableMap(copy);
    }

    public boolean isEmpty() {
        return definitions.isEmpty();
    }
}
