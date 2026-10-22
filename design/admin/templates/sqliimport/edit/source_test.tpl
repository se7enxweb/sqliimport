{* "Test connection" of a format that can try its connection (included from source.tpl inside the format's options):
   the button, the result of the last test of this format (what it found, or the error; never a password or a key)
   and the drivers the format can use here. Input: $sq_tst_format (the format id), $connection_test (false, or the
   result of expImportEditor::testConnection()), $source_drivers (format => drivers). *}
<div class="a4-field a4-field-wide">
<div class="a4-toolbar">
<button type="submit" class="a4-btn" name="TestConnectionButton" value="{$sq_tst_format|wash}" formnovalidate="formnovalidate"><span class="a4-icon a4-bi-plug" aria-hidden="true"></span> {'Test connection'|i18n( 'extension/sqliimport/edit' )}</button>
<span class="a4-help">{'Connects with the options above and reads the first record. Nothing is saved.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
{if $connection_test}{if eq( $connection_test.format, $sq_tst_format )}
{if $connection_test.ok}
<p class="a4-notice is-ok" role="status">{'The connection works.'|i18n( 'extension/sqliimport/edit' )}{if $connection_test.columns|count|gt( 0 )} {'Its fields:'|i18n( 'extension/sqliimport/edit' )} {foreach $connection_test.columns as $sq_tst_col}<code>{$sq_tst_col|wash}</code>{delimiter}, {/delimiter}{/foreach}{/if}{if ne( $connection_test.message, '' )} {$connection_test.message|wash}{/if}</p>
{else}
<p class="a4-notice is-bad" role="alert">{'The test failed:'|i18n( 'extension/sqliimport/edit' )} {$connection_test.message|wash}</p>
{/if}
{/if}{/if}
{if is_set( $source_drivers[$sq_tst_format] )}
<details class="a4-fold"><summary>{'What this server can connect with'|i18n( 'extension/sqliimport/edit' )}</summary>
{include uri='design:sqliimport/parts/source_drivers.tpl' drivers=$source_drivers[$sq_tst_format]}
</details>
{/if}
</div>
