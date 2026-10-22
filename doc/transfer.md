# Imports among the import and export tools

Exponential 6.0.15 and later list their import and export tools in one transfer registry (Setup, Import and export).
7x SQLI Import declares itself there as **Imports (7x SQLI Import)** (`settings/kernelservices.ini.append.php`,
block `[Service_transfer.sqliimport]`, tag `transfer.tool`). An older kernel ignores the block, and the boxes below
are not shown.

Deutsch: [ger-de/transfer.md](ger-de/transfer.md).

## Where Imports is offered

- **Import into this folder…** in a folder's menu opens `sqliimport/edit/new/(parent)/<node id>`: the new import
  creates its objects below that folder (when you may create content there).
- **Import objects of this class…** on a class opens `sqliimport/edit/new/(class)/<class identifier>`: the mapping
  starts with that class; Auto-match fills its lines from the sample.
- The dashboard ends with the other tools you may be looking for, and the left menu lists them.

Unknown or refused values of these parameters are ignored: the form opens as a plain new import.

## Opening a file another tool made

A tool links to `sqliimport/edit/new/(from)/<tool>/(job)/<job id>` (optionally `(preset)/<format>`). Imports asks the
tool for the job's file and gets it only when you may download that job there:

| Tool | Rule | Format |
|---|---|---|
| Export (`xrowextract`) | a finished CSV, JSON or XML class export you may see on its Jobs page, and the policy xrowextract/import or sqliimport/manage | `xrowextract`, with the export's column manifest |
| CIE (`bccie`) | your own background export, while you can still read the form, and the policy bccie/read | `cie` |

The file is copied into the uploads of Imports; the source step shows **Use the file … from … (job …)**. Nothing is
imported before you go through the steps. A file you may not have gives a notice, not an error. Another tool joins
with `sqliimport.ini [HandoverSettings] Tools[]=<tool id>;<class>`, the class having a static
`fileOf( $jobID, eZUser $user )` that returns `array( 'path', 'name', 'manifest' )` or null.

## Round trips

- **Export (xrowextract) files** (format `xrowextract`): with the column manifest (handed over, uploaded as
  `<file>.manifest.json` with it, or inside a JSON or XML export) the mapping is exact, and the remote ID column makes
  a re-import update the objects instead of creating new ones. Without a manifest, set the option *Class*: columns
  named after its attribute identifiers are mapped.
- **CIE form submissions** (format `cie`): UTF-8, UTF-16 or Windows-1252, `;` or `,`; choose the class a submission
  becomes, and the columns are matched to its attributes by identifier or name.
- **RSS export of another Exponential site** (format `exponential_rss`): the articles of the other site's feed.
- **What an import did**: the run page offers **Download results (CSV)**, one line per record with its outcome,
  object, node, remote ID, versions, changed fields and message (`sqliimport/report/<run id>`).
- **Mappings between installations**: the mapping step offers **Download mapping (JSON)** (`sqliimport/mapping/<id>`)
  and **Load a mapping…**; a new import's source step takes a mapping file too. A mapping file names the class, the
  language, the match rule and the lines, not the location; loading it checks that this site has the class and its
  attributes.
