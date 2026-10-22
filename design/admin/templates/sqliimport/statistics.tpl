{* sqliimport/statistics: the run history of 7x SQLI Import for review and tuning (expImportStatistics::page()). A
   GET form with the period and the filters, the key figures, suggestions, the trends of runs, records, durations and
   failure rate, a table per import, the comparison with another period, the most common errors and the CSV export.
   Every chart has a legend and its numbers as a table; heights come from .a4-pct-*. Built from the administration's
   shared components; what they cannot do is in stylesheets/sqliimport.css (.a4-sqliimport-*). No inline CSS or
   script. *}
<div class="context-block a4-page a4-sqliimport">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Import statistics'|i18n( 'extension/sqliimport/statistics' )}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

<p class="a4-intro">{'The run history of the imports: how often they ran, how many records they processed, how long they took and how often they failed, per day, week or month, compared with another period. Dry runs and undo runs are left out unless you choose them.'|i18n( 'extension/sqliimport/statistics' )}</p>

{if $installed|not}
<div class="a4-notice is-warn" role="alert">{'The tables of 7x SQLI Import are missing in this database. Run ./console ext:sqliimport:install-schema on the server.'|i18n( 'extension/sqliimport' )}</div>
{else}
{def $bucket = cond( eq( $stats.unit, 'day' ), 'Day'|i18n( 'extension/sqliimport/statistics' ), eq( $stats.unit, 'week' ), 'Week'|i18n( 'extension/sqliimport/statistics' ), 'Month'|i18n( 'extension/sqliimport/statistics' ) )
     $per = cond( eq( $stats.unit, 'day' ), 'per day'|i18n( 'extension/sqliimport/statistics' ), eq( $stats.unit, 'week' ), 'per week'|i18n( 'extension/sqliimport/statistics' ), 'per month'|i18n( 'extension/sqliimport/statistics' ) )
     $outcomes = hash( 'created', 'Created'|i18n( 'extension/sqliimport/outcome' ), 'updated', 'Updated'|i18n( 'extension/sqliimport/outcome' ),
                       'unchanged', 'Unchanged'|i18n( 'extension/sqliimport/outcome' ), 'skipped', 'Skipped'|i18n( 'extension/sqliimport/outcome' ),
                       'failed', 'Failed'|i18n( 'extension/sqliimport/outcome' ) )
     $s = $stats.summary}

<ul class="a4-badges a4-sqliimport-filters" aria-label="{'Period'|i18n( 'extension/sqliimport/statistics' )|wash}">
{foreach $period_links as $l}
<li><a class="a4-tag{if $l.current} is-accent{/if}" href={$l.url|ezurl}{if $l.current} aria-current="true"{/if}>{'%n days'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $l.days ) )}</a></li>
{/foreach}
{if $stats.period.custom}<li><span class="a4-tag is-accent" aria-current="true">{$stats.period.from_date|wash} – {$stats.period.to_date|wash}</span></li>{/if}
</ul>

