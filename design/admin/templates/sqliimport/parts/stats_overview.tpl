{* The statistics of the dashboard (sqliimport/list): the last 30 days of runs without dry runs
   (expImportStatistics::overview()). Key figures (runs, success rate, records, average and slowest duration), the
   records per day by outcome as a stacked chart, and the imports that failed most, each with a link to its page in
   Statistics. Input: $stats. *}
{if and( is_set( $stats ), $stats )}
<section class="a4-section a4-sqliimport-stats" aria-labelledby="a4-sqliimport-stats-title">
<div class="a4-section-head">
<h2 class="a4-h2" id="a4-sqliimport-stats-title">{'The last %days days'|i18n( 'extension/sqliimport/statistics',, hash( '%days', $stats.days ) )}</h2>
<a class="a4-btn a4-btn-small a4-btn-ghost" href={'/sqliimport/statistics'|ezurl}><span class="a4-icon a4-bi-bar-chart-line" aria-hidden="true"></span> {'Statistics'|i18n( 'extension/sqliimport/statistics' )}</a>
</div>
{if $stats.has_data|not}
<p class="a4-empty">{'No run in the last %days days. Dry runs are not counted here.'|i18n( 'extension/sqliimport/statistics',, hash( '%days', $stats.days ) )}</p>
{else}
<ul class="a4-figures" aria-label="{'Figures of the last %days days'|i18n( 'extension/sqliimport/statistics',, hash( '%days', $stats.days ) )|wash}">
    <li class="a4-figure"><strong>{$stats.summary.runs}</strong><span>{'Runs'|i18n( 'extension/sqliimport/statistics' )}</span></li>
    <li class="a4-figure{if ne( $stats.summary.success_rate, null )}{if ge( $stats.summary.success_rate, 95 )} is-ok{elseif lt( $stats.summary.success_rate, 80 )} is-bad{/if}{/if}"><strong>{$stats.summary.success_rate_text|wash}</strong><span>{'Runs without errors'|i18n( 'extension/sqliimport/statistics' )}</span>
        <span class="a4-meter a4-sqliimport-figure-meter{if ge( $stats.summary.success_pct, 95 )} is-ok{elseif lt( $stats.summary.success_pct, 80 )} is-bad{else} is-warn{/if}" role="presentation"><span class="a4-pct-{$stats.summary.success_pct}"></span></span></li>
    <li class="a4-figure"><strong>{$stats.summary.rows_text|wash}</strong><span>{'Records processed'|i18n( 'extension/sqliimport/statistics' )}</span></li>
    <li class="a4-figure"><strong>{$stats.summary.avg_duration_text|wash}</strong><span>{'Average duration'|i18n( 'extension/sqliimport/statistics' )}</span></li>
    <li class="a4-figure">{if $stats.slowest}<a href={concat( '/sqliimport/run/', $stats.slowest.id )|ezurl}><strong>{$stats.slowest.duration_text|wash}</strong><span>{'Slowest run: %name'|i18n( 'extension/sqliimport/statistics',, hash( '%name', $stats.slowest.name ) )|wash}</span></a>{else}<strong>–</strong><span>{'Slowest run'|i18n( 'extension/sqliimport/statistics' )}</span>{/if}</li>
</ul>
<div class="a4-sqliimport-stats-grid">
<section class="a4-card" aria-labelledby="a4-sqliimport-stats-rows">
<div class="a4-card-head"><h3 class="a4-h3" id="a4-sqliimport-stats-rows">{'Records per day'|i18n( 'extension/sqliimport/statistics' )}</h3></div>
{include uri='design:sqliimport/parts/stats_stack.tpl' chart=$stats.rows_chart bucket='Day'|i18n( 'extension/sqliimport/statistics' )
    title='Records per day of the last %days days, by outcome'|i18n( 'extension/sqliimport/statistics',, hash( '%days', $stats.days ) )
    labels=hash( 'created', 'Created'|i18n( 'extension/sqliimport/outcome' ), 'updated', 'Updated'|i18n( 'extension/sqliimport/outcome' ),
                 'unchanged', 'Unchanged'|i18n( 'extension/sqliimport/outcome' ), 'skipped', 'Skipped'|i18n( 'extension/sqliimport/outcome' ),
                 'failed', 'Failed'|i18n( 'extension/sqliimport/outcome' ) )}
</section>
<section class="a4-card" aria-labelledby="a4-sqliimport-stats-failing">
<div class="a4-card-head"><h3 class="a4-h3" id="a4-sqliimport-stats-failing">{'Imports with the most failures'|i18n( 'extension/sqliimport/statistics' )}</h3></div>
{if $stats.failing|count|eq( 0 )}
<p class="a4-empty">{'No run had errors in the last %days days.'|i18n( 'extension/sqliimport/statistics',, hash( '%days', $stats.days ) )}</p>
{else}
<ol class="a4-sqliimport-failing">
{foreach $stats.failing as $f}
<li>
<div class="a4-row is-between"><a href={concat( '/sqliimport/statistics/(import)/', $f.import_id )|ezurl}>{$f.name|wash}</a>
<span class="a4-meta">{'%runs of %all runs with errors · %rate of records failed'|i18n( 'extension/sqliimport/statistics',, hash( '%runs', sum( $f.stats.with_errors, $f.stats.failed_runs ), '%all', $f.stats.runs, '%rate', $f.stats.failure_rate_text ) )|wash}</span></div>
<span class="a4-meter is-bad a4-sqliimport-figure-meter" role="presentation"><span class="a4-pct-{$f.failure_pct}"></span></span>
{if ne( $f.top_error, '' )}<p class="a4-meta a4-sqliimport-error">{$f.top_error|wash}</p>{/if}
</li>
{/foreach}
</ol>
{/if}
</section>
</div>
{/if}
</section>
{/if}
