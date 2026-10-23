# Erste Schritte: Ihr erster Import, Schritt für Schritt

Diese Seite führt Sie von einer Installation ohne Importe zu fünf Produkten, die aus einer CSV-Datei veröffentlicht
sind, und dann durch einen zweiten Lauf, der zwei davon aktualisiert, zwei hinzufügt und eine fehlerhafte Zeile
aufhält, bevor sie die Website erreicht. Jeder Screenshot und jede Zahl auf dieser Seite stammt aus diesem echten
Import, „Docs: First import (teas CSV)“, angelegt auf einer Testinstallation.

Die Oberfläche ist noch überwiegend auf Englisch; Schaltflächen und Felder stehen hier so, wie die Administration sie
zeigt.

English: [../getting-started.md](../getting-started.md).

## Inhalt

- [Bevor Sie beginnen](#bevor-sie-beginnen)
  - [Die Erweiterung aktivieren](#die-erweiterung-aktivieren)
  - [Wer was darf: Manage imports und Run imports](#wer-was-darf-manage-imports-und-run-imports)
  - [Der Reiter Imports](#der-reiter-imports)
  - [Der Katalog: jedes Format, das die Website lesen kann](#der-katalog-jedes-format-das-die-website-lesen-kann)
- [Ihr erster CSV-Import](#ihr-erster-csv-import)
  - [Die Datei](#die-datei)
  - [Schritt 1: Quelle](#schritt-1-quelle)
  - [Schritt 2: Verarbeitung](#schritt-2-verarbeitung)
  - [Schritt 3: Zuordnung](#schritt-3-zuordnung)
  - [Schritt 4: Vorschau](#schritt-4-vorschau)
  - [Schritt 5: Zeitplan](#schritt-5-zeitplan)
  - [Schritt 6: Prüfen und speichern](#schritt-6-prüfen-und-speichern)
  - [Der Lauf](#der-lauf)
  - [Was im Inhaltsbaum erscheint](#was-im-inhaltsbaum-erscheint)
- [Der zweite Lauf: Änderungen, ein neues Produkt und eine fehlerhafte Zeile](#der-zweite-lauf-änderungen-ein-neues-produkt-und-eine-fehlerhafte-zeile)
- [Was Sie als Nächstes lesen](#was-sie-als-nächstes-lesen)

## Bevor Sie beginnen

### Die Erweiterung aktivieren

7x SQLI Import braucht zwei weitere Erweiterungen: **expqueue** (die Job-Warteschlange, die die Arbeit jedes Laufs
erledigt) und **expui** (die gemeinsamen Bausteine der Administration). [install.md](install.md) hat jede Einzelheit;
die Kurzfassung:

```bash
composer require se7enxweb/sqliimport:^2.0
```

(Bis 2.0.0 erschienen ist, wird die Linie 2.0 mit `^2.0@alpha` für die letzte Vorabversion oder `dev-2.0-dev` für den
Entwicklungszweig installiert.)

```ini
# settings/override/site.ini.append.php
[ExtensionSettings]
ActiveExtensions[]=expqueue
ActiveExtensions[]=expui
ActiveExtensions[]=sqliimport
```

```bash
php bin/php/ezpgenerateautoloads.php -e
./console ext:expqueue:install-schema
./console ext:sqliimport:install-schema
php bin/php/ezcache.php --clear-tag=ini
```

Dazu zwei Cron-Zeilen, jede Minute: `php runcronjobs.php -q expqueue` (die Worker, die die Läufe verarbeiten) und
`php runcronjobs.php -q sqliimport` (die Zeitpläne und das Aufräumen).

Um Importe nur in der Administration anzubieten, führen Sie die drei Erweiterungen stattdessen unter
`ActiveAccessExtensions[]` in der `site.ini.append.php` der Admin-Siteaccesses auf. Ein Siteaccess, dessen
`menu.ini.append.php` `[TopAdminMenu] Tabs[]` zurücksetzt, muss `Tabs[]=sqliimport` selbst hinzufügen, sonst fehlt der
Reiter dort ([troubleshooting.md](troubleshooting.md#der-reiter-importe-fehlt)).

### Wer was darf: Manage imports und Run imports

Das Modul `sqliimport` hat zwei Richtlinien. Vergeben Sie sie unter **Users, Roles** wie jede andere Richtlinie:

| Richtlinie | Angezeigt als | Was der Benutzer tun kann |
|---|---|---|
| `sqliimport/manage` | Manage imports | Alles: Importe, Zeitpläne und Auslöser anlegen, bearbeiten, kopieren, aktivieren, deaktivieren und löschen, die Seite Settings und alles, was Run imports erlaubt |
| `sqliimport/run` | Run imports | Den Reiter Imports sehen, Importe ausführen (Run now, Dry run), Läufe abbrechen, fehlgeschlagene Zeilen wiederholen, Läufe rückgängig machen |

Beide nehmen die Einschränkung **Handler** (`SQLIImport_Type`): Eine Rolle mit *Run imports*, eingeschränkt auf
`mapping`, führt nur die Importe ohne Programmierung aus. Typische Rollen stehen in
[permissions.md](permissions.md#typische-rollen). Der Benutzer, der einen Import ausführt, braucht außerdem die
Inhaltsrichtlinien für das, was der Import anlegt (content/create unter dem Elternknoten, content/edit).

### Der Reiter Imports

Ist die Erweiterung aktiv, hat die Kopfzeile den Reiter **Imports**:

![Die Kopfzeile der Administration mit dem Reiter Imports, hervorgehoben als geöffneter Reiter](../images/gs-imports-tab-header.png)

Sein linkes Menü listet die Seiten des Werkzeugs und darunter die anderen Import- und Exportwerkzeuge der Website:

![Das linke Menü des Reiters Imports: Overview, Statistics, New import, Schedules, Catalogue, Settings, Running runs, Failed runs, Job queue, dann die anderen Import- und Exportwerkzeuge](../images/gs-left-menu.png)

Die **Overview** ist die Übersicht: die Kennzahlen, eine Leiste Run now, die Importe und die Läufe.

![Der obere Teil der Übersicht von Imports: die Beschreibung, die Leiste Run now mit New import und fünf Kennzahlen: Importe, Läufe heute, laufend oder wartend, fehlgeschlagen in 24 Stunden, der nächste geplante Lauf](../images/gs-dashboard-top.png)

Dieselbe Seite im dunklen Modus, wie sie bei 960 Pixeln Breite und 200 % Zoom aussieht:

![Die Übersicht von Imports im dunklen Modus bei 960 Pixeln und doppelter Skalierung, mit den Kennzahlen, dem Diagramm der letzten 30 Tage und der Liste der Importe](../images/gs-dashboard-dark-960.png)

Das Dashboard der Administration (**Dashboard** in der Kopfzeile) bekommt außerdem einen Block **Imports** mit den
letzten Läufen:

![Der Block Imports auf dem Dashboard der Administration: die letzten Läufe mit ihrem Import, Status und Zeitpunkt](../images/gs-admin-dashboard-block.png)

### Der Katalog: jedes Format, das die Website lesen kann

**Catalogue** listet jeden Quelltyp der Website, gruppiert in Dateien und Daten, NoSQL-Datenbanken, Inhalte,
Plattformen, Handel, Medien und Sonstiges. Jede Karte sagt, was das Format liest, bietet eine Beispieldatei und die
Schaltfläche **New import**, die den Editor mit diesem Format öffnet:

![Der obere Teil des Katalogs: die Gruppe Data mit den Karten CSV and delimited text, TSV, Tabellen, JSON und weiteren, jede mit Beschreibung, Beispiel und Schaltfläche New import](../images/gs-catalogue-top.png)

![Die Katalogkarte „CSV and delimited text“: was sie liest, die Dateiendungen, die Beispieldatei products.csv und die Schaltfläche New import](../images/gs-catalogue-csv-card.png)

Durchgerechnete Beispiele jeder Quelle stehen in [source-examples-files.md](source-examples-files.md),
[source-examples-feeds-web.md](source-examples-feeds-web.md), [source-examples-apis.md](source-examples-apis.md),
[source-examples-databases.md](source-examples-databases.md) und
[source-examples-platforms.md](source-examples-platforms.md).

## Ihr erster CSV-Import

Ziel: Fünf Teesorten aus dem Export einer Tabellenkalkulation werden zu **Product**-Objekten (die Klasse `product` des
Webshops) in einem Ordner, mit Name, Produktnummer, Kurzbeschreibung und Preis. Läuft der Import erneut, muss er die
Produkte aktualisieren und darf sie nicht doppelt anlegen.

### Die Datei

`teas.csv`, so wie eine Tabellenkalkulation sie speichert (durch Kommas getrennt, eine Kopfzeile, UTF-8):

```text
id,name,code,description,price,harvest
101,Assam Breakfast,TEA-101,"A strong, malty black tea from the Brahmaputra valley.",6.95,2026-04-12
102,Darjeeling First Flush,TEA-102,"Light and floral, picked in spring.",12.45,2026-03-28
103,Sencha Asahi,TEA-103,"A grassy Japanese green tea, steamed.",9.85,2026-05-02
104,Earl Grey Blue Flower,TEA-104,"Black tea with bergamot and cornflower petals.",7.45,2026-02-15
105,White Peony,TEA-105,"A mild white tea with silver buds.",11.25,2026-04-20
```

Die Spalte `id` ist der Schlüssel: Sie benennt jede Teesorte, und der Import bildet aus ihr die Remote-ID jedes
Produkts, sodass ein späterer Lauf dasselbe Produkt wiederfindet.

### Schritt 1: Quelle

Öffnen Sie **Imports, New import** (oder **New import** auf der Katalogkarte CSV). Füllen Sie aus:

| Feld | Wert |
|---|---|
| Name | `Docs: First import (teas CSV)` |
| Identifier | `docs-first-import` (der Name auf der Kommandozeile; leer: aus dem Namen gebildet) |
| Description | `Five teas from a CSV file as products: the worked example of the manual.` |
| Source type | **CSV and delimited text** |
| Where the data comes from | **An uploaded file**, dann `teas.csv` wählen |
| Delimiter | **Comma** (Detect funktioniert auch) |
| Enclosure | `"` |
| Encoding | **Detect** |
| The first line names the columns | angehakt |
| Key field | `id` |

![Schritt 1 des Editors, Quelle: Name, Bezeichner und Beschreibung ausgefüllt, der Quelltyp CSV gewählt, An uploaded file mit teas.csv und die CSV-Optionen Comma, Detect, Kopfzeile und Schlüsselfeld id](../images/gs-source-step.png)

Wählen Sie **Save and continue**. Der Import existiert jetzt (noch deaktiviert), und die Schritte über dem Formular
werden zu Verweisen.

### Schritt 2: Verarbeitung

| Feld | Wert |
|---|---|
| How the records are processed | **Mapping to a content class (no code)** |
| Parent node of new content | die Knoten-ID des Ordners, in den die Produkte kommen (hier `54990`, der Ordner „First import (teas)“) |
| Section, Language, Run as user | wie sie sind: die Sektion des Elternknotens, die Sprache der Website, der Benutzer, der den Lauf startet |
| Records per chunk | `200` (die Vorgabe) |
| Existing content | **Create new, update existing** |
| When someone edited an object since the last run | **Overwrite with the source** (die anderen Regeln zeigt [runs.md](runs.md#konfliktregeln-wenn-jemand-ein-importiertes-objekt-geändert-hat)) |
| Days a run can be undone | leer: die Einstellung, 30 Tage |
| Notifications | **failed or had errors**, ein Hinweis für den Benutzer, der den Lauf gestartet hat |

![Schritt 2 des Editors, Verarbeitung: der Handler mapping gewählt, Elternknoten 54990, Abschnittsgröße 200, Existing content „Create new, update existing“, die Konfliktregel, die Tage zum Rückgängigmachen und die Benachrichtigungen](../images/gs-processing-step.png)

Die Knoten-ID eines Ordners zeigt der Inhaltsbaum (mit der Maus über den Ordner fahren oder ihn öffnen: Die Adresse
endet mit `/content/view/full/<Knoten-ID>`). Wählen Sie **Save and continue**.

### Schritt 3: Zuordnung

Wählen Sie zuerst die **Content class** `Product (product)` und das Remote-ID-Muster `docs-tea-{id}`, dann **Save**:
Das Raster mit den Attributen der Klasse erscheint.

![Schritt 3, Zuordnung, nach der Wahl der Klasse: Product gewählt, „By a remote ID made from the record“ mit dem Muster docs-tea-{id} und der Elternknoten](../images/gs-mapping-class.png)

**Auto-match by name** füllt, was es selbst findet: hier `name`, `description` (mit `richtext`, weil die
Kurzbeschreibung ein Rich-Text-Attribut ist) und `price`. Es meldet „Auto-match filled 3 lines. Check them, then
save.“:

![Das Zuordnungsraster nach Auto-match by name: name zu Name, description zu Short description mit der Umwandlung richtext, price zu Price](../images/gs-mapping-automatch.png)

Prüfen und ergänzen Sie die Zeilen:

| Attribut | Quellfeld | Umwandlungen | Pflicht |
|---|---|---|---|
| Name (`name`) | `name` | `trim` | angehakt |
| Product number (`product_number`) | `code` | | |
| Short description (`short_description`) | `description` | `richtext` | |
| Price (`price`) | `price` | `number \| template(text="{value}\|1\|1")` | |

Der Preis braucht einen eigenen Text: Der Preis-Datentyp liest `Betrag|MwSt.-Typ|Preis enthält MwSt.`, also liest die
Kette zuerst die Zahl und schreibt dann `6.95|1|1` (MwSt.-Typ 1, der Preis enthält die MwSt.). Ein Preis ohne diese
Form wird als 0 gespeichert ([transforms.md](transforms.md#datentypen-die-einen-besonderen-text-brauchen)).

![Das fertige Zuordnungsraster: name mit trim und Required, Produktnummer aus code, Kurzbeschreibung aus description mit richtext, Preis aus price mit number und der Vorlage für den Preistext](../images/gs-mapping-grid.png)

Wählen Sie **Save and continue**. Dieselbe Zuordnung als Zuordnungsdatei (**Download mapping (JSON)** unten im
Schritt) lautet:

```json
{
  "class": "product",
  "match": { "type": "remote_id", "pattern": "docs-tea-{id}" },
  "fields": [
    { "source": "name", "attribute": "name", "transforms": [ { "name": "trim" } ], "required": true },
    { "source": "code", "attribute": "product_number" },
    { "source": "description", "attribute": "short_description", "transforms": [ { "name": "richtext" } ] },
    { "source": "price", "attribute": "price",
      "transforms": [ { "name": "number" }, { "name": "template", "args": { "text": "{value}|1|1" } } ] }
  ]
}
```

### Schritt 4: Vorschau

Die Vorschau liest die Datei und schickt die ersten Datensätze durch die Zuordnung, **ohne etwas zu veröffentlichen**:
oben die Stichprobe der Quelle, darunter ein Probelauf mit dem, was aus jedem Datensatz würde.

![Schritt 4, Vorschau: die Stichprobe der Quelle mit den Spalten id, name, code, description, price und harvest und der Probelauf, in dem alle fünf Datensätze mit ihren Remote-IDs docs-tea-101 bis docs-tea-105 und ihren Feldern angelegt würden](../images/gs-preview-step.png)

Jeder Datensatz meldet **Created**, mit der Remote-ID, die er bekommt, und den Werten seiner Felder. Stimmt hier etwas
nicht (eine leere Spalte, ein Preis, der 0 liest), gehen Sie zurück zu Schritt 3, korrigieren die Zeile und öffnen
die Vorschau erneut.

### Schritt 5: Zeitplan

Lassen Sie bei einem ersten Import **Runs by itself** auf **Never (only by hand or from the command line)**. Zeitpläne
erklärt [schedules.md](schedules.md), den Auslöser für andere Systeme darunter [triggers.md](triggers.md).

![Schritt 5, Zeitplan: Never gewählt, die Felder für Uhrzeit, Zeitzone, Intervall und Cron-Ausdruck und darunter der Abschnitt „Started by another system“ mit ausgeschaltetem Auslöser](../images/gs-schedule-step.png)

### Schritt 6: Prüfen und speichern

Die Prüfung fasst den Import zusammen. Haken Sie **The import is enabled (it can run and its schedule runs it)** an und
wählen Sie **Save and run**:

![Schritt 6, Prüfen und speichern: die Zusammenfassung von Name, Quelle, Verarbeitung, Ziel und Zeitplan, das Kontrollkästchen The import is enabled und die Schaltflächen Save und Save and run](../images/gs-review-step.png)

### Der Lauf

**Save and run** reiht einen Lauf ein und öffnet seine Seite. Die Seite verfolgt den Lauf live (alle zwei Sekunden),
bis er endet:

![Die Seite von Lauf 180: Completed, Import, von Hand gestartet, ein voller Fortschrittsbalken, 5 von 5 Datensätzen in 2 Sekunden, die Zahlen 5 angelegt, 0 aktualisiert, 0 unverändert, 0 übersprungen, 0 fehlgeschlagen, die fünf Datensätze mit ihren Objekten und Version 1 und das Protokoll](../images/gs-run-page.png)

Fünf Datensätze, fünfmal **Created**, jeder mit seinem Objekt (`#56612` …) und seiner Remote-ID. Das Protokoll sagt
`run ended completed: 5 created, 0 updated, 0 unchanged, 0 skipped, 0 failed`. **Undo this run** brächte die fünf
Produkte innerhalb von 30 Tagen zurück (in den Papierkorb) ([runs.md](runs.md#einen-lauf-rückgängig-machen)).

### Was im Inhaltsbaum erscheint

Der Ordner „First import (teas)“ hat jetzt fünf Produkte:

![Die Unterelemente des Ordners „First import (teas)“: die fünf Produkte Assam Breakfast, Darjeeling First Flush, Sencha Asahi, Earl Grey Blue Flower und White Peony der Klasse Product](../images/gs-content-tree.png)

(Sie zeigen „Hidden by superior“ nur, weil der Beispielordner der Dokumentation versteckt ist; Ihre Produkte sind
sichtbar, wenn ihr Ordner es ist.)

## Der zweite Lauf: Änderungen, ein neues Produkt und eine fehlerhafte Zeile

Eine Woche später hat sich die Tabelle geändert. `teas-v2.csv` unterscheidet sich von `teas.csv` in vier Zeilen:

```diff
-102,Darjeeling First Flush,TEA-102,"Light and floral, picked in spring.",12.45,2026-03-28
+102,Darjeeling First Flush,TEA-102,"Light and floral, picked in spring.",13.95,2026-03-28
-104,Earl Grey Blue Flower,TEA-104,"Black tea with bergamot and cornflower petals.",7.45,2026-02-15
+104,Earl Grey Blue Flower (organic),TEA-104,"Black tea with bergamot and cornflower petals.",7.45,2026-02-15
+106,Genmaicha,TEA-106,"Green tea with roasted rice.",5.65,2026-05-10
+107,,TEA-107,"Smoky black tea, its name is missing in this file.",8.15,2026-03-02
```

1. **Die Datei ersetzen.** Den Import mit **Edit** öffnen, Schritt 1: unter **File** die neue Datei wählen und
   **Save**. Der Schritt sagt, welche Datei verwendet wird („Uploaded: teas-v2.csv. Choose a file only to replace
   it.“).
2. **Zuerst ein Probelauf.** Auf der Übersicht **Dry run** neben dem Import. Der Probelauf liest die ganze Datei,
   veröffentlicht nichts und listet für jeden Datensatz, was geschähe, mit den Feldern, die sich ändern würden:

   ![Der Probelauf 181: 1 angelegt, 2 aktualisiert, 3 unverändert, 1 fehlgeschlagen; Datensatz 102 würde seinen Preis von 12.45|1|1 auf 13.95|1|1 ändern, Datensatz 104 seinen Namen, 106 würde angelegt, und 107 schlägt fehl, weil das Pflichtattribut name keinen Wert hat](../images/gs-dry-run-page.png)

   Zeile 107 hat keinen Namen, und die Zeile `name` ist Pflicht; sie schlägt daher mit „the required attribute 'name'
   has no value“ fehl. In einem echten Lauf wäre sie ebenso nicht veröffentlicht worden, aber der Probelauf zeigt es,
   bevor sich etwas ändert.
3. **Die Datei korrigieren und ausführen.** Die korrigierte `teas-v3.csv` hat in Zeile 107 den Namen `Lapsang
   Souchong`. Laden Sie sie wie in 1. hoch, dann **Run now**:

   ![Lauf 184: Completed, 2 angelegt, 2 aktualisiert, 3 unverändert, 0 fehlgeschlagen; Datensatz 102 und 104 gingen von Version 1 auf 2, 106 und 107 wurden angelegt, die anderen sind unverändert in Version 1](../images/gs-second-run.png)

   Nur was sich geändert hat, bekam eine neue Version: 102 und 104 gingen von Version 1 auf 2; 101, 103 und 105 sind
   **Unchanged** (keine neue Version, nichts neu zu indizieren); 106 und 107 sind neu.

![Die Unterelemente des Ordners nach dem zweiten Lauf: sieben Produkte, Genmaicha und Lapsang Souchong hinzugefügt und Earl Grey Blue Flower (organic) umbenannt](../images/gs-content-tree-after.png)

**Download results (CSV)** auf der Seite des Laufs ergibt eine Zeile pro Datensatz (`sqliimport/report/184`):

```text
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
184,0,101,unchanged,ok,56612,54991,docs-tea-101,1,1,,,2026-10-23T01:23:02Z
184,1,102,updated,ok,56613,54992,docs-tea-102,1,2,,,2026-10-23T01:23:03Z
184,2,103,unchanged,ok,56614,54993,docs-tea-103,1,1,,,2026-10-23T01:23:03Z
184,3,104,updated,ok,56615,54994,docs-tea-104,1,2,,,2026-10-23T01:23:03Z
```

Warum „Datei korrigieren und ausführen“ und nicht **Retry failed rows**: Eine Wiederholung verarbeitet die
fehlgeschlagenen Datensätze so, wie der fehlgeschlagene Lauf sie gespeichert hat; ein Datensatz, der an seinen eigenen
Daten gescheitert ist, schlägt also wieder fehl. Die Wiederholung ist für Ursachen außerhalb des Datensatzes, etwa
einen Elternknoten, den es noch nicht gab ([runs.md](runs.md#fehlgeschlagene-zeilen-wiederholen)).

## Was Sie als Nächstes lesen

| Sie möchten | Lesen Sie |
|---|---|
| Jedes Feld der sechs Schritte kennen | [editor.md](editor.md) |
| Andere Datentypen, Datumsangaben, Bilder, Übersetzungen, Beziehungen zuordnen | [mappings.md](mappings.md), [transforms.md](transforms.md) |
| Ein anderes Format lesen | die Quellbeispiele, zuerst [source-examples-files.md](source-examples-files.md) |
| Läufe anhalten, wiederholen, rückgängig machen, mit Änderungen der Redaktion umgehen, benachrichtigt werden | [runs.md](runs.md) |
| Importe von selbst laufen lassen | [schedules.md](schedules.md) |
| Einen Import aus einem anderen System starten | [triggers.md](triggers.md) |
| Eine Datentabelle statt Inhalten füllen | [datatables.md](datatables.md) |
| Ein Problem beheben | [troubleshooting.md](troubleshooting.md) |
