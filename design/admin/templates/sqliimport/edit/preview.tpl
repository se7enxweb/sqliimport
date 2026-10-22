{* Editor step "preview": the sample of the source as a table, and a dry run of its first records (nothing is
   published): per record what would happen and the fields that would change. *}
{if ne( $sample_error, '' )}<p class="a4-notice is-bad" role="alert">{'The source could not be read: %error'|i18n( 'extension/sqliimport/edit',, hash( '%error', $sample_error ) )|wash}</p>{/if}
{if $sample}
<section class="a4-section" aria-labelledby="sq-sample-title">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-sample-title">{'Sample of the source'|i18n( 'extension/sqliimport/edit' )}</h2>
<span class="a4-meta">{foreach $sample.detected as $dk => $dv}{$dk|wash}: {$dv|wash}{delimiter} · {/delimiter}{/foreach}</span></div>
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="sq-sample-title">
<table class="a4-table is-compact">
<thead><tr><th>{'Record'|i18n( 'extension/sqliimport/edit' )}</th>{foreach $sample.columns as $c}<th>{$c|wash}</th>{/foreach}</tr></thead>
<tbody>
{foreach $sample.records as $r}<tr><td class="a4-nowrap">{$r.ref|wash}</td>{foreach $sample.columns as $c}<td>{if is_set( $r.flat[$c] )}{$r.flat[$c]|shorten( 80 )|wash}{/if}</td>{/foreach}</tr>{/foreach}
</tbody>
</table>
</div>
</section>
{/if}

<section class="a4-section" aria-labelledby="sq-preview-title">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-preview-title">{'Dry run of the first records'|i18n( 'extension/sqliimport/edit' )}</h2></div>
{if ne( $preview_error, '' )}
<p class="a4-notice is-bad" role="alert">{$preview_error|wash}</p>
{elseif $preview}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="sq-preview-title">
<table class="a4-table is-compact">
<thead><tr><th>{'Record'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Would be'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Object'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Changes'|i18n( 'extension/sqliimport/edit' )}</th></tr></thead>
<tbody>
{foreach $preview as $p}
<tr>
<td class="a4-nowrap">{$p.ref|wash}</td>
<td><span class="a4-badge{if eq( $p.outcome, 'failed' )} is-bad{elseif eq( $p.outcome, 'created' )} is-ok{elseif eq( $p.outcome, 'updated' )} is-info{/if}">{$p.outcome_label|wash}</span></td>
<td>{if gt( $p.object_id, 0 )}#{$p.object_id}{/if} <span class="a4-meta">{$p.remote_id|wash}</span></td>
<td>{if $p.diff|count|gt( 0 )}<ul class="a4-plain a4-sqliimport-changes">{foreach $p.diff as $field => $pair}<li><code>{$field|wash}</code>: <del>{$pair[0]|shorten( 60 )|wash}</del> <ins>{$pair[1]|shorten( 60 )|wash}</ins></li>{/foreach}</ul>{else}{$p.message|wash}{/if}</td>
</tr>
{/foreach}
</tbody>
</table>
</div>
{else}
<p class="a4-empty">{'The source has no records.'|i18n( 'extension/sqliimport/edit' )}</p>
{/if}
</section>

<form method="post" action={concat( $base, '/(step)/preview' )|ezurl}>
{include uri='design:sqliimport/edit/buttons.tpl' step='preview'}
</form>
