# Fehlersuche

## Der Reiter Importe fehlt

- Die Erweiterung ist in diesem Siteaccess nicht aktiv: Prüfen Sie `ActiveExtensions[]` oder
  `ActiveAccessExtensions[]`.
- Der Siteaccess setzt `[TopAdminMenu] Tabs[]` in seiner eigenen `menu.ini.append.php` zurück. Tragen Sie dort
  `Tabs[]=sqliimport` ein.
- Der INI-Cache ist veraltet: `php bin/php/ezcache.php --clear-tag=ini`.
- Der Benutzer hat weder `sqliimport/run` noch `sqliimport/manage` (noch eine Richtlinie von 1.x):
  [permissions.md](permissions.md). Eine für 2.0.0-alpha.1 angelegte Rolle mit `sqliimport/view` oder
  `sqliimport/edit` erlaubt nichts mehr; geben Sie ihr `run` oder `manage`.

## New import, Edit oder Settings fehlt

Diese brauchen `sqliimport/manage` (oder `sqliimport/manageimports` von 1.x). Nur mit `sqliimport/run` sieht ein
Benutzer die Importe und führt sie aus, kann sie aber nicht ändern. Mit einer Handler-Einschränkung erscheinen Edit
und die Aktionen unter More nur bei Importen der erlaubten Handler.

## Der Editor

- **Der Schritt Zuordnung zeigt keine Attribute:** Speichern Sie den Schritt, nachdem Sie die Inhaltsklasse gewählt
  haben.
- **„The sample of the source could not be read“:** Die Quelle ist nicht erreichbar oder noch nicht gültig. Prüfen
  Sie den Schritt Quelle (die Datei, die Adresse, die Optionen wie das Blatt einer Tabelle) und öffnen Sie den
  Schritt dann erneut.
- **Auto-match hat keine Zeile gefüllt:** Kein Feld der Stichprobe heißt ähnlich wie ein Attribut. Füllen Sie die
  Zeilen von Hand.
