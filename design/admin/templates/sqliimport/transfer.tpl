{* sqliimport/transfer: "Import definitions". Upload a definitions file; then, per import of the file, what it would
   create on this site (new or a taken identifier, the class and parent node it resolves to, the secrets, what is
   missing here) and the choice create new, replace or skip; Create makes the chosen ones disabled. Shared components
   only (.a4-card, .a4-facts, .a4-notice, .a4-field, .a4-toolbar); no inline CSS or script. *}
<div class="context-block a4-page a4-sqliimport">
<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Import definitions'|i18n( 'extension/sqliimport' )}</h1>
</div></div>
<div class="box-bc"><div class="box-ml"><div class="box-content">
{include uri='design:sqliimport/parts/flash.tpl' flash=$flash}
<p class="a4-intro">{'A definitions file comes from "Download as JSON" of an import or "Export selected" of the dashboard, here or on another installation. Nothing is created before you choose; what is created is disabled, its schedules paused and its trigger switched off, so you review it, run a dry run and enable it.'|i18n( 'extension/sqliimport' )}</p>

{if $errors|count|gt( 0 )}
<div class="a4-notice is-bad a4-error-summary" id="a4-error-summary" role="alert" tabindex="-1">
<p class="a4-notice-title">{'Please check these points:'|i18n( 'extension/sqliimport/edit' )}</p>
<ul>{foreach $errors as $field => $message}<li>{if eq( $field, 'DefinitionsFile' )}<a href="#sq-defs-file">{$message|wash}</a>{else}{$message|wash}{/if}</li>{/foreach}</ul>
</div>
{/if}

