{* The actions of an import in the list: Run now and Dry run (policy run), Edit, and under More: Duplicate, Enable or
   Disable, Delete (policy edit). Input: $i (an entry of expImportAdmin::imports()), $here. *}
<div class="a4-sqliimport-actions">
{if $i.can_run}
{if $i.import.is_enabled}{include uri='design:sqliimport/parts/button.tpl' action='run' import_id=$i.import.id here=$here small=1 label='Run now'|i18n( 'extension/sqliimport' ) kind='primary'}{/if}
{if $i.import.is_legacy|not}{include uri='design:sqliimport/parts/button.tpl' action='dryrun' import_id=$i.import.id here=$here small=1 label='Dry run'|i18n( 'extension/sqliimport' )}{/if}
{/if}
{if $i.can_edit}
<a class="a4-btn a4-btn-small" href={concat( '/sqliimport/edit/', $i.import.id, '/(step)/source' )|ezurl}>{'Edit'|i18n( 'extension/sqliimport' )}</a>
<details class="a4-fold a4-sqliimport-more">
<summary>{'More'|i18n( 'extension/sqliimport' )}<span class="a4-sr-only"> {$i.import.name|wash}</span></summary>
<div class="a4-sqliimport-more-body">
{include uri='design:sqliimport/parts/button.tpl' action='duplicate' import_id=$i.import.id here=$here small=1 label='Duplicate'|i18n( 'extension/sqliimport' )}
{if $i.import.is_enabled}{include uri='design:sqliimport/parts/button.tpl' action='disable' import_id=$i.import.id here=$here small=1 label='Disable'|i18n( 'extension/sqliimport' )}{else}{include uri='design:sqliimport/parts/button.tpl' action='enable' import_id=$i.import.id here=$here small=1 label='Enable'|i18n( 'extension/sqliimport' )}{/if}
{include uri='design:sqliimport/parts/button.tpl' action='delete' import_id=$i.import.id here=$here small=1 kind='danger' label='Delete'|i18n( 'extension/sqliimport' ) confirm='Delete this import with its mapping and schedules? Its runs stay in the history.'|i18n( 'extension/sqliimport' )}
</div>
</details>
{/if}
</div>
