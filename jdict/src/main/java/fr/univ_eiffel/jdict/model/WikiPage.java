package fr.univ_eiffel.jdict.model;

/** Page brute lue dans le dump : titre, espace de noms (0 = article) et texte MediaWiki. */
public record WikiPage(String title, int namespace, String text) {

    public boolean isArticle() {
        return namespace == 0;
    }
}
