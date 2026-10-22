# Berechtigungen

Das Modul `sqliimport` hat in 2.x zwei Richtlinien und behält die beiden Funktionen von 1.x, sodass für 1.x
angelegte Rollen weiter funktionieren.

## Die beiden Richtlinien

| Funktion | Name | Einschränkung | Erlaubt |
|---|---|---|---|
| `manage` | Manage imports | `SQLIImport_Type` | Alles: Importe anlegen, bearbeiten, kopieren, aktivieren, deaktivieren und löschen, ihre Zeitpläne ändern und anhalten, die Seite Settings mit Export und Import der Definitionen, und alles, was `run` erlaubt |
| `run` | Run imports | `SQLIImport_Type` | Den Reiter Importe ansehen und Importe ausführen: Run now, Dry run, Cancel und Retry failed rows (ausführen, Probelauf, abbrechen, fehlgeschlagene Zeilen wiederholen) |

Jede der beiden Richtlinien öffnet den Reiter Importe: die Übersicht (`sqliimport/list`), die Seiten der Läufe
(`sqliimport/run`), die Seite Schedules (`sqliimport/schedules`) und den Katalog (`sqliimport/catalogue`). Der Editor
(`sqliimport/edit`) und die Seite Settings (`sqliimport/settings`) brauchen `manage`.

| Seite oder Aktion | `run` | `manage` |
|---|---|---|
| Übersicht, Seiten der Läufe, Schedules, Catalogue (ansehen) | ja | ja |
| Run now, Dry run, Cancel, Retry failed rows | ja | ja |
| New import, Edit (der Editor) | | ja |
| Duplicate, Enable, Disable, Delete | | ja |
| Pause, Resume, Edit und Delete eines Zeitplans | | ja |
| Settings, Export und Import von Importdefinitionen | | ja |

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

- Jede Änderung auf den Seiten von 2.x und von 1.x ist eine POST-Anfrage mit dem Formular-Token (ezformtoken). Eine
  GET-Anfrage ändert nichts. Auch die Live-Seite eines Laufs holt ihre Aktualisierungen per POST mit dem
  Formular-Token.
- Der Lauf wird als der Benutzer verarbeitet, der ihn gestartet hat, oder als Robot-Benutzer des Imports. Dieser
  Benutzer braucht die Inhaltsrichtlinien, um die Objekte des Imports anzulegen und zu bearbeiten.
- Ein Export von Importdefinitionen enthält keine Läufe und keinen Dateiinhalt, wohl aber die Adressen und Optionen
  der Quellen. Behandeln Sie die Datei wie die Einstellungen der Website.
