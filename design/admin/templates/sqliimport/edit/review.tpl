{* Editor step "review": the summary of the import, what is still missing, and Save (enables it) or Save and run;
   then Download as JSON, Copy to … and Save as template (parts/import_transfer_forms.tpl). *}
{def $sq_rv_src = $import.source}
<dl class="a4-facts is-rows a4-sqliimport-facts">
<dt>{'Name'|i18n( 'extension/sqliimport/edit' )}</dt><dd>{$import.name|wash} <span class="a4-meta">{$import.identifier|wash}</span></dd>
<dt>{'Source'|i18n( 'extension/sqliimport/edit' )}</dt><dd>{if $import.is_legacy}{'1.x handler'|i18n( 'extension/sqliimport' )}{else}{$import.source_type|upcase|wash} · {if is_set( $sq_rv_src.origin )}{if eq( $sq_rv_src.origin, 'url' )}{$sq_rv_src.url|wash}{elseif eq( $sq_rv_src.origin, 'path' )}{$sq_rv_src.path|wash}{else}{first_set( $sq_rv_src.original_name, $sq_rv_src.file, '' )|wash}{/if}{/if}{/if}</dd>
<dt>{'Processing'|i18n( 'extension/sqliimport/edit' )}</dt><dd>{if eq( $import.handler, 'mapping' )}{'Mapping to %class'|i18n( 'extension/sqliimport/edit',, hash( '%class', $mapping.class ) )|wash} ({'%n fields'|i18n( 'extension/sqliimport',, hash( '%n', $mapping.fields|count ) )}){else}{$import.handler|wash}{/if}</dd>
<dt>{'Target'|i18n( 'extension/sqliimport/edit' )}</dt><dd>{'node %node'|i18n( 'extension/sqliimport/edit',, hash( '%node', $target.parent_node_id ) )}{if ne( $target.language, '' )} · {$target.language|wash}{/if}{if gt( $target.owner_id, 0 )} · {'runs as user %user'|i18n( 'extension/sqliimport/edit',, hash( '%user', $target.owner_id ) )}{/if}</dd>
<dt>{'Schedule'|i18n( 'extension/sqliimport/edit' )}</dt><dd>{if eq( $schedule.kind, 'none' )}{'none'|i18n( 'extension/sqliimport/edit' )}{else}{foreach $import.schedules as $s}{$s.description|wash}{if $s.is_active|not} ({'paused'|i18n( 'extension/sqliimport/edit' )}){/if}{/foreach}{/if}</dd>
</dl>
{undef $sq_rv_src}

{if $problems|count|gt( 0 )}
<div class="a4-notice is-warn" role="status">
<p class="a4-notice-title">{'Before the import can run:'|i18n( 'extension/sqliimport/edit' )}</p>
<ul>{foreach $problems as $p}<li>{$p|wash}</li>{/foreach}</ul>
</div>
{/if}

<form class="a4-form" method="post" action={concat( $base, '/(step)/review' )|ezurl}>
<div class="a4-field">
<label class="a4-check"><input type="checkbox" name="Enabled" value="1"{if or( $import.is_enabled, $problems|count|eq( 0 ) )} checked="checked"{/if} /> {'The import is enabled (it can run and its schedule runs it)'|i18n( 'extension/sqliimport/edit' )}</label>
</div>
{include uri='design:sqliimport/edit/buttons.tpl' step='review' last=1}
</form>

{if $import}
<section class="a4-section" aria-labelledby="sq-rv-share">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-rv-share">{'Export, copy or keep as a template'|i18n( 'extension/sqliimport/edit' )}</h2></div>
<p class="a4-help">{'The JSON file moves this import to another installation (Imports, Import definitions there): no source file, no API key, the names of its secrets only.'|i18n( 'extension/sqliimport/edit' )}</p>
<div class="a4-sqliimport-more-body">{include uri='design:sqliimport/parts/import_transfer_forms.tpl' import=$import here=concat( $base, '/(step)/review' ) sq_where='review'}</div>
</section>
{/if}
