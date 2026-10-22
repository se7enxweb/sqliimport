{* The import requests of 1.x on the dashboard (they were the page sqliimport/items): a request of an import of a 1.x
   handler (it was sqliimport/addimport), the requests with their status, progress, notes and run, Cancel (pending) or
   Interrupt (running), and Purge history (it was sqliimport/purgelist). Every change is a POST to sqliimport/action.
   Shown when 1.x handlers are configured or requests exist. Input: $legacy_items, $legacy_count, $legacy_offset,
   $legacy_here, $legacy_prev_url, $legacy_next_url, $legacy_handlers (id => name the user may run), $can_purge. *}
{if or( $legacy_count|gt( 0 ), $legacy_handlers|count|gt( 0 ) )}
<section class="a4-section" id="a4-sqliimport-legacy" aria-labelledby="a4-sqliimport-legacy-title">
<div class="a4-section-head">
<h2 class="a4-h2" id="a4-sqliimport-legacy-title">{'1.x import requests'|i18n( 'extension/sqliimport' )}</h2>
{if gt( $legacy_count, 0 )}<span class="a4-meta">{'%from to %to of %count'|i18n( 'extension/sqliimport',, hash( '%from', sum( $legacy_offset, 1 ), '%to', sum( $legacy_offset, $legacy_items|count ), '%count', $legacy_count ) )}</span>{/if}
{if and( $can_purge, $legacy_count|gt( 0 ) )}<div class="a4-sqliimport-head-end">{include uri='design:sqliimport/parts/button.tpl' action='legacy_purge' here=$legacy_here small=1 kind='danger' icon='trash' label='Purge history'|i18n( 'extension/sqliimport' ) confirm='Remove every ended 1.x import request from the list? Pending and running requests stay.'|i18n( 'extension/sqliimport' )}</div>{/if}
<p>{'Imports of the handlers of sqliimport 1.x, requested here, by a 1.x scheduled import or by a script. The cron part "sqliimport" starts each pending request as a run of the handler; its progress and log are on the run page.'|i18n( 'extension/sqliimport' )}</p>
</div>

{if $legacy_handlers|count|gt( 0 )}
<form class="a4-filterbar" id="a4-sqliimport-legacy-request" method="post" action={'/sqliimport/action'|ezurl}>
<input type="hidden" name="Action" value="legacy_request" />
<input type="hidden" name="RedirectURI" value="{$legacy_here|wash}" />
<div class="a4-field">
<label for="a4-sqliimport-legacy-handler">{'1.x handler'|i18n( 'extension/sqliimport' )}</label>
<select id="a4-sqliimport-legacy-handler" name="ImportHandler">
{foreach $legacy_handlers as $id => $name}<option value="{$id|wash}">{$name|wash}</option>{/foreach}
</select>
</div>
<div class="a4-field">
<label for="a4-sqliimport-legacy-options">{'Options (optional), one name=value per line'|i18n( 'extension/sqliimport' )}</label>
<textarea id="a4-sqliimport-legacy-options" name="ImportOptions" rows="1" placeholder="name=value"></textarea>
</div>
<div class="a4-field">
<button type="submit" class="a4-btn a4-btn-primary"><span class="a4-icon a4-bi-play-fill" aria-hidden="true"></span> {'Request import'|i18n( 'extension/sqliimport' )}</button>
</div>
</form>
{/if}

