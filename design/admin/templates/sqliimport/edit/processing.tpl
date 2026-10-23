{* Editor step "processing": the mapping or a handler with its options, the target and the behaviour of the runs. *}
<form class="a4-form is-wide" method="post" action={concat( $base, '/(step)/processing' )|ezurl}>
{if $import.is_legacy}
<div class="a4-field a4-field-wide">
<p>{'The 1.x handler %handler reads its own source and publishes the content itself.'|i18n( 'extension/sqliimport/edit',, hash( '%handler', $handlers[$import.handler].label ) )|wash}</p>
<label for="sq-legacy-options">{'Options (one per line: name=value)'|i18n( 'extension/sqliimport/edit' )}</label>
<textarea id="sq-legacy-options" name="LegacyOptions" rows="5">{foreach $legacy_options as $k => $v}{$k|wash}={$v|wash}
{/foreach}</textarea>
<span class="a4-help">{'The handler reads them as in 1.x (SQLIImportHandlerOptions); a value may contain "=".'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
{else}
<fieldset class="a4-field a4-field-wide">
<legend>{'How the records are processed'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-radios">
{foreach $handlers as $hid => $h}{if and( $h.legacy|not, eq( $h.status, 'enabled' ) )}
<label><input type="radio" name="Handler" value="{$hid|wash}"{if eq( $handler_choice, $hid )} checked="checked"{/if} /> {$h.label|wash}{if ne( $hid, 'mapping' )} <span class="a4-meta">{$hid|wash}</span>{/if}</label>
{/if}{/foreach}
</div>
</fieldset>
{if and( is_set( $handlers[$handler_choice] ), $handlers[$handler_choice].schema|count|gt( 0 ) )}
<fieldset class="a4-field a4-field-wide">
<legend>{'Options of the handler'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-form is-wide a4-sqliimport-options">
{include uri='design:sqliimport/parts/option_fields.tpl' schema=$handlers[$handler_choice].schema values=$options prefix='HandlerOptions' errors=$errors idprefix='sq-ho'}
</div>
{* the AI assist (doc/ai.md): the data table that fits the source's fields, the best three; "Use this table" chooses
   it in the form, nothing is saved *}
{if and( $ai.available, eq( $handler_choice, 'datatable' ) )}
<div class="a4-sqliimport-ai" id="sq-ai" tabindex="-1">
<div class="a4-toolbar">
<button type="submit" class="a4-btn a4-btn-small" name="AiTableButton" value="1"><span class="a4-icon a4-bi-stars" aria-hidden="true"></span> {'Suggest a data table with AI'|i18n( 'extension/sqliimport/edit' )}</button>
<span class="a4-meta">{if $ai.samples}{'Sends the field names and types and masked sample values.'|i18n( 'extension/sqliimport/edit' )}{else}{'Sends only the field names and types, no values.'|i18n( 'extension/sqliimport/edit' )}{/if}</span>
</div>
{if $ai.classes|count|gt( 0 )}
<ol class="a4-sqliimport-ai-list" aria-label="{'Data tables the AI suggests'|i18n( 'extension/sqliimport/edit' )|wash}">
{foreach $ai.classes as $sq_at}
<li><code>{$sq_at.identifier|wash}</code> <span class="a4-meta">{$sq_at.name|wash}</span> <span class="a4-badge {$sq_at.kind}" title="{'Confidence of the AI'|i18n( 'extension/sqliimport/edit' )|wash}">{$sq_at.percent}%</span>
{if ne( $sq_at.reason, '' )}<span class="a4-meta">{$sq_at.reason|wash}</span>{/if}
{if and( is_set( $options.class ), eq( $options.class, $sq_at.identifier ) )}<span class="a4-tag is-accent">{'In the form'|i18n( 'extension/sqliimport/edit' )}</span>{else}<button type="submit" class="a4-btn a4-btn-small a4-btn-ghost" name="AiUseTableButton" value="{$sq_at.identifier|wash}">{'Use this table'|i18n( 'extension/sqliimport/edit' )}</button>{/if}</li>
{/foreach}
</ol>
{/if}
</div>
{/if}
</fieldset>
{/if}
{/if}

<fieldset class="a4-field a4-field-wide">
<legend>{'Target'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-form is-wide a4-sqliimport-options">
<div class="a4-field">
<label for="sq-parent">{'Parent node of new content'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-parent" name="ParentNodeID" value="{$target.parent_node_id|wash}" min="0"{if is_set( $errors.ParentNodeID )} aria-invalid="true"{/if} />
<span class="a4-help">{'The node ID (a mapping can name another parent per record).'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field">
<label for="sq-section">{'Section'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-section" name="SectionID" value="{$target.section_id|wash}" min="0" />
<span class="a4-help">{'0: the section of the parent.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field">
<label for="sq-language">{'Language'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-language" name="Language">
<option value="">{'The default language of the site'|i18n( 'extension/sqliimport/edit' )}</option>
{foreach $languages as $l}<option value="{$l.locale|wash}"{if eq( $target.language, $l.locale )} selected="selected"{/if}>{$l.name|wash} ({$l.locale|wash})</option>{/foreach}
</select>
</div>
{if $can_settings}
<div class="a4-field">
<label for="sq-owner">{'Run as user (robot)'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-owner" name="OwnerID" value="{$target.owner_id|wash}" min="0" />
<span class="a4-help">{'A user ID; 0: the user who starts or schedules the run. Its permissions apply.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
{/if}
</div>
</fieldset>

<fieldset class="a4-field a4-field-wide">
<legend>{'Runs'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-form is-wide a4-sqliimport-options">
<div class="a4-field">
<label for="sq-chunk">{'Records per chunk'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-chunk" name="ChunkSize" value="{$behaviour.chunk_size|wash}" min="1" max="5000" />
</div>
<div class="a4-field">
<label for="sq-policy">{'Existing content'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-policy" name="UpdatePolicy">
<option value="update"{if eq( $behaviour.update_policy, 'update' )} selected="selected"{/if}>{'Create new, update existing'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="create_only"{if eq( $behaviour.update_policy, 'create_only' )} selected="selected"{/if}>{'Only create, leave existing as they are'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="update_only"{if eq( $behaviour.update_policy, 'update_only' )} selected="selected"{/if}>{'Only update existing'|i18n( 'extension/sqliimport/edit' )}</option>
</select>
</div>
<div class="a4-field">
<label for="sq-conflict">{'When someone edited an object since the last run'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-conflict" name="ConflictPolicy">
<option value="overwrite"{if eq( $behaviour.conflict_policy, 'overwrite' )} selected="selected"{/if}>{'Overwrite with the source'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="merge"{if eq( $behaviour.conflict_policy, 'merge' )} selected="selected"{/if}>{'Merge: keep the fields that were edited'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="fill_empty"{if eq( $behaviour.conflict_policy, 'fill_empty' )} selected="selected"{/if}>{'Only fill empty fields'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="skip"{if eq( $behaviour.conflict_policy, 'skip' )} selected="selected"{/if}>{'Skip the object'|i18n( 'extension/sqliimport/edit' )}</option>
</select>
</div>
<div class="a4-field">
<label for="sq-undo">{'Days a run can be undone'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-undo" name="UndoDays" value="{$behaviour.undo_days|wash}" min="0" max="3650"{if is_set( $errors.UndoDays )} aria-invalid="true"{/if} />
<span class="a4-help">{'Empty: the setting ([RunSettings] UndoDays, %days days). 0: no undo.'|i18n( 'extension/sqliimport/edit',, hash( '%days', $undo_days_default ) )}</span>
</div>
<div class="a4-field">
<label for="sq-max">{'At most this many records per run'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-max" name="MaxRows" value="{$behaviour.max_rows|wash}" min="0" />
<span class="a4-help">{'0: no limit.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
</div>
</fieldset>

<fieldset class="a4-field a4-field-wide">
<legend>{'Notifications'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-form is-wide a4-sqliimport-options">
<div class="a4-field">
<label for="sq-notify-when">{'Tell when a run'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-notify-when" name="NotifyWhen">
<option value="errors"{if eq( $behaviour.notify_when, 'errors' )} selected="selected"{/if}>{'failed or had errors'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="failed"{if eq( $behaviour.notify_when, 'failed' )} selected="selected"{/if}>{'failed'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="always"{if eq( $behaviour.notify_when, 'always' )} selected="selected"{/if}>{'ended (every run)'|i18n( 'extension/sqliimport/edit' )}</option>
<option value="never"{if eq( $behaviour.notify_when, 'never' )} selected="selected"{/if}>{'never'|i18n( 'extension/sqliimport/edit' )}</option>
</select>
</div>
<div class="a4-field">
<label class="a4-check" for="sq-notify-starter"><input type="hidden" name="NotifyStarter" value="0" /><input type="checkbox" id="sq-notify-starter" name="NotifyStarter" value="1"{if $behaviour.notify_starter} checked="checked"{/if} /> {'A notice for the user who started the run'|i18n( 'extension/sqliimport/edit' )}</label>
<span class="a4-help">{'Shown on the Imports dashboard until the run is looked at.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field a4-field-wide">
<label for="sq-notify-emails">{'E-mail addresses'|i18n( 'extension/sqliimport/edit' )}</label>
<textarea id="sq-notify-emails" name="NotifyEmails" rows="2"{if is_set( $errors.NotifyEmails )} aria-invalid="true"{/if}>{$behaviour.notify_emails|implode( "\n" )|wash}</textarea>
<span class="a4-help">{'One per line; they get a mail with the counts, the first errors and the link to the run. Dry runs send no mail.'|i18n( 'extension/sqliimport/edit' )}</span>
{if is_set( $errors.NotifyEmails )}<span class="a4-field-error">{$errors.NotifyEmails|wash}</span>{/if}
</div>
</div>
</fieldset>

{if $ai.available}
<fieldset class="a4-field a4-field-wide">
<legend>{'AI assist'|i18n( 'extension/sqliimport/edit' )}</legend>
<input type="hidden" name="AiOptionShown" value="1" />
<label class="a4-check" for="sq-ai-samples"><input type="checkbox" id="sq-ai-samples" name="AiSendSamples" value="1"{if and( is_set( $behaviour.ai_samples ), $behaviour.ai_samples )} checked="checked"{/if} aria-describedby="sq-ai-samples-help" /> {'Send sample values to the AI'|i18n( 'extension/sqliimport/edit' )}</label>
<span class="a4-help" id="sq-ai-samples-help">{'Off: the AI gets only the names and types of the fields. On: also a few sample values per field, and the values of a failed row it explains; e-mail addresses, phone numbers and IBAN-like values are masked before they are sent.'|i18n( 'extension/sqliimport/edit' )}</span>
</fieldset>
{/if}
{foreach $ai.accepted as $sq_pa}<input type="hidden" name="AiAccepted[]" value="{$sq_pa|wash}" />{/foreach}
{include uri='design:sqliimport/edit/buttons.tpl' step='processing' stay=1}
</form>
