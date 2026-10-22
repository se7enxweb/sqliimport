{* sqliimport/list: the dashboard of the Imports tab (7x SQLI Import). The key figures, a toolbar (run an import now,
   the 1.x pages), the imports with their last run and next schedule, and the runs, filtered by status. Built from the
   administration's shared components only (.a4-page, .a4-figures, .a4-toolbar, .a4-table, .a4-badge, .a4-meter,
   .a4-empty); what they cannot do is in stylesheets/sqliimport.css (.a4-sqliimport-*). No inline CSS or script. *}
<div class="context-block a4-page a4-sqliimport">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Imports'|i18n( 'extension/sqliimport' )}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

{include uri='design:sqliimport/parts/flash.tpl' flash=$flash}

<p class="a4-intro">{'An import brings content from a file or an address (CSV, XML, RSS, JSON) into the site, or runs an import handler. Every run is processed by the job queue in chunks, keeps a row per record and its own log, and can be cancelled or retried.'|i18n( 'extension/sqliimport' )}</p>

{if $installed|not}
<div class="a4-notice is-warn" role="alert">{'The tables of 7x SQLI Import are missing in this database. Run ./console ext:sqliimport:install-schema on the server.'|i18n( 'extension/sqliimport' )}</div>
{else}

{if $queue_ready|not}
<div class="a4-notice is-warn" role="alert">{'The job queue (expqueue) is not installed: runs cannot start. Activate the extension expqueue and run ./console ext:expqueue:install-schema.'|i18n( 'extension/sqliimport' )}</div>
{/if}

<div class="a4-toolbar a4-sqliimport-toolbar">
{if and( $can_run, $imports|count|gt( 0 ) )}
<form class="a4-sqliimport-runform" method="post" action={'/sqliimport/action'|ezurl}>
<input type="hidden" name="Action" value="run" />
<input type="hidden" name="RedirectURI" value="{$here|wash}" />
<label for="a4-sqliimport-runnow" class="a4-sr-only">{'Import to run'|i18n( 'extension/sqliimport' )}</label>
<select id="a4-sqliimport-runnow" name="ImportID">
{foreach $imports as $i}{if $i.import.is_enabled}<option value="{$i.import.id}">{$i.import.name|wash}</option>{/if}{/foreach}
</select>
<button type="submit" class="a4-btn a4-btn-primary"><span class="a4-icon a4-bi-play-fill" aria-hidden="true"></span> {'Run now'|i18n( 'extension/sqliimport' )}</button>
</form>
{/if}
{if $can_legacy}<a class="a4-btn" href={'/sqliimport/addimport'|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'New import'|i18n( 'extension/sqliimport' )}</a>{/if}
<a class="a4-btn a4-btn-ghost" href={'/sqliimport/items'|ezurl}>{'1.x import requests'|i18n( 'extension/sqliimport' )}{if gt( $figures.legacy_pending, 0 )} <span class="a4-badge is-info">{$figures.legacy_pending}</span>{/if}</a>
<a class="a4-btn a4-btn-ghost" href={'/expqueue/dashboard'|ezurl}>{'Job queue'|i18n( 'extension/sqliimport' )}</a>
</div>