- **Eine Zeile mit Umwandlungen wird gemeldet:** Der einzeilige Text der Kette ließ sich nicht lesen, etwa ein Wert
  mit Komma ohne Anführungszeichen ([mappings.md](mappings.md#der-einzeilige-text-einer-kette)).
- **Der Import lässt sich im Schritt Prüfung nicht aktivieren:** Die Prüfung nennt, was noch fehlt; beheben Sie es
  in dem dort genannten Schritt.
- **Eine importierte Definition schlägt sofort fehl:** Ihre hochgeladene Datei gehört nicht zum Export. Laden Sie
  sie im Schritt Quelle erneut hoch ([settings.md](settings.md#import-import-definitions-importdefinitionen-importieren)).

## Eine Tabelle lässt sich nicht lesen

- „reading spreadsheets needs the PHP extension zip“: Installieren Sie `ext-zip` für das PHP der Worker der
  Job-Warteschlange und des Webservers.
- „the spreadsheet has no sheet ...“: Die Option `sheet` nennt ein Blatt, das nicht in der Datei steht. Geben Sie
  seinen genauen Namen oder seine Nummer an.
- Datumsangaben kommen als Zahlen wie `45231`: Ergänzen Sie die Zeile um `date(format=excel)`
  ([mappings.md](mappings.md#datumsangaben-aus-tabellen)).

## „The tables of 7x SQLI Import are missing“

Führen Sie `./console ext:sqliimport:install-schema` aus. `--check` zeigt, was fehlt.

## „The job queue (expqueue) is not installed“

Aktivieren Sie die Erweiterung expqueue und führen Sie `./console ext:expqueue:install-schema` aus.

## Ein Lauf bleibt in der Warteschlange

Die Worker der Job-Warteschlange verarbeiten die Läufe. Prüfen Sie, dass der Cron-Teil `expqueue` jede Minute
läuft (oder sein systemd-Dienst), und sehen Sie in die Übersicht der Job-Warteschlange (`expqueue/dashboard`,
verlinkt im Reiter Importe). Um einen Lauf sofort auf der Kommandozeile zu verarbeiten:

```bash
./console ext:sqliimport:run my_import --wait
```

## „The import already runs (run #12)“

Ein Import läuft immer nur einmal gleichzeitig. Warten Sie, bis der Lauf endet, oder brechen Sie ihn ab:
`./console ext:sqliimport:cancel 12`. Einen Lauf, der auf `running` stehen bleibt, obwohl sein Job bei jedem
Versuch fehlschlug, beendet der Cron-Teil `sqliimport`; prüfen Sie, dass er jede Minute läuft, oder führen Sie
`./console ext:sqliimport:tick` aus.

## Zeitpläne starten nicht

- Der Cron-Teil `sqliimport` muss jede Minute laufen: `php runcronjobs.php -q sqliimport`.
- Der Zeitplan ist vielleicht inaktiv (ein Zeitplan `once` schaltet sich nach seinem Lauf ab).
- Mit `catch_up` = `skip` wartet ein Lauf, der mehr als zehn Minuten zu spät ist, auf den nächsten Termin.
- Ist der Import deaktiviert, wird sein geplanter Lauf übersprungen; `tick` gibt den Grund aus.

## Der Lauf schlug sofort fehl

Sehen Sie in das Protokoll des Laufs (`./console ext:sqliimport:status <Lauf-ID>` oder die Seite des Laufs).
Häufige Meldungen:

| Meldung | Ursache |
|---|---|
| `the uploaded source file is missing; upload it again` | Der Upload wurde aus dem Speicherverzeichnis entfernt |
| `the path is outside the directories ... AllowedPaths[] allows` | Tragen Sie das Verzeichnis in `[SourceSettings] AllowedPaths[]` ein |
| `the host ... is in a private or reserved network` | Eine Intranet-Adresse; siehe `[SourceSettings] AllowPrivateHosts` |
| `only http and https addresses can be read` | Ein anderes Schema wurde verwendet |
| `the download is larger than [SourceSettings] MaxSourceMegabytes` | Erhöhen Sie das Limit oder liefern Sie die Datei in Teilen |
| `the mapping is not valid: ...` | Die Zuordnung nennt eine Klasse oder ein Attribut, das es nicht gibt, oder ihr fehlt die Zuordnungsregel oder ein Elternknoten |
| `unknown handler '...'` | Der Handler ist nicht registriert, oder seine Klasse ist nicht in den Autoloads |

## Datensätze schlagen fehl

Filtern Sie die Seite des Laufs nach **Failed** (`sqliimport/run/<id>/(outcome)/failed`), um jede Meldung zu
sehen. Typische Ursachen:

- `the remote ID needs id, which the record does not have`: Das Muster nennt ein Feld, das dem Datensatz fehlt.
  Prüfen Sie die Feldnamen; CSV-Kopfzeilen unterscheiden Groß- und Kleinschreibung.
- `the required attribute '...' has no value`: Geben Sie eine Vorgabe an oder lassen Sie den Datensatz bewusst
  fehlschlagen.
- `the parent of the record does not exist (yet)`: Das Elternobjekt kommt später in der Quelle oder gar nicht.
  Sortieren Sie die Quelle so, dass Elternobjekte zuerst kommen.
- `lookup: no object with the remote ID '...'`: Das verknüpfte Objekt wurde noch nicht importiert.

Beheben Sie die Ursache und verwenden Sie dann **Retry failed rows** (oder
`./console ext:sqliimport:retry <Lauf-ID>`).

## In einem Lauf fehlen Datensätze

Zwei Datensätze mit demselben Schlüssel werden einmal verarbeitet: Der zweite wird nicht verarbeitet. Machen Sie
das Feld `key` der Quelle eindeutig oder lassen Sie es leer, damit Datensätze nach ihrer Position benannt werden.

## Nichts wird aktualisiert

- `update_policy` = `create_only` überspringt bestehende Objekte.
- Objekte im Papierkorb werden übersprungen.
- Die Zeilen sagen `unchanged`: Kein Feld weicht von der veröffentlichten Version ab, daher entstand keine Version.
  Ein Probelauf zeigt die verglichenen Werte.

## Ein Handler von 1.x

- Seine Zeilen haben das Ergebnis `processed`; die Engine weiß nicht, was der Handler getan hat. Lesen Sie seine
  Fortschrittsnotizen im Protokoll des Laufs.
- „Retry failed rows“ gibt es nicht. Starten Sie den Import erneut.
- Importaufträge von 1.x aus `sqliimport/addimport` warten, bis der Cron-Teil `sqliimport` (oder der veraltete
  `sqliimport_run`) sie an die Job-Warteschlange übergibt. Bleibt einer offen, prüfen Sie, dass ein Cron-Teil läuft.

## Speicher und Zeit

Ein Abschnitt verarbeitet `[RunSettings] ChunkSize` Datensätze (Vorgabe 200) und darf bis zu 1800 Sekunden dauern
(`expqueue.ini [JobType_sqliimport.chunk] Timeout`). Senken Sie die `chunk_size` des Imports für aufwendige
Datensätze, etwa solche mit herunterzuladenden Bildern.
