{* Dashboard block "Imports" (dashboard.ini [DashboardBlock_sqliimport]): the latest runs with their status and the
   failures of the last 24 hours. *}
{def $sq_runs = fetch( 'sqliimport', 'recent_runs', hash( 'limit', $block.number_of_items ) )
     $sq_failed = fetch( 'sqliimport', 'failed_runs_24h' )}
<h2>{'Imports'|i18n( 'extension/sqliimport' )}</h2>
{if gt( $sq_failed, 0 )}<p class="a4-notice is-bad"><a href={'/sqliimport/list/(status)/failed'|ezurl}>{'%n runs failed or ended with errors in the last 24 hours.'|i18n( 'extension/sqliimport',, hash( '%n', $sq_failed ) )}</a></p>{/if}
{if $sq_runs|count}
<table class="list a4-table is-compact">
<tr><th>{'Run'|i18n( 'extension/sqliimport' )}</th><th>{'Import'|i18n( 'extension/sqliimport' )}</th><th>{'Status'|i18n( 'extension/sqliimport' )}</th><th>{'Started'|i18n( 'extension/sqliimport' )}</th></tr>
{foreach $sq_runs as $r}
<tr><td><a href={concat( '/sqliimport/run/', $r.id )|ezurl}>#{$r.id}</a></td><td>{$r.import_name|wash}</td><td>{include uri='design:sqliimport/parts/status.tpl' label=$r.status_label kind=$r.status_kind}</td><td>{if gt( $r.started_at, 0 )}{$r.started_at|l10n( 'shortdatetime' )}{else}–{/if}</td></tr>
{/foreach}
</table>
{else}
<p>{'No import has run yet.'|i18n( 'extension/sqliimport' )}</p>
{/if}
<p><a href={'/sqliimport/list'|ezurl}>{'All imports and runs'|i18n( 'extension/sqliimport' )}</a></p>
{undef $sq_runs $sq_failed}
