{* sqliimport/run/<id>: one run of an import. Its status and progress, the counters per outcome, Cancel and Retry
   failed rows, the records filtered by outcome (with the changed fields of a dry run), and the run's log. Shared
   components only; no inline CSS or script. *}
<div class="context-block a4-page a4-sqliimport"{if $run.is_final|not} data-exp-sqliimport-run='{ldelim}"run": {$run.id}, "log": {$last_log_id}{rdelim}'{/if}>

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Run #%id'|i18n( 'extension/sqliimport',, hash( '%id', $run.id ) )} · {$run.import_name|wash}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

{include uri='design:sqliimport/parts/flash.tpl' flash=$flash}

<div class="a4-toolbar a4-sqliimport-toolbar">
{include uri='design:sqliimport/parts/status.tpl' label=$run.status_label kind=$run.status_kind field='status_label'}
<span class="a4-meta">{$run.kind_label|wash} · {$run.trigger_label|wash}{if ne( $run.user, '' )} · {$run.user|wash}{/if}{if $run.parent_run_id} · {'retry of'|i18n( 'extension/sqliimport' )} <a href={concat( '/sqliimport/run/', $run.parent_run_id )|ezurl}>#{$run.parent_run_id}</a>{/if}</span>
{if $can_run}
{if $run.is_final|not}{include uri='design:sqliimport/parts/button.tpl' action='cancel' run_id=$run.id here=$here label='Cancel'|i18n( 'extension/sqliimport' ) kind='danger' icon='x-circle-fill' confirm='Cancel this run? Records already processed stay as they are.'|i18n( 'extension/sqliimport' )}{/if}
{if $can_retry}{include uri='design:sqliimport/parts/button.tpl' action='retry' run_id=$run.id here=$here label='Retry failed rows'|i18n( 'extension/sqliimport' ) kind='primary' icon='arrow-clockwise'}{/if}
{/if}
{if $can_undo}{include uri='design:sqliimport/parts/button.tpl' action='undo' run_id=$run.id here=$here label='Undo this run'|i18n( 'extension/sqliimport' ) kind='danger' icon='arrow-counterclockwise' confirm='Undo this run? What it created goes to the trash; what it updated gets the version before the run again, unless someone changed it since.'|i18n( 'extension/sqliimport' )}{/if}
{if and( $ai.available, $run.is_final )}
<form class="a4-sqliimport-inline" method="post" action={$here|ezurl}>
<button type="submit" class="a4-btn" name="AiSummaryButton" value="1"><span class="a4-icon a4-bi-stars" aria-hidden="true"></span> {'Summarize'|i18n( 'extension/sqliimport' )}</button>
</form>
{/if}
<a class="a4-btn a4-btn-ghost" href={$here|ezurl}><span class="a4-icon a4-bi-arrow-clockwise" aria-hidden="true"></span> {'Refresh'|i18n( 'extension/sqliimport' )}</a>
{if ne( $run.queue_group, '' )}<a class="a4-btn a4-btn-ghost" href={concat( '/expqueue/group/', $run.queue_group )|ezurl}>{'Jobs of this run'|i18n( 'extension/sqliimport' )}</a>{/if}
</div>
{if gt( $undone_by, 0 )}<p class="a4-notice is-info" role="status">{'This run was undone by run'|i18n( 'extension/sqliimport' )} <a href={concat( '/sqliimport/run/', $undone_by )|ezurl}>#{$undone_by}</a>.</p>
{elseif and( $run.is_final, $can_undo|not, $undo_refusal, or( gt( $run.created, 0 ), gt( $run.updated, 0 ) ), ne( $run.kind, 'undo' ) )}<p class="a4-meta">{'Undo: %reason'|i18n( 'extension/sqliimport',, hash( '%reason', $undo_refusal ) )|wash}</p>{/if}

<div class="a4-meter{if eq( $run.status, 'failed' )} is-bad{elseif eq( $run.status, 'completed_with_errors' )} is-warn{elseif eq( $run.status, 'completed' )} is-ok{/if} a4-sqliimport-meter is-large" data-sq-meter="1" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{$run.percent}" aria-label="{'Progress'|i18n( 'extension/sqliimport' )|wash}"><span class="a4-pct-{$run.percent}"></span></div>
<p class="a4-meta">{'%done of %total records, %percent%'|i18n( 'extension/sqliimport',, hash( '%done', concat( '<span data-sq-field="done">', $run.done, '</span>' ), '%total', concat( '<span data-sq-field="total">', cond( gt( $run.total, 0 ), $run.total, '?' ), '</span>' ), '%percent', concat( '<span data-sq-field="percent">', $run.percent, '</span>' ) ) )} · {'duration %d'|i18n( 'extension/sqliimport',, hash( '%d', concat( '<span data-sq-field="duration">', $run.duration|wash, '</span>' ) ) )}{if gt( $run.memory, 0 )} · {'memory peak %m MB'|i18n( 'extension/sqliimport',, hash( '%m', $run.memory ) )}{/if}</p>