{if $plan|count|eq( 0 )}
<section class="a4-section" aria-labelledby="sq-tr-upload">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-tr-upload">{'Definitions file'|i18n( 'extension/sqliimport' )}</h2></div>
<form class="a4-form" method="post" action={'/sqliimport/transfer'|ezurl} enctype="multipart/form-data">
<div class="a4-field">
<label for="sq-defs-file">{'File (JSON)'|i18n( 'extension/sqliimport' )}</label>
<input type="file" id="sq-defs-file" name="DefinitionsFile" accept=".json,application/json" required="required"{if is_set( $errors.DefinitionsFile )} aria-invalid="true" aria-describedby="sq-defs-file-error"{else} aria-describedby="sq-defs-file-help"{/if} />
{if is_set( $errors.DefinitionsFile )}<span class="a4-field-error" id="sq-defs-file-error">{$errors.DefinitionsFile|wash}</span>{/if}
<span class="a4-help" id="sq-defs-file-help">{'At most %n MB, format sqliimport-definitions (version 1 or 2). Source files are not part of it: upload them again in the editor.'|i18n( 'extension/sqliimport',, hash( '%n', $max_megabytes ) )}</span>
</div>
<div class="a4-toolbar"><button type="submit" class="a4-btn a4-btn-primary" name="UploadButton" value="1"><span class="a4-icon a4-bi-upload" aria-hidden="true"></span> {'Read the file'|i18n( 'extension/sqliimport' )}</button>
<a class="a4-btn a4-btn-ghost" href={'/sqliimport/templates'|ezurl}>{'Templates'|i18n( 'extension/sqliimport' )}</a></div>
</form>
</section>
{else}
<section class="a4-section" aria-labelledby="sq-tr-plan">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-tr-plan">{'What the file would create'|i18n( 'extension/sqliimport' )}</h2>
<span class="a4-meta">{$pending.original_name|wash} · {'%n imports'|i18n( 'extension/sqliimport',, hash( '%n', $plan|count ) )}</span></div>
<form class="a4-form" method="post" action={'/sqliimport/transfer'|ezurl}>
<input type="hidden" name="TransferFile" value="{$pending.file|wash}" />
<div class="a4-sqliimport-plan">
{foreach $plan as $sq_e}
<article class="a4-card" aria-labelledby="sq-tr-{$sq_e.index}">
<div class="a4-card-head">
<h3 class="a4-h3" id="sq-tr-{$sq_e.index}">{$sq_e.name|wash} <span class="a4-meta">{$sq_e.identifier|wash}</span></h3>
{if $sq_e.errors|count|gt( 0 )}<span class="a4-badge is-bad">{'cannot be created'|i18n( 'extension/sqliimport' )}</span>
{elseif $sq_e.existing_id}<span class="a4-badge is-warn">{'identifier taken'|i18n( 'extension/sqliimport' )}</span>
{else}<span class="a4-badge is-ok">{'new'|i18n( 'extension/sqliimport' )}</span>{/if}
</div>
<dl class="a4-facts is-rows a4-sqliimport-facts">
<dt>{'Source'|i18n( 'extension/sqliimport' )}</dt><dd>{$sq_e.source_type|upcase|wash} · {if eq( $sq_e.handler, 'mapping' )}{'Mapping'|i18n( 'extension/sqliimport' )}{else}{$sq_e.handler|wash}{/if}</dd>
{if ne( $sq_e.class, '' )}<dt>{'Class'|i18n( 'extension/sqliimport' )}</dt><dd><code>{$sq_e.class|wash}</code>{if $sq_e.class_found|not} <span class="a4-badge is-warn">{'not on this site'|i18n( 'extension/sqliimport' )}</span>{/if}</dd>{/if}
<dt>{'Parent node'|i18n( 'extension/sqliimport' )}</dt><dd>{if gt( $sq_e.parent_node_id, 0 )}<a href={concat( '/content/view/full/', $sq_e.parent_node_id )|ezurl}>#{$sq_e.parent_node_id}</a>{else}<span class="a4-meta">{'from the mapping'|i18n( 'extension/sqliimport' )}</span>{/if}</dd>
{if $sq_e.existing_id}<dt>{'Existing import'|i18n( 'extension/sqliimport' )}</dt><dd><a href={concat( '/sqliimport/edit/', $sq_e.existing_id, '/(step)/review' )|ezurl}>{$sq_e.identifier|wash}</a> · {'a new one would be %identifier'|i18n( 'extension/sqliimport',, hash( '%identifier', $sq_e.new_identifier ) )|wash}</dd>{/if}
{if or( gt( $sq_e.schedules, 0 ), $sq_e.trigger )}<dt>{'Schedules and trigger'|i18n( 'extension/sqliimport' )}</dt><dd>{'%n schedules (paused)'|i18n( 'extension/sqliimport',, hash( '%n', $sq_e.schedules ) )}{if $sq_e.trigger} · {'trigger switched off, no keys'|i18n( 'extension/sqliimport' )}{/if}</dd>{/if}
{if $sq_e.secrets|count|gt( 0 )}<dt>{'Secrets'|i18n( 'extension/sqliimport' )}</dt><dd>{foreach $sq_e.secrets as $sq_name => $sq_set}<code>{$sq_name|wash}</code> {if $sq_set}<span class="a4-badge is-ok">{'set'|i18n( 'extension/sqliimport' )}</span>{else}<span class="a4-badge is-warn">{'missing'|i18n( 'extension/sqliimport' )}</span>{/if}{delimiter} · {/delimiter}{/foreach}</dd>{/if}
</dl>
{if $sq_e.errors|count|gt( 0 )}<div class="a4-notice is-bad" role="note"><ul>{foreach $sq_e.errors as $sq_m}<li>{$sq_m|wash}</li>{/foreach}</ul></div>{/if}
{if $sq_e.warnings|count|gt( 0 )}<div class="a4-notice is-warn" role="note"><p class="a4-notice-title">{'To check in the editor:'|i18n( 'extension/sqliimport' )}</p><ul>{foreach $sq_e.warnings as $sq_m}<li>{$sq_m|wash}</li>{/foreach}</ul></div>{/if}
<fieldset class="a4-field">
<legend>{'What to do'|i18n( 'extension/sqliimport' )}<span class="a4-sr-only"> {$sq_e.name|wash}</span></legend>
{if $sq_e.choices|contains( 'new' )}<label class="a4-check"><input type="radio" name="Choice[{$sq_e.index}]" value="new"{if eq( $sq_e.choice, 'new' )} checked="checked"{/if} /> {if $sq_e.existing_id}{'Create a new import (%identifier)'|i18n( 'extension/sqliimport',, hash( '%identifier', $sq_e.new_identifier ) )|wash}{else}{'Create it'|i18n( 'extension/sqliimport' )}{/if}</label>{/if}
{if $sq_e.choices|contains( 'replace' )}<label class="a4-check"><input type="radio" name="Choice[{$sq_e.index}]" value="replace"{if eq( $sq_e.choice, 'replace' )} checked="checked"{/if} /> {'Replace the existing import (it is disabled; its runs and API keys stay)'|i18n( 'extension/sqliimport' )}</label>{/if}
<label class="a4-check"><input type="radio" name="Choice[{$sq_e.index}]" value="skip"{if eq( $sq_e.choice, 'skip' )} checked="checked"{/if} /> {'Skip it'|i18n( 'extension/sqliimport' )}</label>
</fieldset>
</article>
{/foreach}
</div>
<div class="a4-toolbar">
<button type="submit" class="a4-btn a4-btn-primary" name="ApplyButton" value="1">{'Create the chosen imports (disabled)'|i18n( 'extension/sqliimport' )}</button>
<button type="submit" class="a4-btn" name="CancelButton" value="1" formnovalidate="formnovalidate">{'Cancel'|i18n( 'extension/sqliimport' )}</button>
</div>
</form>
</section>
{/if}
</div></div></div>
</div>
