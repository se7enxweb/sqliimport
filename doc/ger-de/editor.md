# Der Import-Editor

Der Import-Editor (`sqliimport/edit`) legt Importe in der Administration an und ändert sie, ohne PHP. Er führt
einen Import durch sechs Schritte: Quelle (Source), Verarbeitung (Processing), Zuordnung (Mapping), Vorschau
(Preview), Zeitplan (Schedule) und Prüfung (Review and save). Jeder Schritt ist ein Formular, das für sich
gespeichert wird. So geht ein Import nie auf halbem Weg verloren, und jeder Schritt lässt sich später wieder öffnen.

Die Oberfläche der Administration ist auf Englisch; die Namen der Schaltflächen und Felder stehen hier deshalb so,
wie sie auf der Seite erscheinen.

English: [../editor.md](../editor.md).

## Den Editor öffnen

- **New import**: die Schaltfläche auf der Übersicht (`sqliimport/list`) oder der Link im linken Menü des Reiters
  Importe. Sie öffnet `sqliimport/edit/new`, das immer mit dem Schritt Quelle beginnt.
- **Edit**: die Schaltfläche neben einem Import in der Liste. Sie öffnet `sqliimport/edit/<id>/(step)/source`.
- **Edit** auf der Seite Schedules öffnet direkt den Schritt Zeitplan dieses Imports.

Jeder Schritt hat eine eigene Adresse, `sqliimport/edit/<id>/(step)/<step>`; die Leiste der Schritte oben auf der
Seite verlinkt jeden Schritt eines gespeicherten Imports.

Der Editor braucht die Richtlinie `sqliimport/manage` (oder `sqliimport/manageimports` von 1.x). Mit einer
Handler-Einschränkung lassen sich nur Importe der erlaubten Handler öffnen ([permissions.md](permissions.md)).

Nicht jeder Import hat alle sechs Schritte:

- den Schritt **Zuordnung** gibt es nur für den Handler `mapping`;
- den Schritt **Vorschau** gibt es nicht für einen Handler von 1.x, weil ein Handler von 1.x nicht laufen kann,
  ohne zu veröffentlichen.

## Die Schaltflächen eines Schritts

| Schaltfläche | Wirkung |
|---|---|
| **Save and continue** | Speichert den Schritt und öffnet den nächsten |
| **Save** | Speichert den Schritt und bleibt darauf (Quelle, Verarbeitung, Zuordnung und Zeitplan) |
| **Back to the imports** | Zurück zur Übersicht; nicht Gespeichertes geht verloren |

Jeder Wert wird auf dem Server geprüft: die Optionen der Quelle und des Handlers gegen ihre Schemata, die Zuordnung
gegen die Inhaltsklasse, der Zeitplan nach seinen eigenen Regeln. Ein Schritt mit Fehlern wird erneut gezeigt, mit
markierten Feldern und einer Liste der zu prüfenden Punkte. Jedes Speichern ist eine POST-Anfrage mit dem
Formular-Token und erhöht die Versionsnummer des Imports; die Änderung wird im Audit-Protokoll festgehalten.

## Schritt 1: Quelle (Source)

| Feld | Bedeutung |
|---|---|
| Name | Pflichtfeld. Der Name, den die Administration zeigt |
| Identifier | Nennt den Import auf der Kommandozeile (`./console ext:sqliimport:run <identifier>`). Kleinbuchstaben, Ziffern, `_` und `-`, eindeutig. Leer: aus dem Namen gebildet. |
| Description | Freier Text |
| Source type | CSV, XML, RSS/Atom, JSON, Tabelle (XLSX, ODS) oder „A 1.x handler that reads its own source“ |
| Where the data comes from | Eine hochgeladene Datei, eine Adresse (`http` oder `https`, bei jedem Lauf gelesen) oder eine Datei auf dem Server |
| Options of the source | Die Optionen des gewählten Quelltyps, etwa das Trennzeichen einer CSV-Datei oder das Blatt einer Tabelle ([sources.md](sources.md)) |

Hinweise:

