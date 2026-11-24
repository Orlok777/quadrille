package fr.univ_eiffel.jdict.text;

import java.util.Comparator;

/**
 * Ordre de l'index : d'abord la forme normalisée, puis, à égalité, l'ordre naturel des String
 * (codes Unicode). Ainsi congres &lt; congrès &lt; Congrès... sont regroupés et départagés.
 */
public final class WordOrder implements Comparator<String> {

    public static final WordOrder INSTANCE = new WordOrder();

    private WordOrder() {
    }

    @Override
    public int compare(String a, String b) {
        int c = WordNormalizer.normalize(a).compareTo(WordNormalizer.normalize(b));
        return c != 0 ? c : a.compareTo(b);
    }
}
