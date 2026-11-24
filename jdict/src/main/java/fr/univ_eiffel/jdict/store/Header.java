package fr.univ_eiffel.jdict.store;

import java.time.ZoneOffset;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;

/** Première ligne du fichier de définitions : métadonnées (date de création, langue, compression). */
public record Header(String description, String createdOn, String language, String compression) {

    public static final String DESCRIPTION = "definition file";

    private static final DateTimeFormatter DATE = DateTimeFormatter.ofPattern("yyyyMMdd'T'HHmmss'Z'");

    public static Header now(String language, Codec codec) {
        return new Header(DESCRIPTION, ZonedDateTime.now(ZoneOffset.UTC).format(DATE), language, codec.name());
    }
}
