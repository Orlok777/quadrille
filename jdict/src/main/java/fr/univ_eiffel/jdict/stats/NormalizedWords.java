package fr.univ_eiffel.jdict.stats;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.text.WordNormalizer;

import java.util.Arrays;
import java.util.Set;
import java.util.SortedSet;
import java.util.TreeSet;
import java.util.stream.Collectors;

/** Formes normalisées triées et sans doublon, éventuellement limitées à certaines natures de mots. */
public final class NormalizedWords {

    private final Set<String> natures;   // vide = toutes
    private final SortedSet<String> words = new TreeSet<>();

    public NormalizedWords(Set<String> natures) {
        this.natures = Set.copyOf(natures);
    }

    /** « nom propre,adjectif » -> {nom propre, adjectif}. */
    public static Set<String> parseNatures(String list) {
        return Arrays.stream(list.split(","))
                .map(String::strip)
                .filter(s -> !s.isEmpty())
                .collect(Collectors.toSet());
    }

    public void add(Entry entry) {
        if (natures.isEmpty() || entry.definitions().keySet().stream().anyMatch(natures::contains)) {
            words.add(WordNormalizer.normalize(entry.title()));
        }
    }

    public SortedSet<String> words() {
        return words;
    }
}
