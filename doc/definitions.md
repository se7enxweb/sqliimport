# Exporting, importing, copying and templates of imports

An import can leave the installation it was made on as a JSON file, come back on another one, be copied with a new
name, or become a template that new imports start from. None of these carries a secret value, an API key or an
uploaded file.

Deutsch: [ger-de/definitions.md](ger-de/definitions.md).

| What | Where | Policy |
|---|---|---|
| **Download as JSON** | Dashboard, an import's **More** menu; the editor's last step (Review and save) | Manage imports (for the import's handler) |
| **Export selected (JSON)** | Dashboard, under the list of imports (tick the imports first); Settings, Export import definitions | Manage imports |
| **Import definitions** | `sqliimport/transfer` (left menu, dashboard toolbar, Settings) | Manage imports |
| **Copy to …** | **More** menu; the editor's last step | Manage imports |
| **Save as template** | **More** menu; the editor's last step | Manage imports |
| **New import from a template** | `sqliimport/templates` (left menu, dashboard toolbar, **From a template** on a format's card in the Catalogue) | Manage imports |

Exporting needs **Manage imports** and not just the right to look at imports: a definition names the hosts,
databases, queries, server paths and addresses of this installation, which are not for everyone who may see the
dashboard. The handler limitation applies to every import of a file: an import of a handler you may not manage is
left out of an export and cannot be created or replaced.

## The definitions file

One import gives `sqliimport-import-<identifier>.json`, several give `sqliimport-imports-<date>-<time>.json`:

```json
{
    "format": "sqliimport-definitions",
    "version": 2,
    "exported": "2026-10-22T09:30:00Z",
    "generator": "7x SQLI Import",
    "secrets": [ "crm_db" ],
    "imports": [
        {
            "identifier": "crm_contacts",
            "name": "Contacts from the CRM",
            "description": "",
            "enabled": true,
            "source_type": "sql",
            "source": { "origin": "remote", "driver": "pgsql", "host": "crm.example.com", "password": "crm_db", "query": "SELECT ..." },
            "handler": "mapping",
            "options": {},
            "target": {
                "parent_node_id": 61, "section_id": 3, "language": "eng-GB", "owner_id": 0,
                "parent": { "node_id": 61, "remote_id": "0c1e5d...", "object_remote_id": "8f2a77...", "path": "contacts" },
                "section": { "id": 3, "identifier": "restricted" },
                "owner": null
            },
            "behaviour": { "chunk_size": 200, "update_policy": "update", "conflict_policy": "overwrite" },
            "mapping": {
                "class": "contact",
                "language": "eng-GB",
                "match": { "type": "remote_id", "pattern": "crm-{id}" },
                "fields": [
                    { "source": "name", "attribute": "name", "transforms": [], "default": "", "required": true, "language": "" },
                    { "source": "name_de", "attribute": "name", "transforms": [], "default": "", "required": false, "language": "ger-DE" }
                ],
                "location": { "parent_node_id": 61, "parent": { "node_id": 61, "remote_id": "0c1e5d..." } }
            },
            "schedules": [ { "kind": "daily", "run_at": 1761116400, "interval_minutes": 0, "cron_expr": "", "timezone": "Europe/Berlin", "catch_up": "one", "notify": [], "active": true } ],
            "trigger": { "enabled": true, "allow_push": false, "secret": "", "run_user": { "object_id": 14, "remote_id": "3f0e..." } },
            "secrets": [ "crm_db" ],
            "connections": []
        }
    ]
}
```

- **Source**: the origin (upload, url, path, remote) and the options of the source type. For an upload only the
  original name of the file is kept, never the file or the name it was stored under; upload it again on the other
  installation. A user and password in an address (`https://user:password@host/`) are taken out.
- **Target and mapping location**: the node IDs stay as they were, and next to each one a reference: the node's
  remote ID, the object's remote ID and the path. The other installation looks for the node by its remote ID, then
  by the object's remote ID, then by the node ID; the section by its identifier; the user an import runs as by the
  remote ID of the user object. Classes are named by their identifiers, as the mapping always does.
- **Mapping**: the lines with their transforms and translations (a line per language), the match rule, the class
  per record (`class_from` and `variants`).
- **Schedules** and **trigger settings**: the trigger is exported with whether it is on, whether it takes data,
  the name of its signing secret and its run user. **Never** its API keys, the salt its signing secret is derived
  from, or a secret value.
- **Secrets**: a secret option of a source or handler holds the name of a secret, never its value (the editor
  stores a typed value in the secret store and keeps only the name). The file lists those names per import and
  for the whole file, so the other installation can set them up first. A value in a secret option that is not a
  name is not written; the free options of a 1.x handler whose names look like a password, token or key are left
  out.

Version 1, the export of 2.0.0-alpha.1 (node IDs without references), is read too.

## Import definitions

1. Open **Import definitions** and choose the file (JSON, at most 10 MB), then **Read the file**. A file that is not
   a definitions file, has a newer version, an entry without an identifier or name, an identifier twice, or values
   of the wrong form is refused as a whole, and nothing is created.
2. The page shows a card per import:
   - **new** or **identifier taken** (with a link to the import that has it);
   - the source type and processing, the class and whether this site has it, the parent node it resolves to;
   - the schedules (they will be paused) and the trigger (it will be off, without keys);
   - the secrets it needs, each **set** or **missing** here;
   - what cannot be created (an unknown source type or handler, an option value of the wrong kind) and what to check
     in the editor (a parent node, section, language, class, attribute, secret or named connection this site does
     not have, a required option without a value, a file to upload again).
   The checks are the editor's: the option schemas of the source and handler, the mapper's validation of the
   mapping and its variants, the schedule rules, and the address and path rules of the site.
3. Choose per import: **Create it** (an identifier that is taken gets `_2`, `_3` ...), **Replace the existing import**
   (its definition, mapping and schedules are replaced; its runs, its API keys and its signing salt stay), or
   **Skip it**. An identifier that is taken is skipped unless you choose otherwise; an import that runs now is not
   replaced.
4. **Create the chosen imports (disabled)**. Every import is created or replaced **disabled**, with its schedules
   **paused** and its trigger **switched off**. Open each one, upload its file if it had one, run a **Dry run**, and
   enable it in the last step; resume its schedules on the Schedules page. Every created or replaced import is in
   the audit trail (`system.import.import.create`, `system.import.import.change`), and the file as a whole
   (`system.import.definitions.import`); an export is `system.import.definitions.export`.

The file waits between the two steps in `var/<site>/storage/sqliimport/transfer/` under a random name (the session
knows only that name); it is removed when you create or cancel, and an unused one after a day.

## Copy to …

**Duplicate** makes "Copy of <name>" at once. **Copy to …** asks for the name and the identifier (empty: made from
the name) and whether to **copy the schedules too** (off by default; copied paused). The copy is disabled and opens
in the editor. It keeps the source (the same uploaded file), the processing, the target and the mapping. The trigger,
its API keys and its signing secret are never copied, and neither are the runs.

## Templates

A template is an import without its source data and with placeholders: **Save as template** keeps the source type
and its options, the processing, the target and the mapping, and leaves out the uploaded file, the address and the
server path, the schedules, the trigger, the user the import runs as and the notification addresses. The name
becomes `{{name}}` and the identifier `{{identifier}}`, also in the remote ID pattern of the mapping.

**New import from a template** lists the templates (filtered by format when opened from the Catalogue). **Use** asks
for the placeholders and shows what the template needs here (the class, the secrets, what to check); **Create the
import** makes it disabled and opens it in the editor, where you choose its file or check its address, preview,
dry-run and enable it.

Where templates come from:

- **This site**: JSON files in `var/<site>/storage/sqliimport/templates/`, made with **Save as template**; they can be
  deleted on the Templates page. Files and not a table, because a template is a document people copy between
  installations and keep in a repository, extensions ship them the same way, and the tables need no change.
- **Extensions**, read only: `sqliimport.ini [TemplateSettings] Templates[]` names an extension (its
  `share/templates/*.json` are read) or a JSON file relative to the installation's root. 7x SQLI Import ships
  **News feed into articles** (RSS or Atom from an address) and **Articles from a CSV file**.

```ini
[TemplateSettings]
Templates[]
Templates[]=sqliimport
Templates[]=myextension
Templates[]=extension/myextension/share/import-templates/products.json
```

A template file:

```json
{
    "format": "sqliimport-template",
    "version": 1,
    "title": "News feed into articles",
    "description": "An RSS or Atom feed read from its address at every run.",
    "source_type": "rss",
    "placeholders": {
        "name": { "label": "Name", "default": "News feed" },
        "identifier": { "label": "Identifier", "default": "", "optional": true },
        "feed_url": { "label": "Address of the feed", "default": "", "help": "https://..." }
    },
    "secrets": [],
    "import": { "identifier": "{{identifier}}", "name": "{{name}}", "source_type": "rss",
                "source": { "origin": "url", "url": "{{feed_url}}" }, "handler": "mapping", "mapping": { "...": "..." } }
}
```

`import` is an entry of a definitions file. Every `{{placeholder}}` in its texts is replaced by the value given;
`name` and `identifier` are always asked for, the others are declared in `placeholders` (`optional: true` lets one
stay empty, `default` fills the field). A placeholder that is used but not declared makes the template invalid, and a
value may not contain `{{`. The result goes through the same checks as an imported definition.
