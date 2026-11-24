package fr.univ_eiffel.jdict.stats;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.text.WordNormalizer;
import fr.univ_eiffel.jdict.wiki.WikiText;

import java.util.Locale;
import java.util.Map;
import java.util.TreeMap;

/**
 * Table de fréquences des lettres d'une langue, calculée sur les titres et les définitions.
 * Un digramme (ex. QU en français) est retenu quand une lettre est presque toujours suivie de la même :
 * il devient alors une « lettre » à part entière (une case QU dans la grille du Boggle).
 */
public final class CharTable {

    /** Part minimale des occurrences de X suivies de Y pour faire de XY un digramme. */
    public static final double DIGRAM_THRESHOLD = 0.9;

    private final long[] letters = new long[26];
    private final long[][] pairs = new long[26][26];

    public void add(Entry entry) {
        count(entry.title());
        entry.definitions().values().forEach(list -> list.forEach(d -> count(WikiText.plain(d))));
    }

    private void count(String text) {
        String s = WordNormalizer.normalize(text);
        int previous = -1;
        for (int i = 0; i < s.length(); i++) {
            int c = s.charAt(i) - 'A';
            if (c < 0 || c >= 26) {
                previous = -1;   // une espace ou une ponctuation coupe le mot
                continue;
            }
            letters[c]++;
            if (previous >= 0) {
                pairs[previous][c]++;
            }
            previous = c;
        }
    }

    /** Poids de chaque « lettre » (lettre simple ou digramme), en pourcentage, par ordre alphabétique. */
    public Map<String, Double> frequencies() {
        long[] single = letters.clone();
        Map<String, Long> counts = new TreeMap<>();
        for (int x = 0; x < 26; x++) {
            int best = 0;
            for (int y = 1; y < 26; y++) {
                if (pairs[x][y] > pairs[x][best]) {
                    best = y;
                }
            }
            if (letters[x] > 0 && pairs[x][best] >= DIGRAM_THRESHOLD * letters[x]) {
                counts.put(letter(x) + letter(best), pairs[x][best]);
                single[x] -= pairs[x][best];      // ces X et ces Y sont consommés par le digramme
                single[best] -= pairs[x][best];
            }
        }
        for (int x = 0; x < 26; x++) {
            if (single[x] > 0) {
                counts.put(letter(x), single[x]);
            }
        }
        double total = counts.values().stream().mapToLong(Long::longValue).sum();
        Map<String, Double> frequencies = new TreeMap<>();
        counts.forEach((k, v) -> frequencies.put(k, total == 0 ? 0 : 100.0 * v / total));
        return frequencies;
    }

    /** Format lu par le moteur C (grid_build) : « LETTRES poids » par ligne. */
    public String format(String language) {
        var out = new StringBuilder("# Table de fréquences (").append(language).append("), en pourcentage\n");
        frequencies().forEach((k, v) -> out.append(k).append(' ').append(String.format(Locale.ROOT, "%.4f", v)).append('\n'));
        return out.toString();
    }

    private static String letter(int i) {
        return String.valueOf((char) ('A' + i));
    }
}
