# Zeitpläne

Ein Zeitplan startet einen Import von selbst (Tabelle `sqliimport_schedule`, Klasse `expImportSchedule`). Ein
Import kann mehrere Zeitpläne haben.

## Arten

| `kind` | Läuft | Verwendet |
|---|---|---|
| `once` | Einmal, zu `run_at` | `run_at` |
| `interval` | Alle `interval_minutes` Minuten, gezählt ab `run_at` (mindestens 5 Minuten) | `run_at`, `interval_minutes` |
| `hourly` | Jede Stunde zur Minute von `run_at` | `run_at` |
| `daily` | Jeden Tag zur Uhrzeit von `run_at` | `run_at` |
| `weekly` | Jede Woche am Wochentag und zur Uhrzeit von `run_at` | `run_at` |
| `monthly` | Jeden Monat am Tag und zur Uhrzeit von `run_at`; in einem kürzeren Monat am letzten Tag | `run_at` |
| `cron` | Nach einem Cron-Ausdruck mit fünf Feldern | `cron_expr` |

## Cron-Ausdrücke

Fünf Felder: Minute, Stunde, Tag des Monats, Monat, Wochentag. Unterstützt werden:

- `*` und `?`, Listen (`1,15`), Bereiche (`1-5`), Schritte (`*/10`, `0-30/5`);
- Namen der Monate (`jan` bis `dec`) und der Tage (`sun` bis `sat`); Tag `0` und `7` sind Sonntag;
- die Kurzformen `@hourly`, `@daily`, `@midnight`, `@weekly`, `@monthly`, `@yearly` und `@annually`.

Sind sowohl der Tag des Monats als auch der Wochentag eingeschränkt, genügt einer von beiden, wie bei cron.

Beispiele:

| Ausdruck | Läuft |
|---|---|
| `30 2 * * *` | Jeden Tag um 02:30 |
| `0 */4 * * *` | Alle vier Stunden |
| `0 6 * * mon-fri` | Werktags um 06:00 |
| `15 3 1 * *` | Am Ersten jedes Monats um 03:15 |

## Zeitzonen und Sommerzeit

Jeder Zeitplan hat eine `timezone` (Vorgabe `UTC`). Uhrzeiten gelten in dieser Zeitzone, „täglich um 02:30“ bleibt
also auch beim Wechsel der Sommerzeit um 02:30 Ortszeit:

- Eine Uhrzeit, die es am Tag des Wechsels nicht gibt (02:30, wenn die Uhr von 02:00 auf 03:00 springt), wird an
  diesem Tag ausgelassen.
- Eine Uhrzeit, die es zweimal gibt (die Uhr wird zurückgestellt), läuft einmal.

## Verpasste Läufe und Benachrichtigungen

- `catch_up` regelt, was geschieht, wenn Läufe verpasst wurden, etwa weil cron nicht lief: `one` (Vorgabe) läuft
  jetzt einmal; `skip` wartet auf den nächsten Termin, wenn der Lauf mehr als zehn Minuten zu spät ist.
- `notify_json` ist eine Liste von E-Mail-Adressen. Sie bekommen eine Nachricht, wenn ein Lauf des Zeitplans
  fehlschlägt oder mit fehlgeschlagenen Datensätzen endet. Absender ist `site.ini [MailSettings] EmailSender`, sonst
  `AdminEmail`.
- Ein Zeitplan `once` schaltet sich nach seinem Lauf selbst ab.
- Ein Zeitplan, dessen Import entfernt wurde, wird abgeschaltet.
- Läuft der Import noch, wenn sein Zeitplan fällig ist, wird der neue Lauf nicht gestartet; der Cron-Teil meldet das,
  und der Zeitplan wartet auf seinen nächsten Termin.

## Der Cron-Teil

Der Cron-Teil `sqliimport` muss jede Minute laufen:

```bash
* * * * * cd /pfad/zu/exponential && php runcronjobs.php -q sqliimport
```

Jede Runde (`expImportScheduler::tick()`):

1. beendet Läufe, deren Jobs ohne sie beendet wurden;
2. stellt die Läufe fälliger Zeitpläne in die Warteschlange (die Läufe verarbeiten die Worker der
   Job-Warteschlange, Cron-Teil `expqueue`);
3. übergibt offene Importaufträge und fällige geplante Importe von 1.x an die Engine von 2.x;
4. entfernt beendete Läufe, die älter als `[RunSettings] KeepDays` sind.

`./console ext:sqliimport:tick` führt eine Runde von Hand aus und gibt aus, was sie getan hat.

Die Cron-Teile `sqliimport_run` und `sqliimport_cleanup` von 1.x sind veraltet und bleiben für eine Hauptversion
erhalten. Ist die Job-Warteschlange installiert, übergibt `sqliimport_run` die Importe von 1.x an die Engine von
2.x, wie es der Teil `sqliimport` tut; ohne sie führt er sie wie in 1.x aus. Ersetzen Sie sie durch `sqliimport`.

## Einen Zeitplan in PHP anlegen

Der Zeitplan-Editor kommt mit dem Import-Editor in 2.0.0-beta.1. Bis dahin:

```php
<?php
$import = expImportDefinition::fetchByIdentifier( 'news_feed' );
$zone = new DateTimeZone( 'Europe/Berlin' );
$schedule = new expImportSchedule( array(
    'import_id' => $import->attribute( 'id' ),
    'kind' => 'daily',
    'run_at' => ( new DateTime( '2026-11-01 02:30', $zone ) )->getTimestamp(),
    'interval_minutes' => 0,
    'cron_expr' => '',
    'timezone' => 'Europe/Berlin',
    'catch_up' => 'one',
    'notify_json' => json_encode( array( 'webmaster@example.com' ) ),
    'is_active' => 1,
    'last_run' => 0,
    'created' => time(),
    'modified' => time(),
    'creator_id' => eZUser::currentUserID(),
) );
$errors = expImportCron::validate( $schedule->calcArray() );   // leer, wenn der Zeitplan gültig ist
$schedule->computeNext();
$schedule->store();
```

Geplante Läufe laufen als der Ersteller des Zeitplans oder als Robot-Benutzer des Imports.