<form class="a4-filterbar a4-sqliimport-statform" method="get" action={'/sqliimport/statistics'|ezurl} aria-label="{'Period and filters'|i18n( 'extension/sqliimport/statistics' )|wash}">
<div class="a4-field">
<label for="sq-st-days">{'Last'|i18n( 'extension/sqliimport/statistics' )}</label>
<select id="sq-st-days" name="days">
{foreach array( 7, 30, 90, 365 ) as $d}<option value="{$d}"{if and( $stats.period.custom|not, eq( $stats.period.days, $d ) )} selected="selected"{/if}>{'%n days'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $d ) )}</option>{/foreach}
</select>
</div>
<div class="a4-field">
<label for="sq-st-from">{'Or from'|i18n( 'extension/sqliimport/statistics' )}</label>
<input type="date" id="sq-st-from" name="from" value="{if $stats.period.custom}{$stats.period.from_date|wash}{/if}" />
</div>
<div class="a4-field">
<label for="sq-st-to">{'to'|i18n( 'extension/sqliimport/statistics' )}</label>
<input type="date" id="sq-st-to" name="to" value="{if $stats.period.custom}{$stats.period.to_date|wash}{/if}" />
</div>
<div class="a4-field">
<label for="sq-st-import">{'Import'|i18n( 'extension/sqliimport' )}</label>
<select id="sq-st-import" name="import">
<option value="">{'all'|i18n( 'extension/sqliimport' )}</option>
{foreach $stats.options.imports as $o}<option value="{$o.value}"{if eq( $o.value, $stats.filter.import )} selected="selected"{/if}>{$o.label|wash} ({$o.runs})</option>{/foreach}
</select>
</div>
<div class="a4-field">
<label for="sq-st-source">{'Source type'|i18n( 'extension/sqliimport/statistics' )}</label>
<select id="sq-st-source" name="source">
<option value="">{'all'|i18n( 'extension/sqliimport' )}</option>
{foreach $stats.options.sources as $o}<option value="{$o.value|wash}"{if eq( $o.value, $stats.filter.source )} selected="selected"{/if}>{$o.label|wash} ({$o.runs})</option>{/foreach}
</select>
</div>
<div class="a4-field">
<label for="sq-st-user">{'User'|i18n( 'extension/sqliimport/statistics' )}</label>
<select id="sq-st-user" name="user">
<option value="">{'all'|i18n( 'extension/sqliimport' )}</option>
{foreach $stats.options.users as $o}<option value="{$o.value}"{if eq( $o.value, $stats.filter.user )} selected="selected"{/if}>{$o.label|wash} ({$o.runs})</option>{/foreach}
</select>
</div>
<div class="a4-field">
<label for="sq-st-scope">{'Runs'|i18n( 'extension/sqliimport/statistics' )}</label>
<select id="sq-st-scope" name="scope">
<option value="production"{if eq( $stats.filter.scope, 'production' )} selected="selected"{/if}>{'Imports and retries'|i18n( 'extension/sqliimport/statistics' )}</option>
<option value="dry_run"{if eq( $stats.filter.scope, 'dry_run' )} selected="selected"{/if}>{'Dry runs only'|i18n( 'extension/sqliimport/statistics' )}</option>
<option value="all"{if eq( $stats.filter.scope, 'all' )} selected="selected"{/if}>{'All, with dry runs and undo'|i18n( 'extension/sqliimport/statistics' )}</option>
</select>
</div>
<div class="a4-field">
<label for="sq-st-compare">{'Compare with'|i18n( 'extension/sqliimport/statistics' )}</label>
<select id="sq-st-compare" name="compare">
<option value="previous"{if eq( $stats.compare, 'previous' )} selected="selected"{/if}>{'The period before'|i18n( 'extension/sqliimport/statistics' )}</option>
<option value="custom"{if eq( $stats.compare, 'custom' )} selected="selected"{/if}>{'The period below'|i18n( 'extension/sqliimport/statistics' )}</option>
<option value="none"{if eq( $stats.compare, 'none' )} selected="selected"{/if}>{'Nothing'|i18n( 'extension/sqliimport/statistics' )}</option>
</select>
</div>
<div class="a4-field">
<label for="sq-st-cfrom">{'Second period from'|i18n( 'extension/sqliimport/statistics' )}</label>
<input type="date" id="sq-st-cfrom" name="cfrom" value="{if eq( $stats.compare, 'custom' )}{$stats.other.from_date|wash}{/if}" />
</div>
<div class="a4-field">
<label for="sq-st-cto">{'Second period to'|i18n( 'extension/sqliimport/statistics' )}</label>
<input type="date" id="sq-st-cto" name="cto" value="{if eq( $stats.compare, 'custom' )}{$stats.other.to_date|wash}{/if}" />
</div>
<div class="a4-row a4-sqliimport-statform-actions">
<button type="submit" class="a4-btn a4-btn-primary">{'Show'|i18n( 'extension/sqliimport/statistics' )}</button>
<a class="a4-btn a4-btn-ghost" href={'/sqliimport/statistics'|ezurl}>{'Reset'|i18n( 'extension/sqliimport/statistics' )}</a>
</div>
<p class="a4-filter-count">{'From and to, when both are given, replace the last days. The days follow the time zone of the site.'|i18n( 'extension/sqliimport/statistics' )}</p>
</form>

