# Importe unter den Import- und Export-Werkzeugen

Exponential 6.0.15 und neuer führen ihre Import- und Export-Werkzeuge in einem Transfer-Register (Einrichtung, Import
und Export). 7x SQLI Import trägt sich dort als **Imports (7x SQLI Import)** ein
(`settings/kernelservices.ini.append.php`, Block `[Service_transfer.sqliimport]`, Tag `transfer.tool`). Ein älterer
Kernel übergeht den Block, und die Kästen unten erscheinen nicht.

English: [../transfer.md](../transfer.md).

## Wo Importe angeboten werden

- **In diesen Ordner importieren …** im Menü eines Ordners öffnet `sqliimport/edit/new/(parent)/<Knoten-ID>`: der neue
  Import legt seine Objekte unter diesem Ordner an (wenn Sie dort Inhalte anlegen dürfen).
- **Objekte dieser Klasse importieren …** bei einer Klasse öffnet `sqliimport/edit/new/(class)/<Klassenkennung>`: die
  Zuordnung beginnt mit dieser Klasse; Auto-match füllt die Zeilen aus der Stichprobe.
- Die Übersicht endet mit den anderen Werkzeugen, die Sie vielleicht suchen, und das linke Menü führt sie auf.

Unbekannte oder abgelehnte Werte dieser Parameter werden übergangen: das Formular öffnet als einfacher neuer Import.

## Eine Datei eines anderen Werkzeugs öffnen

Ein Werkzeug verlinkt auf `sqliimport/edit/new/(from)/<Werkzeug>/(job)/<Job-ID>` (wahlweise mit `(preset)/<Format>`).
Imports fragt das Werkzeug nach der Datei des Jobs und erhält sie nur, wenn Sie diesen Job dort herunterladen dürfen:

| Werkzeug | Regel | Format |
|---|---|---|
| Export (`xrowextract`) | ein fertiger CSV-, JSON- oder XML-Klassenexport, den Sie auf seiner Seite Jobs sehen, und die Richtlinie xrowextract/import oder sqliimport/manage | `xrowextract`, mit dem Spalten-Manifest des Exports |
| CIE (`bccie`) | Ihr eigener Hintergrund-Export, solange Sie das Formular noch lesen dürfen, und die Richtlinie bccie/read | `cie` |

Die Datei wird in die Uploads von Imports kopiert; der Schritt Quelle zeigt **Die Datei … aus … verwenden (Job …)**.
Importiert wird erst, wenn Sie die Schritte durchgehen. Eine Datei, die Ihnen nicht zusteht, ergibt einen Hinweis,
keinen Fehler. Ein weiteres Werkzeug meldet sich mit `sqliimport.ini [HandoverSettings] Tools[]=<Werkzeug-ID>;<Klasse>`
an; die Klasse hat eine statische Methode `fileOf( $jobID, eZUser $user )`, die `array( 'path', 'name', 'manifest' )`
oder null liefert.

## Hin und zurück

- **Dateien von Export (xrowextract)** (Format `xrowextract`): mit dem Spalten-Manifest (übergeben, als
  `<Datei>.manifest.json` mit hochgeladen oder in einem JSON- oder XML-Export enthalten) ist die Zuordnung genau, und
  die Spalte der Remote-ID lässt einen erneuten Import die Objekte aktualisieren statt neue anzulegen. Ohne Manifest
  setzen Sie die Option *Class*: Spalten, die wie ihre Attributkennungen heißen, werden zugeordnet.
- **CIE-Formulareinsendungen** (Format `cie`): UTF-8, UTF-16 oder Windows-1252, `;` oder `,`; wählen Sie die Klasse, zu
  der eine Einsendung wird, und die Spalten werden ihren Attributen nach Kennung oder Name zugeordnet.
- **RSS-Export einer anderen Exponential-Site** (Format `exponential_rss`): die Artikel des Feeds der anderen Site.
- **Was ein Import getan hat**: die Seite des Laufs bietet **Ergebnisse herunterladen (CSV)**, eine Zeile je Datensatz
  mit Ergebnis, Objekt, Knoten, Remote-ID, Versionen, geänderten Feldern und Meldung (`sqliimport/report/<Lauf-ID>`).
- **Zuordnungen zwischen Installationen**: der Schritt Zuordnung bietet **Zuordnung herunterladen (JSON)**
  (`sqliimport/mapping/<ID>`) und **Zuordnung laden …**; der Schritt Quelle eines neuen Imports nimmt ebenfalls eine
  Zuordnungsdatei an. Eine Zuordnungsdatei nennt Klasse, Sprache, Zuordnungsregel und Zeilen, nicht den Ort; beim
  Laden wird geprüft, dass diese Site die Klasse und ihre Attribute hat.
