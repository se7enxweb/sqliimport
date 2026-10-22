{* One action of the import pages as a small POST form (ezformtoken adds its token to every POST form). Changes are
   never made by a GET link. Input: $url (the action URL), $label; optional $confirm (a question asked before it is
   sent: data-a4-confirm, asked by admin4's a4_actions.js and, in the other administration designs, by
   javascript/sqliimport_confirm.js; no inline handler, so the page works under a Content Security Policy), $kind
   (danger). *}
<form class="a4-sqliimport-inline" method="post" action={$url|ezurl}{if is_set( $confirm )} data-a4-confirm="{$confirm|wash}"{/if}>
<button type="submit" class="a4-btn a4-btn-small button{if and( is_set( $kind ), eq( $kind, 'danger' ) )} a4-btn-outline-danger{/if}">{$label|wash}</button>
</form>