<div class="a4-section-head">
<h2 class="a4-h2">{'%from to %to'|i18n( 'extension/sqliimport/statistics',, hash( '%from', $stats.period.from|l10n( 'shortdate' ), '%to', sub( $stats.period.to, 1 )|l10n( 'shortdate' ) ) )|wash} <span class="a4-meta">{'%n days'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $stats.period.days ) )}</span></h2>
<a class="a4-btn a4-btn-small" href={$csv_url|ezurl} download><span class="a4-icon a4-bi-download" aria-hidden="true"></span> {'Table as CSV'|i18n( 'extension/sqliimport/statistics' )}</a>
</div>
{if $stats.runs_capped}<div class="a4-notice is-warn">{'This period has more runs than the page reads at once; the figures are from the oldest ones. Choose a shorter period or an import.'|i18n( 'extension/sqliimport/statistics' )}</div>{/if}

{if eq( $s.runs, 0 )}
<div class="a4-empty">
<p><strong>{'No run in this period.'|i18n( 'extension/sqliimport/statistics' )}</strong></p>
<p>{'Choose a longer period or fewer filters. A run counts on the day it started.'|i18n( 'extension/sqliimport/statistics' )}</p>
</div>
{else}

<ul class="a4-figures" aria-label="{'Key figures of the period'|i18n( 'extension/sqliimport/statistics' )|wash}">
    <li class="a4-figure"><strong>{$s.runs}</strong><span>{'Runs'|i18n( 'extension/sqliimport/statistics' )}{if gt( $s.cancelled, 0 )} · {'%n cancelled'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $s.cancelled ) )}{/if}</span></li>
    <li class="a4-figure{if ne( $s.success_rate, null )}{if ge( $s.success_rate, 95 )} is-ok{elseif lt( $s.success_rate, 80 )} is-bad{/if}{/if}"><strong>{$s.success_rate_text|wash}</strong><span>{'Runs without errors'|i18n( 'extension/sqliimport/statistics' )}</span></li>
    <li class="a4-figure"><strong>{$s.rows_text|wash}</strong><span>{'Records processed'|i18n( 'extension/sqliimport/statistics' )}</span></li>
    <li class="a4-figure{if gt( $s.failed, 0 )} is-bad{/if}"><strong>{$s.failure_rate_text|wash}</strong><span>{'Records failed (%n)'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $s.failed ) )}</span></li>
    <li class="a4-figure"><strong>{$s.avg_duration_text|wash}</strong><span>{'Average duration'|i18n( 'extension/sqliimport/statistics' )}</span></li>
    <li class="a4-figure"><strong>{$s.p95_duration_text|wash}</strong><span>{'95 % of runs took at most'|i18n( 'extension/sqliimport/statistics' )}</span></li>
    <li class="a4-figure"><strong>{$s.rows_per_second_text|wash}</strong><span>{'Records per second'|i18n( 'extension/sqliimport/statistics' )}</span></li>
</ul>

{if $stats.suggestions|count}
<section class="a4-section" aria-labelledby="sq-st-suggest">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-st-suggest">{'Suggestions'|i18n( 'extension/sqliimport/statistics' )}</h2></div>
<ul class="a4-sqliimport-suggestions">
{foreach $stats.suggestions as $sg}
<li class="a4-card is-attention"><span class="a4-icon a4-bi-info-circle" aria-hidden="true"></span><p>{$sg.text|wash} <a href={concat( '/sqliimport/statistics/(import)/', $sg.import_id, cond( $stats.period.custom, concat( '/(from)/', $stats.period.from_date, '/(to)/', $stats.period.to_date ), concat( '/(days)/', $stats.period.days ) ) )|ezurl}>{'Look at this import'|i18n( 'extension/sqliimport/statistics' )}</a></p></li>
{/foreach}
</ul>
</section>
{/if}

<section class="a4-section" aria-labelledby="sq-st-trends">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-st-trends">{'Trends'|i18n( 'extension/sqliimport/statistics' )} <span class="a4-meta">{$per|wash}</span></h2></div>
<div class="a4-sqliimport-stats-grid">
<section class="a4-card" aria-labelledby="sq-st-t-runs">
<div class="a4-card-head"><h3 class="a4-h3" id="sq-st-t-runs">{'Runs'|i18n( 'extension/sqliimport/statistics' )}</h3></div>
{include uri='design:sqliimport/parts/stats_stack.tpl' chart=$stats.trends.runs bucket=$bucket title=concat( 'Runs'|i18n( 'extension/sqliimport/statistics' ), ' ', $per )
    labels=hash( 'ok', 'Completed'|i18n( 'extension/sqliimport/status' ), 'with_errors', 'Completed with errors'|i18n( 'extension/sqliimport/status' ),
                 'failed', 'Failed'|i18n( 'extension/sqliimport/status' ), 'other', 'Cancelled or running'|i18n( 'extension/sqliimport/statistics' ) )}
