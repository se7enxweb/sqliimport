{* sqliimport/templates: the templates of imports (this site's and the ones extensions ship), filtered by format, and
   "New import from a template" ((use)/<id>): the placeholders, what the template needs on this site, Create.
   Shared components only; no inline CSS or script. *}
<div class="context-block a4-page a4-sqliimport">
<div class="box-header"><div class="box-ml">
<h1 class="context-title">{if $use}{'New import from the template "%title"'|i18n( 'extension/sqliimport',, hash( '%title', $use.title ) )|wash}{else}{'Templates'|i18n( 'extension/sqliimport' )}{/if}</h1>
</div></div>
<div class="box-bc"><div class="box-ml"><div class="box-content">
{include uri='design:sqliimport/parts/flash.tpl' flash=$flash}

{if $use}
{if ne( $use.description, '' )}<p class="a4-intro">{$use.description|wash}</p>{/if}
{if $errors|count|gt( 0 )}
<div class="a4-notice is-bad a4-error-summary" id="a4-error-summary" role="alert" tabindex="-1">
<p class="a4-notice-title">{'Please check these points:'|i18n( 'extension/sqliimport/edit' )}</p>
<ul>{foreach $errors as $field => $message}<li>{if is_set( $use.placeholders[$field] )}<a href="#sq-ph-{$field|wash}">{$message|wash}</a>{else}{$message|wash}{/if}</li>{/foreach}</ul>
</div>
{/if}
<dl class="a4-facts is-rows a4-sqliimport-facts">
<dt>{'Format'|i18n( 'extension/sqliimport' )}</dt><dd>{if is_set( $source_labels[$use.source_type] )}{$source_labels[$use.source_type]|wash}{else}{$use.source_type|wash}{/if} <code>{$use.source_type|wash}</code></dd>
<dt>{'From'|i18n( 'extension/sqliimport' )}</dt><dd>{if eq( $use.origin, 'site' )}{'this site'|i18n( 'extension/sqliimport' )}{else}{'the extension %name'|i18n( 'extension/sqliimport',, hash( '%name', $use.origin ) )|wash}{/if}</dd>
{if $check}
{if ne( $check.class, '' )}<dt>{'Class'|i18n( 'extension/sqliimport' )}</dt><dd><code>{$check.class|wash}</code>{if $check.class_found|not} <span class="a4-badge is-warn">{'not on this site'|i18n( 'extension/sqliimport' )}</span>{/if}</dd>{/if}
{if $check.secrets|count|gt( 0 )}<dt>{'Secrets'|i18n( 'extension/sqliimport' )}</dt><dd>{foreach $check.secrets as $name => $set}<code>{$name|wash}</code> {if $set}<span class="a4-badge is-ok">{'set'|i18n( 'extension/sqliimport' )}</span>{else}<span class="a4-badge is-warn">{'missing'|i18n( 'extension/sqliimport' )}</span>{/if}{delimiter} · {/delimiter}{/foreach}</dd>{/if}
{/if}
</dl>
{if $check}{if $check.warnings|count|gt( 0 )}<div class="a4-notice is-warn" role="note"><p class="a4-notice-title">{'To check in the editor after it is made:'|i18n( 'extension/sqliimport' )}</p><ul>{foreach $check.warnings as $m}<li>{$m|wash}</li>{/foreach}</ul></div>{/if}{/if}

<form class="a4-form" method="post" action={concat( '/sqliimport/templates/(use)/', $use.id )|ezurl}>
<input type="hidden" name="TemplateID" value="{$use.id|wash}" />
{foreach $use.placeholders as $name => $p}
<div class="a4-field">
<label for="sq-ph-{$name|wash}">{$p.label|wash}{if $p.required} <span aria-hidden="true">*</span>{/if}</label>
<input type="text" id="sq-ph-{$name|wash}" name="Placeholders[{$name|wash}]" value="{$values[$name]|wash}"{if $p.required} required="required"{/if} maxlength="{if eq( $name, 'identifier' )}100{else}1000{/if}"{if is_set( $errors[$name] )} aria-invalid="true" aria-describedby="sq-ph-{$name|wash}-error"{elseif ne( $p.help, '' )} aria-describedby="sq-ph-{$name|wash}-help"{/if} />
{if is_set( $errors[$name] )}<span class="a4-field-error" id="sq-ph-{$name|wash}-error">{$errors[$name]|wash}</span>{/if}
{if ne( $p.help, '' )}<span class="a4-help" id="sq-ph-{$name|wash}-help">{$p.help|wash}</span>{/if}
</div>
{/foreach}
<p class="a4-help">{'The import is created disabled and opens in the editor: choose its file or check its address there, then preview, dry-run and enable it.'|i18n( 'extension/sqliimport' )}</p>
<div class="a4-toolbar">
<button type="submit" class="a4-btn a4-btn-primary" name="CreateButton" value="1"><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'Create the import'|i18n( 'extension/sqliimport' )}</button>
<a class="a4-btn a4-btn-ghost" href={'/sqliimport/templates'|ezurl}>{'All templates'|i18n( 'extension/sqliimport' )}</a>
</div>
</form>

