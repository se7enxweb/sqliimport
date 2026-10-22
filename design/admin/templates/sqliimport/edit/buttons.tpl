{* The buttons at the end of an editor step. Input: $step; optional $stay (show Save and stay), $last. *}
<input type="hidden" name="Step" value="{$step|wash}" />
<div class="a4-toolbar is-bottom">
{if is_set( $last )}
<button type="submit" class="a4-btn a4-btn-primary" name="SaveButton" value="1">{'Save'|i18n( 'extension/sqliimport/edit' )}</button>
<button type="submit" class="a4-btn" name="SaveAndRunButton" value="1"><span class="a4-icon a4-bi-play-fill" aria-hidden="true"></span> {'Save and run'|i18n( 'extension/sqliimport/edit' )}</button>
{else}
<button type="submit" class="a4-btn a4-btn-primary" name="SaveButton" value="1">{'Save and continue'|i18n( 'extension/sqliimport/edit' )}</button>
{if is_set( $stay )}<button type="submit" class="a4-btn" name="StayButton" value="1">{'Save'|i18n( 'extension/sqliimport/edit' )}</button>{/if}
{/if}
<a class="a4-btn a4-btn-ghost" href={'/sqliimport/list'|ezurl}>{'Back to the imports'|i18n( 'extension/sqliimport/edit' )}</a>
</div>