</section>
<section class="a4-card" aria-labelledby="sq-st-t-rows">
<div class="a4-card-head"><h3 class="a4-h3" id="sq-st-t-rows">{'Records'|i18n( 'extension/sqliimport/statistics' )}</h3></div>
{include uri='design:sqliimport/parts/stats_stack.tpl' chart=$stats.trends.rows bucket=$bucket labels=$outcomes title=concat( 'Records by outcome'|i18n( 'extension/sqliimport/statistics' ), ' ', $per )}
</section>
<section class="a4-card" aria-labelledby="sq-st-t-duration">
<div class="a4-card-head"><h3 class="a4-h3" id="sq-st-t-duration">{'Average duration of a run'|i18n( 'extension/sqliimport/statistics' )}</h3></div>
{include uri='design:sqliimport/parts/stats_series.tpl' chart=$stats.trends.duration bucket=$bucket kind='duration' value='Average duration'|i18n( 'extension/sqliimport/statistics' )
    title=concat( 'Average duration of a run'|i18n( 'extension/sqliimport/statistics' ), ' ', $per )}
</section>
<section class="a4-card" aria-labelledby="sq-st-t-failure">
<div class="a4-card-head"><h3 class="a4-h3" id="sq-st-t-failure">{'Failure rate of records'|i18n( 'extension/sqliimport/statistics' )}</h3></div>
{include uri='design:sqliimport/parts/stats_series.tpl' chart=$stats.trends.failure bucket=$bucket kind='failure' value='Records failed'|i18n( 'extension/sqliimport/statistics' )
    title=concat( 'Failure rate of records'|i18n( 'extension/sqliimport/statistics' ), ' ', $per )}
</section>
</div>
</section>

<section class="a4-section" aria-labelledby="sq-st-imports">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-st-imports">{'Per import'|i18n( 'extension/sqliimport/statistics' )}</h2></div>
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="sq-st-imports">
<table class="a4-table is-compact a4-sqliimport-stattable">
<thead><tr>
<th scope="col">{'Import'|i18n( 'extension/sqliimport' )}</th>
<th scope="col">{'Records %per'|i18n( 'extension/sqliimport/statistics',, hash( '%per', $per ) )}</th>
<th scope="col" class="a4-num">{'Runs'|i18n( 'extension/sqliimport/statistics' )}</th>
<th scope="col" class="a4-num">{'Average'|i18n( 'extension/sqliimport/statistics' )}</th>
<th scope="col" class="a4-num"><abbr title="{'95th percentile of the duration'|i18n( 'extension/sqliimport/statistics' )|wash}">{'P95'|i18n( 'extension/sqliimport/statistics' )}</abbr></th>
<th scope="col" class="a4-num">{'Records/s'|i18n( 'extension/sqliimport/statistics' )}</th>
<th scope="col" class="a4-num">{'Failure rate'|i18n( 'extension/sqliimport/statistics' )}</th>
</tr></thead>
<tbody>
{foreach $stats.imports as $i}
<tr>
<th scope="row" class="a4-sqliimport-name"><a href={concat( '/sqliimport/list/(import)/', $i.import_id )|ezurl}>{$i.name|wash}</a>{if ne( $i.source_type, '' )}<br /><span class="a4-meta">{$i.source_type|upcase|wash}</span>{/if}{if ne( $i.slow_date, '' )} <span class="a4-badge is-warn">{'slow since %date'|i18n( 'extension/sqliimport/statistics',, hash( '%date', $i.slow_date ) )|wash}</span>{/if}</th>
<td><span class="a4-sqliimport-spark" role="img" aria-label="{'Records %per: %values'|i18n( 'extension/sqliimport/statistics',, hash( '%per', $per, '%values', $i.spark_values|implode( ', ' ) ) )|wash}">{foreach $i.spark as $p}<span class="a4-pct-{$p}"></span>{/foreach}</span><span class="a4-meta">{$i.stats.rows_text|wash}</span></td>
<td class="a4-num">{$i.stats.runs}{if gt( sum( $i.stats.with_errors, $i.stats.failed_runs ), 0 )}<br /><span class="a4-meta">{'%n with errors'|i18n( 'extension/sqliimport/statistics',, hash( '%n', sum( $i.stats.with_errors, $i.stats.failed_runs ) ) )}</span>{/if}</td>
<td class="a4-num">{$i.stats.avg_duration_text|wash}</td>
<td class="a4-num">{$i.stats.p95_duration_text|wash}</td>
<td class="a4-num">{$i.stats.rows_per_second_text|wash}</td>
<td class="a4-num">{if gt( $i.stats.failed, 0 )}<a class="a4-bad-text" href={concat( '/sqliimport/list/(import)/', $i.import_id, '/(status)/failed' )|ezurl}>{$i.stats.failure_rate_text|wash}</a>{else}{$i.stats.failure_rate_text|wash}{/if}</td>
</tr>
{if $i.errors|count}<tr class="a4-sqliimport-subrow"><td colspan="7"><div class="a4-sqliimport-errors"><span class="a4-meta">{'Most common errors'|i18n( 'extension/sqliimport/statistics' )}:</span><ul>{foreach $i.errors as $e}<li><span class="a4-badge is-bad">{$e.count}</span> {$e.message|wash}</li>{/foreach}</ul></div></td></tr>{/if}
{/foreach}
</tbody>
</table>
</div>
</section>

