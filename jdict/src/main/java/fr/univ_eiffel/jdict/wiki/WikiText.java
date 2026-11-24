package fr.univ_eiffel.jdict.wiki;

import java.util.regex.Pattern;

/** Retire le balisage MediaWiki pour ne garder que le texte lisible (utile pour compter les lettres). */
public final class WikiText {

    private static final Pattern TEMPLATE = Pattern.compile("\\{\\{[^{}]*}}");
    private static final Pattern LINK_WITH_LABEL = Pattern.compile("\\[\\[[^\\[\\]|]*\\|([^\\[\\]]*)]]");
    private static final Pattern LINK = Pattern.compile("\\[\\[([^\\[\\]]*)]]");
    private static final Pattern QUOTES = Pattern.compile("'{2,}");

    private WikiText() {
    }

    public static String plain(String wiki) {
        String s = wiki;
        String previous;
        do {   // modèles imbriqués : {{a|{{b}}}} se retire en plusieurs passes
            previous = s;
            s = TEMPLATE.matcher(s).replaceAll("");
        } while (!s.equals(previous));
        s = LINK_WITH_LABEL.matcher(s).replaceAll("$1");   // [[étendue|Étendue]] -> Étendue
        s = LINK.matcher(s).replaceAll("$1");              // [[sable]] -> sable
        return QUOTES.matcher(s).replaceAll("");
    }
}
