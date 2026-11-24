package fr.univ_eiffel.jdict.store;

import fr.univ_eiffel.jdict.model.Entry;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParseException;
import com.google.gson.JsonParser;

import java.io.IOException;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/** Conversion JSON des entrées et de l'en-tête (Gson). */
public final class Json {

    /** Sans échappement HTML : les ' et = du balisage MediaWiki restent lisibles. */
    private static final Gson GSON = new GsonBuilder().disableHtmlEscaping().create();

    private Json() {
    }

    public static String toJson(Entry entry) {
        JsonObject definitions = new JsonObject();
        entry.definitions().forEach((nature, list) -> {
            JsonArray array = new JsonArray();
            list.forEach(array::add);
            definitions.add(nature, array);
        });
        JsonObject o = new JsonObject();
        o.addProperty("title", entry.title());
        o.add("definitions", definitions);
        return GSON.toJson(o);
    }

    public static Entry entryFromJson(String json) throws IOException {
        try {
            JsonObject o = JsonParser.parseString(json).getAsJsonObject();
            Map<String, List<String>> definitions = new LinkedHashMap<>();
            for (Map.Entry<String, JsonElement> e : o.getAsJsonObject("definitions").entrySet()) {
                List<String> list = new ArrayList<>();
                e.getValue().getAsJsonArray().forEach(d -> list.add(d.getAsString()));
                definitions.put(e.getKey(), list);
            }
            return new Entry(o.get("title").getAsString(), definitions);
        } catch (JsonParseException | IllegalStateException | NullPointerException | ClassCastException e) {
            throw new IOException("Entrée JSON invalide", e);
        }
    }

    public static String toJson(Header header) {
        JsonObject o = new JsonObject();
        o.addProperty("description", header.description());
        o.addProperty("created_on", header.createdOn());
        o.addProperty("language", header.language());
        if (header.compression() != null) {
            o.addProperty("compression", header.compression());
        }
        return GSON.toJson(o);
    }

    public static Header headerFromJson(String json) throws IOException {
        try {
            JsonObject o = JsonParser.parseString(json).getAsJsonObject();
            return new Header(
                    o.get("description").getAsString(),
                    o.get("created_on").getAsString(),
                    o.get("language").getAsString(),
                    o.has("compression") ? o.get("compression").getAsString() : null);
        } catch (JsonParseException | IllegalStateException | NullPointerException | ClassCastException e) {
            throw new IOException("En-tête du fichier de définitions invalide", e);
        }
    }
}
