{* The badge of a run status. Input: $label (the translated status), $kind (is-ok, is-bad, is-warn, is-accent or ''). *}
<span class="a4-badge{if ne( $kind, '' )} {$kind}{/if}">{$label|wash}</span>