<ul class="a4-figures" aria-label="{'Key figures'|i18n( 'extension/sqliimport' )|wash}">
    <li class="a4-figure"><strong>{$figures.imports}</strong><span>{'Imports'|i18n( 'extension/sqliimport' )}</span></li>
    <li class="a4-figure"><strong>{$figures.runs_today}</strong><span>{'Runs today'|i18n( 'extension/sqliimport' )}</span></li>
    <li class="a4-figure{if gt( $figures.active, 0 )} is-current{/if}"><a href={'/sqliimport/list/(status)/active'|ezurl}><strong>{$figures.active}</strong><span>{'Running or queued'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure{if gt( $figures.failed_24h, 0 )} is-bad{else} is-ok{/if}"><a href={'/sqliimport/list/(status)/failed'|ezurl}><strong>{$figures.failed_24h}</strong><span>{'Failed in 24 hours'|i18n( 'extension/sqliimport' )}</span></a></li>
    <li class="a4-figure"><strong>{if gt( $figures.next_run, 0 )}{$figures.next_run|l10n( 'shortdatetime' )}{else}–{/if}</strong><span>{'Next scheduled run'|i18n( 'extension/sqliimport' )}{if gt( $figures.schedules, 0 )} ({$figures.schedules}){/if}</span></li>
</ul>

<section class="a4-section" aria-labelledby="a4-sqliimport-imports-title">
<div class="a4-section-head"><h2 class="a4-h2" id="a4-sqliimport-imports-title">{'Imports'|i18n( 'extension/sqliimport' )}</h2></div>
{if $imports|count|eq( 0 )}
<div class="a4-empty">
<p><strong>{'No import yet.'|i18n( 'extension/sqliimport' )}</strong></p>
<p>{'An import is made from a source (a file or an address) and a mapping to a content class, or from an import handler. The import editor comes with the next release; until then, imports are created with the command line or from a 1.x handler.'|i18n( 'extension/sqliimport' )}</p>
<p>{if $can_legacy}<a class="a4-btn a4-btn-primary" href={'/sqliimport/addimport'|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'New import'|i18n( 'extension/sqliimport' )}</a> {/if}<a class="a4-btn" href={'/exphelp/extensions/sqliimport'|ezurl}><span class="a4-icon a4-bi-book" aria-hidden="true"></span> {'Read the manual'|i18n( 'extension/sqliimport' )}</a></p>
</div>
{else}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="a4-sqliimport-imports-title">
<table class="a4-table">
<thead><tr>
<th>{'Import'|i18n( 'extension/sqliimport' )}</th>
<th>{'Source'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-sqliimport-hide-narrow">{'Processing'|i18n( 'extension/sqliimport' )}</th>
<th>{'Last run'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-sqliimport-hide-narrow">{'Next run'|i18n( 'extension/sqliimport' )}</th>
{if $can_run}<th><span class="a4-sr-only">{'Actions'|i18n( 'extension/sqliimport' )}</span></th>{/if}
</tr></thead>
<tbody>
{foreach $imports as $i}
<tr>
<td class="a4-sqliimport-name"><a href={concat( '/sqliimport/list/(import)/', $i.import.id )|ezurl}>{$i.import.name|wash}</a><br /><span class="a4-meta">{$i.import.identifier|wash}</span>{if $i.import.is_enabled|not} <span class="a4-badge">{'disabled'|i18n( 'extension/sqliimport' )}</span>{/if}</td>
<td>{if $i.import.is_legacy}<span class="a4-badge is-info">{'1.x handler'|i18n( 'extension/sqliimport' )}</span>{else}{$i.import.source_type|upcase|wash}{/if}</td>
<td class="a4-sqliimport-hide-narrow">{if eq( $i.import.handler, 'mapping' )}{'Mapping'|i18n( 'extension/sqliimport' )}{else}{$i.import.handler|wash}{/if}</td>
<td>{if $i.last_run}<a href={concat( '/sqliimport/run/', $i.last_run.id )|ezurl}>#{$i.last_run.id}</a> {include uri='design:sqliimport/parts/status.tpl' label=$i.last_run.status_label kind=$i.last_run.status_kind}{else}<span class="a4-meta">{'never'|i18n( 'extension/sqliimport' )}</span>{/if}</td>
<td class="a4-sqliimport-hide-narrow">{if gt( $i.next_run, 0 )}{$i.next_run|l10n( 'shortdatetime' )}{else}<span class="a4-meta">–</span>{/if}</td>
{if $can_run}<td class="a4-end">{if $i.can_run}{if $i.import.is_enabled}{include uri='design:sqliimport/parts/button.tpl' action='run' import=$i.import.id here=$here small=1 label='Run now'|i18n( 'extension/sqliimport' ) kind='primary'}{/if}{if $i.import.is_legacy|not}{include uri='design:sqliimport/parts/button.tpl' action='dryrun' import=$i.import.id here=$here small=1 label='Dry run'|i18n( 'extension/sqliimport' )}{/if}{/if}</td>{/if}
</tr>
{/foreach}
</tbody>
</table>
</div>
{/if}
</section>

<section class="a4-section" aria-labelledby="a4-sqliimport-runs-title">
<div class="a4-section-head">
<h2 class="a4-h2" id="a4-sqliimport-runs-title">{'Runs'|i18n( 'extension/sqliimport' )}</h2>
{if gt( $run_count, 0 )}<span class="a4-meta">{'%from to %to of %count'|i18n( 'extension/sqliimport',, hash( '%from', sum( $offset, 1 ), '%to', sum( $offset, $runs|count ), '%count', $run_count ) )}</span>{/if}
</div>
<ul class="a4-badges a4-sqliimport-filters" aria-label="{'Status'|i18n( 'extension/sqliimport' )|wash}">
{foreach $status_links as $l}
<li><a class="a4-tag{if $l.current} is-accent{/if}" href={$l.url|ezurl}{if $l.current} aria-current="true"{/if}>{if eq( $l.name, '' )}{'all'|i18n( 'extension/sqliimport' )}{else}{$l.label|wash}{/if}</a></li>
{/foreach}
{if $filter.import}<li><a class="a4-tag is-accent" href={concat( '/sqliimport/list', cond( ne( $filter.status, '' ), concat( '/(status)/', $filter.status ), '' ) )|ezurl} aria-current="true">{'one import ×'|i18n( 'extension/sqliimport' )}</a></li>{/if}
</ul>
{if $runs|count|eq( 0 )}
<p class="a4-empty">{'No run matches.'|i18n( 'extension/sqliimport' )}</p>
{else}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="a4-sqliimport-runs-title">
<table class="a4-table is-compact">
<thead><tr>
<th>{'Run'|i18n( 'extension/sqliimport' )}</th>
<th>{'Import'|i18n( 'extension/sqliimport' )}</th>
<th>{'Status'|i18n( 'extension/sqliimport' )}</th>
<th>{'Progress'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-num a4-sqliimport-hide-narrow">{'Created'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-num a4-sqliimport-hide-narrow">{'Updated'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-num">{'Failed'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-sqliimport-hide-narrow">{'Started'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-num">{'Duration'|i18n( 'extension/sqliimport' )}</th>
</tr></thead>
<tbody>
{foreach $runs as $r}
<tr>
<td class="a4-nowrap"><a href={concat( '/sqliimport/run/', $r.id )|ezurl}>#{$r.id}</a>{if ne( $r.kind, 'import' )} <span class="a4-meta">{$r.kind_label|wash}</span>{/if}</td>
<td class="a4-sqliimport-name"><a href={concat( '/sqliimport/list/(import)/', $r.import_id )|ezurl}>{$r.import_name|wash}</a><br /><span class="a4-meta">{$r.trigger_label|wash}{if ne( $r.user, '' )} · {$r.user|wash}{/if}</span></td>
<td>{include uri='design:sqliimport/parts/status.tpl' label=$r.status_label kind=$r.status_kind}</td>
<td><div class="a4-meter{if eq( $r.status, 'failed' )} is-bad{elseif eq( $r.status, 'completed_with_errors' )} is-warn{elseif eq( $r.status, 'completed' )} is-ok{/if} a4-sqliimport-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{$r.percent}" aria-label="{'Progress'|i18n( 'extension/sqliimport' )|wash}"><span class="a4-pct-{$r.percent}"></span></div><span class="a4-meta">{$r.done}{if gt( $r.total, 0 )} / {$r.total}{/if}</span></td>
<td class="a4-num a4-sqliimport-hide-narrow">{$r.created}</td>
<td class="a4-num a4-sqliimport-hide-narrow">{$r.updated}</td>
<td class="a4-num">{if gt( $r.failed, 0 )}<a class="a4-bad-text" href={concat( '/sqliimport/run/', $r.id, '/(outcome)/failed' )|ezurl}>{$r.failed}</a>{else}0{/if}</td>
<td class="a4-sqliimport-hide-narrow a4-nowrap">{if gt( $r.started_at, 0 )}{$r.started_at|l10n( 'shortdatetime' )}{else}<span class="a4-meta">{'queued'|i18n( 'extension/sqliimport/status' )}</span>{/if}</td>
<td class="a4-num a4-nowrap">{$r.duration|wash}</td>
</tr>
{/foreach}
</tbody>
</table>
</div>
<p class="a4-row a4-pager">
{if ne( $prev_url, '' )}<a class="a4-btn a4-btn-small" href={$prev_url|ezurl}>{'Newer'|i18n( 'extension/sqliimport' )}</a>{/if}
{if ne( $next_url, '' )}<a class="a4-btn a4-btn-small" href={$next_url|ezurl}>{'Older'|i18n( 'extension/sqliimport' )}</a>{/if}
</p>
{/if}
</section>

{/if}

</div></div></div>

</div>
