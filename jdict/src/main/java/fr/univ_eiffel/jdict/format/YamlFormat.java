package fr.univ_eiffel.jdict.format;

import fr.univ_eiffel.jdict.model.Entry;

import java.util.List;

/**
 * Sortie YAML, un document par entrée (séparés par ---). Les chaînes sont toujours entre guillemets :
 * le balisage MediaWiki ({{...}}, [[...]], « : ») ne peut pas être pris pour de la syntaxe YAML.
 */
public final class YamlFormat implements OutputFormat {

    @Override
    public String format(List<Entry> entries) {
        var out = new StringBuilder();
        for (Entry e : entries) {
            out.append("---\n");
            out.append("title: ").append(quote(e.title())).append('\n');
            out.append("definitions:\n");
            e.definitions().forEach((nature, list) -> {
                out.append("  ").append(quote(nature)).append(":\n");
                list.forEach(d -> out.append("    - ").append(quote(d)).append('\n'));
            });
        }
        return out.toString();
    }

    static String quote(String s) {
        var q = new StringBuilder("\"");
        for (char c : s.toCharArray()) {
            switch (c) {
                case '"' -> q.append("\\\"");
                case '\\' -> q.append("\\\\");
                case '\n' -> q.append("\\n");
                case '\t' -> q.append("\\t");
                default -> {
                    if (c < 0x20) {
                        q.append(String.format("\\x%02x", (int) c));
                    } else {
                        q.append(c);
                    }
                }
            }
        }
        return q.append('"').toString();
    }
}
