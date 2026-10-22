{* sqliimport/run/<id>: one run of an import. Its status and progress, the counters per outcome, Cancel and Retry
   failed rows, the records filtered by outcome (with the changed fields of a dry run), and the run's log. Shared
   components only; no inline CSS or script. *}
<div class="context-block a4-page a4-sqliimport">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Run #%id'|i18n( 'extension/sqliimport',, hash( '%id', $run.id ) )} · {$run.import_name|wash}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

{include uri='design:sqliimport/parts/flash.tpl' flash=$flash}

<div class="a4-toolbar a4-sqliimport-toolbar">
{include uri='design:sqliimport/parts/status.tpl' label=$run.status_label kind=$run.status_kind}
<span class="a4-meta">{$run.kind_label|wash} · {$run.trigger_label|wash}{if ne( $run.user, '' )} · {$run.user|wash}{/if}{if $run.parent_run_id} · {'retry of'|i18n( 'extension/sqliimport' )} <a href={concat( '/sqliimport/run/', $run.parent_run_id )|ezurl}>#{$run.parent_run_id}</a>{/if}</span>
{if $can_run}
{if $run.is_final|not}{include uri='design:sqliimport/parts/button.tpl' action='cancel' run=$run.id here=$here label='Cancel'|i18n( 'extension/sqliimport' ) kind='danger' icon='x-circle-fill' confirm='Cancel this run? Records already processed stay as they are.'|i18n( 'extension/sqliimport' )}{/if}
{if $can_retry}{include uri='design:sqliimport/parts/button.tpl' action='retry' run=$run.id here=$here label='Retry failed rows'|i18n( 'extension/sqliimport' ) kind='primary' icon='arrow-clockwise'}{/if}
{/if}
<a class="a4-btn a4-btn-ghost" href={$here|ezurl}><span class="a4-icon a4-bi-arrow-clockwise" aria-hidden="true"></span> {'Refresh'|i18n( 'extension/sqliimport' )}</a>
{if ne( $run.queue_group, '' )}<a class="a4-btn a4-btn-ghost" href={concat( '/expqueue/group/', $run.queue_group )|ezurl}>{'Jobs of this run'|i18n( 'extension/sqliimport' )}</a>{/if}
</div>

<div class="a4-meter{if eq( $run.status, 'failed' )} is-bad{elseif eq( $run.status, 'completed_with_errors' )} is-warn{elseif eq( $run.status, 'completed' )} is-ok{/if} a4-sqliimport-meter is-large" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{$run.percent}" aria-label="{'Progress'|i18n( 'extension/sqliimport' )|wash}"><span class="a4-pct-{$run.percent}"></span></div>
<p class="a4-meta">{'%done of %total records, %percent%'|i18n( 'extension/sqliimport',, hash( '%done', $run.done, '%total', cond( gt( $run.total, 0 ), $run.total, '?' ), '%percent', $run.percent ) )} · {'duration %d'|i18n( 'extension/sqliimport',, hash( '%d', $run.duration ) )}{if gt( $run.memory, 0 )} · {'memory peak %m MB'|i18n( 'extension/sqliimport',, hash( '%m', $run.memory ) )}{/if}</p>

