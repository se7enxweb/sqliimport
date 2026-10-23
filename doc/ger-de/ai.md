# Die KI-Unterstützung

Die KI-Unterstützung hilft bei den Teilen eines Imports, die am meisten Hinsehen kosten: welches Feld in welches
Attribut gehört, welche Klasse zu einer Quelle passt, warum ein Datensatz fehlschlug und was ein Lauf getan hat. Sie
**unterstützt nur**: Sie macht Vorschläge, und ein Mensch entscheidet. Nichts davon wird übernommen, ohne dass ein
Mensch es annimmt und das Formular speichert, und ein Import, dessen Zuordnung angenommene Vorschläge enthält, braucht
vor dem nächsten echten Lauf einen Probelauf.

English: [../ai.md](../ai.md).

## Was sie anbietet

| Wo | Schaltfläche | Was geschieht |
|---|---|---|
| Editor, Schritt Zuordnung | **Suggest with AI** (neben Auto-match by name) | Ein Vorschlag je Attribut der Klasse: ein Quellfeld, Transformationen in der einzeiligen Kettenschreibweise, eine Sicherheit (als Abzeichen: grün ab 80 %, gelb ab 50 %, rot darunter) und eine kurze Begründung, in einer zusätzlichen Spalte des Rasters. **Accept** übernimmt eine Zeile ins Formular, **Accept all** jede Zeile mit Vorschlag. |
| Editor, Schritt Zuordnung | **Suggest a class with AI** | Die drei Inhaltsklassen, die am besten zu den Feldern der Quelle passen, je mit Sicherheit und Begründung; **Use this class** wählt sie im Formular. |
| Editor, Schritt Verarbeitung, Ziel „Rows of a data table“ | **Suggest a data table with AI** | Dasselbe für die Datentabellen (persistente Klassen); **Use this table** wählt sie im Formular. |
| Seite eines Laufs, eine fehlgeschlagene Zeile | **Explain** | Eine kurze Erklärung, was schiefging, und ein Vorschlag, wie die Zuordnung oder die Quelle zu ändern ist. |
| Seite eines beendeten Laufs | **Summarize** | Eine kurze Zusammenfassung des Laufs: was er tat, was schiefging, worauf als Nächstes zu schauen ist. |

Die Schaltflächen senden nur das Formular der Seite. Einen Vorschlag anzunehmen ändert, was das Formular zeigt, nicht
was gespeichert ist: Gespeichert wird nur mit den eigenen **Save**-Schaltflächen des Formulars, und wer die Seite
ohne Speichern verlässt, behält den Import, wie er war. Ein Vorschlag, der schon im Formular steht, ist mit „In the
form“ markiert.

### Angenommene Vorschläge brauchen einen Probelauf

Wird ein Schritt Zuordnung oder Verarbeitung mit angenommenen Vorschlägen gespeichert (eine Zeile, eine Klasse oder
eine Datentabelle), wird diese Version des Imports markiert. Bis ein Probelauf dieser oder einer späteren Version
beendet ist (abgeschlossen oder mit Fehlern abgeschlossen), wird ein echter Lauf (Run now, ein Zeitplan, ein Trigger,
die Kommandozeile, Retry failed rows) mit „the mapping has lines suggested by the AI that were accepted: run a dry run
of this version first“ abgelehnt. Starten Sie den Probelauf mit **Dry run** in der Liste der Importe (der Schritt
Vorschau des Editors zählt nicht: er ist kein Lauf), sehen Sie sich die Ergebnisse an und starten Sie dann den Import.

## Was an das Modell geht

Standardmäßig **nur Namen und Typen**:

- **Zuordnungsvorschläge:** die Attribute der Klasse (Bezeichner, Name, Datentyp) und die Namen der Felder der Quelle
  mit dem Typ, der aus der Stichprobe erkannt wird (integer, number, boolean, date, datetime, email, url, html, list,
  text oder empty). Die Werte der Stichprobe werden hier gelesen, um die Typen zu erkennen; gesendet werden sie nicht.
