package fr.univ_eiffel.jdict;

import fr.univ_eiffel.jdict.format.Formats;
import fr.univ_eiffel.jdict.model.Entry;
import fr.univ_eiffel.jdict.stats.CharTable;
import fr.univ_eiffel.jdict.stats.NormalizedWords;
import fr.univ_eiffel.jdict.store.Dictionary;
import fr.univ_eiffel.jdict.store.DictionaryLocation;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.nio.charset.Charset;
import java.util.List;
import java.util.Set;

/**
 * Mode interactif : un seul lancement de la JVM pour plusieurs commandes.
 * <pre>java fr.univ_eiffel.jdict.InteractiveUI [definitions]</pre>
 */
public final class InteractiveUI {

    private static final String HELP = """
            Commandes :
              open DICTIONNAIRE        ouvre un dictionnaire (ex. open definitions)
              MOT                      définitions du mot (MAJUSCULES = toutes les formes)
              yaml:MOT                 idem, au format YAML
              words [natures]          formes normalisées (ex. words nom propre,adjectif)
              chartable                table de fréquences des lettres
              info                     informations sur le dictionnaire ouvert
              help                     cette aide
              quit                     quitter""";

    private Dictionary dictionary;

    public static void main(String[] args) throws IOException {
        var ui = new InteractiveUI();
        if (args.length == 1) {
            ui.open(args[0]);
        }
        ui.run();
    }

    private void run() throws IOException {
        Cli.OUT.println("jdict : tapez « help » pour la liste des commandes.");
        // La console Windows ne lit pas forcément en UTF-8 : on suit son encodage réel.
        Charset charset = System.console() != null ? System.console().charset() : Charset.defaultCharset();
        var in = new BufferedReader(new InputStreamReader(System.in, charset));
        while (true) {
            Cli.OUT.print("jdict> ");
            Cli.OUT.flush();
            String line = in.readLine();
            if (line == null || line.strip().equals("quit")) {
                break;
            }
            try {
                execute(line.strip());
            } catch (IOException e) {
                Cli.OUT.println("Erreur : " + e.getMessage());
            }
        }
        if (dictionary != null) {
            dictionary.close();
        }
        Cli.OUT.flush();
    }

    private void execute(String line) throws IOException {
        if (line.isEmpty()) {
            return;
        }
        String command = line.split("\\s+", 2)[0];
        String argument = line.length() > command.length() ? line.substring(command.length()).strip() : "";
        switch (command) {
            case "help" -> Cli.OUT.println(HELP);
            case "open" -> open(argument);
            case "info" -> requireDictionary(() -> Cli.OUT.printf("%d mots, langue %s, créé le %s, compression : %s%n",
                    dictionary.size(), dictionary.header().language(), dictionary.header().createdOn(),
                    dictionary.header().compression() == null ? "aucune" : dictionary.header().compression()));
            case "words" -> requireDictionary(() -> {
                Set<String> natures = argument.isEmpty() ? Set.of() : NormalizedWords.parseNatures(argument);
                var words = new NormalizedWords(natures);
                dictionary.forEachEntry(words::add);
                words.words().forEach(Cli.OUT::println);
            });
            case "chartable" -> requireDictionary(() -> {
                var table = new CharTable();
                dictionary.forEachEntry(table::add);
                Cli.OUT.print(table.format());
            });
            default -> requireDictionary(() -> {
                Formats.Query query = Formats.parse(line);
                List<Entry> found = dictionary.lookup(query.word());
                Cli.OUT.print(found.isEmpty() ? "Aucune définition pour « " + query.word() + " »\n"
                        : Formats.format(query.format(), found));
            });
        }
    }

    private void open(String base) {
        try {
            Dictionary opened = Dictionary.open(DictionaryLocation.parse(base));
            if (dictionary != null) {
                dictionary.close();
            }
            dictionary = opened;
            Cli.OUT.printf("Dictionnaire %s ouvert : %d mots.%n", base, dictionary.size());
        } catch (IOException e) {
            Cli.OUT.println("Impossible d'ouvrir " + base + " : " + e.getMessage());
        }
    }

    private interface Action {
        void run() throws IOException;
    }

    private void requireDictionary(Action action) throws IOException {
        if (dictionary == null) {
            Cli.OUT.println("Aucun dictionnaire ouvert : utilisez « open DICTIONNAIRE ».");
            return;
        }
        action.run();
    }
}
