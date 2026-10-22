{* sqliimport/edit: the import editor (expImportEditor). The steps as ol.a4-steps (a step of a stored import is a link),
   the errors of the last save as an .a4-error-summary, then the form of the step (sqliimport/edit/<step>.tpl). Every
   form is a POST with the form token; no inline CSS or script. *}
{def $base = cond( $import, concat( '/sqliimport/edit/', $import.id ), '/sqliimport/edit/new' )
     $names = hash( 'source', 'Source'|i18n( 'extension/sqliimport/edit' ), 'processing', 'Processing'|i18n( 'extension/sqliimport/edit' ),
                    'mapping', 'Mapping'|i18n( 'extension/sqliimport/edit' ), 'preview', 'Preview'|i18n( 'extension/sqliimport/edit' ),
                    'schedule', 'Schedule'|i18n( 'extension/sqliimport/edit' ), 'review', 'Review and save'|i18n( 'extension/sqliimport/edit' ) )
     $state = 'done'}
<div class="context-block a4-page a4-sqliimport">

<div class="box-header"><div class="box-ml">
<h1 class="context-title">{if $import}{'Edit import: %name'|i18n( 'extension/sqliimport/edit',, hash( '%name', $import.name ) )|wash}{else}{'New import'|i18n( 'extension/sqliimport' )}{/if}</h1>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

<nav aria-label="{'Steps of the import editor'|i18n( 'extension/sqliimport/edit' )|wash}">
<ol class="a4-steps a4-sqliimport-steps">
{foreach $steps as $s}
{if eq( $s, $step )}
<li class="is-current" aria-current="step">{$names[$s]|wash}</li>
{set $state = 'todo'}
{elseif $import}
<li{if eq( $state, 'done' )} class="is-done"{/if}><a href={concat( $base, '/(step)/', $s )|ezurl}>{$names[$s]|wash}</a></li>
{else}
<li>{$names[$s]|wash}</li>
{/if}
{/foreach}
</ol>
</nav>

{if ne( $notice, '' )}<p class="a4-notice is-info" role="status">{$notice|wash}</p>{/if}
{if $errors|count|gt( 0 )}
<div class="a4-notice is-bad a4-error-summary" id="a4-error-summary" role="alert" tabindex="-1" aria-labelledby="a4-error-summary-title">
<p class="a4-notice-title" id="a4-error-summary-title">{'Please check these points:'|i18n( 'extension/sqliimport/edit' )}</p>
<ul>{foreach $error_links as $sq_el}<li>{if ne( $sq_el.target, '' )}<a href="#{$sq_el.target|wash}">{$sq_el.message|wash}</a>{else}{$sq_el.message|wash}{/if}</li>{/foreach}</ul>
<p class="a4-meta">{'What you entered is kept; a file that was uploaded stays uploaded.'|i18n( 'extension/sqliimport/edit' )}</p>
</div>
{/if}

{include uri=concat( 'design:sqliimport/edit/', $step, '.tpl' ) base=$base}

</div></div></div>

</div>
{undef $base $names $state}
