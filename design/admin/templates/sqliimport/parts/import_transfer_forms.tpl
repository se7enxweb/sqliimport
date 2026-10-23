{* The transfer actions of one import (policy Manage imports): Download as JSON, Copy to … (a name, an identifier,
   its schedules on request; never the trigger or its keys) and Save as template. Used in the More menu of the
   dashboard and in the editor's review step. Input: $import (expImportDefinition), $here (the page to come back to),
   $sq_where (a word that keeps the ids of the fields unique on the page). No inline CSS or script. *}
{include uri='design:sqliimport/parts/button.tpl' action='export' import_id=$import.id here=$here small=1 icon='download' label='Download as JSON'|i18n( 'extension/sqliimport' )}
<details class="a4-fold a4-sqliimport-subform">
<summary>{'Copy to …'|i18n( 'extension/sqliimport' )}<span class="a4-sr-only"> {$import.name|wash}</span></summary>
<form class="a4-form" method="post" action={'/sqliimport/action'|ezurl}>
<input type="hidden" name="Action" value="copy_to" />
<input type="hidden" name="RedirectURI" value="{$here|wash}" />
<input type="hidden" name="ImportID" value="{$import.id|wash}" />
<div class="a4-field">
<label for="sq-copy-name-{$sq_where|wash}-{$import.id|wash}">{'Name'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-copy-name-{$sq_where|wash}-{$import.id|wash}" name="CopyName" value="{'Copy of %name'|i18n( 'extension/sqliimport',, hash( '%name', $import.name ) )|wash}" required="required" maxlength="255" />
</div>
<div class="a4-field">
<label for="sq-copy-id-{$sq_where|wash}-{$import.id|wash}">{'Identifier'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-copy-id-{$sq_where|wash}-{$import.id|wash}" name="CopyIdentifier" value="" maxlength="100" pattern="[a-z0-9_\-]+" aria-describedby="sq-copy-id-help-{$sq_where|wash}-{$import.id|wash}" />
<span class="a4-help" id="sq-copy-id-help-{$sq_where|wash}-{$import.id|wash}">{'Lower case letters, digits, _ and -; empty: made from the name.'|i18n( 'extension/sqliimport' )}</span>
</div>
<div class="a4-field">
<label class="a4-check"><input type="checkbox" name="CopySchedules" value="1" /> {'Copy its schedules too (paused)'|i18n( 'extension/sqliimport' )}</label>
<span class="a4-help">{'The copy is disabled until it is saved. Its trigger and API keys are never copied.'|i18n( 'extension/sqliimport' )}</span>
</div>
<div class="a4-toolbar"><button type="submit" class="a4-btn a4-btn-small">{'Copy'|i18n( 'extension/sqliimport' )}</button></div>
</form>
</details>
<details class="a4-fold a4-sqliimport-subform">
<summary>{'Save as template'|i18n( 'extension/sqliimport' )}<span class="a4-sr-only"> {$import.name|wash}</span></summary>
<form class="a4-form" method="post" action={'/sqliimport/action'|ezurl}>
<input type="hidden" name="Action" value="save_template" />
<input type="hidden" name="RedirectURI" value="{$here|wash}" />
<input type="hidden" name="ImportID" value="{$import.id|wash}" />
<div class="a4-field">
<label for="sq-tpl-title-{$sq_where|wash}-{$import.id|wash}">{'Title of the template'|i18n( 'extension/sqliimport' )}</label>
<input type="text" id="sq-tpl-title-{$sq_where|wash}-{$import.id|wash}" name="TemplateTitle" value="{$import.name|wash}" required="required" maxlength="200" />
</div>
<div class="a4-field">
<label for="sq-tpl-desc-{$sq_where|wash}-{$import.id|wash}">{'Description'|i18n( 'extension/sqliimport/edit' )}</label>
<textarea id="sq-tpl-desc-{$sq_where|wash}-{$import.id|wash}" name="TemplateDescription" rows="2" maxlength="1000"></textarea>
<span class="a4-help">{'A template keeps the source type and its options, the processing, the mapping and the target; not the file, address or path, the schedules, the trigger or the notification addresses. Name and identifier become placeholders.'|i18n( 'extension/sqliimport' )}</span>
</div>
<div class="a4-toolbar"><button type="submit" class="a4-btn a4-btn-small">{'Save as template'|i18n( 'extension/sqliimport' )}</button></div>
</form>
</details>
