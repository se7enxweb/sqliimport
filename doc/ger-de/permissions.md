# Berechtigungen

Das Modul `sqliimport` hat in 2.x zwei Richtlinien und behält die beiden Funktionen von 1.x, sodass für 1.x
angelegte Rollen weiter funktionieren. Diese Seite nennt, was jede Richtlinie erlaubt, zeigt, wie eine Richtlinie einer
Rolle gegeben wird, und endet mit typischen Rollen.

English: [../permissions.md](../permissions.md).

## Inhalt

- [Die beiden Richtlinien](#die-beiden-richtlinien)
- [Beispiel: eine Rolle, die Importe ausführen darf](#beispiel-eine-rolle-die-importe-ausführen-darf)
- [Die Handler-Einschränkung](#die-handler-einschränkung)
- [Wie Richtlinien von 1.x zählen](#wie-richtlinien-von-1x-zählen)
- [Typische Rollen](#typische-rollen)
- [Hinweise zur Sicherheit](#hinweise-zur-sicherheit)

## Die beiden Richtlinien

| Funktion | Name | Einschränkung | Erlaubt |
|---|---|---|---|
| `manage` | Manage imports | `SQLIImport_Type` | Alles: Importe anlegen, bearbeiten, kopieren, aktivieren, deaktivieren und löschen, ihre Zeitpläne ändern und anhalten, die Seite Settings mit Export und Import der Definitionen, und alles, was `run` erlaubt |
| `run` | Run imports | `SQLIImport_Type` | Den Reiter Importe ansehen und Importe ausführen: Run now, Dry run, Cancel, Retry failed rows und Undo this run (ausführen, Probelauf, abbrechen, fehlgeschlagene Zeilen wiederholen, rückgängig machen) |

Jede der beiden Richtlinien öffnet den Reiter Importe: die Übersicht (`sqliimport/list`), die Seiten der Läufe
(`sqliimport/run`), die Seite Schedules (`sqliimport/schedules`) und den Katalog (`sqliimport/catalogue`). Der Editor
(`sqliimport/edit`) und die Seite Settings (`sqliimport/settings`) brauchen `manage`.

| Seite oder Aktion | `run` | `manage` |
|---|---|---|
| Übersicht, Seiten der Läufe, Schedules, Catalogue (ansehen) | ja | ja |
| Run now, Dry run, Cancel, Retry failed rows, Undo this run | ja | ja |
| Download results (CSV), die Seite Statistics, die Hinweise der Glocke | ja | ja |
| New import, Edit (der Editor) | | ja |
| Duplicate, Enable, Disable, Delete | | ja |
| Pause, Resume, Edit und Delete eines Zeitplans | | ja |
| Settings, Export und Import von Importdefinitionen | | ja |
| Download as JSON, Export selected, Import definitions, Copy to …, Save as template, New import from a template | | ja |
| Der Auslöser eines Imports (einschalten, Schlüssel, Geheimnis) | | ja |
| More > New data table from its fields | | ja, und `setup/setup` |

Ein Lauf, den der Auslöser startet, läuft als Laufbenutzer des Auslösers; dieser braucht `run` oder `manage` für den
Handler des Imports ([triggers.md](triggers.md)).

## Beispiel: eine Rolle, die Importe ausführen darf

Ziel: Die Redaktion des Nachrichtenressorts darf Importe starten und verfolgen, aber nicht ändern. Sie bekommt die
Richtlinie **Run imports** (`sqliimport/run`).

1. **Users, Roles**: Öffnen Sie die Rolle der Redaktion (oder legen Sie eine Rolle für sie an), dann **Edit**.
   Exponential bearbeitet einen Entwurf der Rolle; nichts erreicht ihre Benutzer vor **Save**.
2. **New policy**. Wählen Sie im Assistenten für Richtlinien das Modul `sqliimport` und die Funktion `run`:

   ![Schritt 1 des Assistenten für Richtlinien einer Rolle: Module sqliimport, Function run und die Schaltflächen Grant access to all functions, Grant full access und Grant limited access](../images/perm-wizard-function.png)

3. **Grant full access** gibt Run imports für jeden Import. **Grant limited access** öffnet Schritt 3, die
   Einschränkung `SQLIImport_Type` (siehe den nächsten Abschnitt):

   ![Schritt 3 des Assistenten für Richtlinien: Module sqliimport und Function run erledigt, Limitations mit der Liste SQLIImport_Type, die Any, Mapping to a content class (no code) (mapping) und Rows of a data table (a persistent class) (datatable) anbietet, und die Schaltflächen OK, Go back to step two, Go back to step one und Cancel](../images/perm-wizard-limitation.png)

4. **OK** fügt die Richtlinie dem Entwurf hinzu; **Save** an der Rolle wendet sie auf alle an, die die Rolle haben.
   Die Benutzer sehen den Reiter Importe nach ihrer nächsten Anmeldung.

Die Screenshots stammen von der Testinstallation, wo der Assistent an einer Rolle geöffnet und mit **Cancel**
verlassen wurde: Keine Rolle wurde geändert. Für eine Rolle, die Importe auch anlegen und ändern darf, wählen Sie
stattdessen die Funktion `manage`.

Die Funktionen `view`, `edit`, `rollback`, `ai` und `settings` der Version 2.0.0-alpha.1 gibt es nicht mehr. Eine
Rolle, die noch eine davon hat, erhält dadurch nichts; geben Sie ihr stattdessen `manage` oder `run`.

## Die Handler-Einschränkung

Die Einschränkung `SQLIImport_Type` nennt Handler über ihren Bezeichner (`mapping`, ein Handler für 2.x oder ein
Handler von 1.x). Mit ihr darf ein Benutzer nur die Importe ausführen oder verwalten, die diese Handler verwenden:

- die Schaltflächen anderer Importe werden nicht gezeigt, und ihre Aktionen werden abgelehnt;
- der Editor öffnet nur Importe der erlaubten Handler, und der Schritt Verarbeitung lehnt einen Handler außerhalb
  der Einschränkung ab;
- die Richtlinie Run imports mit der Einschränkung `mapping` erlaubt nur, die Importe ohne Programmierung
  auszuführen.

Was der Assistent für Richtlinien anbietet: Die Liste der Werte von `SQLIImport_Type` nennt zuerst `mapping`, dann
die anderen Handler von 2.x (`datatable` und die Handler von Erweiterungen), dann die Handler von 1.x aus
`sqliimport.ini [ImportSettings] AvailableSourceHandlers[]`, wie im Bild oben. Vor 2.0.0 enthielt sie nur die Handler
von 1.x, eine Installation ohne sie bot also nur **Any** an. Eine Richtlinie ohne die Einschränkung gilt für jeden
Handler.

## Wie Richtlinien von 1.x zählen

`expImportAdmin::can()` akzeptiert die Funktionen von 1.x neben denen von 2.x:

| Funktion von 2.x | Auch gewährt durch |
|---|---|
| den Reiter Importe ansehen | `listimports` oder `manageimports` |
| `run` | `manageimports` (mit seiner Handler-Einschränkung) |
| `manage` | `manageimports` (mit seiner Handler-Einschränkung) |

Eine Rolle mit `sqliimport/manageimports` kann in 2.x also schon alles, und eine Rolle mit `sqliimport/listimports`
kann zusehen. `./console ext:sqliimport:migrate-1x --apply --roles` fügt jeder Rolle mit `manageimports` die
Richtlinie `sqliimport/manage` mit derselben Handler-Einschränkung hinzu, damit diese Rollen weiter funktionieren,
wenn die Funktionen von 1.x entfallen ([migration-1x.md](migration-1x.md)). Eine Rolle mit `listimports` braucht
nichts Neues, und eine Rolle mit `sqliimport/*` bleibt unverändert.

## Typische Rollen

| Rolle | Richtlinien |
|---|---|
| Führt die News-Importe aus | `sqliimport/run` eingeschränkt auf den Handler der News-Importe |
| Redakteur, der jeden Import ausführt | `sqliimport/run` |
| Import-Administrator | `sqliimport/manage` (oder `sqliimport/*`) |

Das linke Menü des Reiters Importe zeigt einen Link nur Benutzern, die seine Seite öffnen dürfen
(`menu.ini [MenuAccessSettings] CheckViewAccess`). Der Link zur Job-Warteschlange braucht die Richtlinien von
expqueue. Der Block „Imports“ auf dem Dashboard der Administration erscheint für Benutzer, die den Reiter Importe
öffnen dürfen.

## Hinweise zur Sicherheit

- Jede Änderung auf den Seiten von 2.x und über die alten Adressen der Seiten von 1.x ist eine POST-Anfrage mit dem Formular-Token (ezformtoken). Eine
  GET-Anfrage ändert nichts. Auch die Live-Seite eines Laufs holt ihre Aktualisierungen per POST mit dem
  Formular-Token.
- Der Lauf wird als der Benutzer verarbeitet, der ihn gestartet hat, oder als Robot-Benutzer des Imports. Dieser
  Benutzer braucht die Inhaltsrichtlinien, um die Objekte des Imports anzulegen und zu bearbeiten.
- Ein Export von Importdefinitionen enthält keine Läufe, keine hochgeladene Datei, keinen API-Schlüssel, kein
  Signatursalz und keinen geheimen Wert (nur die Namen von Geheimnissen), wohl aber die Adressen, Abfragen und
  Optionen der Quellen. Behandeln Sie die Datei wie die Einstellungen der Website; der Export verlangt Manage imports
  ([definitions.md](definitions.md)).
