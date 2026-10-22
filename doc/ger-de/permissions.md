# Berechtigungen

Das Modul `sqliimport` hat die Funktionen von 2.x und behält die beiden Funktionen von 1.x, sodass für 1.x
angelegte Rollen weiter funktionieren.

## Funktionen

| Funktion | Einschränkung | Erlaubt |
|---|---|---|
| `view` | | Den Reiter Importe: die Übersicht (`sqliimport/list`) und die Seiten der Läufe (`sqliimport/run`) |
| `run` | `SQLIImport_Type` | Run now, Dry run, Cancel und Retry failed rows (ausführen, Probelauf, abbrechen, wiederholen) |
| `edit` | `SQLIImport_Type` | Importe anlegen und ändern (vom Import-Editor in 2.0.0-beta.1 verwendet) |
| `manage` | | Importe insgesamt verwalten (für spätere Versionen vorgesehen) |
| `rollback` | | Einen Lauf rückgängig machen (für spätere Versionen vorgesehen) |
| `ai` | | Für spätere Versionen vorgesehen |
| `settings` | | Die Einstellungen der Importe, etwa der Robot-Benutzer, als der ein Import läuft (für spätere Versionen vorgesehen) |
| `listimports` | | 1.x: der Reiter Importe und die Listen von 1.x |
| `manageimports` | `SQLIImport_Type` | 1.x: die Seiten von 1.x, die etwas ändern (neuer Importauftrag, geplante Importe, abbrechen, unterbrechen, entfernen, bereinigen) |

Die Einschränkung `SQLIImport_Type` nennt Handler über ihren Bezeichner (`mapping`, ein Handler für 2.x oder ein
Handler von 1.x). Ein auf bestimmte Handler eingeschränkter Benutzer kann nur die Importe ausführen, die sie
verwenden.

## Wie Richtlinien von 1.x zählen

`expImportAdmin::can()` akzeptiert die Funktion von 1.x neben der von 2.x:

| Funktion von 2.x | Auch gewährt durch |
|---|---|
| `view` | `listimports` oder `manageimports` |
| `edit`, `run`, `manage` | `manageimports` (mit seiner Handler-Einschränkung) |

Eine Rolle mit `sqliimport/manageimports` kann in 2.x also schon Importe ausführen. `migrate-1x --apply --roles`
fügt solchen Rollen die passenden Richtlinien von 2.x hinzu, damit sie weiter funktionieren, wenn die Funktionen
von 1.x entfallen:

- eine Rolle mit `listimports` oder `manageimports` bekommt `view`;
- eine Rolle mit `manageimports` bekommt `edit` und `run` mit derselben Handler-Einschränkung;
- eine Rolle mit `sqliimport/*` bleibt unverändert.

## Typische Rollen

| Rolle | Richtlinien |
|---|---|
| Beobachtet Importe | `sqliimport/view` |
| Führt die News-Importe aus | `sqliimport/view`, `sqliimport/run` eingeschränkt auf den News-Handler |
| Import-Administrator | `sqliimport/*` |

Das linke Menü des Reiters Importe zeigt einen Link nur Benutzern, die seine Seite öffnen dürfen
(`menu.ini [MenuAccessSettings] CheckViewAccess`). Der Link zur Job-Warteschlange braucht die Richtlinien von
expqueue.

## Hinweise zur Sicherheit

- Jede Änderung auf den Seiten von 2.x und von 1.x ist eine POST-Anfrage mit dem Formular-Token (ezformtoken). Eine
  GET-Anfrage ändert nichts.
- Der Lauf wird als der Benutzer verarbeitet, der ihn gestartet hat, oder als Robot-Benutzer des Imports. Dieser
  Benutzer braucht die Inhaltsrichtlinien, um die Objekte des Imports anzulegen und zu bearbeiten.