- **Klassenvorschläge:** die Bezeichner und Namen der Klassen (oder Datentabellen) und die Feldnamen und -typen.
- **Explain:** die Meldung der Zeile und die Zuordnungszeilen, die sie betrifft (Attribut, Quellfeld, Transformationen,
  ob Pflicht; keine Standardwerte). Eine Zeile ist betroffen, wenn die Meldung ihr Attribut oder ihr Quellfeld nennt;
  nennt sie keines, werden alle Zeilen gesendet.
- **Summarize:** die eigenen Zahlen des Laufs: Status, Art, Auslöser, die Anzahlen je Ergebnis, die Dauer, die
  Wartezeit, die Zeit je Datensatz, der Speicherhöchstwert, die fünf häufigsten Fehlermeldungen mit ihrer Anzahl und
  die Felder, die Aktualisierungen am häufigsten änderten, mit ihrer Anzahl. Nie ein Wert eines Datensatzes.

Meldungen werden vor dem Senden maskiert; in Zusammenfassungen fallen außerdem die Teile einer Meldung in
Anführungszeichen weg, weil sie oft einen Wert des Datensatzes enthalten.

### Beispielwerte nur, wenn der Import es wählt

Der Schritt Verarbeitung eines Imports hat die Option **Send sample values to the AI** (standardmäßig aus). Ist sie an,
bekommen die Zuordnungs- und Klassenvorschläge zusätzlich bis zu `[AiSettings] SampleValues` (3) verschiedene
Beispielwerte je Feld, je höchstens 80 Zeichen, und **Explain** zusätzlich die Werte der zugeordneten Felder des
fehlgeschlagenen Datensatzes. Bevor etwas gesendet wird, werden die Werte hier auf dem Server maskiert:

| Wert | Gesendet als |
|---|---|
| E-Mail-Adressen | `[email]` |
| IBAN-artige Werte (zwei Buchstaben, zwei Ziffern, 11 bis 30 Buchstaben oder Ziffern) | `[iban]` |
| Telefonnummer-artige Werte: sieben Ziffern oder mehr mit Leerzeichen, Punkten, Bindestrichen, Schrägstrichen oder Klammern dazwischen (auch Karten- und Kontonummern); kein Datum und keine Uhrzeit | `[phone]` |

Die Maskierung ist ein Sicherheitsnetz, kein Grund, personenbezogene Daten zu senden: Schalten Sie die Option nur für
Quellen ein, deren Werte nach Ihren Datenschutzregeln an den Anbieter des Modells gehen dürfen.

## Nur über expai

Die Unterstützung hat keinen API-Schlüssel, keinen Anbieter und keine eigene Anfrage: Jede Anfrage geht über die
Erweiterung **expai** (Exponential Intelligence) der Installation, als eine ihrer Anfragen:

- der **Hauptschalter** von expai (`expai.ini [AISettings] Enabled`, `DisabledSiteAccesses[]`) und seine
  **Veto-Regeln**;
- die **Berechtigungen** von expai: Der Benutzer braucht die Richtlinie `expai/write` und darf das Modell verwenden,
  das expai für ihn wählt (den Standard der Site oder seine eigene Wahl, wo die Modellwahl an ist und
  `expai/model_use` dessen Anbieter erlaubt);
- die **Datenrichtlinie** von expai: Die Sektion des Ziels des Imports (seine Sektion, sonst die Sektion seines
  Elternknotens) und die Inhaltsklasse müssen an den Anbieter des Modells gehen dürfen (`[DataPolicy]`); für eine
  Datentabelle oder eine Klasse, die noch zu wählen ist, die Sektion allein;
- die **Limits** von expai, das **Web-Budget** (ein Modell, das für eine Seitenanfrage zu langsam ist, wird abgelehnt)
  und das **Monatsbudget** (`[Budgets]`: eine Anfrage, deren Schätzung nicht passt, wird abgelehnt);
- das **Nutzungsprotokoll** und das **Audit-Protokoll** von expai: Jede Anfrage ist eine Zeile des Nutzungsprotokolls
  mit der Aktion `sqliimport:mapping`, `sqliimport:class`, `sqliimport:explain` oder `sqliimport:summary` und dem
  Elternknoten des Ziels.

