package fr.univ_eiffel;

/**
 * Point d'entrée au nom indiqué par l'énoncé (fr.univ_eiffel.DictionaryMaker) ; le programme lui-même
 * est {@link fr.univ_eiffel.jdict.DictionaryMaker}, rangé avec les autres commandes.
 */
public final class DictionaryMaker {

    private DictionaryMaker() {
    }

    public static void main(String[] args) throws Exception {
        fr.univ_eiffel.jdict.DictionaryMaker.main(args);
    }
}