{if $ai.summary}
<section class="a4-card a4-sqliimport-ai" aria-labelledby="sq-ai-summary-title">
<h2 class="a4-h3" id="sq-ai-summary-title">{'Summary by the AI'|i18n( 'extension/sqliimport' )}</h2>
<p>{$ai.summary.summary|wash}</p>
<p class="a4-meta">{'Made from the figures of the run, without record values; check it against them.'|i18n( 'extension/sqliimport' )}{if ne( $ai.summary.model, '' )} · {$ai.summary.model|wash}{/if}</p>
</section>
{/if}

<ul class="a4-figures" aria-label="{'Outcomes'|i18n( 'extension/sqliimport' )|wash}">
    <li class="a4-figure is-ok"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/created' )|ezurl}><strong data-sq-field="created">{$run.created}</strong><span>{'Created'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/updated' )|ezurl}><strong data-sq-field="updated">{$run.updated}</strong><span>{'Updated'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/unchanged' )|ezurl}><strong data-sq-field="unchanged">{$run.unchanged}</strong><span>{'Unchanged'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/skipped' )|ezurl}><strong data-sq-field="skipped">{$run.skipped}</strong><span>{'Skipped'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure{if gt( $run.failed, 0 )} is-bad{/if}"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/failed' )|ezurl}><strong data-sq-field="failed">{$run.failed}</strong><span>{'Failed'|i18n( 'extension/sqliimport' )}</span></a></li>
    {if gt( $run.undone, 0 )}<li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/undone' )|ezurl}><strong data-sq-field="undone">{$run.undone}</strong><span>{'Undone'|i18n( 'extension/sqliimport' )}</span></a></li>{/if}
    {if gt( $run.processed, 0 )}<li class="a4-figure"><a href={concat( '/sqliimport/run/', $run.id, '/(outcome)/processed' )|ezurl}><strong data-sq-field="processed">{$run.processed}</strong><span>{'Processed (1.x)'|i18n( 'extension/sqliimport' )}</span></a></li>{/if}
</ul>

<section class="a4-section" aria-labelledby="a4-sqliimport-rows-title">
<div class="a4-section-head">
<h2 class="a4-h2" id="a4-sqliimport-rows-title">{'Records'|i18n( 'extension/sqliimport' )}</h2>
{if gt( $row_count, 0 )}<span class="a4-meta">{'%from to %to of %count'|i18n( 'extension/sqliimport',, hash( '%from', sum( $offset, 1 ), '%to', sum( $offset, $rows|count ), '%count', $row_count ) )}</span>
<a class="a4-btn a4-btn-ghost" href={$report_url|ezurl} download><span class="a4-icon a4-bi-download" aria-hidden="true"></span> {'Download results (CSV)'|i18n( 'extension/sqliimport' )}</a>{/if}
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
{if and( $ai.available, eq( $row.outcome, 'failed' ) )}
{if $row.ai}
<div class="a4-sqliimport-ai" id="sq-ai-row-{$row.id}">
<p><strong>{'Explanation by the AI'|i18n( 'extension/sqliimport' )}:</strong> {$row.ai.explanation|wash}</p>
<p><strong>{'Suggested fix'|i18n( 'extension/sqliimport' )}:</strong> {$row.ai.fix|wash}</p>
<p class="a4-meta">{if $row.ai.samples}{'Asked with the message, the mapping lines and the masked values of the record.'|i18n( 'extension/sqliimport' )}{else}{'Asked with the message and the mapping lines, without record values.'|i18n( 'extension/sqliimport' )}{/if} {'Nothing was changed; change the mapping yourself.'|i18n( 'extension/sqliimport' )}</p>
</div>
{else}
<form class="a4-sqliimport-inline" method="post" action={$here|ezurl}>
<button type="submit" class="a4-btn a4-btn-small a4-btn-ghost" name="AiExplainButton" value="{$row.id}" aria-label="{'Explain the failure of record %r'|i18n( 'extension/sqliimport',, hash( '%r', $row.ref ) )|wash}"><span class="a4-icon a4-bi-stars" aria-hidden="true"></span> {'Explain'|i18n( 'extension/sqliimport' )}</button>
</form>
{/if}
{/if}
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
<p class="a4-empty" data-sq-log-empty="1">{'Nothing logged yet.'|i18n( 'extension/sqliimport' )}</p>
{/if}
<ol class="a4-timeline a4-sqliimport-log" data-sq-log="1"{if $log|count|eq( 0 )} hidden="hidden"{/if}>
{foreach $log as $line}
<li class="{if eq( $line.severity, 'error' )}is-bad{elseif eq( $line.severity, 'warning' )}is-warn{elseif eq( $line.severity, 'notice' )}is-info{else}is-ok{/if}">
<span class="a4-timeline-time">{$line.time|l10n( 'shortdatetime' )}</span>
<span class="a4-timeline-body"><span class="a4-badge{if eq( $line.severity, 'error' )} is-bad{elseif eq( $line.severity, 'warning' )} is-warn{/if}">{$line.severity|wash}</span>{if ne( $line.ref, '' )} <span class="a4-meta">{'record %r'|i18n( 'extension/sqliimport',, hash( '%r', $line.ref ) )|wash}</span>{/if} <span>{$line.message|wash}</span></span>
</li>
{/foreach}
</ol>
</section>

</div></div></div>

</div>
