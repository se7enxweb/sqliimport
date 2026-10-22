{* A stacked bar chart of the statistics (expImportStatistics::stack()): one bar per day, week or month, its
   segments bottom to top, heights from .a4-pct-* (no style=""), a legend, and the numbers as a table in a <details>
   for screen readers and for reading them exactly. Inputs: $chart, $labels (hash series => translated name), $title
   (what the chart shows, for its label), $bucket (the name of the first column: Day, Week, Month). *}
<div class="a4-sqliimport-chart" role="img" aria-label="{concat( $title, '. ', 'The table below the chart has the numbers.'|i18n( 'extension/sqliimport/statistics' ) )|wash}">
{foreach $chart.bars as $b}
<div class="a4-sqliimport-bar" title="{$b.label|wash}: {foreach $b.segments as $s}{if gt( $s.value, 0 )}{$labels[$s.name]|wash} {$s.value} · {/if}{/foreach}{'total'|i18n( 'extension/sqliimport/statistics' )} {$b.total}">{foreach $b.segments as $s}{if gt( $s.pct, 0 )}<span class="a4-sqliimport-seg is-{$s.name} a4-pct-{$s.pct}"></span>{/if}{/foreach}</div>
{/foreach}
</div>
<div class="a4-sqliimport-axis" aria-hidden="true">{foreach $chart.bars as $b}<span>{if $b.mark}{$b.label|wash}{/if}</span>{/foreach}</div>
<ul class="a4-sqliimport-legend" aria-label="{'Legend'|i18n( 'extension/sqliimport/statistics' )|wash}">
{foreach $chart.series as $name}<li class="is-{$name}"><i aria-hidden="true"></i>{$labels[$name]|wash} <span class="a4-meta">{$chart.sum[$name]}</span></li>{/foreach}
<li><span class="a4-meta">{'highest bar: %n'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $chart.max ) )}</span></li>
</ul>
<details class="a4-sqliimport-tableview"><summary>{'Show as a table'|i18n( 'extension/sqliimport/statistics' )}</summary>
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-label="{$title|wash}">
<table class="a4-table is-compact">
<caption class="a4-sr-only">{$title|wash}</caption>
<thead><tr><th scope="col">{$bucket|wash}</th>{foreach $chart.series as $name}<th scope="col" class="a4-num">{$labels[$name]|wash}</th>{/foreach}<th scope="col" class="a4-num">{'Total'|i18n( 'extension/sqliimport/statistics' )}</th></tr></thead>
<tbody>
{foreach $chart.bars as $b}{if gt( $b.total, 0 )}<tr><th scope="row">{$b.start|l10n( 'shortdate' )}</th>{foreach $b.segments as $s}<td class="a4-num">{$s.value}</td>{/foreach}<td class="a4-num">{$b.total}</td></tr>{/if}{/foreach}
</tbody>
</table>
</div>
</details>
