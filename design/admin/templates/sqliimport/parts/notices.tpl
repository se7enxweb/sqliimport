{* The current user's new notices of runs that ended (expImportNotifier): one line each with its run, and Mark all
   as read. Input: $notices (expImportNotifier::notices()). Nothing when there is none. *}
{if $notices|count|gt( 0 )}
<section class="a4-notice is-warn a4-sqliimport-notices" aria-labelledby="sq-notices-title">
<h2 class="a4-h3" id="sq-notices-title">{'New since you last looked'|i18n( 'extension/sqliimport' )}</h2>
<ul class="a4-sqliimport-notice-list">
{foreach $notices as $sq_nt}
<li><a href={$sq_nt.url|ezurl}>{'Run #%run'|i18n( 'extension/sqliimport',, hash( '%run', $sq_nt.run_id ) )}</a> · {$sq_nt.import|wash} · <span class="a4-badge{if eq( $sq_nt.kind, 'failed' )} is-bad{elseif eq( $sq_nt.kind, 'errors' )} is-warn{else} is-ok{/if}">{if eq( $sq_nt.kind, 'failed' )}{'failed'|i18n( 'extension/sqliimport' )}{elseif eq( $sq_nt.kind, 'errors' )}{'completed with errors'|i18n( 'extension/sqliimport' )}{elseif eq( $sq_nt.kind, 'cancelled' )}{'cancelled'|i18n( 'extension/sqliimport' )}{else}{'completed'|i18n( 'extension/sqliimport' )}{/if}</span> <span class="a4-meta">{$sq_nt.created|l10n( 'shortdatetime' )}</span></li>
{/foreach}
</ul>
{include uri='design:sqliimport/parts/button.tpl' action='notices_seen' here='/sqliimport/list' label='Mark all as read'|i18n( 'extension/sqliimport' ) small=1}
</section>
{/if}