Das Audit-Protokoll von 7x SQLI Import verzeichnet `system.import.ai.suggest` (Zuordnungs- oder Klassenvorschläge
angefragt, mit der Zahl der Vorschläge und ob Werte gesendet wurden), `system.import.ai.accept` (ein Schritt mit
angenommenen Vorschlägen gespeichert, mit dem Angenommenen), `system.import.ai.explain` und
`system.import.ai.summary`. Sie enthalten nie einen Wert eines Datensatzes oder den Text einer Antwort.

## Geprüfte Antworten

Das Modell wird um JSON Lines gebeten (ein JSON-Objekt je Zeile). Seine Antwort wird geprüft, bevor etwas gezeigt wird:

- eine Antwort, die kein JSON Lines ist (Fließtext, eine Zeile, die kein Objekt ist, mehr Zeilen als erbeten, eine zu
  lange Antwort), wird **abgelehnt** mit „The answer of the model could not be read.“;
- ein Zuordnungsvorschlag wird **verworfen**, wenn sein Attribut keines der Klasse ist, sein Quellfeld keines der
  Stichprobe, seine Transformationen keine lesbare Kette bekannter Transformationen mit gültigen Argumenten oder seine
  Sicherheit keine Zahl zwischen 0 und 1; von zwei Vorschlägen für dasselbe Attribut zählt der sicherere. Die Seite
  sagt, wie viele weggelassen wurden;
- ein Klassenvorschlag wird verworfen, wenn seine Klasse keine der Kandidaten ist; die besten drei werden gezeigt;
- eine Erklärung braucht die Erklärung und den Vorschlag, eine Zusammenfassung ihren Text; Auszeichnungen werden
  entfernt und lange Texte gekürzt.

## Wann sie erscheint

Die Schaltflächen erscheinen nur, wenn all das gilt; sonst sehen die Seiten aus wie ohne die Unterstützung:

1. `sqliimport.ini [AiSettings] Enabled=enabled` (standardmäßig `disabled`);
2. die Erweiterung expai ist installiert und aktiv (ihre Klassen gibt es) und für den Siteaccess eingeschaltet;
3. der Benutzer hat `expai/write` und darf das Modell von expai verwenden;
4. der Import läuft auf der 2.x-Engine (kein 1.x-Handler), und der Benutzer darf ihn bearbeiten (Editor) oder sehen
   (Seite eines Laufs).

## Antworten bleiben für die Sitzung

Eine Antwort bleibt in der Sitzung des Benutzers (die letzten 40): Zuordnungsvorschläge je Import, Klasse und Satz von
Feldern; Klassenvorschläge je Import; Erklärungen je Lauf und Zeile; Zusammenfassungen je Lauf und Zustand. Wird die
Seite wieder geöffnet, erscheint die gespeicherte Antwort, ohne das Modell erneut zu fragen; eine erneute Anfrage mit
denselben Feldern verwendet sie ebenfalls. Eine neue Sitzung fragt neu.

## Einstellungen

```ini
[AiSettings]
# enabled oder disabled
Enabled=disabled
# maskierte Beispielwerte je Feld für einen Import, der es wählt, 1-5
SampleValues=3
# die meisten Felder der Quelle, die eine Anfrage beschreibt
MaxFields=150
```

## Für Entwickler

`expImportAi` (classes/exp/ai/) enthält die Anfragen, die Maskierung, die Typerkennung, das Lesen und Prüfen der
Antworten, das Übernehmen in ein Formular und die Probelauf-Regel; mit einem Modell spricht die Klasse nur über einen
`expImportAiTransport`. Der Transport der Installation ist `expImportAiExpaiTransport`. Die Unit-Tests
(`tests/unit/AiAssistTest.php`) setzen `expImportAi::$transport` auf ein Double, das ohne Anbieter antwortet, und
prüfen, dass ohne die Option kein Beispielwert gesendet wird, dass die Maskierung greift, dass fehlerhafte Antworten
abgelehnt und Vorschläge geprüft werden; ein Test vergleicht außerdem die Aufrufe des expai-Transports mit den
Methoden des expai der Installation.
