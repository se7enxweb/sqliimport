{* The badge of a run status. Input: $label (the translated status), $kind (is-ok, is-bad, is-warn, is-accent or '');
   optional $field (data-sq-field, for the live run page). *}
<span class="a4-badge{if ne( $kind, '' )} {$kind}{/if}"{if is_set( $field )} data-sq-field="{$field|wash}"{/if}>{$label|wash}</span>
