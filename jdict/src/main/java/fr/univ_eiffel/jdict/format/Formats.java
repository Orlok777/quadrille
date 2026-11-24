package fr.univ_eiffel.jdict.format;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.store.Json;

import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

/**
 * Registre des formats de sortie. Une requête peut être préfixée par le format voulu :
 * « yaml:CONGRES ». Sans préfixe connu, la sortie est en JSON (une ligne par entrée).
 */
public final class Formats {

    public static final OutputFormat JSON = entries -> entries.stream()
            .map(Json::toJson)
            .collect(Collectors.joining("\n", "", entries.isEmpty() ? "" : "\n"));

    public static final OutputFormat YAML = new YamlFormat();

    private static final Map<String, OutputFormat> BY_PREFIX = Map.of("json", JSON, "yaml", YAML);

    /** Requête décomposée : le format demandé et le mot recherché. */
    public record Query(OutputFormat format, String word) {
    }

    private Formats() {
    }

    public static Query parse(String query) {
        int colon = query.indexOf(':');
        if (colon > 0) {
            OutputFormat format = BY_PREFIX.get(query.substring(0, colon));
            if (format != null) {
                return new Query(format, query.substring(colon + 1));
            }
        }
        return new Query(JSON, query);
    }

    public static String format(OutputFormat format, List<Entry> entries) {
        return format.format(entries);
    }
}