{if $legacy_items|count|eq( 0 )}
<div class="a4-empty">
<p><strong>{'No 1.x import request.'|i18n( 'extension/sqliimport' )}</strong></p>
<p>{'Choose a 1.x handler above and request an import; or let it run by itself with a 1.x scheduled import.'|i18n( 'extension/sqliimport' )}</p>
<p><a class="a4-btn" href={'/sqliimport/schedules'|ezurl}><span class="a4-icon a4-bi-calendar3" aria-hidden="true"></span> {'Schedules'|i18n( 'extension/sqliimport' )}</a></p>
</div>
{else}
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="a4-sqliimport-legacy-title">
<table class="a4-table is-compact">
<thead><tr>
<th>{'Request'|i18n( 'extension/sqliimport' )}</th>
<th>{'Handler'|i18n( 'extension/sqliimport' )}</th>
<th>{'Status'|i18n( 'extension/sqliimport' )}</th>
<th>{'Progress'|i18n( 'extension/sqliimport' )}</th>
<th class="a4-sqliimport-hide-narrow">{'Requested on'|i18n( 'extension/sqliimport' )}</th>
<th><span class="a4-sr-only">{'Actions'|i18n( 'extension/sqliimport' )}</span></th>
</tr></thead>
<tbody>
{foreach $legacy_items as $l}
<tr>
<td class="a4-nowrap">#{$l.id}<br /><span class="a4-meta">{if $l.scheduled}{if gt( $l.scheduled_id, 0 )}<a href={concat( '/sqliimport/schedules/(legacy)/', $l.scheduled_id )|ezurl}>{'Scheduled'|i18n( 'extension/sqliimport/type' )}</a>{else}{'Scheduled'|i18n( 'extension/sqliimport/type' )}{/if}{else}{'Immediate'|i18n( 'extension/sqliimport/type' )}{/if}</span></td>
<td class="a4-sqliimport-name">{$l.handler_name|wash}{if ne( $l.options, '' )}<br /><span class="a4-meta a4-sqliimport-options-text">{$l.options|wash}</span>{/if}</td>
<td>{include uri='design:sqliimport/parts/status.tpl' label=$l.status_label kind=$l.status_kind}</td>
<td class="a4-sqliimport-notes"><div class="a4-meter{if eq( $l.status_code, 'failed' )} is-bad{elseif eq( $l.status_code, 'completed' )} is-ok{/if} a4-sqliimport-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{$l.percent}" aria-label="{'Progress'|i18n( 'extension/sqliimport' )|wash}"><span class="a4-pct-{$l.percent}"></span></div><span class="a4-meta">{$l.percent}%{if ne( $l.notes, '' )} · {$l.notes|wash}{/if}</span></td>
<td class="a4-sqliimport-hide-narrow a4-nowrap">{$l.requested_time|l10n( 'shortdatetime' )}<br /><span class="a4-meta">{if ne( $l.user, '' )}{$l.user|wash} · {/if}{$l.duration|wash}</span></td>
<td class="a4-end"><div class="a4-sqliimport-actions">
{if gt( $l.run_id, 0 )}<a class="a4-btn a4-btn-small" href={concat( '/sqliimport/run/', $l.run_id )|ezurl}>{'Run #%id'|i18n( 'extension/sqliimport',, hash( '%id', $l.run_id ) )}</a>{/if}
{if $l.can_change}
{if eq( $l.status_code, 'pending' )}{include uri='design:sqliimport/parts/button.tpl' action='legacy_cancel' item_id=$l.id here=$legacy_here small=1 kind='danger' label='Cancel'|i18n( 'extension/sqliimport' ) confirm='Are you sure you want to cancel this import?'|i18n( 'extension/sqliimport' )}
{elseif eq( $l.status_code, 'running' )}{include uri='design:sqliimport/parts/button.tpl' action='legacy_interrupt' item_id=$l.id here=$legacy_here small=1 kind='danger' label='Interrupt'|i18n( 'extension/sqliimport' ) confirm='Are you sure you want to interrupt this import?'|i18n( 'extension/sqliimport' )}{/if}
{/if}
</div></td>
</tr>
{/foreach}
</tbody>
</table>
</div>
<p class="a4-row a4-pager">
{if ne( $legacy_prev_url, '' )}<a class="a4-btn a4-btn-small" href={concat( $legacy_prev_url, '#a4-sqliimport-legacy' )|ezurl}>{'Newer'|i18n( 'extension/sqliimport' )}</a>{/if}
{if ne( $legacy_next_url, '' )}<a class="a4-btn a4-btn-small" href={concat( $legacy_next_url, '#a4-sqliimport-legacy' )|ezurl}>{'Older'|i18n( 'extension/sqliimport' )}</a>{/if}
</p>
{/if}
</section>
{/if}
