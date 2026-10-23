# Permissions

The module `sqliimport` has two policies in 2.x and keeps the two functions of 1.x, so roles made for 1.x keep
working.

## The two policies

| Function | Name | Limitation | Allows |
|---|---|---|---|
| `manage` | Manage imports | `SQLIImport_Type` | Everything: create, edit, copy, enable, disable and delete imports, change and pause their schedules, the Settings page with the export and import of definitions, and everything `run` allows |
| `run` | Run imports | `SQLIImport_Type` | Looking at the Imports tab and running imports: Run now, Dry run, Cancel and Retry failed rows |

Either policy opens the Imports tab: the dashboard (`sqliimport/list`), the run pages (`sqliimport/run`), the
Schedules page (`sqliimport/schedules`) and the Catalogue (`sqliimport/catalogue`). The editor (`sqliimport/edit`)
and the Settings page (`sqliimport/settings`) need `manage`.

| Page or action | `run` | `manage` |
|---|---|---|
| Dashboard, run pages, Schedules, Catalogue (looking) | yes | yes |
| Run now, Dry run, Cancel, Retry failed rows | yes | yes |
| New import, Edit (the editor) | | yes |
| Duplicate, Enable, Disable, Delete | | yes |
| Pause, Resume, Edit and Delete of a schedule | | yes |
| Settings, export and import of import definitions | | yes |
| Download as JSON, Export selected, Import definitions, Copy to …, Save as template, New import from a template | | yes |

The 2.x functions `view`, `edit`, `rollback`, `ai` and `settings` of release 2.0.0-alpha.1 are gone. A role that
still holds one of them grants nothing through it; give it `manage` or `run` instead.

## The handler limitation

The limitation `SQLIImport_Type` lists handlers by their identifier (`mapping`, a 2.x handler, or a 1.x handler).
With it, a user may run or manage only the imports that use those handlers:

- the buttons of other imports are not shown, and their actions are refused;
- the editor opens only imports of the allowed handlers, and the processing step refuses a handler outside the
  limitation;
- the Run imports policy with the limitation `mapping` allows running the no-code imports only.

## How 1.x policies count

`expImportAdmin::can()` accepts the 1.x functions next to the 2.x ones:

| 2.x function | Also granted by |
|---|---|
| looking at the Imports tab | `listimports` or `manageimports` |
| `run` | `manageimports` (with its handler limitation) |
| `manage` | `manageimports` (with its handler limitation) |

So a role with `sqliimport/manageimports` can already do everything in 2.x, and a role with
`sqliimport/listimports` can look. `./console ext:sqliimport:migrate-1x --apply --roles` adds `sqliimport/manage`
with the same handler limitation to every role that has `manageimports`, so these roles keep working when the 1.x
functions are retired ([migration-1x.md](migration-1x.md)). A role with `listimports` needs nothing new, and a role
with `sqliimport/*` is left alone.

## Typical roles

| Role | Policies |
|---|---|
| Runs the news imports | `sqliimport/run` limited to the handler of the news imports |
| Editor who runs every import | `sqliimport/run` |
| Import administrator | `sqliimport/manage` (or `sqliimport/*`) |

The left menu of the Imports tab shows a link only to users who may open its page (`menu.ini [MenuAccessSettings]
CheckViewAccess`). The link to the job queue needs the policies of expqueue. The block "Imports" on the admin
dashboard is shown to users who may open the Imports tab.

## Security notes

- Every change in the 2.x pages and through the old addresses of the 1.x pages is a POST request with the form token (ezformtoken). A GET
  request changes nothing. The live run page reads its updates by POST with the form token as well.
- The run is processed as the user who started it, or as the robot user of the import. That user needs the content
  policies to create and edit the objects of the import.
- An export of import definitions contains no runs, no uploaded file, no API key, no signing salt and no secret
  value (only the names of secrets), but it does contain the source addresses, queries and options. Treat the file
  as you would treat the settings of the site; exporting needs Manage imports ([definitions.md](definitions.md)).
