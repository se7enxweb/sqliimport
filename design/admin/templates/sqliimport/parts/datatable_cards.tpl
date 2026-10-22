{* The catalogue's group "Data tables" (Exponential 6.0.15, Setup > Data tables): what 7x SQLI Import does with the
   kernel's data tables. Everything runs in the kernel: an import writes rows through its writer, a new table comes
   from its module table wizard, a synchronisation is its definition and its runner. Input: $data_tables
   (expImportDataTables::forCatalogue()), $can_manage. Nothing is shown on an older kernel. *}
{if $data_tables.available}
<section class="a4-section" aria-labelledby="sq-cat-datatables">
<div class="a4-section-head"><h2 class="a4-h2" id="sq-cat-datatables">{'Data tables'|i18n( 'extension/sqliimport' )}</h2></div>
<p class="a4-help">{'Tables of modules and extensions (persistent classes): import into them, make a new one from an import, keep them and content in sync. Setup, Data tables lists them.'|i18n( 'extension/sqliimport' )}</p>
<div class="a4-sqliimport-formats">

{if $data_tables.writer}
<article class="a4-card" aria-labelledby="sq-dt-import">
<div class="a4-card-head"><h3 class="a4-h3" id="sq-dt-import">{'Import into a data table'|i18n( 'extension/sqliimport' )}</h3></div>
<p class="a4-sqliimport-format-desc">{'Any format can fill the table of a persistent class instead of making content: choose the target "Rows of a data table" in the step Processing. Rows are matched by a key, checked against the class and written by the kernel, never with raw SQL.'|i18n( 'extension/sqliimport' )}</p>
<p class="a4-meta">{'Data tables of this site: %n'|i18n( 'extension/sqliimport',, hash( '%n', $data_tables.tables|count ) )}</p>
{if $can_manage}<a class="a4-btn a4-btn-primary" href={'/sqliimport/edit/new'|ezurl}><span class="a4-icon a4-bi-plus-lg" aria-hidden="true"></span> {'New import'|i18n( 'extension/sqliimport' )}</a>{/if}
</article>
{/if}

{if $data_tables.wizard}
<article class="a4-card" aria-labelledby="sq-dt-new">
<div class="a4-card-head"><h3 class="a4-h3" id="sq-dt-new">{'Create a new table from an import'|i18n( 'extension/sqliimport' )}</h3></div>
<p class="a4-sqliimport-format-desc">{'The module table wizard reads the fields of an import (from a sample of its source: names, types, required and unique columns), makes the class, the table and its admin views in an extension, and the import can then fill it. Each import offers it under More, "New data table from its fields".'|i18n( 'extension/sqliimport' )}</p>
<a class="a4-btn" href={concat( '/', $data_tables.wizard_url )|ezurl}><span class="a4-icon a4-bi-magic" aria-hidden="true"></span> {'Open the module table wizard'|i18n( 'extension/sqliimport' )}</a>
</article>
{/if}

{if $data_tables.sync}
<article class="a4-card a4-sqliimport-sync-card" aria-labelledby="sq-dt-sync">
<div class="a4-card-head"><h3 class="a4-h3" id="sq-dt-sync">{'Content and data tables in sync'|i18n( 'extension/sqliimport' )}</h3></div>
<ul class="a4-sqliimport-format-list">
<li><strong>{'Export content to a table'|i18n( 'extension/sqliimport' )}</strong>: {'direction content to table, run once'|i18n( 'extension/sqliimport' )}</li>
<li><strong>{'Migrate content into a table'|i18n( 'extension/sqliimport' )}</strong>: {'content to table once; removing the content afterwards is a separate step, never the default'|i18n( 'extension/sqliimport' )}</li>
<li><strong>{'Keep a table in sync with content'|i18n( 'extension/sqliimport' )}</strong>: {'content to table, scheduled or live'|i18n( 'extension/sqliimport' )}</li>
<li><strong>{'Import a table into content'|i18n( 'extension/sqliimport' )}</strong>: {'direction table to content'|i18n( 'extension/sqliimport' )}</li>
<li><strong>{'Two-way sync'|i18n( 'extension/sqliimport' )}</strong>: {'both ways, with a rule for conflicts (newest, content, table, or ask)'|i18n( 'extension/sqliimport' )}</li>
</ul>
<form method="post" action={'/sqliimport/catalogue'|ezurl} class="a4-form a4-sqliimport-options">
<div class="a4-field">
<label for="sq-dt-class">{'Data table'|i18n( 'extension/sqliimport' )}</label>
<select id="sq-dt-class" name="DataTableClass">
<option value="">{'Choose in the form'|i18n( 'extension/sqliimport' )}</option>
{foreach $data_tables.tables as $sq_dt_class => $sq_dt_label}<option value="{$sq_dt_class|wash}">{$sq_dt_label|wash}</option>{/foreach}
</select>
</div>
<div class="a4-field">
<label for="sq-dt-content">{'Content class'|i18n( 'extension/sqliimport' )}</label>
<select id="sq-dt-content" name="DataTableContentClass">
<option value="">{'Choose in the form'|i18n( 'extension/sqliimport' )}</option>
{foreach $data_tables.content_classes as $sq_dt_cc => $sq_dt_name}<option value="{$sq_dt_cc|wash}">{$sq_dt_name|wash} ({$sq_dt_cc|wash})</option>{/foreach}
</select>
</div>
<div class="a4-toolbar">
<button type="submit" class="a4-btn a4-btn-primary" name="DataTableSyncButton" value="1"><span class="a4-icon a4-bi-arrow-down-up" aria-hidden="true"></span> {'New synchronisation'|i18n( 'extension/sqliimport' )}</button>
<a class="a4-btn" href={concat( '/', $data_tables.syncs_url )|ezurl}>{'Synchronisations'|i18n( 'extension/sqliimport' )}</a>
<a class="a4-btn a4-btn-ghost" href={concat( '/', $data_tables.conflicts_url )|ezurl}>{'Conflicts'|i18n( 'extension/sqliimport' )}</a>
</div>
<p class="a4-help">{'The form of Setup, Data tables opens with these two filled in; choose the direction and the mode there.'|i18n( 'extension/sqliimport' )}</p>
</form>
</article>
{/if}

</div>
</section>
{/if}