- Ein Upload darf höchstens `[SourceSettings] MaxSourceMegabytes` groß sein. Ist eine Datei hochgeladen, nennt der
  Schritt sie; wählen Sie nur dann erneut eine Datei, wenn Sie sie ersetzen möchten.
- „A file on the server“ wird nur angeboten, wenn `[SourceSettings] AllowedPaths[]` ein Verzeichnis nennt, und der
  Pfad muss in einem davon liegen.
- Die Optionen eines anderen Quelltyps erscheinen, nachdem der Schritt mit diesem Typ gespeichert wurde.
- Ein neuer Import wird angelegt, wenn der Schritt Quelle zum ersten Mal gespeichert wird. Er bleibt deaktiviert,
  bis der Schritt Prüfung ihn aktiviert.

Für einen Handler von 1.x wählen Sie hier den Handler. Er liest seine eigene Quelle, daher hat der Schritt keine
Datei und keine Adresse.

## Schritt 2: Verarbeitung (Processing)

| Feld | Bedeutung |
|---|---|
| How the records are processed | Die Zuordnung ohne Programmierung, `mapping`, oder ein anderer Handler für 2.x. Ein Handler für 2.x zeigt ein Formular aus seinen Optionen; ein Handler von 1.x nimmt seine Optionen als Text, ein `name=value` pro Zeile, wie in 1.x. |
| Parent node of new content | Eine Knoten-ID. Eine Zuordnung kann pro Datensatz einen anderen Elternknoten nennen. |
| Section | Eine Sektions-ID; `0`: die Sektion des Elternknotens |
| Language | Die Sprache des Imports; leer: die Standardsprache der Website |
| Run as user (robot) | Eine Benutzer-ID; `0`: der Benutzer, der den Lauf startet oder plant. Es gelten dessen Berechtigungen. |
| Records per chunk | Zwischen 1 und 5000 (je ein Job der Job-Warteschlange) |
| At most this many records per run | `0`: keine Grenze |
| Existing content | Neue anlegen, vorhandene aktualisieren (`update`); nur anlegen, vorhandene unverändert lassen (`create_only`); nur vorhandene aktualisieren (`update_only`) |
| Send sample values to the AI | Nur mit der KI-Unterstützung ([ai.md](ai.md)); standardmäßig aus. Aus: Die KI bekommt nur die Namen und Typen der Felder. An: zusätzlich einige Beispielwerte je Feld und die Werte einer fehlgeschlagenen Zeile, die sie erklärt; E-Mail-, Telefon- und IBAN-artige Werte werden vorher maskiert. |

Mit dem Ziel „Rows of a data table“ und der KI-Unterstützung zeigt **Suggest a data table with AI** die drei
Datentabellen, die am besten zu den Feldern passen; **Use this table** wählt eine im Formular (gespeichert mit dem
Schritt).

## Schritt 3: Zuordnung (Mapping)

Der Schritt Zuordnung legt fest, welches Feld eines Datensatzes welches Attribut einer Inhaltsklasse füllt
([mappings.md](mappings.md)).

1. **Inhaltsklasse** (Content class). Wählen Sie die Klasse und speichern Sie den Schritt, um ihre Attribute zu
   sehen. Wählen Sie später eine andere Klasse, beginnt ein neues Raster; die Zeilen der alten Klasse gelten nicht
   mehr.
2. **Sprache der Felder** (Language of the fields). Leer: die Sprache des Imports.
3. **Wie ein Datensatz sein Objekt findet** (How a record finds its object). Entweder über eine Remote-ID aus dem
   Datensatz, mit einem Muster wie `product-{sku}` (Felder des Datensatzes in geschweiften Klammern), oder über den
   Wert eines Attributs: das Attribut und das Quellfeld, die verglichen werden. Ohne Treffer wird ein neues Objekt
   angelegt.
4. **Wohin neue Objekte kommen.** Der Elternknoten neuer Objekte; wahlweise ein Feld mit der Remote-ID des
   Elternknotens pro Datensatz und ein Präfix davor; und ein Feld mit weiteren Platzierungen.
