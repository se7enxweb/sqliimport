{* One action of the 2.x pages: a small POST form to sqliimport/action (ezformtoken adds its token to every POST
   form). Input: $action (run, dryrun, cancel, retry), $label, $here (the page to come back to); optional $import_id,
   $run_id, $schedule_id, $kind (primary, danger), $icon (an .a4-bi-* name), $confirm (asked before it is sent: data-a4-confirm, asked
   by admin4's a4_actions.js and, in the other administration designs, by javascript/sqliimport_confirm.js; no
   inline handler, so the page works under a Content Security Policy). Shown only to users who may run (the caller
   checks). *}
<form class="a4-sqliimport-inline" method="post" action={'/sqliimport/action'|ezurl}{if is_set( $confirm )} data-a4-confirm="{$confirm|wash}"{/if}>
<input type="hidden" name="Action" value="{$action|wash}" />
<input type="hidden" name="RedirectURI" value="{$here|wash}" />
{if is_set( $import_id )}<input type="hidden" name="ImportID" value="{$import_id|wash}" />{/if}
{if is_set( $run_id )}<input type="hidden" name="RunID" value="{$run_id|wash}" />{/if}
{if is_set( $schedule_id )}<input type="hidden" name="ScheduleID" value="{$schedule_id|wash}" />{/if}
<button type="submit" class="a4-btn{if is_set( $small )} a4-btn-small{/if}{if is_set( $kind )}{if eq( $kind, 'primary' )} a4-btn-primary{elseif eq( $kind, 'danger' )} a4-btn-outline-danger{/if}{/if}">{if is_set( $icon )}<span class="a4-icon a4-bi-{$icon}" aria-hidden="true"></span> {/if}{$label|wash}</button>
</form>
