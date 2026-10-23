{* Delete a template of this site (sqliimport/action template_delete, asked first with data-a4-confirm). Input:
   $template_id, $title. *}
<form class="a4-sqliimport-inline" method="post" action={'/sqliimport/action'|ezurl} data-a4-confirm="{'Delete this template? Imports made from it stay.'|i18n( 'extension/sqliimport' )|wash}">
<input type="hidden" name="Action" value="template_delete" />
<input type="hidden" name="RedirectURI" value="/sqliimport/templates" />
<input type="hidden" name="TemplateID" value="{$template_id|wash}" />
<button type="submit" class="a4-btn a4-btn-small a4-btn-outline-danger">{'Delete'|i18n( 'extension/sqliimport' )}<span class="a4-sr-only"> {$title|wash}</span></button>
</form>
