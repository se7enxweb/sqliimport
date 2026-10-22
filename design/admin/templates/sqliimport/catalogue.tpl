{* sqliimport/catalogue: what this site can import from and with: the formats by group (description, files,
   examples, the mapping a format comes with, New import), the handlers (1.x ones marked legacy) and the transforms. *}
<div class="context-block a4-page a4-sqliimport">
<div class="box-header"><div class="box-ml">
<h1 class="context-title">{'Catalogue'|i18n( 'extension/sqliimport' )}</h1>
</div></div>
<div class="box-bc"><div class="box-ml"><div class="box-content">
<p class="a4-intro">{'Formats are kernel services tagged sqliimport.source (one source class and one block in kernelservices.ini each); handlers and transforms come from sqliimport.ini and from services tagged sqliimport.handler and sqliimport.transform.'|i18n( 'extension/sqliimport' )}</p>

{foreach $sources_by_group as $gid => $g}
<section class="a4-section" aria-labelledby="sq-cat-{$gid|wash}">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-cat-{$gid|wash}">{$g.label|wash}</h2></div>
<div class="a4-sqliimport-formats">
{foreach $g.sources as $id => $s}
<article class="a4-card" aria-labelledby="sq-fmt-{$id|wash}">
<div class="a4-card-head">
<h3 class="a4-h3" id="sq-fmt-{$id|wash}">{$s.label|wash}</h3>
<span class="a4-badge{if eq( $s.status, 'enabled' )} is-ok{elseif eq( $s.status, 'example' )} is-info{/if}">{$s.status|wash}</span>
</div>
{if ne( $s.description, '' )}<p class="a4-sqliimport-format-desc">{$s.description|wash}</p>{/if}
{if is_set( $source_drivers[$id] )}{if $source_drivers[$id]|count|gt( 0 )}
<details class="a4-fold"><summary>{'What this server can connect with'|i18n( 'extension/sqliimport' )}</summary>
{include uri='design:sqliimport/parts/source_drivers.tpl' drivers=$source_drivers[$id]}
</details>
{/if}{/if}
<p class="a4-meta">{'Files'|i18n( 'extension/sqliimport' )}: {$s.extensions|implode( ', ' )|wash} · <code>{$id|wash}</code>{if $s.schema|count|gt( 0 )} · {'Options'|i18n( 'extension/sqliimport' )}: {foreach $s.schema as $o => $d}<code>{$o|wash}</code>{delimiter}, {/delimiter}{/foreach}{/if}</p>
{if $s.preset|count|gt( 0 )}
<details class="a4-fold"><summary>{'Comes with a mapping to the class %class'|i18n( 'extension/sqliimport',, hash( '%class', $s.preset.class ) )|wash}</summary>
<ul class="a4-sqliimport-format-list">{foreach $s.preset.fields as $f}<li><code>{$f.source|wash}</code> → <code>{$f.attribute|wash}</code>{if is_set( $f.transforms )}{if $f.transforms|count|gt( 0 )} <span class="a4-meta">({foreach $f.transforms as $t}{$t.name|wash}{delimiter} | {/delimiter}{/foreach})</span>{/if}{/if}</li>{/foreach}</ul>
</details>
{/if}
<div class="a4-sqliimport-format-foot">
{foreach $s.examples as $n => $e}
{if or( is_set( $e.file ), is_set( $e.path ) )}<a class="a4-btn a4-btn-ghost" href={concat( '/sqliimport/catalogue/(example)/', $id, '/(n)/', $n )|ezurl} download title="{$e.label|wash}"><span class="a4-icon a4-bi-download" aria-hidden="true"></span> {first_set( $e.file, $e.path )|wash}</a>
{elseif is_set( $e.url )}<a class="a4-btn a4-btn-ghost" href="{$e.url|wash}" rel="noopener noreferrer" target="_blank" title="{$e.label|wash}"><span class="a4-icon a4-bi-box-arrow-up-right" aria-hidden="true"></span> {'Example'|i18n( 'extension/sqliimport' )}</a>{/if}
{/foreach}
{foreach $s.examples as $e}{if and( is_unset( $e.url ), is_set( $e.options ), is_set( $s.origins ) )}{if $s.origins|contains( 'remote' )}<p class="a4-help">{'Example'|i18n( 'extension/sqliimport' )}: {$e.label|wash}</p>{/if}{/if}{/foreach}
{if and( $can_manage, eq( $s.status, 'enabled' ) )}<a class="a4-btn a4-btn-primary" href={concat( '/sqliimport/edit/new/(source)/', $id )|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'New import'|i18n( 'extension/sqliimport' )}</a>{/if}
</div>
</article>
{/foreach}
</div>
</section>
{/foreach}

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
