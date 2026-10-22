{* sqliimport/catalogue: what this site can import from and with: the source types, the handlers (1.x ones marked
   legacy) and the transforms, with their options. *}
<div class="context-block a4-page a4-sqliimport">
<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Catalogue'|i18n( 'extension/sqliimport' )}</h1>
</div></div>
<div class="box-bc"><div class="box-ml"><div class="box-content">
<p class="a4-intro">{'Sources, handlers and transforms come from sqliimport.ini and from kernel services tagged sqliimport.source, sqliimport.handler and sqliimport.transform.'|i18n( 'extension/sqliimport' )}</p>

<section class="a4-section" aria-labelledby="sq-cat-sources">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-cat-sources">{'Sources'|i18n( 'extension/sqliimport' )}</h2></div>
<table class="a4-table">
<thead><tr><th>{'Source'|i18n( 'extension/sqliimport' )}</th><th>{'Files'|i18n( 'extension/sqliimport' )}</th><th>{'Options'|i18n( 'extension/sqliimport' )}</th><th>{'Status'|i18n( 'extension/sqliimport' )}</th></tr></thead>
<tbody>{foreach $sources as $id => $s}<tr><td>{$s.label|wash}<br /><span class="a4-meta">{$id|wash} · {$s.class|wash}</span></td><td>{$s.extensions|implode( ', ' )|wash}</td><td>{foreach $s.schema as $o => $d}<code>{$o|wash}</code>{delimiter}, {/delimiter}{/foreach}</td><td><span class="a4-badge{if eq( $s.status, 'enabled' )} is-ok{elseif eq( $s.status, 'example' )} is-info{/if}">{$s.status|wash}</span></td></tr>{/foreach}</tbody>
</table>
</section>

<section class="a4-section" aria-labelledby="sq-cat-handlers">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-cat-handlers">{'Handlers'|i18n( 'extension/sqliimport' )}</h2></div>
<table class="a4-table">
<thead><tr><th>{'Handler'|i18n( 'extension/sqliimport' )}</th><th>{'Class'|i18n( 'extension/sqliimport' )}</th><th>{'Options'|i18n( 'extension/sqliimport' )}</th><th>{'Status'|i18n( 'extension/sqliimport' )}</th></tr></thead>
<tbody>{foreach $handlers as $id => $h}<tr><td>{$h.label|wash}<br /><span class="a4-meta">{$id|wash}</span>{if $h.legacy} <span class="a4-badge is-info">{'1.x handler'|i18n( 'extension/sqliimport' )}</span>{/if}</td><td><code>{$h.class|wash}</code></td><td>{if $h.legacy}<span class="a4-meta">{'free name=value options'|i18n( 'extension/sqliimport' )}</span>{else}{foreach $h.schema as $o => $d}<code>{$o|wash}</code>{delimiter}, {/delimiter}{/foreach}{/if}</td><td><span class="a4-badge{if eq( $h.status, 'enabled' )} is-ok{/if}">{$h.status|wash}</span></td></tr>{/foreach}</tbody>
</table>
</section>

<section class="a4-section" aria-labelledby="sq-cat-transforms">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-cat-transforms">{'Transforms'|i18n( 'extension/sqliimport' )}</h2></div>
<table class="a4-table is-compact">
<thead><tr><th>{'Name'|i18n( 'extension/sqliimport' )}</th><th>{'What it does'|i18n( 'extension/sqliimport' )}</th><th>{'Arguments'|i18n( 'extension/sqliimport' )}</th><th>{'Origin'|i18n( 'extension/sqliimport' )}</th></tr></thead>
<tbody>{foreach $transforms as $name => $t}<tr><td><code>{$name|wash}</code></td><td>{$t.label|wash}</td><td>{foreach $t.schema as $o => $d}<code>{$o|wash}</code>{if is_set( $d.required )}{if $d.required}*{/if}{/if}{delimiter}, {/delimiter}{/foreach}</td><td>{if $t.builtin}{'built in'|i18n( 'extension/sqliimport' )}{else}{'site'|i18n( 'extension/sqliimport' )}{/if}</td></tr>{/foreach}</tbody>
</table>
</section>
</div></div></div>
</div>
