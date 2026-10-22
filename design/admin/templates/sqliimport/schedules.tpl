{* sqliimport/schedules: the schedules of the imports with their next runs; Pause, Resume, Edit and Delete for users
   who may edit the import. *}
<div class="context-block a4-page a4-sqliimport">
<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Schedules'|i18n( 'extension/sqliimport' )}</h1>
</div></div>
<div class="box-bc"><div class="box-ml"><div class="box-content">
{include uri='design:sqliimport/parts/flash.tpl' flash=$flash}
<p class="a4-intro">{'The cron part "sqliimport" queues the runs that are due every minute; the workers of the job queue do them.'|i18n( 'extension/sqliimport' )}</p>
{if gt( $legacy_count, 0 )}<p class="a4-notice is-info">{'%n scheduled imports of 1.x are not migrated yet; they keep running. ./console ext:sqliimport:migrate-1x moves them here.'|i18n( 'extension/sqliimport',, hash( '%n', $legacy_count ) )} <a href={'/sqliimport/scheduledlist'|ezurl}>{'1.x scheduled imports'|i18n( 'extension/sqliimport' )}</a></p>{/if}
{if $schedules|count|eq( 0 )}
<div class="a4-empty"><p>{'No import runs by itself yet. A schedule is the fifth step of the import editor.'|i18n( 'extension/sqliimport' )}</p></div>
{else}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-label="{'Schedules'|i18n( 'extension/sqliimport' )|wash}">
<table class="a4-table">
<thead><tr><th>{'Import'|i18n( 'extension/sqliimport' )}</th><th>{'When'|i18n( 'extension/sqliimport' )}</th><th>{'Next runs'|i18n( 'extension/sqliimport' )}</th><th>{'Last run'|i18n( 'extension/sqliimport' )}</th><th><span class="a4-sr-only">{'Actions'|i18n( 'extension/sqliimport' )}</span></th></tr></thead>
<tbody>
{foreach $schedules as $s}
<tr>
<td class="a4-sqliimport-name"><a href={concat( '/sqliimport/list/(import)/', $s.import.id )|ezurl}>{$s.import.name|wash}</a>{if $s.import.is_enabled|not} <span class="a4-badge">{'disabled'|i18n( 'extension/sqliimport' )}</span>{/if}</td>
<td>{$s.description|wash} {if $s.is_active}<span class="a4-badge is-ok">{'active'|i18n( 'extension/sqliimport' )}</span>{else}<span class="a4-badge">{'paused'|i18n( 'extension/sqliimport' )}</span>{/if}</td>
<td>{if $s.next|count|gt( 0 )}<ul class="a4-plain">{foreach $s.next as $t}<li class="a4-nowrap">{$t|l10n( 'shortdatetime' )}</li>{/foreach}</ul>{else}<span class="a4-meta">–</span>{/if}</td>
<td>{if gt( $s.last_run, 0 )}<a href={concat( '/sqliimport/run/', $s.last_run )|ezurl}>#{$s.last_run}</a>{else}<span class="a4-meta">{'never'|i18n( 'extension/sqliimport' )}</span>{/if}</td>
<td class="a4-end">{if $s.can_edit}
{if $s.is_active}{include uri='design:sqliimport/parts/button.tpl' action='schedule_pause' schedule_id=$s.id here=$here small=1 label='Pause'|i18n( 'extension/sqliimport' )}{else}{include uri='design:sqliimport/parts/button.tpl' action='schedule_resume' schedule_id=$s.id here=$here small=1 label='Resume'|i18n( 'extension/sqliimport' ) kind='primary'}{/if}
<a class="a4-btn a4-btn-small" href={concat( '/sqliimport/edit/', $s.import.id, '/(step)/schedule' )|ezurl}>{'Edit'|i18n( 'extension/sqliimport' )}</a>
{include uri='design:sqliimport/parts/button.tpl' action='schedule_delete' schedule_id=$s.id here=$here small=1 kind='danger' label='Delete'|i18n( 'extension/sqliimport' ) confirm='Delete this schedule? The import stays.'|i18n( 'extension/sqliimport' )}
{/if}</td>
</tr>
{/foreach}
</tbody>
</table>
</div>
{/if}
</div></div></div>
</div>
