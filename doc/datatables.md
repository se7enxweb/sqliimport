# Imports and data tables

A **data table** is the table of a persistent class: the own table of a module or an extension, such as a list of
books, a product register or a log, listed in **Setup > Data tables** of Exponential 6.0.15 and later. 7x SQLI Import
can fill such a table instead of making content items, help you create a new one from the fields of an import, and
open the kernel's synchronisations between a table and content. This page shows all three with a real example.

German: [ger-de/datatables.md](ger-de/datatables.md).

## Table of contents

- [What you need](#what-you-need)
- [The group Data tables in the catalogue](#the-group-data-tables-in-the-catalogue)
- [Worked example: books into a data table](#worked-example-books-into-a-data-table)
  - [The file](#the-file)
  - [Step Processing: the target and its options](#step-processing-the-target-and-its-options)
  - [The dry run](#the-dry-run)
  - [The first run and a second run](#the-first-run-and-a-second-run)
  - [The rows in the table](#the-rows-in-the-table)
- [The options of the target](#the-options-of-the-target)
- [Undo and data tables](#undo-and-data-tables)
- [A new table from an import: the module table wizard](#a-new-table-from-an-import-the-module-table-wizard)
- [Content and tables in sync](#content-and-tables-in-sync)
- [Related pages](#related-pages)

## What you need

- Exponential 6.0.15 or later (the kernel's data tables, its writer `expDataTableWriter` and the module table
  wizard). On an older kernel the target and the group are not offered.
- A persistent class with a table, listed in **Setup > Data tables**. The example uses the demo module
  `datatabledemo` with the class `datatableDemoBook` and its table `datatabledemo_book`.
- To fill a table: the policy **Manage imports** to set up the import. To make a new table: `setup/setup`. To set up a
  synchronisation: `datatable/sync`.

## The group Data tables in the catalogue

**Imports > Catalogue** (`sqliimport/catalogue`) ends with the group **Data tables**: three cards, one for each way
data tables and imports work together.

![The group Data tables of the catalogue: Import into a data table with New import, Create a new table from an import with Open the module table wizard, and Content and data tables in sync with the five kinds of synchronisation, a data table and a content class to choose, and New synchronisation, Synchronisations and Conflicts](images/int-datatable-catalogue-group.png)

| Card | What it opens |
|---|---|
| **Import into a data table** | A new import. In its step Processing you choose the target **Rows of a data table** ([below](#worked-example-books-into-a-data-table)) |
| **Create a new table from an import** | The kernel's module table wizard (`setup/moduletable`), fed with the fields of an import ([below](#a-new-table-from-an-import-the-module-table-wizard)) |
| **Content and data tables in sync** | The kernel's synchronisations (`datatable/sync`), with the table and the content class you chose filled in ([below](#content-and-tables-in-sync)) |

## Worked example: books into a data table

This is the demo import #76 "Demo: Books into a data table" (identifier `demo_books_datatable`) of the test
installation.

### The file

`books-datatable-v2.csv`, three invented books:

```text
title,isbn,price,in_print,summary
The test garden (second edition),SQ688-0001,12.50,1,An invented book for the check
Tea for testers,SQ688-0002,9.90,1,Another invented book
Old test notes,SQ688-0003,4.00,0,Out of print
```

The column names are the same as the columns of the table `datatabledemo_book` (`title`, `isbn`, `price`,
`in_print`, `summary`), so no column needs a line of its own.

### Step Processing: the target and its options

1. **Imports > New import**. Step **Source**: name `Demo: Books into a data table`, source type **CSV and delimited
   text**, **An uploaded file**, the file above. Delimiter and encoding stay `auto`. Save and continue.
2. Step **Processing**: under **How the records are processed**, choose **Rows of a data table (a persistent
   class)**. The options of the target appear:

   ![The step Processing of import 76: Rows of a data table chosen, Data table datatableDemoBook, Rows "Add new rows, update existing ones", Match rows by isbn, Columns empty, then Target, Runs and Notifications](images/int-datatable-processing.png)

   | Field | Value in the example | Stored option |
   |---|---|---|
   | **Data table** | `datatableDemoBook (datatabledemo, …)` | `class = datatableDemoBook` |
   | **Rows** | Add new rows, update existing ones | `mode = upsert` |
   | **Match rows by** | `isbn` (one column per line) | `keys = isbn` |
   | **Columns** | empty: every field whose name is a column | `columns =` (empty) |

   Save and continue. The target has no mapping step: the columns are the mapping. **Target**, the parent node and
   the class of content do not apply to rows; the fields under **Runs** and **Notifications** do.
3. Step **Preview**: the first records of the file, and what a dry run would do with them. Then **Schedule** (Never)
   and **Review**: tick **The import is enabled** and **Save**.

When the columns of the file are named differently, or a value needs a transform, write one line per column in
**Columns**, `column = field of the record | transforms`, the same one-line chain as the mapping grid
([transforms.md](transforms.md)):

```text
title = Title | trim
isbn = ISBN
price = Price (EUR) | number
in_print = Available | bool
summary = Description | strip_tags | truncate(length=250)
```

### The dry run

**Dry run** on the dashboard (run #116) checks every row with the kernel's writer and writes nothing:

![Run 116, a dry run of the books import: Completed, 3 of 3 records, 3 created, nothing else; each record says "dry run:" and the log says run started: 3 records, handler datatable, dry_run](images/int-datatable-run-116.png)

3 rows would be created. A value the table refuses (a text in a number column, a missing required column) would
fail its row here, with the row's number and the field, before anything is written.

### The first run and a second run

**Run now** (run #118) writes the three rows: **3 created**.

![Run 118 of the books import: Completed, 3 of 3 records, 3 created](images/int-datatable-run-118.png)

Then one title changed in the file ("The test garden" became "The test garden (second edition)") and the file was
uploaded again in the step Source. **Run now** (run #119) matches every row by its `isbn`, writes only the one that
differs and leaves the others alone: **0 created, 1 updated, 2 unchanged**.

![Run 119 of the books import: Completed, 3 of 3 records, 0 created, 1 updated, 2 unchanged; the records #1 updated and #2 and #3 unchanged with their rows 3, 4 and 5; the log with run started: 3 records, handler datatable](images/int-datatable-run-119.png)

The run page's **Download results (CSV)** lists every record with its outcome; for a data table the message names
the row.

### The rows in the table

The rows are in `datatabledemo_book` now, on the module's own list page (`datatabledemo/list`, here with two rows of
the module's own demo next to the three imported ones) and in **Setup > Data tables**, `datatable/rows/datatableDemoBook`:

![The page Data tables demo: books with five rows: Old test notes, Tea for testers and The test garden (second edition) with the ISBN SQ688-0003, SQ688-0002 and SQ688-0001, their prices and in_print, and two older rows of the demo module](images/int-datatable-rows.png)

From the command line the same runs are:

```bash
./console ext:sqliimport:run demo_books_datatable --dry-run --wait
./console ext:sqliimport:run demo_books_datatable --wait
```

## The options of the target

| Option | Values | Default |
|---|---|---|
| `class` (**Data table**) | a persistent class with a table, from the list of the site's data tables | required |
| `mode` (**Rows**) | `upsert` add new rows, update existing ones; `insert` only add; `update` only update existing rows; `replace` remove an existing row and write it again | `upsert` |
| `keys` (**Match rows by**) | the columns a row is matched by, one per line | the keys of the class |
| `columns` (**Columns**) | one line per column: `column = field \| transforms`; empty: every field whose name is a column | empty |

- The rows are written by the kernel's `expDataTableWriter`: checked against the class's definition, on the class's
  own connection (which may be another database), one transaction per chunk, never raw SQL. A row the database
  refuses fails alone; the others of its chunk are written.
- A relation column (an object, a node or a user) takes a reference to content instead of a number:
  `@object:<remote id>`, `@node:<remote id>` or `@path:<url alias>`. A reference to nothing fails the row.
- Every source of 7x SQLI Import can fill a table: a spreadsheet, a feed, a REST API, another database. Schedules,
  the webhook trigger, notifications and statistics work the same as for content.
- The conflict rule of the step Processing (*When someone edited an object since the last run*) is about content
  items. It does not apply to rows: a row is written as the file says.

More on the target for developers: [handlers.md](handlers.md#the-target-a-data-table-exponential-6015).

## Undo and data tables

An undo puts content back: what a run created goes to the trash, what it updated gets its earlier version. A data
table has neither trash nor versions, so a run into a table cannot be undone.

What the administration does today: the run page of such a run still shows **Undo this run** (the check only looks
at the kind of run and its counts). An undo run started there changes no row. Each of its records ends as
**Skipped** with the message "the object no longer exists", because a row is not a content object. To take rows out
again, remove them on the table's own page or in **Setup > Data tables**. To change them back, run the import with
the earlier file.

## A new table from an import: the module table wizard

When there is no table yet, let the import describe one. On the dashboard, open **More** next to the import and
choose **New data table from its fields** (policy `setup/setup`):

![The More menu of the books import: Duplicate, New data table from its fields, Disable and Delete](images/int-datatable-more-menu.png)

The kernel's module table wizard opens (`setup/moduletable/(source)/sqliimport/(ref)/76`). In its first section you
name the extension, the module, the table and the PHP class, and choose the connection:

![The section The extension and the table of the module table wizard: Extension, Module, Title, Table, PHP class and the connection This installation's database](images/int-datatable-wizard-where.png)

The section **The fields** shows what the import's source suggests. 7x SQLI Import reads a sample of the source
(here the three books) and guesses for each field a name, a type (string, text, integer, float, boolean, date,
datetime, json), a length, and whether it is required and unique:

![The section The fields: Where the fields come from sqliimport, Which one 76, Copy its fields into the list, the list box, and the guessed fields id, title string (32) required unique, isbn string (32) required unique, price float required unique, in_print integer required, summary string (32) required unique, created and modified](images/int-datatable-wizard-fields.png)

The guesses only know the sample, so check them before the wizard writes anything. With three rows they are too
strict and too short:

| Guessed | Why | Correct it to |
|---|---|---|
| `title string(32) required unique` | the longest sampled title fits 32 characters, and the three titles differ | `title string(255) required` |
| `isbn string(32) required unique` | every sampled value differs | keep it: `isbn string(32) required unique` (this is the key) |
| `price float required unique` | the three prices differ | `price float` |
| `in_print integer required` | the values are `0` and `1` | `in_print boolean` |
| `summary string(32) required unique` | short sampled texts that differ | `summary text` |

The rule behind it: a field is **unique** when all sampled values differ, **required** when every sampled record has
a value, and a string gets the smallest of the lengths 32, 64, 128 and 255 that holds the longest sampled value. A
larger sample (more rows in the file) gives better guesses.

To correct them:

1. Press **Copy its fields into the list**. The list box then holds the guessed fields, one per line
   (`name type[(length)] [required] [unique]`). Until then it shows the wizard's own example lines, not your import.
2. Edit the lines:

   ```text
   title string(255) required
   isbn string(32) required unique
   price float
   in_print boolean
   summary text
   ```

3. Choose whether each row is tied to content (**Tie each row to content**), keep **Add created and modified**, and
   let the wizard show and write its files.

After the table is installed, set the import's target to the new class ([above](#step-processing-the-target-and-its-options))
and match rows by `isbn`. What the wizard writes (the class, the schema for every engine, the admin pages, the
policies, the fetch functions and a JSON endpoint) is described in the kernel documentation, "Data tables", section
"The module table wizard" (`doc/eng-us/features/6.0/data-tables.md`).

## Content and tables in sync

An import reads from outside. When the data is already in Exponential, as content items of a class, the kernel's
**synchronisations** copy it between content and a table, and keep both in step. 7x SQLI Import copies nothing
itself here; its third card only opens the form with the table and the content class filled in. The kernel runs the
synchronisations and lists their runs in the run history of all import and export tools, as the tool **Data tables**.

![The form New synchronisation opened from the catalogue: Name Books to folders, Direction Content to the table, Runs When started, Data table datatableDemoBook, Content class Folder, Node, the map box, Key columns, When both sides changed The newer change wins, Every 3600 seconds, and the buttons Save, Save and run now, Propose the map and Back to the list](images/int-datatable-sync-form.png)

| Choice in the form | Values |
|---|---|
| **Direction** | Content to the table; the table to content; both ways |
| **Runs** | When started (once, with **Save and run now** or **Run now** on the list); on a schedule (every N seconds, by the cronjob part `datatable`); live (at every publish, move and removal of an object of the class below the node, and on the schedule too) |
| **Map** | one line per column, `column = source`; a source is an attribute identifier or `@object_id`, `@node_id`, `@parent_node_id`, `@remote_id`, `@node_remote_id`, `@name`, `@published`, `@modified`, `@class`, `@section`, `@owner`, `@path`. **Propose the map** fills it from the column names |
| **Key column(s)** | the mapped columns a row is matched by, for example `contentobject_id` or `remote_id` |
| **When both sides changed** | the newer change wins; the content wins; the table wins; ask (the conflict waits in **Conflicts**) |

The five uses the card lists, and how to set them:

| Use | Direction | Runs |
|---|---|---|
| Export content to a table | content to the table | when started |
| Migrate content into a table | content to the table, once; removing the content afterwards is a separate step, never the default | when started |
| Keep a table in sync with content | content to the table | on a schedule, or live |
| Import a table into content | the table to content | when started or on a schedule |
| Two-way sync | both ways, with a rule for conflicts | on a schedule, or live |

Example map for books kept as folders, both ways, for a table the wizard made with **Tie each row to content**
(the column `contentobject_id`) and a `remote_id` column, matched by `contentobject_id`:

```text
contentobject_id = @object_id
remote_id = @remote_id
title = name
```

Illustrative: this synchronisation was not saved on the test installation, so its runs are not shown. The rules of
each direction, the live mirror and the conflict queue are in the kernel documentation, "Data tables", sections
"Synchronisation", "Live mirror" and "Conflicts".

## Related pages

- [handlers.md](handlers.md#the-target-a-data-table-exponential-6015): the target for developers
- [transfer.md](transfer.md): Imports among the other import and export tools
- [runs.md](runs.md): run pages, undo, the CSV report
- [editor.md](editor.md): the six steps of the editor
- [transforms.md](transforms.md): the transforms of a column line
