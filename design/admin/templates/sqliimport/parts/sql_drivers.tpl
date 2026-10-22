{* The databases the SQL source can read in this PHP (green: a driver is there, grey: none), what the job queue's
   workers have (they read the runs, and their PHP may differ from the web server's) and what the host needs for a
   missing driver. Input: $drivers (expImportSqlSource::drivers()). *}
<ul class="a4-sqliimport-format-list">
{foreach $drivers as $sq_dr_id => $sq_dr}
<li><span class="a4-badge{if $sq_dr.available} is-ok{/if}">{if $sq_dr.available}{'available'|i18n( 'extension/sqliimport' )}{else}{'no driver'|i18n( 'extension/sqliimport' )}{/if}</span> {$sq_dr.label|wash}{if $sq_dr.available} <span class="a4-meta">{$sq_dr.via|wash}</span>{/if}
{if eq( $sq_dr.worker, 'yes' )}{if $sq_dr.available|not} <span class="a4-badge is-ok">{'in the workers'|i18n( 'extension/sqliimport' )}</span> <span class="a4-meta">{'Runs work: the workers of the job queue have a driver (%php).'|i18n( 'extension/sqliimport',, hash( '%php', $sq_dr.worker_php ) )|wash}</span>{/if}
{elseif eq( $sq_dr.worker, 'no' )}{if $sq_dr.available} <span class="a4-badge is-warn">{'not in the workers'|i18n( 'extension/sqliimport' )}</span> <span class="a4-meta">{'The workers of the job queue (%php), which read the runs, have no driver.'|i18n( 'extension/sqliimport',, hash( '%php', $sq_dr.worker_php ) )|wash}</span>{/if}
{/if}
{if or( $sq_dr.available|not, eq( $sq_dr.worker, 'no' ) )}<br /><span class="a4-help">{'The host needs:'|i18n( 'extension/sqliimport' )} {$sq_dr.needs|wash}</span>{/if}
</li>
{/foreach}
</ul>
