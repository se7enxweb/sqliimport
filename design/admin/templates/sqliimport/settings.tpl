{* sqliimport/settings: the settings as they stand, and the export and import of import definitions (JSON). *}
<div class="context-block a4-page a4-sqliimport">
<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Settings'|i18n( 'extension/sqliimport' )}</h1>
</div></div>
<div class="box-bc"><div class="box-ml"><div class="box-content">
{include uri='design:sqliimport/parts/flash.tpl' flash=$flash}

<section class="a4-section" aria-labelledby="sq-set-ini">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-set-ini">{'sqliimport.ini'|i18n( 'extension/sqliimport' )}</h2></div>
<p class="a4-help">{'Changed in settings/override/sqliimport.ini.append.php or a siteaccess (the manual, page Settings, describes each).'|i18n( 'extension/sqliimport' )}</p>
<table class="a4-table is-compact"><tbody>
{foreach $settings as $s}<tr><th scope="row"><code>{$s.name|wash}</code></th><td>{if is_array( $s.value )}{$s.value|implode( ', ' )|wash}{else}{$s.value|wash}{/if}</td></tr>{/foreach}
</tbody></table>
</section>

<section class="a4-section" aria-labelledby="sq-set-secrets">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-set-secrets">{'Secrets'|i18n( 'extension/sqliimport' )}</h2></div>
<p class="a4-help">{'The names an import can give as a password (database, mailbox). The values are set in [SecretSettings] Secrets[] of the settings of the site and are never shown; env:VARIABLE names an environment variable instead.'|i18n( 'extension/sqliimport' )}</p>
{if $secret_names|count|gt( 0 )}
<ul class="a4-sqliimport-format-list">{foreach $secret_names as $name}<li><code>{$name|wash}</code></li>{/foreach}</ul>
{else}
<p class="a4-meta">{'No secret is set.'|i18n( 'extension/sqliimport' )}</p>
{/if}
</section>

<section class="a4-section" aria-labelledby="sq-set-export">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-set-export">{'Export import definitions'|i18n( 'extension/sqliimport' )}</h2></div>
<form class="a4-form" method="post" action={'/sqliimport/settings'|ezurl}>
<fieldset class="a4-field">
<legend>{'Imports (none ticked: all)'|i18n( 'extension/sqliimport' )}</legend>
{foreach $imports as $i}<label class="a4-check"><input type="checkbox" name="ImportIDs[]" value="{$i.id}" /> {$i.name|wash} <span class="a4-meta">{$i.identifier|wash}</span></label>{/foreach}
</fieldset>
<p class="a4-help">{'A JSON file with the source, processing, mapping, target and options of each import; no run, schedule or file content.'|i18n( 'extension/sqliimport' )}</p>
<div class="a4-toolbar"><button type="submit" class="a4-btn" name="ExportButton" value="1"><span class="a4-icon a4-bi-download" aria-hidden="true"></span> {'Export'|i18n( 'extension/sqliimport' )}</button></div>
</form>
</section>

<section class="a4-section" aria-labelledby="sq-set-import">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-set-import">{'Import import definitions'|i18n( 'extension/sqliimport' )}</h2></div>
<form class="a4-form" method="post" action={'/sqliimport/settings'|ezurl} enctype="multipart/form-data">
<div class="a4-field">
<label for="sq-defs">{'Exported file'|i18n( 'extension/sqliimport' )}</label>
<input type="file" id="sq-defs" name="DefinitionsFile" accept=".json,application/json" />
<span class="a4-help">{'An import with the same identifier is updated; the others are created disabled. Uploaded source files are not part of an export: upload them again in the editor.'|i18n( 'extension/sqliimport' )}</span>
</div>
<div class="a4-toolbar"><button type="submit" class="a4-btn a4-btn-primary" name="ImportButton" value="1"><span class="a4-icon a4-bi-upload" aria-hidden="true"></span> {'Import'|i18n( 'extension/sqliimport' )}</button></div>
</form>
</section>
</div></div></div>
</div>
