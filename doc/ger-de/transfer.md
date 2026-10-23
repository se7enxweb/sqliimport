# Importe unter den Import- und Export-Werkzeugen

Exponential 6.0.15 und neuer führen ihre Import- und Export-Werkzeuge in einem Transfer-Register (Einrichtung, Import
und Export). 7x SQLI Import trägt sich dort als **Imports (7x SQLI Import)** ein
(`settings/kernelservices.ini.append.php`, Block `[Service_transfer.sqliimport]`, Tag `transfer.tool`). Von diesem
Eintrag aus bietet die Administration Importe dort an, wo sie passen: im Menü eines Ordners, auf der Seite einer
Klasse, in den Kästen **Looking for…?** und in einem gemeinsamen Laufverlauf. Diese Seite zeigt jede dieser Stellen,
dann einen vollständigen Hin- und Rückweg mit Export (xrowextract), Formulareinsendungen aus CIE und die Ergebnisse
eines Laufs als CSV. Ein älterer Kernel übergeht den Block, und die Kästen unten erscheinen nicht.

Die Oberfläche ist noch überwiegend auf Englisch; Schaltflächen und Menüs stehen hier so, wie sie erscheinen.

English: [../transfer.md](../transfer.md).

## Inhalt

- [Wo Importe angeboten werden](#wo-importe-angeboten-werden)
  - [Der Kasten Looking for…?](#der-kasten-looking-for)
  - [Import into this folder…](#import-into-this-folder)
  - [Import objects of this class…](#import-objects-of-this-class)
- [Eine Datei öffnen, die ein anderes Werkzeug erstellt hat](#eine-datei-öffnen-die-ein-anderes-werkzeug-erstellt-hat)
- [Hin- und Rückwege](#hin--und-rückwege)
- [Beispiel: ein Hin- und Rückweg mit Export (xrowextract)](#beispiel-ein-hin--und-rückweg-mit-export-xrowextract)
- [Beispiel: CIE-Formulareinsendungen als Artikel](#beispiel-cie-formulareinsendungen-als-artikel)
- [Der gemeinsame Laufverlauf](#der-gemeinsame-laufverlauf)
- [Die Ergebnisse eines Laufs als CSV](#die-ergebnisse-eines-laufs-als-csv)

## Wo Importe angeboten werden

### Der Kasten Looking for…?

Die Hauptseite jedes Werkzeugs endet mit einem Kasten **Looking for another import or export?** (Suchen Sie einen
anderen Import oder Export?). Er listet die anderen Werkzeuge, die Sie benutzen dürfen, jedes mit seiner Richtung
(Import, Export, Import and export), und kennzeichnet mit **Works with this tool** die Werkzeuge, deren Dateien
dieses liest oder die seine Dateien lesen. Auf der Übersicht von Imports sind das Export (xrowextract), RSS export,
Collected information export (CIE) und Data tables. Der letzte Verweis öffnet die Hilfeseite „Welches Import- oder
Exportwerkzeug brauche ich?“.

![Der Kasten Looking for another import or export? auf der Übersicht von Imports: Export (xrowextract), RSS export, Collected information export (CIE) und Data tables mit der Marke Works with this tool, dann Packages, Syndication, Documents, RSS import, Collected information, Newsletter subscribers, Audit export und PDF export, und der Verweis Which import or export tool do I need?](../images/int-looking-for.png)

Die Partner kommen aus dem Register (`RoundTrip[]` im Block jedes Werkzeugs): Export nennt `transfer.sqliimport`,
Imports nennt Export, CIE und Data tables, so erscheint jedes im Kasten des anderen.

### Import into this folder…

An einem Ordner in der Inhaltsstruktur listet die Schaltfläche **Import/Export** alles, was sich mit diesem Objekt tun
lässt. **Import into this folder…** (in diesen Ordner importieren) ist der Eintrag von Imports (er erscheint, wenn Sie
in diesem Ordner Inhalte anlegen dürfen):

![Das Menü Import/Export des Ordners Media: Pakete, CSV-Exporte, Import into this item… von Export, Jobs, Verlauf und Zeitpläne, Copy to another site…, Download as Word, PDF or OpenDocument…, Import into this folder…, Import documents here und Which import or export tool do I need?](../images/int-folder-import-export-menu.png)

Er öffnet `sqliimport/edit/new/(parent)/<Knoten-ID>`: einen neuen Import, dessen Objekte unter diesem Ordner
angelegt werden. Der Schritt Quelle sagt das oben („The objects will be created in Media“), und der Ordner wird zum
**Parent node of new content** (Elternknoten neuer Inhalte) des Schritts Verarbeitung. Alles andere wählen Sie wie
bei jedem neuen Import ([editor.md](editor.md)).

Verwechseln Sie ihn nicht mit **Import into this item…** im selben Menü: Das ist der Import von Export (xrowextract)
für Dateien, die Export erstellt hat.

### Import objects of this class…

Auf der Seite einer Klasse (**Setup > Classes**, eine Klasse) listet der Kasten **Import and export** die Werkzeuge,
die mit Objekten dieser Klasse arbeiten:

![Der Kasten Import and export auf der Seite der Klasse Folder: Export classes as a package… (Packages), Import objects of this class… (Imports (7x SQLI Import)), Export the objects of this class… (Export (xrowextract)) und Which import or export tool do I need?](../images/int-class-import-and-export.png)

**Import objects of this class…** öffnet `sqliimport/edit/new/(class)/<Klassenbezeichner>`: Die Zuordnung des neuen
Imports beginnt mit dieser Klasse, und sobald eine Quelle gespeichert ist, füllt **Auto-match by name** ihre Zeilen
aus der Stichprobe.

Ein Wert, den diese Parameter nicht annehmen (ein Knoten, in dem Sie nichts anlegen dürfen, eine Klasse, die es nicht
gibt), wird übergangen: Das Formular öffnet sich als einfacher neuer Import.

## Eine Datei öffnen, die ein anderes Werkzeug erstellt hat

Ein Werkzeug kann eine seiner Dateien an Imports übergeben, indem es auf
`sqliimport/edit/new/(from)/<Werkzeug>/(job)/<Job-ID>` verweist (optional `(preset)/<Format>`). Imports fragt das
Werkzeug nach der Datei des Jobs und bekommt sie nur, wenn Sie diesen Job dort herunterladen dürfen:

| Werkzeug | Regel | Format |
|---|---|---|
| Export (`xrowextract`) | ein fertiger CSV-, JSON- oder XML-Export einer Klasse, den Sie auf seiner Seite Jobs sehen, und die Richtlinie xrowextract/import oder sqliimport/manage | `xrowextract`, mit dem Spaltenmanifest des Exports |
| CIE (`bccie`) | Ihr eigener Hintergrund-Export, solange Sie das Formular noch lesen dürfen, und die Richtlinie bccie/read | `cie` |

Die Datei wird in die Uploads von Imports kopiert, mit dem Manifest, wenn das Werkzeug eines hat; der Schritt Quelle
zeigt **Use the file … from … (job …)**. Nichts wird importiert, bevor Sie die Schritte durchgehen. Eine Datei, die
Sie nicht haben dürfen, ergibt einen Hinweis, keinen Fehler.

Wo der Verweis steht: CIE zeigt **Open in Imports** neben einem fertigen CSV-Export. Die Seiten Jobs und History von
Export bieten **Re-import this file** an; das öffnet den eigenen Import von Export, nicht Imports. Um einen Job von
Export in Imports zu öffnen, geben Sie die Adresse oben mit der ID des Jobs ein.

Ein weiteres Werkzeug schließt sich mit `sqliimport.ini [HandoverSettings] Tools[]=<Werkzeug-ID>;<Klasse>` an; die
Klasse hat eine statische Methode `fileOf( $jobID, eZUser $user )`, die `array( 'path', 'name', 'manifest' )` oder
null zurückgibt.

## Hin- und Rückwege

- **Dateien von Export (xrowextract)** (Format `xrowextract`): Mit dem Spaltenmanifest ist die Zuordnung exakt, und die
  Spalte mit der Remote-ID lässt einen erneuten Import die Objekte aktualisieren, statt neue anzulegen. Das Manifest
  steckt in einem JSON- oder XML-Export, kommt mit einem übergebenen Job oder liegt als Begleitdatei
  `<datei>.manifest.json` neben einer Datei auf dem Server. Das Upload-Feld des Editors nimmt eine Datei; eine
  CSV-Datei, die Sie dort hochladen, hat also kein Manifest: Stellen Sie die Zuordnung ein wie im Beispiel unten. Ohne
  Manifest nennt die Option *Class* die Klasse, und Spalten, die nach ihren Attributbezeichnern benannt sind, werden
  zugeordnet.
- **CIE-Formulareinsendungen** (Format `cie`): UTF-8, UTF-16 oder Windows-1252, `;` oder `,`; wählen Sie die Klasse,
  zu der eine Einsendung wird, und die Spalten werden ihren Attributen nach Bezeichner oder Name zugeordnet.
- **RSS-Export einer anderen Exponential-Website** (Format `exponential_rss`): die Artikel aus dem Feed der anderen
  Website.
- **Was ein Import getan hat**: Die Seite des Laufs bietet **Download results (CSV)** an
  ([unten](#die-ergebnisse-eines-laufs-als-csv)).
- **Zuordnungen zwischen Installationen**: Der Schritt Zuordnung bietet **Download mapping (JSON)**
  (`sqliimport/mapping/<id>`) und **Load a mapping…** an; der Schritt Quelle eines neuen Imports nimmt ebenfalls eine
  Zuordnungsdatei. Eine Zuordnungsdatei nennt Klasse, Sprache, Abgleichsregel und Zeilen, nicht den Ort; beim Laden
  wird geprüft, ob diese Website die Klasse und ihre Attribute hat.
- **Datentabellen**: Ein Import füllt eine Datentabelle, legt aus seinen Feldern eine neue an oder öffnet eine
  Synchronisation zwischen Tabelle und Inhalten ([datatables.md](datatables.md)).

## Beispiel: ein Hin- und Rückweg mit Export (xrowextract)

Drei Ordner werden mit Export exportiert, ein Name wird in der Datei geändert, und die Datei geht mit Imports zurück,
sodass der Ordner **aktualisiert** und nicht neu angelegt wird. Das lief auf der Testinstallation, unter dem
versteckten Dokumentationsordner „Round trip (Export)“ (Knoten 55002). Seine drei Ordner hat ein kleiner Import der
Katalogdatei `products.csv` angelegt: „Café crème“, „Tea, green“ und „Hot chocolate“, mit den Remote-IDs
`sq688doc-rt-1` bis `sq688doc-rt-3`.

**1. Die Ordner mit ihren Remote-IDs exportieren.** Wählen Sie im Reiter **Export** die Klasse *Folder* unter diesem
Knoten, die Spalten *Name* und *Summary* und den Spaltensatz **identity**, der Objekt-ID und Remote-ID hinzufügt. Auf
der Kommandozeile ist es derselbe Export:

```bash
./console ext:xrowextract:csv --class=folder --node=55002 --depth=list \
    --columns=name,short_description --sets=identity --format=csv --output=teas.csv
```

Export schreibt `teas.csv` und sein Spaltenmanifest `teas.csv.manifest.json`:

```text
"name","short-description","object-id","remote-id","class","object-name","language"
"Café crème","<p>9.50</p>","56624","sq688doc-rt-1","folder","Café crème","eng-US"
"Tea, green","<p>4.20</p>","56625","sq688doc-rt-2","folder","Tea, green","eng-US"
"Hot chocolate","<p>5.00</p>","56626","sq688doc-rt-3","folder","Hot chocolate","eng-US"
```

**2. Einen Wert ändern.** Benennen Sie in einer Tabellenkalkulation oder einem Texteditor „Tea, green“ in der Spalte
`name` in „Green tea“ um und speichern Sie die Datei als CSV (UTF-8).

**3. Wieder importieren.**

1. **Imports > New import** (oder **Import into this folder…** am Ordner). Quelltyp **Export (xrowextract) file**
   (Gruppe Platforms), **An uploaded file**: `teas.csv`. Option *Class*: `folder`. Speichern und weiter.
2. Schritt **Processing**: **Create new, update existing** stehen lassen. Speichern und weiter.
3. Schritt **Mapping**: Die CSV-Datei kam ohne Manifest, prüfen Sie also Abgleichsregel und Zeilen:
   - **How a record finds its object**: **By a remote ID made from the record**, Muster `{remote-id}` (die Spalte,
     die der Spaltensatz identity geschrieben hat). Das lässt den Import die drei Ordner aktualisieren.
   - Das Raster: `name` bei *Name*. Lassen Sie jede andere Zeile leer, auch *Summary*: Export schreibt Rich Text als
     HTML (`<p>4.20</p>`), das als Rich-Text-XML zurückgespeichert wird, und zwar etwas anders geschrieben als das
     gespeicherte; jede Zeile zählte dann als **Updated**, obwohl sich ihr Text nicht geändert hat. Importieren Sie
     nur die Spalten, die Sie geändert haben.

   ![Der Schritt Zuordnung des Rückweg-Imports: Klasse Folder, By a remote ID made from the record mit dem Muster {remote-id}, der Elternknoten und das Raster mit name bei Name](../images/int-roundtrip-mapping.png)

   Mit einem JSON-Export (`--format=json`, das Manifest steckt in der Datei) füllt der Editor Abgleichsregel und Zeilen
   selbst, sobald der Schritt Quelle gespeichert ist: Muster `{remote-id}`, `name` bei *Name*, `short-description` bei
   *Summary*. Leeren Sie auch dort die Zeile *Summary*.
4. **Dry run** (Probelauf). Eine Zeile würde aktualisiert, Feld für Feld; die anderen bleiben unverändert:

   ![Der Probelauf des Rückwegs: Datensatz 1 unverändert, Datensatz 2 würde aktualisiert, name jetzt Tea, green und neu Green tea, Datensatz 3 unverändert](../images/int-roundtrip-dry-run.png)

5. **Save and run**. Der Lauf (#197 auf der Testinstallation) endet mit **0 created, 1 updated, 2 unchanged**:

   ![Lauf 197 des Rückweg-Imports: Completed, 3 von 3 Datensätzen, 0 angelegt, 1 aktualisiert, 2 unverändert](../images/int-roundtrip-run.png)

Der Ordner `sq688doc-rt-2` heißt jetzt „Green tea“, als Version 2; die anderen beiden behielten Version 1. Nichts
wurde verdoppelt, und nichts sonst bekam eine neue Version. Läuft dieselbe Datei noch einmal, ergibt das **3
unchanged** (Lauf #198). Stimmt das Ergebnis nicht, veröffentlicht **Undo this run** auf der Seite des Laufs wieder
Version 1 ([runs.md](runs.md)).

Für eine CSV-Datei ohne Manifest schlägt der Editor das Muster der Remote-ID aus der Spalte `remote-id` des Exports
vor (`{remote-id}`; vor 2.0.0 schlug er `xrowextract-folder-{name}` vor, das die exportierten Ordner nicht fand und
drei neue anlegte). Spalten des Exports, die keine Attribute sind (`object-id`, `class`, `language` und ähnliche),
bleiben ohne Zuordnung, und eine Überschrift wie `short-description` wird `short_description` zugeordnet. Prüfen Sie
immer den Probelauf: Für Objekte, die Sie exportiert haben, muss er **Updated** oder **Unchanged** sagen, nie
**Created**.

## Beispiel: CIE-Formulareinsendungen als Artikel

CIE (Collected information export) lädt herunter, was ein Formular gesammelt hat, als CSV-Datei im Zeichensatz, den
Ihre Tabellenkalkulation braucht. Imports liest eine solche Datei mit dem Format **CIE form submissions (bccie CSV)**
und macht aus jeder Einsendung ein Inhaltsobjekt, etwa um Kundenstimmen nach einer Prüfung zu veröffentlichen.

Die Beispieldatei des Katalogs `cie-testimonials.csv` (UTF-16, `;`, drei erfundene Einsendungen):

```text
Name;E-mail;Testimonial;Rating;Created;ID
Anna Example;anna@example.org;Lovely tea, fast delivery.;5;2026-04-02 10:12;101
Ben Sample;ben@example.com;"Good; the sencha is great";4;2026-04-03 08:40;102
Cara Test;cara@example.org;Friendly shop.;5;2026-04-05 17:03;103
```

1. Exportieren Sie in **CIE** das Formular im Hintergrund als CSV und klicken Sie neben dem fertigen Job auf **Open in
   Imports**. Oder laden Sie die Datei in einem neuen Import mit dem Format **CIE form submissions (bccie CSV)** hoch.
2. Optionen der Quelle: **Key field** `ID` (die Nummer der Einsendung), *Class* `article`.
3. Zuordnung: **By a remote ID made from the record**, Muster `cie-testimonial-{ID}`, damit eine Einsendung nur einmal
   importiert wird, so oft der Export auch importiert wird; `Name` bei *Title*, `Testimonial` mit der Umwandlung
   `richtext` bei *Summary*. Lassen Sie `E-mail` weg: Das sind personenbezogene Daten, die auf keine öffentliche Seite
   gehören.
4. **Dry run**: 3 würden angelegt.

![Der Probelauf des CIE-Beispiels: 3 von 3 Datensätzen, 3 angelegt; Datensatz 101 würde mit dem Titel Anna Example und der Einleitung als Rich Text angelegt, Datensatz 102 mit dem Titel Ben Sample](../images/int-cie-dry-run.png)

Zur Veranschaulichung: Dieses Beispiel lief nur als Probelauf (Lauf #199), es wurde nichts veröffentlicht. Legen Sie
die neuen Artikel in einen versteckten Ordner oder eine Sektion mit Freigabe, wenn die Einsendungen gelesen werden
sollen, bevor sie erscheinen.

## Der gemeinsame Laufverlauf

Jeder Lauf von Imports, Export, CIE und den Synchronisationen der Datentabellen wird im Laufverlauf des Kernels
festgehalten (Tabelle `exp_transfer_run`): wer ihn gestartet hat, wann, wie lange er dauerte, wie viele Datensätze er
bearbeitet hat und wie er endete. Die Laufliste der Übersicht von Imports und die Seite **Statistics** haben einen
Filter nach Werkzeug: **Imports**, ein anderes Werkzeug oder **All import and export tools**
(`sqliimport/list/(tool)/<Werkzeug-ID>`, etwa `transfer.datatable`):

![Die Läufe der Übersicht von Imports mit dem Werkzeugfilter: die Statusfilter, dann Imports, Packages, Export (xrowextract), Syndication, Documents, RSS import, RSS export, Collected information, Collected information export (CIE), Data tables gewählt, Newsletter subscribers, Audit export, PDF export und All import and export tools; kein Lauf passt](../images/int-runs-tool-filter.png)

Ein Lauf eines anderen Werkzeugs verweist auf seine eigene Seite (den Job mit seiner Datei); die eigenen Seiten jedes
Werkzeugs behalten ihre Listen und verweisen mit **All import and export runs** hierher zurück. Sie sehen Ihre eigenen
Läufe und die Läufe aller Benutzer eines Werkzeugs, wenn Ihre Rolle es erlaubt (bei Export die Richtlinie
xrowextract/all_jobs). Der Verlauf behält beendete Läufe `[TransferSettings] KeepDays` Tage (365); die Läufe von
Imports selbst folgen `[RunSettings] KeepDays` ([runs.md](runs.md)). Im Bild war noch keine Synchronisation
gelaufen, daher hat **Data tables** keine Läufe.

## Die Ergebnisse eines Laufs als CSV

**Download results (CSV)** auf der Seite eines Laufs (`sqliimport/report/<Lauf-ID>`) oder
`./console ext:sqliimport:report <Lauf-ID>` ergibt eine Zeile pro Datensatz. Für den Rückweg-Lauf #197:

```text
run_id,row,ref,outcome,status,object_id,node_id,remote_id,version_before,version_after,changed_fields,message,time
197,0,#1,unchanged,ok,56624,55003,sq688doc-rt-1,1,1,,,2026-10-23T01:35:10Z
197,1,#2,updated,ok,56625,55004,sq688doc-rt-2,1,2,,,2026-10-23T01:35:10Z
197,2,#3,unchanged,ok,56626,55005,sq688doc-rt-3,1,1,,,2026-10-23T01:35:10Z
```

`version_before` und `version_after` zeigen, welche Objekte eine neue Version bekamen. `changed_fields` ist bei
Probeläufen gefüllt (die Felder, die sich ändern würden); bei einem echten Lauf bleibt es leer. `--outcome=failed`
schreibt nur die fehlgeschlagenen Datensätze, und die Datei öffnet sich in einer Tabellenkalkulation für die Person,
die die Quelle korrigiert ([cli.md](cli.md)).
