package fr.univ_eiffel.jdict.wiki;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.model.WikiPage;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Wiktionary francophone : section « == {{langue|fr}} == », puis sous-sections de nature
 * « === {{S|nom|fr}} === », « === {{S|verbe|fr|flexion}} === »... Les sous-sections sans code de
 * langue (étymologie, traductions, synonymes...) sont ignorées.
 */
public final class FrenchExtractor implements DefinitionExtractor {

    private static final Pattern LANGUAGE = Pattern.compile("^==\\s*\\{\\{langue\\|([^|}]+)[^}]*}}\\s*==\\s*$");
    private static final Pattern LEVEL2 = Pattern.compile("^==[^=].*==\\s*$");
    private static final Pattern SECTION = Pattern.compile("^={3,}\\s*\\{\\{S\\|([^}]*)}}.*$");
    private static final Pattern HEADING = Pattern.compile("^={3,}.*={3,}\\s*$");

    /** Abréviations rencontrées dans les modèles {{S|...}}. */
    private static final Map<String, String> ALIASES = Map.of(
            "adj", "adjectif",
            "adv", "adverbe",
            "nom-pr", "nom propre",
            "prép", "préposition",
            "conj", "conjonction",
            "interj", "interjection",
            "pronom-pers", "pronom personnel");

    private final String language;

    public FrenchExtractor(String language) {
        this.language = language;
    }

    @Override
    public String language() {
        return language;
    }

    @Override
    public Optional<Entry> extract(WikiPage page) {
        Map<String, List<String>> definitions = new LinkedHashMap<>();
        boolean inLanguage = false;
        String nature = null;
        for (String line : page.text().split("\n")) {
            Matcher lang = LANGUAGE.matcher(line);
            if (lang.matches()) {
                inLanguage = lang.group(1).strip().equals(language);
                nature = null;
                continue;
            }
            if (LEVEL2.matcher(line).matches()) {
                inLanguage = false;
                continue;
            }
            if (!inLanguage) {
                continue;
            }
            Matcher section = SECTION.matcher(line);
            if (section.matches()) {
                nature = natureOf(section.group(1));
                continue;
            }
            if (HEADING.matcher(line).matches()) {
                nature = null;
                continue;
            }
            if (nature != null && DefinitionExtractor.isDefinitionLine(line)) {
                String text = DefinitionExtractor.definitionText(line);
                if (!text.isEmpty()) {
                    definitions.computeIfAbsent(nature, k -> new ArrayList<>()).add(text);
                }
            }
        }
        return definitions.isEmpty() ? Optional.empty() : Optional.of(new Entry(page.title(), definitions));
    }

    /** {{S|nom|fr|flexion}} -> "nom" ; {{S|étymologie}} -> null (pas de code de langue). */
    private String natureOf(String parameters) {
        String[] p = parameters.split("\\|");
        if (p.length < 2 || !p[1].strip().equals(language)) {
            return null;
        }
        String name = p[0].strip();
        return ALIASES.getOrDefault(name, name);
    }
}
