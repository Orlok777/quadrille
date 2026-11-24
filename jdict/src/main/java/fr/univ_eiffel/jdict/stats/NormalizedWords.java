package fr.univ_eiffel.jdict.stats;

import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.text.WordNormalizer;

import java.util.Arrays;
import java.util.Set;
import java.util.SortedSet;
import java.util.TreeSet;
import java.util.regex.Pattern;
import java.util.stream.Collectors;

/** Formes normalisées triées et sans doublon, éventuellement limitées à certaines natures de mots. */
public final class NormalizedWords {

    private static final Pattern PLAYABLE = Pattern.compile("[A-Z]+");

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

    /**
     * Seuls les mots écrits avec A-Z sont gardés : c'est le format du dictionnaire du moteur, où un
     * espace commence un commentaire (« POMME DE TERRE », « PORTE-MONNAIE » ne sont pas jouables).
     */
    public void add(Entry entry) {
        if (natures.isEmpty() || entry.definitions().keySet().stream().anyMatch(natures::contains)) {
            String normalized = WordNormalizer.normalize(entry.title());
            if (PLAYABLE.matcher(normalized).matches()) {
                words.add(normalized);
            }
        }
    }

    public SortedSet<String> words() {
        return words;
    }
}
