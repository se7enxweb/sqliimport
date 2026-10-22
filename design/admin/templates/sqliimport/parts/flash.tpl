{* The message of the last action (sqliimport/action). Input: $flash (kind ok, warn, bad; message), may be null. *}
{if and( is_set( $flash ), $flash )}
<p class="a4-notice is-{$flash.kind|wash}" role="status">{$flash.message|wash}</p>
{/if}
