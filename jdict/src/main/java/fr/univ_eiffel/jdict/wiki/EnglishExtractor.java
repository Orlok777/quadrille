package fr.univ_eiffel.jdict.wiki;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.model.WikiPage;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Optional;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Wiktionary anglophone : section « ==English== », puis titres de nature « ===Noun=== » (ou de niveau 4
 * quand ils sont rangés sous « ===Etymology 1=== »). Seuls les titres connus comme natures sont retenus.
 */
public final class EnglishExtractor implements DefinitionExtractor {

    private static final Pattern LEVEL2 = Pattern.compile("^==\\s*([^=].*?)\\s*==\\s*$");
    private static final Pattern HEADING = Pattern.compile("^(={3,6})\\s*(.*?)\\s*\\1\\s*$");

    private static final Set<String> PARTS_OF_SPEECH = Set.of(
            "noun", "proper noun", "verb", "adjective", "adverb", "pronoun", "preposition",
            "conjunction", "interjection", "determiner", "article", "numeral", "particle",
            "prefix", "suffix", "phrase", "prepositional phrase", "proverb", "contraction",
            "abbreviation", "initialism", "acronym", "symbol", "letter");

    private final String languageName;

    public EnglishExtractor() {
        this.languageName = "English";
    }

    @Override
    public String language() {
        return "en";
    }

    @Override
    public Optional<Entry> extract(WikiPage page) {
        Map<String, List<String>> definitions = new LinkedHashMap<>();
        boolean inLanguage = false;
        String nature = null;
        for (String line : page.text().split("\n")) {
            Matcher level2 = LEVEL2.matcher(line);
            if (level2.matches()) {
                inLanguage = level2.group(1).equals(languageName);
                nature = null;
                continue;
            }
            if (!inLanguage) {
                continue;
            }
            Matcher heading = HEADING.matcher(line);
            if (heading.matches()) {
                String title = heading.group(2).toLowerCase(Locale.ROOT);
                nature = PARTS_OF_SPEECH.contains(title) ? title : null;
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
}