5. **Das Raster.** Eine Zeile pro Attribut der Klasse, mit seinem Namen, seinem Datentyp und der Angabe, ob die
   Klasse es verlangt:

   | Spalte | Bedeutung |
   |---|---|
   | Source field | Der Pfad eines Felds im Datensatz (`title`, `author.name`). Die Felder der Stichprobe stehen über dem Raster. |
   | Transforms | Eine Kette als einzeiliger Text, zum Beispiel `trim \| date(format=d.m.Y)` ([mappings.md](mappings.md#der-einzeilige-text-einer-kette)) |
   | Default | Der Wert, wenn der Quellwert nach den Umwandlungen leer ist |
   | Required | Der Datensatz schlägt fehl, wenn der Wert dann noch leer ist |

   Eine Zeile ohne Quellfeld, ohne Umwandlungen und ohne Vorgabe entfällt. Die Tabelle „The transforms“ unter dem
   Raster nennt jede Umwandlung mit ihren Argumenten.

**Auto-match by name** füllt die Zeilen, die noch kein Quellfeld haben, aus den Feldern der Stichprobe: Die Namen
des Attributs (Bezeichner und Name) und des Felds werden ohne Groß- und Kleinschreibung, Akzente und Satzzeichen
verglichen, zuerst auf Gleichheit, dann darauf, ob ein Name den anderen enthält. Jedes Feld wird einmal verwendet.
Ein Rich-Text-Attribut bekommt die Umwandlung `richtext`, ein Datum `date`, ein Bild `image` und ein Kontrollkästchen
`bool`. Auto-match verwendet keinen externen Dienst. Es speichert die Zuordnung und bleibt auf dem Schritt, mit der
Meldung „Auto-match filled N lines. Check them, then save.“; prüfen Sie die Zeilen, bevor Sie weitergehen.

**Suggest with AI** (mit der KI-Unterstützung, [ai.md](ai.md)) fragt das Modell des expai der Installation nach einem
Quellfeld und Umwandlungen je Attribut, aus den Attributen der Klasse und den Namen und Typen der Felder
(Beispielwerte nur, wenn der Import es im Schritt Verarbeitung wählt, und dann maskiert). Die Vorschläge erscheinen in
einer zusätzlichen Spalte des Rasters mit einem Abzeichen für die Sicherheit und einer kurzen Begründung; Vorschläge,
die ein Attribut, ein Feld oder eine Umwandlung nennen, die es nicht gibt, werden weggelassen. **Accept** übernimmt
eine Zeile ins Formular, **Accept all** jede Zeile mit Vorschlag; gespeichert wird erst, wenn Sie den Schritt
speichern, und ist ein Schritt mit angenommenen Zeilen gespeichert, braucht der Import vor dem nächsten echten Lauf
einen Probelauf. **Suggest a class with AI** neben der Klasse zeigt die drei Klassen, die am besten zu den Feldern
passen; **Use this class** wählt eine im Formular.

Die Zuordnung wird auch dann gespeichert, wenn sie noch nicht vollständig ist, damit nichts Eingegebenes verloren
geht; die Fehler werden aufgeführt, bis sie behoben sind.

## Schritt 4: Vorschau (Preview)

Die Vorschau läuft in der Anfrage selbst; sie startet keinen Job und speichert keinen Lauf.

- **Sample of the source**: die ersten 20 Datensätze als Tabelle (bis zu 30 Spalten), mit dem, was die Quelle
  erkannt hat, etwa Trennzeichen und Zeichenkodierung einer CSV-Datei oder die Blätter einer Tabelle.
- **Dry run of the first records**: die ersten `[RunSettings] DryRunRows` Datensätze (standardmäßig 20) durch die
  Zuordnung oder den Handler. Für jeden Datensatz: was geschehen würde (angelegt, aktualisiert, unverändert,
  übersprungen, fehlgeschlagen), das Objekt und die Felder, die sich ändern würden, mit dem aktuellen und dem
  neuen Wert.

Nichts wird veröffentlicht, und kein Bild und keine Datei wird heruntergeladen: Ein Probelauf verwendet die Adresse
anstelle der Datei. Kann die Quelle nicht gelesen werden, zeigt der Schritt die Meldung. Ändern Sie die Quelle oder
die Zuordnung und öffnen Sie die Vorschau erneut.

## Schritt 5: Zeitplan (Schedule)

| Feld | Bedeutung |
|---|---|
| Runs by itself | Nie (nur von Hand oder von der Kommandozeile); einmal; alle N Minuten; stündlich; täglich; wöchentlich; monatlich; ein Cron-Ausdruck |
| Date and time | Der erste oder einzige Lauf; stündlich, täglich, wöchentlich und monatlich übernehmen davon Minute, Uhrzeit, Wochentag oder Tag |
| Every how many minutes | Mindestens 5 |
| Cron expression | Minute, Stunde, Tag des Monats, Monat, Wochentag ([schedules.md](schedules.md)) |
| Time zone | Die Zeitzone der Zeiten oben |
| When runs were missed | Einmal so bald wie möglich laufen (`one`) oder auf die nächste Zeit warten (`skip`) |
| E-mail when a run fails | Adressen, eine pro Zeile |
| The schedule is active | Aus: Der Zeitplan bleibt erhalten, ist aber angehalten |

Nach dem Speichern nennt der Schritt die nächsten fünf Laufzeiten. Der Editor führt einen Zeitplan pro Import;
„Never“ entfernt ihn. Auf der Seite Schedules werden Zeitpläne aufgelistet, angehalten und fortgesetzt
([schedules.md](schedules.md)).

Unter dem Zeitplan schaltet der Abschnitt **Started by another system (webhook or API)** den Auslöser des Imports an
oder aus, wählt seinen Laufbenutzer, erlaubt Daten im Aufruf, zeigt ein neues Signaturgeheimnis oder einen neuen
API-Schlüssel einmal und erneuert das Geheimnis oder sperrt Schlüssel. Jedes davon ist ein eigenes Formular
([triggers.md](triggers.md)).

## Schritt 6: Prüfung (Review and save)

Die Prüfung zeigt den Import als Zusammenfassung: Name und Bezeichner, Quelle, Verarbeitung, Ziel und Zeitplan. Sie
nennt außerdem, was noch fehlt, bevor der Import laufen kann, etwa eine Zuordnung ohne Regel zum Finden des Objekts.

- **The import is enabled**: Ein aktivierter Import kann laufen, und sein Zeitplan startet ihn. Solange etwas fehlt,
  lässt er sich nicht aktivieren.
- **Save** speichert den Import und kehrt zur Übersicht zurück.
- **Save and run** speichert den aktivierten Import, startet sofort einen Lauf und öffnet dessen Seite
  ([runs.md](runs.md)).

## Ändern, kopieren und löschen

Die Liste der Importe auf der Übersicht hat diese Aktionen:

| Aktion | Richtlinie | Wirkung |
|---|---|---|
| Run now | run oder manage | Startet einen Lauf eines aktivierten Imports |
| Dry run | run oder manage | Startet einen Lauf, der nichts veröffentlicht (nicht für Handler von 1.x) |
| Edit | manage | Öffnet den Editor |
| More > Duplicate | manage | Legt eine deaktivierte Kopie „Copy of ...“ mit Quelle, Verarbeitung und Zuordnung an, ohne Zeitpläne und Läufe, und öffnet sie im Editor |
| More > Enable / Disable | manage | Schaltet den Import ein oder aus |
| More > Delete | manage | Entfernt den Import mit seiner Zuordnung und seinen Zeitplänen nach einer Rückfrage. Seine Läufe bleiben im Verlauf, bis sie bereinigt werden. Ein Import, der gerade läuft, lässt sich nicht löschen; brechen Sie den Lauf zuerst ab. |

Um Importe von einer Installation in eine andere zu übertragen, exportieren Sie sie auf der Seite Settings als JSON
([settings.md](settings.md#die-seite-settings)).