<ul class="a4-figures" aria-label="{'Outcomes'|i18n( 'extension/sqliimport' )|wash}">
    <li class="a4-figure is-ok"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/created' )|ezurl}><strong>{$run.created}</strong><span>{'Created'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/updated' )|ezurl}><strong>{$run.updated}</strong><span>{'Updated'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/unchanged' )|ezurl}><strong>{$run.unchanged}</strong><span>{'Unchanged'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/skipped' )|ezurl}><strong>{$run.skipped}</strong><span>{'Skipped'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure{if gt( $run.failed, 0 )} is-bad{/if}"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/failed' )|ezurl}><strong>{$run.failed}</strong><span>{'Failed'|i18n( 'extension/sqliimport' )}</span></a></li>
    {if gt( $run.processed, 0 )}<li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/processed' )|ezurl}><strong>{$run.processed}</strong><span>{'Processed (1.x)'|i18n( 'extension/sqliimport' )}</span></a></li>{/if}
</ul>

<section class="a4-section" aria-labelledby="a4-sqliimport-rows-title">
<div class="a4-section-head">
<h2 class="a4-h2" id="a4-sqliimport-rows-title">{'Records'|i18n( 'extension/sqliimport' )}</h2>
{if gt( $row_count, 0 )}<span class="a4-meta">{'%from to %to of %count'|i18n( 'extension/sqliimport',, hash( '%from', sum( $offset, 1 ), '%to', sum( $offset, $rows|count ), '%count', $row_count ) )}</span>{/if}
</div>
<ul class="a4-badges a4-sqliimport-filters" aria-label="{'Outcome'|i18n( 'extension/sqliimport' )|wash}">
{foreach $outcome_links as $l}
<li><a class="a4-tag{if $l.current} is-accent{/if}" href={$l.url|ezurl}{if $l.current} aria-current="true"{/if}>{if eq( $l.name, '' )}{'all'|i18n( 'extension/sqliimport' )}{else}{$l.label|wash}{/if}</a></li>
{/foreach}
</ul>
{if $rows|count|eq( 0 )}
<p class="a4-empty">{'No record matches.'|i18n( 'extension/sqliimport' )}</p>
{else}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="a4-sqliimport-rows-title">
<table class="a4-table is-compact">
<thead><tr>
<th>{'Record'|i18n( 'extension/sqliimport' )}</th>
<th>{'Outcome'|i18n( 'extension/sqliimport' )}</th>
<th>{'Object'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-sqliimport-hide-narrow">{'Version'|i18n( 'extension/sqliimport' )}</th>
<th>{'Message'|i18n( 'extension/sqliimport' )}</th>
</tr></thead>
<tbody>
{foreach $rows as $row}
<tr>
<td class="a4-nowrap">{$row.ref|wash}</td>
<td><span class="a4-badge{if eq( $row.outcome, 'failed' )} is-bad{elseif eq( $row.outcome, 'created' )} is-ok{elseif eq( $row.outcome, 'updated' )} is-info{/if}">{$row.outcome_label|wash}</span></td>
<td>{if gt( $row.object_id, 0 )}<a href={concat( '/content/view/full/', fetch( 'content', 'object', hash( 'object_id', $row.object_id ) ).main_node_id )|ezurl}>#{$row.object_id}</a>{if ne( $row.remote_id, '' )} <span class="a4-meta">{$row.remote_id|wash}</span>{/if}{else}<span class="a4-meta">–</span>{/if}</td>
<td class="a4-sqliimport-hide-narrow a4-nowrap">{if gt( $row.version_after, 0 )}{if and( gt( $row.version_before, 0 ), ne( $row.version_before, $row.version_after ) )}{$row.version_before} → {/if}{$row.version_after}{/if}</td>
<td>{$row.message|wash}
{if $row.diff|count|gt( 0 )}
<details class="a4-fold a4-sqliimport-diff"><summary>{'%n fields'|i18n( 'extension/sqliimport',, hash( '%n', $row.diff|count ) )}</summary>
<table class="a4-table is-compact"><thead><tr><th>{'Field'|i18n( 'extension/sqliimport' )}</th><th>{'Now'|i18n( 'extension/sqliimport' )}</th><th>{'New'|i18n( 'extension/sqliimport' )}</th></tr></thead>
<tbody>{foreach $row.diff as $field => $pair}<tr><td>{$field|wash}</td><td>{$pair[0]|shorten( 120 )|wash}</td><td>{$pair[1]|shorten( 120 )|wash}</td></tr>{/foreach}</tbody></table>
</details>
{/if}
</td>
</tr>
{/foreach}
</tbody>
</table>
</div>
<p class="a4-row a4-pager">
{if ne( $prev_url, '' )}<a class="a4-btn a4-btn-small" href={$prev_url|ezurl}>{'Previous'|i18n( 'extension/sqliimport' )}</a>{/if}
{if ne( $next_url, '' )}<a class="a4-btn a4-btn-small" href={$next_url|ezurl}>{'Next'|i18n( 'extension/sqliimport' )}</a>{/if}
</p>
{/if}
</section>

<section class="a4-section" aria-labelledby="a4-sqliimport-log-title">
<div class="a4-section-head"><h2 class="a4-h2" id="a4-sqliimport-log-title">{'Log'|i18n( 'extension/sqliimport' )}</h2></div>
{if $log|count|eq( 0 )}
<p class="a4-empty">{'Nothing logged yet.'|i18n( 'extension/sqliimport' )}</p>
{else}
<ol class="a4-timeline a4-sqliimport-log">
{foreach $log as $line}
<li class="{if eq( $line.severity, 'error' )}is-bad{elseif eq( $line.severity, 'warning' )}is-warn{elseif eq( $line.severity, 'notice' )}is-info{else}is-ok{/if}">
<span class="a4-timeline-time">{$line.time|l10n( 'shortdatetime' )}</span>
<span class="a4-timeline-body"><span class="a4-badge{if eq( $line.severity, 'error' )} is-bad{elseif eq( $line.severity, 'warning' )} is-warn{/if}">{$line.severity|wash}</span>{if ne( $line.ref, '' )} <span class="a4-meta">{'record %r'|i18n( 'extension/sqliimport',, hash( '%r', $line.ref ) )|wash}</span>{/if} <span>{$line.message|wash}</span></span>
</li>
{/foreach}
</ol>
{/if}
</section>

</div></div></div>

</div>
