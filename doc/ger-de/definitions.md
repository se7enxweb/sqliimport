# Importe exportieren, importieren, kopieren und als Vorlage nutzen

Ein Import kann die Installation, auf der er entstand, als JSON-Datei verlassen, auf einer anderen wieder angelegt,
unter neuem Namen kopiert oder zur Vorlage werden, mit der neue Importe beginnen. Keiner dieser Wege enthält einen
geheimen Wert, einen API-Schlüssel oder eine hochgeladene Datei.

English: [../definitions.md](../definitions.md).

| Was | Wo | Richtlinie |
|---|---|---|
| **Download as JSON** (als JSON herunterladen) | Übersicht, Menü **More** eines Imports; letzter Schritt des Editors (Review and save) | Manage imports (für den Handler des Imports) |
| **Export selected (JSON)** (Auswahl exportieren) | Übersicht, unter der Liste der Importe (Importe vorher ankreuzen); Settings, Export import definitions | Manage imports |
| **Import definitions** (Definitionen importieren) | `sqliimport/transfer` (linkes Menü, Werkzeugleiste der Übersicht, Settings) | Manage imports |
| **Copy to …** (Kopieren nach …) | Menü **More**; letzter Schritt des Editors | Manage imports |
| **Save as template** (als Vorlage speichern) | Menü **More**; letzter Schritt des Editors | Manage imports |
| **New import from a template** (neuer Import aus einer Vorlage) | `sqliimport/templates` (linkes Menü, Werkzeugleiste der Übersicht, **From a template** auf der Karte eines Formats im Katalog) | Manage imports |

Der Export verlangt **Manage imports** und nicht nur das Recht, Importe anzusehen: Eine Definition nennt Hosts,
Datenbanken, Abfragen, Serverpfade und Adressen dieser Installation, die nicht für alle bestimmt sind, die die
Übersicht sehen dürfen. Die Handler-Einschränkung gilt für jeden Import einer Datei: Ein Import eines Handlers, den
Sie nicht verwalten dürfen, fehlt im Export und kann weder angelegt noch ersetzt werden.

## Die Definitionsdatei

Ein Import ergibt `sqliimport-import-<identifier>.json`, mehrere ergeben `sqliimport-imports-<datum>-<zeit>.json`.
Aufbau (gekürzt; das vollständige Beispiel steht in [../definitions.md](../definitions.md)):

```json
{
    "format": "sqliimport-definitions",
    "version": 2,
    "exported": "2026-10-22T09:30:00Z",
    "generator": "7x SQLI Import",
    "secrets": [ "crm_db" ],
    "imports": [ { "identifier": "crm_contacts", "name": "Kontakte aus dem CRM", "source_type": "sql",
                   "source": { "origin": "remote", "host": "crm.example.com", "password": "crm_db" },
                   "target": { "parent_node_id": 61, "parent": { "node_id": 61, "remote_id": "0c1e5d...", "path": "contacts" } },
                   "mapping": { "class": "contact", "fields": [] }, "schedules": [], "trigger": null,
                   "secrets": [ "crm_db" ], "connections": [] } ]
}
```

- **Quelle**: der Ursprung (upload, url, path, remote) und die Optionen des Quelltyps. Bei einem Upload bleibt nur
  der ursprüngliche Dateiname, nie die Datei und nie der Name, unter dem sie gespeichert war; laden Sie sie auf der
  anderen Installation erneut hoch. Benutzer und Passwort in einer Adresse (`https://user:password@host/`) werden
  entfernt.
- **Ziel und Ort der Zuordnung**: Die Knoten-IDs bleiben erhalten, und neben jeder steht eine Referenz: die
  Remote-ID des Knotens, die Remote-ID des Objekts und der Pfad. Die andere Installation sucht den Knoten zuerst über
  seine Remote-ID, dann über die Remote-ID des Objekts, zuletzt über die Knoten-ID; die Sektion über ihren
  Bezeichner; den Benutzer, als der ein Import läuft, über die Remote-ID des Benutzerobjekts. Klassen werden wie in
  jeder Zuordnung über ihre Bezeichner genannt.
