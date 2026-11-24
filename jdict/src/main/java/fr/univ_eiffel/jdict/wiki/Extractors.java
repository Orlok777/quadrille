package fr.univ_eiffel.jdict.wiki;

import java.util.Map;
import java.util.Optional;
import java.util.Set;
import java.util.function.Supplier;

/** Registre des Wiktionary pris en charge, par code de langue. */
public final class Extractors {

    private static final Map<String, Supplier<DefinitionExtractor>> REGISTRY = Map.of(
            "fr", () -> new FrenchExtractor("fr"),
            "en", EnglishExtractor::new);

    private Extractors() {
    }

    public static Optional<DefinitionExtractor> forLanguage(String code) {
        return Optional.ofNullable(REGISTRY.get(code)).map(Supplier::get);
    }

    public static Set<String> languages() {
        return REGISTRY.keySet();
    }
}