{if $stats.comparison|count}
<section class="a4-section" aria-labelledby="sq-st-compare-title">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-st-compare-title">{'Comparison'|i18n( 'extension/sqliimport/statistics' )}</h2>
<span class="a4-meta">{'%from – %to compared with %ofrom – %oto'|i18n( 'extension/sqliimport/statistics',, hash( '%from', $stats.period.from_date, '%to', $stats.period.to_date, '%ofrom', $stats.other.from_date, '%oto', $stats.other.to_date ) )|wash}</span></div>
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="sq-st-compare-title">
<table class="a4-table is-compact a4-sqliimport-compare">
<thead><tr>
<th scope="col">{'Figure'|i18n( 'extension/sqliimport/statistics' )}</th>
<th scope="col" class="a4-num">{'This period'|i18n( 'extension/sqliimport/statistics' )}</th>
<th scope="col" class="a4-num">{'Compared period'|i18n( 'extension/sqliimport/statistics' )}</th>
<th scope="col" class="a4-num">{'Change'|i18n( 'extension/sqliimport/statistics' )}</th>
</tr></thead>
<tbody>
{foreach $stats.comparison as $c}
<tr>
<th scope="row">{$c.label|wash}</th>
<td class="a4-num">{$c.current_text|wash}</td>
<td class="a4-num">{$c.previous_text|wash}</td>
<td class="a4-num{if eq( $c.delta.kind, 'good' )} a4-ok-text{elseif eq( $c.delta.kind, 'bad' )} a4-bad-text{/if}">{if eq( $c.delta.direction, 'up' )}<span class="a4-icon a4-bi-arrow-up" aria-hidden="true"></span> {elseif eq( $c.delta.direction, 'down' )}<span class="a4-icon a4-bi-arrow-down" aria-hidden="true"></span> {/if}{$c.diff_text|wash}{if eq( $c.delta.kind, 'good' )}<span class="a4-sr-only"> ({'better'|i18n( 'extension/sqliimport/statistics' )})</span>{elseif eq( $c.delta.kind, 'bad' )}<span class="a4-sr-only"> ({'worse'|i18n( 'extension/sqliimport/statistics' )})</span>{/if}</td>
</tr>
{/foreach}
</tbody>
</table>
</div>
</section>
{/if}

{if $stats.errors|count}
<section class="a4-section" aria-labelledby="sq-st-errors">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-st-errors">{'Most common errors'|i18n( 'extension/sqliimport/statistics' )}</h2>
<span class="a4-meta">{'numbers in the messages are shown as #, so the same error counts once'|i18n( 'extension/sqliimport/statistics' )}</span></div>
<ol class="a4-sqliimport-failing">
{foreach $stats.errors as $e}
<li><div class="a4-row is-between"><span class="a4-sqliimport-error">{$e.message|wash}</span><span class="a4-meta">{'%n times, %share %'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $e.count, '%share', $e.share ) )}</span></div>
<span class="a4-meter is-bad a4-sqliimport-figure-meter" role="presentation"><span class="a4-pct-{$e.share|round}"></span></span></li>
{/foreach}
</ol>
</section>
{/if}

{/if}
{undef $bucket $per $outcomes $s}
{/if}

</div></div></div>

</div>
