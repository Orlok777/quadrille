package fr.univ_eiffel.jdict.text;

import java.text.Normalizer;
import java.util.Locale;
import java.util.regex.Pattern;

/** Forme normalisée d'un mot : majuscules, sans diacritiques (congrès -> CONGRES, cœur -> COEUR). */
public final class WordNormalizer {

    private static final Pattern MARKS = Pattern.compile("\\p{M}+");

    private WordNormalizer() {
    }

    public static String normalize(String word) {
        String ligatures = word
                .replace("œ", "oe").replace("Œ", "OE")
                .replace("æ", "ae").replace("Æ", "AE")
                .replace("ß", "ss");
        // NFD sépare chaque lettre de ses accents (é = e + accent aigu), qu'il suffit ensuite de supprimer.
        String decomposed = Normalizer.normalize(ligatures, Normalizer.Form.NFD);
        return MARKS.matcher(decomposed).replaceAll("").toUpperCase(Locale.ROOT);
    }

    /** Un mot écrit sous sa forme normalisée (ex. CONGRES) désigne toutes les entrées de même forme. */
    public static boolean isNormalized(String word) {
        return !word.isEmpty() && word.equals(normalize(word));
    }
}
