# The AI assist

The AI assist helps with the parts of an import that take the most looking: which field goes into which attribute,
which class fits a source, why a record failed and what a run did. It is **assistive only**: it makes suggestions,
and a person decides. Nothing it says is applied without a person accepting it and saving the form, and an import
whose mapping has accepted suggestions needs a dry run before its next real run.

German: [ger-de/ai.md](ger-de/ai.md).

## What it offers

| Where | Button | What happens |
|---|---|---|
| Editor, step Mapping | **Suggest with AI** (next to Auto-match by name) | A suggestion per attribute of the class: a source field, transforms in the one-line chain syntax, a confidence (shown as a badge: green from 80 %, yellow from 50 %, red below) and a short reason, in an extra column of the grid. **Accept** fills one line into the form, **Accept all** every line that has a suggestion. |
| Editor, step Mapping | **Suggest a class with AI** | The three content classes that fit the source's fields best, each with its confidence and reason; **Use this class** chooses it in the form. |
| Editor, step Processing, target "Rows of a data table" | **Suggest a data table with AI** | The same for the data tables (persistent classes); **Use this table** chooses it in the form. |
| Run page, a failed row | **Explain** | A short explanation of what went wrong and a suggested fix of the mapping or the source. |
| Run page of a finished run | **Summarize** | A short summary of the run: what it did, what went wrong, what to look at next. |

The buttons only post the form of the page. Accepting a suggestion changes what the form shows, not what is
stored: the form is saved only with its own **Save** buttons, and leaving the page without saving keeps the import as
it was. A suggestion that is already in the form is marked "In the form".

### Accepted suggestions need a dry run

When a mapping or processing step is saved with accepted suggestions in it (a line, a class or a data table), that
version of the import is marked. Until a dry run of that version or a later one has finished (completed, or completed
with errors), a real run (Run now, a schedule, a trigger, the command line, Retry failed rows) is refused with "the
mapping has lines suggested by the AI that were accepted: run a dry run of this version first". Start the dry run
with **Dry run** in the imports list (the preview step of the editor does not count: it is no run), look at its
results, then run the import.

## What is sent to the model

By default, **only names and types**:

- **Mapping suggestions:** the attributes of the class (identifier, name, datatype), and the names of the source's
  fields with the type detected from the sample (integer, number, boolean, date, datetime, email, url, html, list,
  text or empty). The values of the sample are read here to detect the types; they are not sent.
- **Class suggestions:** the identifiers and names of the classes (or data tables), and the field names and types.
- **Explain:** the row's message and the mapping lines it concerns (attribute, source field, transforms, whether it
  is required; no default values). A line is concerned when the message names its attribute or its source field;
  when it names none, every line is sent.
- **Summarize:** the run's own figures: status, kind, trigger, the counts per outcome, the duration, the waiting time,
  the time per record, the memory peak, the five most common error messages with their counts, and the fields that
  updates changed most often with their counts. Never a value of a record.

Messages are masked before they are sent; in summaries, quoted parts of a message are left out as well, since they
often hold a value of the record.

### Sample values only when the import opts in

The processing step of an import has the option **Send sample values to the AI** (off by default). When it is on,
the mapping and class suggestions also get up to `[AiSettings] SampleValues` (3) different sample values per field,
each at most 80 characters, and **Explain** also gets the values of the failed record's mapped fields. Before
anything is sent, the values are masked here, on the server:

| Value | Sent as |
|---|---|
| E-mail addresses | `[email]` |
| IBAN-like values (two letters, two digits, 11 to 30 letters or digits) | `[iban]` |
| Phone-like values: seven digits or more with spaces, dots, dashes, slashes or brackets between them (also card and account numbers); not a date or a time | `[phone]` |

Masking is a safety net, not a reason to send personal data: switch the option on only for sources whose values may
go to the model's provider under your data protection rules.

## Through expai only

The assist has no API key, no provider and no request of its own: every request goes through the extension
**expai** (Exponential Intelligence) of the installation, as one of its requests:

- expai's **master switch** (`expai.ini [AISettings] Enabled`, `DisabledSiteAccesses[]`) and its **veto rules**;
- expai's **permissions**: the user needs the policy `expai/write` and may use the model expai chooses for them (the
  site default, or their own choice where model choice is on and `expai/model_use` allows its provider);
- expai's **data policy**: the section of the import's target (its section, else the section of its parent node) and
  the content class must be allowed to go to the provider of the model (`[DataPolicy]`); for a data table or a class
  still to be chosen, the section alone;
- expai's **limits**, the **web budget** (a model too slow for a page request is refused) and the **monthly
  budget** (`[Budgets]`: a request whose estimate does not fit is refused);
- expai's **usage log** and **audit trail**: every request is a row of the usage log with the action
  `sqliimport:mapping`, `sqliimport:class`, `sqliimport:explain` or `sqliimport:summary` and the target's parent node.

The audit trail of 7x SQLI Import records `system.import.ai.suggest` (mapping or class suggestions asked for, with
the number of suggestions and whether values were sent), `system.import.ai.accept` (a step saved with accepted
suggestions, with what was accepted), `system.import.ai.explain` and `system.import.ai.summary`. They never hold a
value of a record or the text of an answer.

## Checked answers

The model is asked for JSON Lines (one JSON object per line). Its answer is checked before anything is shown:

- an answer that is not JSON Lines (prose, a line that is not an object, more lines than asked for, an answer that is
  too long) is **rejected** with "The answer of the model could not be read.";
- a mapping suggestion is **dropped** when its attribute is not one of the class, its source field not one of the
  sample, its transforms not a readable chain of known transforms with valid arguments, or its confidence not a
  number between 0 and 1; of two suggestions for the same attribute the more confident one counts. The page says how
  many were left out;
- a class suggestion is dropped when its class is not one of the candidates; the best three are shown;
- an explanation needs both the explanation and the fix, a summary its text; markup is removed and long texts are
  shortened.

## When it is shown

The buttons appear only when all of these hold; otherwise the pages look as without the assist:

1. `sqliimport.ini [AiSettings] Enabled=enabled` (`disabled` by default);
2. the extension expai is installed and active (its classes exist) and switched on for the siteaccess;
3. the user has `expai/write` and may use expai's model;
4. the import runs on the 2.x engine (not a 1.x handler), and the user may edit it (editor) or see it (run page).

## Answers are kept for the session

An answer is kept in the user's session (the last 40): mapping suggestions per import, class and set of fields;
class suggestions per import; explanations per run and row; summaries per run and state. Opening the page again
shows the kept answer without asking the model again; asking again with the same fields uses the kept answer too.
A new session asks again.

## Settings

```ini
[AiSettings]
# enabled or disabled
Enabled=disabled
# masked sample values per field for an import that opts in, 1-5
SampleValues=3
# the most source fields one request describes
MaxFields=150
```

## For developers

`expImportAi` (classes/exp/ai/) holds the requests, masking, type detection, the reading and checking of answers,
accepting into a form and the dry run rule; it talks to a model only through an `expImportAiTransport`. The
installation's transport is `expImportAiExpaiTransport`. The unit tests (`tests/unit/AiAssistTest.php`) set
`expImportAi::$transport` to a double that answers without a provider, and assert that no sample value is sent
without the option, that masking works, that malformed answers are rejected and that suggestions are checked; a
test also compares the calls of the expai transport with the methods of the installation's expai.
