{* A bar chart of one value per day, week or month (expImportStatistics::series()): heights from .a4-pct-*, a bar
   without a value is left empty, and the values as a table in a <details>. Inputs: $chart, $title, $bucket (the name
   of the first column), $value (the name of the value column), $kind (the colour: duration or failure). *}
<div class="a4-sqliimport-chart is-{$kind}" role="img" aria-label="{concat( $title, '. ', 'The table below the chart has the numbers.'|i18n( 'extension/sqliimport/statistics' ) )|wash}">
{foreach $chart.bars as $b}
<div class="a4-sqliimport-bar" title="{$b.label|wash}: {$b.text|wash}">{if gt( $b.pct, 0 )}<span class="a4-sqliimport-seg is-{$kind} a4-pct-{$b.pct}"></span>{/if}</div>
{/foreach}
</div>
<div class="a4-sqliimport-axis" aria-hidden="true">{foreach $chart.bars as $b}<span>{if $b.mark}{$b.label|wash}{/if}</span>{/foreach}</div>
<ul class="a4-sqliimport-legend" aria-label="{'Legend'|i18n( 'extension/sqliimport/statistics' )|wash}">
<li class="is-{$kind}"><i aria-hidden="true"></i>{$value|wash}</li>
<li><span class="a4-meta">{'highest bar: %n'|i18n( 'extension/sqliimport/statistics',, hash( '%n', $chart.max_text ) )}</span></li>
</ul>
<details class="a4-sqliimport-tableview"><summary>{'Show as a table'|i18n( 'extension/sqliimport/statistics' )}</summary>
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-label="{$title|wash}">
<table class="a4-table is-compact">
<caption class="a4-sr-only">{$title|wash}</caption>
<thead><tr><th scope="col">{$bucket|wash}</th><th scope="col" class="a4-num">{$value|wash}</th></tr></thead>
<tbody>
{foreach $chart.bars as $b}{if ne( $b.text, '–' )}<tr><th scope="row">{$b.start|l10n( 'shortdate' )}</th><td class="a4-num">{$b.text|wash}</td></tr>{/if}{/foreach}
</tbody>
</table>
</div>
</details>
