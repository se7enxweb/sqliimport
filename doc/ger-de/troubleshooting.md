# Fehlersuche

Diese Seite hat zwei Teile. **Probleme** listet, was schiefgehen kann, mit der Meldung, die Sie sehen, und was zu tun
ist. **Häufige Fragen** erklärt Verhalten, das wie ein Fehler aussieht, aber so gewollt ist, und was Sie erwarten
können.

## Inhalt

- [Probleme](#probleme)
  - [Der Reiter Importe fehlt](#der-reiter-importe-fehlt)
  - [New import, Edit oder Settings fehlt](#new-import-edit-oder-settings-fehlt)
  - [Der Editor](#der-editor)
  - [Eine Tabelle lässt sich nicht lesen](#eine-tabelle-lässt-sich-nicht-lesen)
  - [„The tables of 7x SQLI Import are missing“](#the-tables-of-7x-sqli-import-are-missing)
  - [„The job queue (expqueue) is not installed“](#the-job-queue-expqueue-is-not-installed)
  - [Ein Lauf bleibt in der Warteschlange](#ein-lauf-bleibt-in-der-warteschlange)
  - [„The import already runs (run #12)“](#the-import-already-runs-run-12)
  - [Zeitpläne starten nicht](#zeitpläne-starten-nicht)
  - [Der Lauf schlug sofort fehl](#der-lauf-schlug-sofort-fehl)
  - [Datensätze schlagen fehl](#datensätze-schlagen-fehl)
  - [In einem Lauf fehlen Datensätze](#in-einem-lauf-fehlen-datensätze)
  - [Nichts wird aktualisiert](#nichts-wird-aktualisiert)
  - [Ein Handler von 1.x](#ein-handler-von-1x)
  - [Speicher und Zeit](#speicher-und-zeit)
  - [Velocity oder PHP-FPM führen noch den alten Code aus](#velocity-oder-php-fpm-führen-noch-den-alten-code-aus)
  - [Ein Datenbanktreiber fehlt im Editor oder nur in den Läufen](#ein-datenbanktreiber-fehlt-im-editor-oder-nur-in-den-läufen)
- [Häufige Fragen](#häufige-fragen)
  - [Wird die Live-Seite eines Laufs von Velocity oder einem Antwort-Cache zwischengespeichert?](#wird-die-live-seite-eines-laufs-von-velocity-oder-einem-antwort-cache-zwischengespeichert)
  - [Was sagt ein Probelauf eines Ordnerbaums zu den Kindern?](#was-sagt-ein-probelauf-eines-ordnerbaums-zu-den-kindern)
  - [Ich habe die Datei korrigiert, aber Retry failed rows schlägt wieder fehl](#ich-habe-die-datei-korrigiert-aber-retry-failed-rows-schlägt-wieder-fehl)
  - [Preise sind 0, oder jeder Lauf aktualisiert jeden Preis](#prices-are-0-or-every-run-updates-every-price)
  - [Warum kann ein Import die eigene Datenbank der Website nicht lesen?](#warum-kann-ein-import-die-eigene-datenbank-der-website-nicht-lesen)
  - [Oracle: oci8, pdo_oci oder ezoracle?](#oracle-oci8-pdo_oci-oder-ezoracle)

## Probleme

### Der Reiter Importe fehlt

- Die Erweiterung ist in diesem Siteaccess nicht aktiv: Prüfen Sie `ActiveExtensions[]` oder
  `ActiveAccessExtensions[]`.
- Der Siteaccess setzt `[TopAdminMenu] Tabs[]` in seiner eigenen `menu.ini.append.php` zurück. Das leere `Tabs[]`
  verwirft die Reiter, die die Erweiterungen hinzugefügt haben, auch das `Tabs[]=sqliimport` der Erweiterung. Tragen
  Sie es in diese Liste ein, zum Beispiel in `settings/siteaccess/<Admin-Siteaccess>/menu.ini.append.php`:

  ```ini
  [TopAdminMenu]
  Tabs[]
  Tabs[]=dashboard
  Tabs[]=content
  Tabs[]=media
  Tabs[]=users
  Tabs[]=setup
  Tabs[]=sqliimport
  ```

  Der Reiter selbst (`[Topmenu_sqliimport]`: Name, Symbol und Navigationsteil) kommt aus der `menu.ini.append.php`
  der Erweiterung und braucht im Siteaccess nichts.
- Der INI-Cache ist veraltet: `php bin/php/ezcache.php --clear-tag=ini`.
- Der Benutzer hat weder `sqliimport/run` noch `sqliimport/manage` (noch eine Richtlinie von 1.x):
  [permissions.md](permissions.md). Eine für 2.0.0-alpha.1 angelegte Rolle mit `sqliimport/view` oder
  `sqliimport/edit` erlaubt nichts mehr; geben Sie ihr `run` oder `manage`.

### New import, Edit oder Settings fehlt

Diese brauchen `sqliimport/manage` (oder `sqliimport/manageimports` von 1.x). Nur mit `sqliimport/run` sieht ein
Benutzer die Importe und führt sie aus, kann sie aber nicht ändern. Mit einer Handler-Einschränkung erscheinen Edit
und die Aktionen unter More nur bei Importen der erlaubten Handler.

### Der Editor

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

### Eine Tabelle lässt sich nicht lesen

- „reading spreadsheets needs the PHP extension zip“: Installieren Sie `ext-zip` für das PHP der Worker der
  Job-Warteschlange und des Webservers.
- „the spreadsheet has no sheet ...“: Die Option `sheet` nennt ein Blatt, das nicht in der Datei steht. Geben Sie
  seinen genauen Namen oder seine Nummer an.
- Datumsangaben kommen als Zahlen wie `45231`: Ergänzen Sie die Zeile um `date(format=excel)`
  ([mappings.md](mappings.md#datumsangaben-aus-tabellen)).

### „The tables of 7x SQLI Import are missing“

Führen Sie `./console ext:sqliimport:install-schema` aus. `--check` zeigt, was fehlt.

### „The job queue (expqueue) is not installed“

Aktivieren Sie die Erweiterung expqueue und führen Sie `./console ext:expqueue:install-schema` aus.

### Ein Lauf bleibt in der Warteschlange

Die Worker der Job-Warteschlange verarbeiten die Läufe, nicht die Seite. Ein Lauf, der auf *Queued* bleibt, wurde noch
von keinem Worker übernommen. Prüfen Sie, dass der Cron-Teil `expqueue` jede Minute läuft (oder sein systemd-Dienst),
und sehen Sie in die Übersicht der Job-Warteschlange (`expqueue/dashboard`, verlinkt im Reiter Importe). Um einen
Lauf sofort auf der Kommandozeile zu verarbeiten:

```bash
./console ext:sqliimport:run my_import --wait
```

Die Seite des Laufs ist nie der Grund: Sie fragt den Server alle zwei Sekunden nach dem Stand des Laufs und zeigt,
was sie bekommt ([siehe die häufigen Fragen](#wird-die-live-seite-eines-laufs-von-velocity-oder-einem-antwort-cache-zwischengespeichert)).

### „The import already runs (run #12)“

Ein Import läuft immer nur einmal gleichzeitig. Warten Sie, bis der Lauf endet, oder brechen Sie ihn ab:
`./console ext:sqliimport:cancel 12`. Einen Lauf, der auf `running` stehen bleibt, obwohl sein Job bei jedem
Versuch fehlschlug, beendet der Cron-Teil `sqliimport`; prüfen Sie, dass er jede Minute läuft, oder führen Sie
`./console ext:sqliimport:tick` aus.

### Zeitpläne starten nicht

- Der Cron-Teil `sqliimport` muss jede Minute laufen: `php runcronjobs.php -q sqliimport`.
- Der Zeitplan ist vielleicht inaktiv (ein Zeitplan `once` schaltet sich nach seinem Lauf ab).
- Mit `catch_up` = `skip` wartet ein Lauf, der mehr als zehn Minuten zu spät ist, auf den nächsten Termin.
- Ist der Import deaktiviert, wird sein geplanter Lauf übersprungen; `tick` gibt den Grund aus.

### Der Lauf schlug sofort fehl

Sehen Sie in das Protokoll des Laufs (`./console ext:sqliimport:status <Lauf-ID>` oder die Seite des Laufs).
Häufige Meldungen:

| Meldung | Ursache |
|---|---|
| `the uploaded source file is missing; upload it again` | Der Upload wurde aus dem Speicherverzeichnis entfernt |
| `the path is outside the directories ... AllowedPaths[] allows` | Tragen Sie das Verzeichnis in `[SourceSettings] AllowedPaths[]` ein |
| `the host ... is in a private or reserved network` | Eine Intranet-Adresse; siehe `[SourceSettings] AllowPrivateHosts` oder `AllowedHosts[]` im eigenen Abschnitt der Quelle |
| `only http and https addresses can be read` | Ein anderes Schema wurde verwendet |
| `the download is larger than [SourceSettings] MaxSourceMegabytes` | Erhöhen Sie das Limit oder liefern Sie die Datei in Teilen |
| `the mapping is not valid: ...` | Die Zuordnung nennt eine Klasse oder ein Attribut, das es nicht gibt, oder ihr fehlt die Zuordnungsregel oder ein Elternknoten |
| `unknown handler '...'` oder `unknown source type '...'` | Der Handler oder das Format ist nicht registriert, seine Klasse ist nicht in den Autoloads, oder ein langlebiger Worker startete, bevor er hinzukam ([Velocity oder PHP-FPM führen noch den alten Code aus](#velocity-oder-php-fpm-führen-noch-den-alten-code-aus)) |
| `SQL: only a SELECT (or WITH ... SELECT) query is allowed` | Die Abfrage einer SQL-Quelle schreibt oder enthält eine zweite Anweisung ([sources.md](sources.md#sql-datenbank-oder--abfrage-sql)) |

### Datensätze schlagen fehl

Filtern Sie die Seite des Laufs nach **Failed** (`sqliimport/run/<id>/(outcome)/failed`), um jede Meldung zu
sehen. Typische Ursachen:

- `the remote ID needs id, which the record does not have`: Das Muster nennt ein Feld, das dem Datensatz fehlt.
  Prüfen Sie die Feldnamen; CSV-Kopfzeilen unterscheiden Groß- und Kleinschreibung.
- `the required attribute '...' has no value`: Geben Sie eine Vorgabe an oder lassen Sie den Datensatz bewusst
  fehlschlagen.
- `the parent of the record does not exist (yet)`: Das Elternobjekt kommt später in der Quelle oder gar nicht.
  Sortieren Sie die Quelle so, dass Elternobjekte zuerst kommen. Ein Probelauf zählt die Elternobjekte mit, die er
  selbst anlegen würde ([siehe die häufigen Fragen](#was-sagt-ein-probelauf-eines-ordnerbaums-zu-den-kindern)), die Meldung bedeutet in einem Probelauf
  also dasselbe wie in einem Lauf.
- `lookup: no object with the remote ID '...'`: Das verknüpfte Objekt wurde noch nicht importiert.

Was zu tun ist, hängt davon ab, wo die Ursache liegt
([siehe die häufigen Fragen](#ich-habe-die-datei-korrigiert-aber-retry-failed-rows-schlägt-wieder-fehl)):

- **Die Ursache liegt außerhalb des Datensatzes** (ein Elternobjekt oder ein Ziel von `lookup`, das inzwischen
  importiert wurde, ein inzwischen hinzugefügtes Attribut der Klasse, ein vorübergehender Serverfehler): **Retry failed
  rows** (oder `./console ext:sqliimport:retry <Lauf-ID>`) verarbeitet die fehlgeschlagenen Datensätze erneut.
- **Die Ursache liegt im Datensatz** (ein fehlender Name, ein falsches Datum in der Datei): Korrigieren Sie die
  Quelle, dann **Run now**.

### In einem Lauf fehlen Datensätze

Zwei Datensätze mit demselben Schlüssel werden einmal verarbeitet: Der zweite wird nicht verarbeitet. Machen Sie
das Feld `key` der Quelle eindeutig oder lassen Sie es leer, damit Datensätze nach ihrer Position benannt werden.

### Nichts wird aktualisiert

- `update_policy` = `create_only` überspringt bestehende Objekte.
- Objekte im Papierkorb werden übersprungen.
- Die Zeilen sagen `unchanged`: Kein Feld weicht von der veröffentlichten Version ab, daher entstand keine Version.
  Ein Probelauf zeigt die verglichenen Werte.

### Ein Handler von 1.x

- Seine Zeilen haben das Ergebnis `processed`; die Engine weiß nicht, was der Handler getan hat. Lesen Sie seine
  Fortschrittsnotizen im Protokoll des Laufs
  ([migration-1x.md](migration-1x.md#durchgespieltes-beispiel-ein-handler-von-1x-in-der-engine-von-2x)).
- „Retry failed rows“ gibt es nicht. Starten Sie den Import erneut.
- Importaufträge von 1.x (**Request import** in der Übersicht oder ein POST an `sqliimport/addimport`) warten, bis
  der Cron-Teil `sqliimport` (oder der veraltete `sqliimport_run`) sie an die Job-Warteschlange übergibt. Bleibt
  einer offen, prüfen Sie, dass ein Cron-Teil läuft.

### Speicher und Zeit

Ein Abschnitt verarbeitet `[RunSettings] ChunkSize` Datensätze (Vorgabe 200) und darf bis zu 1800 Sekunden dauern
(`expqueue.ini [JobType_sqliimport.chunk] Timeout`). Senken Sie die `chunk_size` des Imports für aufwendige
Datensätze, etwa solche mit herunterzuladenden Bildern.

### Velocity oder PHP-FPM führen noch den alten Code aus

Nach einer Aktualisierung der Erweiterung, einer neuen Quelle, einem neuen Handler oder einer neuen Umwandlung oder
neuen Kernel-Klassen liegt die Änderung auf der Platte, aber ein langlebiger PHP-Prozess hat sie vielleicht noch nicht:

- **Exponential Velocity** lädt Klassen und Einstellungen beim Aufwärmen, und jeder Worker erbt sie. Ein Worker
  behält die Klassen seines Aufwärmens sein ganzes Leben lang; eine neue Klasse, ein neuer Block `[Source_<id>]` oder
  ein geänderter Dienst ist ihm also unbekannt („unknown source type“, „unknown handler“, eine Umwandlung, die nicht
  angeboten wird). Starten Sie Velocity nach dem Einspielen neu:

  ```bash
  php bin/php/ezpgenerateautoloads.php -e
  php bin/php/ezcache.php --clear-tag=ini
  ./console exp:velocity restart
  ```

  Wo die Installation es hat, erledigt `./console exp:velocity deploy` diese und die übrigen Schritte eines
  Einspielens in der richtigen Reihenfolge. Velocitys eigene PHP-Einstellungen setzen
  `opcache.file_update_protection=0` (`settings/velocity.ini`, `[PHPSettings] IniOptions[]`): Mit der Vorgabe von PHP,
  2 Sekunden, galt jede Datei, die nach dem Start eines Workers geschrieben wurde (der INI-Cache, die übersetzten
  Templates, die Klassen, die ein Einspielen neu erzeugt), sein ganzes Leben lang als zu neu und kam nie in den
  OPcache. Lassen Sie den Wert auf 0.
- **PHP-FPM** behält eine geänderte Datei in seinem OPcache, bis er sie neu prüft (`opcache.revalidate_freq`). Laden
  Sie den Pool, der die Website ausliefert, nach dem Einspielen neu (`systemctl reload <sein PHP-FPM-Dienst>`).
- **Die Worker der Job-Warteschlange** laufen im PHP ihres Cronjobs oder Dienstes. Der Cron-Teil `expqueue` startet
  jede Minute einen neuen PHP-Prozess, der den neuen Code liest; ein Worker, der als systemd-Dienst läuft, braucht
  einen Neustart.

### Ein Datenbanktreiber fehlt im Editor oder nur in den Läufen

Der Editor (Verbindung testen, Stichprobe und Vorschau) läuft im **PHP des Webservers**; die Läufe laufen im **PHP der
Worker der Job-Warteschlange** (dem PHP des Cronjobs oder von Velocity). Das sind oft verschiedene Programme mit
verschiedenen Erweiterungen: Auf einem Plesk-Server liefert vielleicht Plesks PHP-FPM die Verwaltung aus, während der
Cronjob das `php` des Systems startet.

- Die Karte im Katalog und *What this server can connect with* im Schritt Quelle zeigen die Treiber des PHP des
  Webservers und, sobald ein Lauf sie gemeldet hat, die der Worker („in the workers“). Die Worker vermerken ihr PHP
  und seine Treiber bei jedem Lauf.
- Ein Test im Editor, der einen fehlenden Treiber meldet, während die Liste sagt, dass die Worker ihn haben, ist kein
  Fehler: Läufe funktionieren trotzdem. Installieren Sie den Treiber im PHP des Webservers nur, wenn Sie dort auch
  Test und Vorschau wollen.
- Ein Lauf, der mit einem fehlenden Treiber fehlschlägt, braucht ihn im PHP der Worker: `php -m` dieses Programms
  listet seine Erweiterungen.

Auf dem Server der Dokumentation zum Beispiel liest das PHP des Webservers SQL Server über `PDO_ODBC` mit FreeTDS,
während das PHP der Kommandozeile gar keinen Treiber für SQL Server hat.

## Häufige Fragen

### Wird die Live-Seite eines Laufs von Velocity oder einem Antwort-Cache zwischengespeichert?

Nein. Die Seite des Laufs (`sqliimport/run/<id>`) lädt einmal und fragt den Server dann **alle zwei Sekunden** nach
Status, Fortschritt, Zählern und neuen Protokollzeilen des Laufs, über die Serverfunktion `sqliimport::runstatus`
(ezjscore, aufgerufen über `Exp.io` von expui). Der Aufruf ist ein **POST** mit dem Formular-Token, also beantwortet
ihn weder der Antwort-Cache von Velocity noch ein anderer HTTP-Cache. Wenn der Lauf endet, lädt die Seite neu, um die
Datensätze zu zeigen. Eine ausgebliebene Antwort (ein Neustart, das Netz) wird bis zu fünfmal im Abstand von fünf
Sekunden erneut versucht; danach bleibt die Seite, wie sie ist, und **Refresh** lädt sie.

Ein Lauf, der auf dieser Seite auf *Queued* bleibt, wartet auf einen Worker der Job-Warteschlange
([Ein Lauf bleibt in der Warteschlange](#ein-lauf-bleibt-in-der-warteschlange)); Neuladen ändert daran nichts.

### Was sagt ein Probelauf eines Ordnerbaums zu den Kindern?

Ein Probelauf veröffentlicht nichts. Baut ein Import in einem Lauf einen Baum auf (Ordner vor ihren Dokumenten: die
Dokumente eines ZIP, die Ordner eines Laufwerks, eine CSV mit Eltern-IDs), gibt es die Ordner, die der Probelauf
anlegen würde, für die Datensätze darunter nicht. Der Probelauf merkt sich die Objekte, die er anlegen würde, also
wird ein Datensatz unter einem davon als **Created** gemeldet, mit der Meldung „dry run: would be created below
<Remote-ID des Elternobjekts>, which this run would create“. Ein Datensatz, dessen Elternobjekt weder auf der Website
existiert noch früher im Lauf angelegt wird, schlägt mit „the parent of the record does not exist (yet)“ fehl, im
Probelauf wie im echten Lauf.

Das durchgespielte Beispiel in
[source-examples-platforms.md](source-examples-platforms.md#dokumente-eine-datei-oder-ein-ordner-als-zip-documents)
zeigt es: Ein Probelauf einer frischen Kopie des Dokumenten-Imports meldet alle 5 Datensätze als angelegt. Vor 2.0.0
schlugen die Kinder neuer Ordner im Probelauf fehl (der Probelauf #162 hatte 1 angelegt und 4 fehlgeschlagen),
obwohl der echte Lauf #163 alle 5 anlegte.

### Ich habe die Datei korrigiert, aber Retry failed rows schlägt wieder fehl

**Retry failed rows** liest die Quelle nicht erneut. Es verarbeitet die **gespeicherten Datensätze** der
fehlgeschlagenen Zeilen dieses Laufs, so wie sie damals gelesen wurden. Ein Datensatz, dessen eigene Daten falsch
waren, schlägt mit derselben Meldung wieder fehl, wie auch immer die Datei inzwischen korrigiert wurde.

- Die Ursache lag im Datensatz (ein leerer Name, ein falsches Datum, ein Preis, der keine Zahl ist): Korrigieren Sie
  die Quelle (die korrigierte Datei hochladen, die Zeile in der Datenbank berichtigen), dann **Run now**. Der Import
  liest die Quelle erneut; die Zeilen, die vorher stimmten, kommen unverändert heraus, das kostet also wenig.
- Die Ursache lag außerhalb des Datensatzes: ein Elternobjekt oder ein Ziel von `lookup`, das damals fehlte und
  inzwischen importiert wurde, ein der Klasse hinzugefügtes Attribut, ein vorübergehender Fehler eines Servers. Dann
  ist **Retry failed rows** richtig: dieselben Datensätze, die neuen Umstände.

Ein Handler von 1.x speichert keine Datensätze, seine Läufe haben also kein Retry; starten Sie den Import erneut.

<a id="prices-are-0-or-every-run-updates-every-price"></a>

### Preise sind 0, oder jeder Lauf aktualisiert jeden Preis

Der Datentyp `ezprice` (das Attribut `price` der Webshop-Klasse `product`) liest einen Wert in seiner Textform
`Betrag|MwSt.-Typ|enthält MwSt.`, zum Beispiel `6.90|1|1`.

**Eine bloße Zahl erhält die MwSt. des Attributs.** Eine Zuordnungszeile, die `ezprice` nur einen Betrag gibt
(`6.90`, `6,90`, `1.234,50`), wird um den MwSt.-Typ und die Einstellung „Preis enthält MwSt.“ des Klassenattributs
ergänzt: `6.90` wird zu `6.9|1|1`, wenn der vorgegebene MwSt.-Typ des Attributs 1 ist und Preise die MwSt. enthalten.
Vor 2.0.0 wurde ein solcher Preis als 0 gespeichert; der Probelauf eines älteren Stands zeigte jedes Objekt mit dem
Preis `0|1|1`.

Für einen anderen MwSt.-Typ oder „ohne MwSt.“ als die Vorgabe des Attributs schreiben Sie die Textform selbst:
`template(text="{price}|1|0")` (MwSt.-Typ 1, der Preis enthält keine MwSt.). Die Produktvorlage der Shop-Formate tut
das ([sources.md](sources.md#die-produktvorlage)).

**Ein unveränderter Preis bleibt unverändert.** `ezprice` speichert einen Betrag so, wie PHP die Zahl schreibt, und
gibt ihn so zurück (`6.90` als `6.9|1|1`). Die Engine schreibt jeden Preis vor dem Vergleich in dieser Form, also sind
`6.90` in der Quelle und `6.9` im Objekt derselbe Preis, und ein Lauf aktualisiert nur die Produkte, deren Preis sich
wirklich geändert hat. Vor 2.0.0 galt jeder Preis, der auf eine Null endet, bei jedem Lauf als „aktualisiert“.

Ein Wert, der keine Zahl ist (`free`, `auf Anfrage`), bleibt, wie er ist: Das Preisattribut lehnt ihn ab, und die Zeile
des Datensatzes sagt es.

### Warum kann ein Import die eigene Datenbank der Website nicht lesen?

Die SQL-Quelle kann die eigene Datenbank der Installation (der Treiber oder die Verbindung `site`) nur lesen mit

```ini
[SqlSourceSettings]
SiteDatabase=enabled
```

in `settings/override/sqliimport.ini.append.php`. Standardmäßig ist das aus, weil diese Tabellen jedes Benutzerkonto
mit seinem Passwort-Hash, die Rollen und die Sitzungen enthalten, und jede Person, die Importe bearbeiten darf, sie
mit einer Abfrage lesen könnte. Auch nur lesend, über eine eigene Verbindung, ist das mehr, als die meisten
Redakteure sehen sollten. Aktivieren Sie es nur, wenn die Personen, die Importe bearbeiten, alles davon sehen dürfen.
Sonst legen Sie eine Datenbanksicht oder einen eigenen nur lesenden Benutzer mit nur den Tabellen an, die der Import
braucht, und dafür eine benannte Verbindung (Setup, Data tables, Connections).

### Oracle: oci8, pdo_oci oder ezoracle?

Zwei Stellen lesen Oracle, mit verschiedenen Treibern:

- **Die Optionen der SQL-Quelle** (Host, Dienstname, Benutzer, Geheimnis) verbinden sich über die PHP-Erweiterung
  `oci8`, oder über `pdo_oci`, wenn `oci8` fehlt (`[SqlSourceSettings] OracleDriver=auto`, `oci8` oder `pdo_oci`).
  Sie brauchen den Oracle Instant Client im PHP der Worker.
- **Eine benannte Verbindung** mit der Engine Oracle (Setup, Data tables, Connections, Exponential 6.0.15) öffnet der
  Kernel: über die Erweiterung **ezoracle** und ihren Handler `eZOracleDB`, wenn ezoracle aktiv ist, sonst über PDO
  `oci`. Der Import nennt dann nur die Verbindung.

Die SQL-Karte des Katalogs listet beide, zum Beispiel „Oracle: oci8, eZOracleDB (ezoracle) for named connections“
([source-examples-databases.md](source-examples-databases.md#oracle-oci8-und-ezoracledb)). Zeigt die Karte Oracle in
Grau, hat dieses PHP keinen der Treiber. ezoracle braucht es für Importe nicht: `oci8` allein genügt für die eigenen
Optionen der Quelle.
