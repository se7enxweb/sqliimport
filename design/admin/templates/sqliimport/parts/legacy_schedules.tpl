{* The scheduled imports of 1.x on sqliimport/schedules (they were the page sqliimport/scheduledlist): label, handler
   and options, frequency, next run, state, and Run now, Edit, Enable or Disable, Delete (POST to sqliimport/action).
   A migrated one (an import of 2.x since ext:sqliimport:migrate-1x) links to that import's schedule. Shown when such
   scheduled imports exist or the user may add one. Input: $legacy_schedules (expImportLegacy::scheduled()),
   $can_add_legacy, $here. *}
{if or( $legacy_schedules|count|gt( 0 ), $can_add_legacy )}
<section class="a4-section" id="a4-sqliimport-legacy" aria-labelledby="a4-sqliimport-legacy-title">
<div class="a4-section-head">
<h2 class="a4-h2" id="a4-sqliimport-legacy-title">{'1.x scheduled imports'|i18n( 'extension/sqliimport' )}</h2>
{if $can_add_legacy}<a class="a4-btn a4-btn-small" href={'/sqliimport/schedules/(legacy)/new'|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'New 1.x scheduled import'|i18n( 'extension/sqliimport' )}</a>{/if}
</div>
<p class="a4-help">{'They request an import of a handler of sqliimport 1.x; the cron part "sqliimport" runs them until they are migrated with ./console ext:sqliimport:migrate-1x, which makes each an import with a schedule above.'|i18n( 'extension/sqliimport' )}</p>
{if $legacy_schedules|count|eq( 0 )}
<div class="a4-empty">
<p>{'No 1.x scheduled import.'|i18n( 'extension/sqliimport' )}</p>
<p><a class="a4-btn a4-btn-primary" href={'/sqliimport/schedules/(legacy)/new'|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'New 1.x scheduled import'|i18n( 'extension/sqliimport' )}</a></p>
</div>
{else}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="a4-sqliimport-legacy-title">
<table class="a4-table">
<thead><tr><th>{'Import'|i18n( 'extension/sqliimport' )}</th><th>{'Frequency'|i18n( 'extension/sqliimport/schedulefrequency' )}</th><th>{'Next run'|i18n( 'extension/sqliimport' )}</th><th><span class="a4-sr-only">{'Actions'|i18n( 'extension/sqliimport' )}</span></th></tr></thead>
<tbody>
{foreach $legacy_schedules as $s}
<tr>
<td class="a4-sqliimport-name">{if ne( $s.label, '' )}{$s.label|wash}{else}#{$s.id}{/if}<br /><span class="a4-meta">{$s.handler_name|wash}</span>{if ne( $s.options, '' )}<br /><span class="a4-meta a4-sqliimport-options-text">{$s.options|wash}</span>{/if}</td>
<td>{switch match=$s.frequency}
{case match='none'}{'Once'|i18n( 'extension/sqliimport/schedulefrequency' )}{/case}
{case match='hourly'}{'Hourly'|i18n( 'extension/sqliimport/schedulefrequency' )}{/case}
{case match='daily'}{'Daily'|i18n( 'extension/sqliimport/schedulefrequency' )}{/case}
{case match='weekly'}{'Weekly'|i18n( 'extension/sqliimport/schedulefrequency' )}{/case}
{case match='monthly'}{'Monthly'|i18n( 'extension/sqliimport/schedulefrequency' )}{/case}
{case match='manual'}{'Every %n minutes'|i18n( 'extension/sqliimport/schedulefrequency',, hash( '%n', $s.manual_frequency ) )}{/case}
{case}{$s.frequency|wash}{/case}
{/switch}
{if gt( $s.migrated_import_id, 0 )} <span class="a4-badge is-info">{'migrated'|i18n( 'extension/sqliimport' )}</span>{elseif $s.is_active} <span class="a4-badge is-ok">{'active'|i18n( 'extension/sqliimport' )}</span>{else} <span class="a4-badge">{'disabled'|i18n( 'extension/sqliimport' )}</span>{/if}</td>
<td class="a4-nowrap">{if and( $s.is_active, gt( $s.next, 0 ), eq( $s.migrated_import_id, 0 ) )}{$s.next|l10n( 'shortdatetime' )}{else}<span class="a4-meta">–</span>{/if}</td>
<td class="a4-end"><div class="a4-sqliimport-actions">
{if gt( $s.migrated_import_id, 0 )}
<a class="a4-btn a4-btn-small" href={concat( '/sqliimport/edit/', $s.migrated_import_id, '/(step)/schedule' )|ezurl}>{'Open the import'|i18n( 'extension/sqliimport' )}</a>
{else}
{if $s.can_run}{include uri='design:sqliimport/parts/button.tpl' action='legacy_schedule_run' legacy_schedule_id=$s.id here=$here small=1 kind='primary' label='Run now'|i18n( 'extension/sqliimport' )}{/if}
{if $s.can_edit}
<a class="a4-btn a4-btn-small" href={concat( '/sqliimport/schedules/(legacy)/', $s.id )|ezurl}>{'Edit'|i18n( 'extension/sqliimport' )}</a>
{if $s.is_active}{include uri='design:sqliimport/parts/button.tpl' action='legacy_schedule_disable' legacy_schedule_id=$s.id here=$here small=1 label='Disable'|i18n( 'extension/sqliimport' )}{else}{include uri='design:sqliimport/parts/button.tpl' action='legacy_schedule_enable' legacy_schedule_id=$s.id here=$here small=1 label='Enable'|i18n( 'extension/sqliimport' )}{/if}
{include uri='design:sqliimport/parts/button.tpl' action='legacy_schedule_delete' legacy_schedule_id=$s.id here=$here small=1 kind='danger' label='Delete'|i18n( 'extension/sqliimport' ) confirm='Are you sure you want to remove this scheduled import?'|i18n( 'extension/sqliimport' )}
{/if}
{/if}
</div></td>
</tr>
{/foreach}
</tbody>
</table>
</div>
{/if}
</section>
{/if}
