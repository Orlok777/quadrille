package fr.univ_eiffel.jdict.format;

import fr.univ_eiffel.jdict.model.Entry;

import java.util.List;

/** Présentation des résultats d'une recherche. Ajouter un format = l'enregistrer dans {@link Formats}. */
public interface OutputFormat {

    String format(List<Entry> entries);
}
