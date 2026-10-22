# Permissions

The module `sqliimport` has the functions of 2.x and keeps the two functions of 1.x, so roles made for 1.x keep
working.

## Functions

| Function | Limitation | Allows |
|---|---|---|
| `view` | | The Imports tab: the dashboard (`sqliimport/list`) and the run pages (`sqliimport/run`) |
| `run` | `SQLIImport_Type` | Run now, Dry run, Cancel and Retry failed rows |
| `edit` | `SQLIImport_Type` | Creating and changing imports (used by the import editor of 2.0.0-beta.1) |
| `manage` | | Managing imports as a whole (reserved for later releases) |
| `rollback` | | Undoing a run (reserved for later releases) |
| `ai` | | Reserved for later releases |
| `settings` | | The settings of the imports, such as the robot user an import runs as (reserved for later releases) |
| `listimports` | | 1.x: the Imports tab and the 1.x lists |
| `manageimports` | `SQLIImport_Type` | 1.x: the 1.x pages that change something (new import request, scheduled imports, cancel, interrupt, remove, purge) |

The limitation `SQLIImport_Type` lists handlers by their identifier (`mapping`, a 2.x handler, or a 1.x handler).
A user limited to some handlers can run only the imports that use them.

## How 1.x policies count

`expImportAdmin::can()` accepts the 1.x function next to the 2.x one:

| 2.x function | Also granted by |
|---|---|
| `view` | `listimports` or `manageimports` |
| `edit`, `run`, `manage` | `manageimports` (with its handler limitation) |

So a role with `sqliimport/manageimports` can already run imports in 2.x. `migrate-1x --apply --roles` adds the
matching 2.x policies to such roles, so they keep working when the 1.x functions are retired:

- a role with `listimports` or `manageimports` gets `view`;
- a role with `manageimports` gets `edit` and `run` with the same handler limitation;
- a role with `sqliimport/*` is left alone.

## Typical roles

| Role | Policies |
|---|---|
| Watches imports | `sqliimport/view` |
| Runs the news imports | `sqliimport/view`, `sqliimport/run` limited to the news handler |
| Import administrator | `sqliimport/*` |

The left menu of the Imports tab shows a link only to users who may open its page (`menu.ini [MenuAccessSettings]
CheckViewAccess`). The link to the job queue needs the policies of expqueue.

## Security notes

- Every change in the 2.x pages and in the 1.x pages is a POST request with the form token (ezformtoken). A GET
  request changes nothing.
- The run is processed as the user who started it, or as the robot user of the import. That user needs the content
  policies to create and edit the objects of the import.
