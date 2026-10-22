{* The source step's "Test connection" of the SQL source (included from source.tpl for the format sql): the button,
   the result of the last test (the columns of the query, or the error, never a password) and the databases this
   PHP can read. Input: $sql_test (false, or expImportSqlSource::test() of the posted options), $sql_drivers. *}
<div class="a4-field a4-field-wide">
<div class="a4-toolbar">
<button type="submit" class="a4-btn" name="TestConnectionButton" value="1" formnovalidate="formnovalidate"><span class="a4-icon a4-bi-plug" aria-hidden="true"></span> {'Test connection'|i18n( 'extension/sqliimport/edit' )}</button>
<span class="a4-help">{'Connects with the options above and reads the first row of the query. Nothing is saved.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
{if $sql_test}
{if $sql_test.ok}
<p class="a4-notice is-ok" role="status">{'The connection works.'|i18n( 'extension/sqliimport/edit' )} {if gt( $sql_test.rows, 0 )}{'The query gives the columns:'|i18n( 'extension/sqliimport/edit' )} {foreach $sql_test.columns as $sq_st_col}<code>{$sq_st_col|wash}</code>{delimiter}, {/delimiter}{/foreach}{else}{'The query gives no row.'|i18n( 'extension/sqliimport/edit' )}{if $sql_test.columns|count|gt( 0 )} {'Its columns:'|i18n( 'extension/sqliimport/edit' )} {foreach $sql_test.columns as $sq_st_col}<code>{$sq_st_col|wash}</code>{delimiter}, {/delimiter}{/foreach}{/if}{/if}</p>
{else}
<p class="a4-notice is-bad" role="alert">{'The test failed:'|i18n( 'extension/sqliimport/edit' )} {$sql_test.message|wash}</p>
{/if}
{/if}
<details class="a4-fold"{if $sql_test}{if $sql_test.ok|not} open="open"{/if}{/if}><summary>{'Databases this server can read'|i18n( 'extension/sqliimport/edit' )}</summary>
{include uri='design:sqliimport/parts/sql_drivers.tpl' drivers=$sql_drivers}
</details>
</div>