- **Zuordnung**: die Zeilen mit ihren Transformationen und Übersetzungen (eine Zeile je Sprache), die Abgleichsregel,
  die Klasse je Datensatz (`class_from` und `variants`).
- **Zeitpläne** und **Trigger-Einstellungen**: Der Trigger wird exportiert mit: eingeschaltet oder nicht, nimmt er
  Daten an, der Name seines Signaturgeheimnisses und sein Ausführungsbenutzer. **Nie** seine API-Schlüssel, das Salz,
  aus dem sein Signaturgeheimnis abgeleitet wird, oder ein geheimer Wert.
- **Geheimnisse**: Eine geheime Option einer Quelle oder eines Handlers enthält den Namen eines Geheimnisses, nie
  seinen Wert. Die Datei listet diese Namen je Import und für die ganze Datei, damit die andere Installation sie
  vorher einrichten kann. Ein Wert in einer geheimen Option, der kein Name ist, wird nicht geschrieben; freie
  Optionen eines 1.x-Handlers, deren Namen nach Passwort, Token oder Schlüssel aussehen, fehlen.

Version 1, der Export von 2.0.0-alpha.1 (Knoten-IDs ohne Referenzen), wird ebenfalls gelesen.

## Definitionen importieren

1. Öffnen Sie **Import definitions**, wählen Sie die Datei (JSON, höchstens 10 MB) und dann **Read the file**. Eine
   Datei, die keine Definitionsdatei ist, eine neuere Version hat, einen Eintrag ohne Bezeichner oder Namen, einen
   Bezeichner doppelt oder Werte falscher Form enthält, wird als Ganzes abgelehnt; es wird nichts angelegt.
2. Die Seite zeigt eine Karte je Import:
   - **new** (neu) oder **identifier taken** (Bezeichner vergeben, mit Link zum Import, der ihn hat);
   - Quelltyp und Verarbeitung, die Klasse und ob diese Site sie hat, den Elternknoten, zu dem die Referenz führt;
   - die Zeitpläne (sie werden pausiert) und den Trigger (er wird ausgeschaltet, ohne Schlüssel);
   - die Geheimnisse, die er braucht, jeweils **set** (vorhanden) oder **missing** (fehlt);
   - was nicht angelegt werden kann (ein unbekannter Quelltyp oder Handler, ein Optionswert falscher Art) und was
     im Editor zu prüfen ist (ein Elternknoten, eine Sektion, Sprache, Klasse, ein Attribut, Geheimnis oder eine
     benannte Verbindung, die diese Site nicht hat, eine Pflichtoption ohne Wert, eine erneut hochzuladende Datei).
   Geprüft wird wie im Editor: die Optionsschemata von Quelle und Handler, die Prüfung der Zuordnung und ihrer
   Varianten durch den Mapper, die Regeln der Zeitpläne sowie die Adress- und Pfadregeln der Site.
3. Wählen Sie je Import: **Create it** (anlegen; ein vergebener Bezeichner erhält `_2`, `_3` ...), **Replace the
   existing import** (Definition, Zuordnung und Zeitpläne werden ersetzt; Läufe, API-Schlüssel und Signatursalz
   bleiben) oder **Skip it** (überspringen). Ein vergebener Bezeichner wird übersprungen, solange Sie nichts anderes
   wählen; ein Import, der gerade läuft, wird nicht ersetzt.
4. **Create the chosen imports (disabled)**. Jeder Import wird **deaktiviert** angelegt oder ersetzt, seine Zeitpläne
   **pausiert**, sein Trigger **ausgeschaltet**. Öffnen Sie jeden, laden Sie gegebenenfalls seine Datei hoch, machen
   Sie einen **Dry run** (Probelauf) und aktivieren Sie ihn im letzten Schritt; setzen Sie seine Zeitpläne auf der
   Seite Schedules fort. Jeder angelegte oder ersetzte Import steht im Audit-Trail
   (`system.import.import.create`, `system.import.import.change`), die Datei als Ganzes ebenso
   (`system.import.definitions.import`); ein Export ist `system.import.definitions.export`.