{else}
<p class="a4-intro">{'A template is an import without its source data, with placeholders for its name and identifier. Save one from an import (More, Save as template, or the last step of the editor); extensions ship their own (sqliimport.ini [TemplateSettings] Templates[]).'|i18n( 'extension/sqliimport' )}</p>
{if ne( $format, '' )}<ul class="a4-badges a4-sqliimport-filters"><li><a class="a4-tag is-accent" href={'/sqliimport/templates'|ezurl} aria-current="true">{'%format ×'|i18n( 'extension/sqliimport',, hash( '%format', $format_label ) )|wash}</a></li></ul>{/if}
{if $templates|count|eq( 0 )}
<div class="a4-empty">
<p><strong>{if ne( $format, '' )}{'No template for this format.'|i18n( 'extension/sqliimport' )}{else}{'No template yet.'|i18n( 'extension/sqliimport' )}{/if}</strong></p>
<p><a class="a4-btn" href={'/sqliimport/edit/new'|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'New import'|i18n( 'extension/sqliimport' )}</a></p>
</div>
{else}
<div class="a4-sqliimport-formats">
{foreach $templates as $id => $t}
<article class="a4-card" aria-labelledby="sq-tpl-{$id|wash}">
<div class="a4-card-head">
<h2 class="a4-h3" id="sq-tpl-{$id|wash}">{$t.title|wash}</h2>
<span class="a4-badge{if eq( $t.origin, 'site' )} is-info{/if}">{if eq( $t.origin, 'site' )}{'this site'|i18n( 'extension/sqliimport' )}{else}{$t.origin|wash}{/if}</span>
</div>
{if ne( $t.description, '' )}<p class="a4-sqliimport-format-desc">{$t.description|wash}</p>{/if}
<p class="a4-meta">{if is_set( $source_labels[$t.source_type] )}{$source_labels[$t.source_type]|wash}{else}{$t.source_type|wash}{/if}{if $t.secrets|count|gt( 0 )} · {'Secrets'|i18n( 'extension/sqliimport' )}: {foreach $t.secrets as $s}<code>{$s|wash}</code>{delimiter}, {/delimiter}{/foreach}{/if}</p>
<div class="a4-sqliimport-format-foot">
<a class="a4-btn a4-btn-primary" href={concat( '/sqliimport/templates/(use)/', $id )|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'Use'|i18n( 'extension/sqliimport' )}<span class="a4-sr-only"> {$t.title|wash}</span></a>
{if $t.deletable}{include uri='design:sqliimport/parts/template_delete.tpl' template_id=$id title=$t.title}{/if}
</div>
</article>
{/foreach}
</div>
{/if}
{/if}
</div></div></div>
</div>