Zwischen den beiden Schritten wartet die Datei in `var/<site>/storage/sqliimport/transfer/` unter einem zufälligen
Namen (die Sitzung kennt nur diesen Namen); sie wird beim Anlegen oder Abbrechen entfernt, eine unbenutzte nach einem
Tag.

## Copy to … (Kopieren nach …)

**Duplicate** legt sofort "Copy of <Name>" an. **Copy to …** fragt nach Name und Bezeichner (leer: aus dem Namen
gebildet) und ob die **Zeitpläne mitkopiert** werden (standardmäßig nicht; kopiert werden sie pausiert). Die Kopie ist
deaktiviert und öffnet sich im Editor. Sie behält die Quelle (dieselbe hochgeladene Datei), die Verarbeitung, das
Ziel und die Zuordnung. Trigger, API-Schlüssel und Signaturgeheimnis werden nie kopiert, Läufe ebenso wenig.

## Vorlagen

Eine Vorlage ist ein Import ohne Quelldaten und mit Platzhaltern: **Save as template** behält den Quelltyp mit seinen
Optionen, die Verarbeitung, das Ziel und die Zuordnung und lässt die hochgeladene Datei, die Adresse und den
Serverpfad, die Zeitpläne, den Trigger, den Ausführungsbenutzer und die Benachrichtigungsadressen weg. Der Name wird
zu `{{name}}`, der Bezeichner zu `{{identifier}}`, auch im Remote-ID-Muster der Zuordnung.

**New import from a template** listet die Vorlagen (nach Format gefiltert, wenn aus dem Katalog geöffnet). **Use**
fragt nach den Platzhaltern und zeigt, was die Vorlage hier braucht (Klasse, Geheimnisse, was zu prüfen ist);
**Create the import** legt ihn deaktiviert an und öffnet ihn im Editor, wo Sie seine Datei wählen oder seine Adresse
prüfen, die Vorschau ansehen, einen Probelauf machen und ihn aktivieren.

Woher Vorlagen kommen:

- **Diese Site**: JSON-Dateien in `var/<site>/storage/sqliimport/templates/`, angelegt mit **Save as template**; sie
  können auf der Seite Templates gelöscht werden. Dateien statt einer Tabelle, weil eine Vorlage ein Dokument ist,
  das zwischen Installationen kopiert und in einem Repository gepflegt wird, Erweiterungen sie genauso mitliefern und
  die Tabellen unverändert bleiben.
- **Erweiterungen**, nur lesend: `sqliimport.ini [TemplateSettings] Templates[]` nennt eine Erweiterung (deren
  `share/templates/*.json` gelesen werden) oder eine JSON-Datei relativ zum Wurzelverzeichnis der Installation.
  7x SQLI Import liefert **News feed into articles** (RSS oder Atom von einer Adresse) und **Articles from a CSV
  file** mit.

```ini
[TemplateSettings]
Templates[]
Templates[]=sqliimport
Templates[]=myextension
Templates[]=extension/myextension/share/import-templates/products.json
```

Eine Vorlagendatei hat `format` `sqliimport-template`, `version` 1, `title`, `description`, `source_type`,
`placeholders`, `secrets` und `import` (ein Eintrag einer Definitionsdatei). Jeder `{{platzhalter}}` in dessen Texten
wird durch den angegebenen Wert ersetzt; `name` und `identifier` werden immer abgefragt, weitere werden in
`placeholders` erklärt (`optional: true` erlaubt einen leeren Wert, `default` füllt das Feld vor). Ein benutzter, aber
nicht erklärter Platzhalter macht die Vorlage ungültig, und ein Wert darf `{{` nicht enthalten. Das Ergebnis
durchläuft dieselben Prüfungen wie eine importierte Definition.
