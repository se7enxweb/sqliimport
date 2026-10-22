{* Editor step "source": name, source type, origin (upload, address, server file) and the source's options. *}
<form class="a4-form is-wide" method="post" action={concat( $base, '/(step)/source' )|ezurl} enctype="multipart/form-data">
{if $import|not}
{if gt( $entry_parent, 0 )}<input type="hidden" name="EntryParent" value="{$entry_parent}" />{/if}
{if ne( $entry_class, '' )}<input type="hidden" name="EntryClass" value="{$entry_class|wash}" />{/if}
{if or( gt( $entry_parent, 0 ), ne( $entry_class, '' ) )}<p class="a4-notice is-info" role="status">{if gt( $entry_parent, 0 )}{'The objects will be created in %folder.'|i18n( 'extension/sqliimport/edit',, hash( '%folder', $entry_parent_name ) )|wash} {/if}{if ne( $entry_class, '' )}{'The mapping will start with the class %class.'|i18n( 'extension/sqliimport/edit',, hash( '%class', $entry_class ) )|wash}{/if}</p>{/if}
{if $handover}
<div class="a4-field a4-field-wide">
<label class="a4-check"><input type="checkbox" name="UseHandover" value="1" checked="checked" /> {'Use the file %name from %tool (job %job)'|i18n( 'extension/sqliimport/edit',, hash( '%name', $handover.original_name, '%tool', $handover.tool, '%job', $handover.job ) )|wash}</label>
<span class="a4-help">{if ne( $handover.manifest, '' )}{'Its column manifest comes with it: the mapping is made from it.'|i18n( 'extension/sqliimport/edit' )}{else}{'Choose the format below, or upload another file instead.'|i18n( 'extension/sqliimport/edit' )}{/if}</span>
</div>
{/if}
{/if}
<div class="a4-field">
<label for="sq-name">{'Name'|i18n( 'extension/sqliimport/edit' )} <span class="a4-required-mark" aria-hidden="true">*</span></label>
<input type="text" id="sq-name" name="Name" value="{if $sql_test}{$sql_test.form.name|wash}{elseif $import}{$import.name|wash}{/if}" required="required" maxlength="255"{if is_set( $errors.Name )} aria-invalid="true"{/if} />
</div>
<div class="a4-field">
<label for="sq-identifier">{'Identifier'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-identifier" name="Identifier" value="{if $sql_test}{$sql_test.form.identifier|wash}{elseif $import}{$import.identifier|wash}{/if}" maxlength="100" />
<span class="a4-help">{'Names the import on the command line (ext:sqliimport:run followed by the identifier). Empty: made from the name.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field a4-field-wide">
<label for="sq-description">{'Description'|i18n( 'extension/sqliimport/edit' )}</label>
<textarea id="sq-description" name="Description" rows="2">{if $sql_test}{$sql_test.form.description|wash}{elseif $import}{$import.description|wash}{/if}</textarea>
</div>

<fieldset class="a4-field a4-field-wide">
<legend>{'Source type'|i18n( 'extension/sqliimport/edit' )}</legend>
<span class="a4-help">{'Every format, with an example and what it maps to, is described in the'|i18n( 'extension/sqliimport/edit' )} <a href={'/sqliimport/catalogue'|ezurl}>{'catalogue'|i18n( 'extension/sqliimport/edit' )}</a>.</span>
{foreach $sources_by_group as $gid => $g}
<div class="a4-sqliimport-format-group">
<h3 class="a4-h3">{$g.label|wash}</h3>
<div class="a4-radios">
{foreach $g.sources as $id => $s}{if eq( $s.status, 'enabled' )}
<label><input type="radio" name="SourceType" value="{$id|wash}"{if eq( $source_type, $id )} checked="checked"{/if} /> {$s.label|wash} <span class="a4-meta">{$s.extensions|implode( ', ' )|wash}</span></label>
{/if}{/foreach}
</div>
</div>
{/foreach}
<div class="a4-radios">
{def $sq_src_legacy = array()}{foreach $handlers as $hid => $h}{if and( $h.legacy, eq( $h.status, 'enabled' ) )}{set $sq_src_legacy = $sq_src_legacy|append( hash( 'id', $hid, 'label', $h.label ) )}{/if}{/foreach}
{if $sq_src_legacy|count|gt( 0 )}
<label><input type="radio" name="SourceType" value="legacy"{if and( $import, $import.is_legacy )} checked="checked"{/if} /> {'A 1.x handler that reads its own source'|i18n( 'extension/sqliimport/edit' )}</label>
{/if}
</div>
</fieldset>

<fieldset class="a4-field a4-field-wide">
<legend>{'Where the data comes from'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-radios">
<label><input type="radio" name="Origin" value="upload"{if or( is_unset( $source.origin ), eq( $source.origin, 'upload' ) )} checked="checked"{/if} /> {'An uploaded file'|i18n( 'extension/sqliimport/edit' )}</label>
<label><input type="radio" name="Origin" value="url"{if and( is_set( $source.origin ), eq( $source.origin, 'url' ) )} checked="checked"{/if} /> {'An address (http or https), read at every run'|i18n( 'extension/sqliimport/edit' )}</label>
{if and( is_set( $source.origin ), eq( $source.origin, 'remote' ) )}<label><input type="radio" name="Origin" value="remote" checked="checked" /> {'Read by the source itself (a database, a mailbox, a site), with the options below'|i18n( 'extension/sqliimport/edit' )}</label>{/if}
{if $allowed_paths|count|gt( 0 )}<label><input type="radio" name="Origin" value="path"{if and( is_set( $source.origin ), eq( $source.origin, 'path' ) )} checked="checked"{/if} /> {'A file on the server'|i18n( 'extension/sqliimport/edit' )}</label>{/if}
</div>
</fieldset>
<div class="a4-field">
<label for="sq-file">{'File'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="file" id="sq-file" name="SourceFile"{if is_set( $errors.SourceFile )} aria-invalid="true"{/if} />
<span class="a4-help">{if is_set( $source.original_name )}{'Uploaded: %name. Choose a file only to replace it.'|i18n( 'extension/sqliimport/edit',, hash( '%name', $source.original_name ) )|wash}{else}{'At most %mb MB.'|i18n( 'extension/sqliimport/edit',, hash( '%mb', $max_megabytes ) )}{/if}</span>
</div>
<div class="a4-field">
<label for="sq-url">{'Address'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="url" id="sq-url" name="SourceURL" value="{if is_set( $source.url )}{$source.url|wash}{/if}" placeholder="https://"{if is_set( $errors.SourceURL )} aria-invalid="true"{/if} />
</div>
{if $allowed_paths|count|gt( 0 )}
<div class="a4-field">
<label for="sq-path">{'Server file'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-path" name="SourcePath" value="{if is_set( $source.path )}{$source.path|wash}{/if}" />
<span class="a4-help">{'Inside: %paths'|i18n( 'extension/sqliimport/edit',, hash( '%paths', $allowed_paths|implode( ', ' ) ) )|wash}</span>
</div>
{/if}
{if $sq_src_legacy|count|gt( 0 )}
<div class="a4-field">
<label for="sq-legacy">{'1.x handler'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-legacy" name="LegacyHandler">
{foreach $sq_src_legacy as $lh}<option value="{$lh.id|wash}"{if and( $import, eq( $import.handler, $lh.id ) )} selected="selected"{/if}>{$lh.label|wash}</option>{/foreach}
</select>
</div>
{/if}
{undef $sq_src_legacy}

{def $sq_src_type = $source_type}
{if is_set( $sources[$sq_src_type] )}
<fieldset class="a4-field a4-field-wide">
<legend>{'Options of the source (%type)'|i18n( 'extension/sqliimport/edit',, hash( '%type', $sources[$sq_src_type].label ) )|wash}</legend>
<div class="a4-form is-wide a4-sqliimport-options">
{include uri='design:sqliimport/parts/option_fields.tpl' schema=$sources[$sq_src_type].schema values=$source prefix='SourceOptions' errors=$errors idprefix='sq-so'}
</div>
{if eq( $sq_src_type, 'sql' )}{include uri='design:sqliimport/edit/sql_test.tpl'}{/if}
<span class="a4-help">{'The options of another source type are shown after it is saved.'|i18n( 'extension/sqliimport/edit' )}</span>
</fieldset>
{/if}
{undef $sq_src_type}

<div class="a4-field">
<label for="sq-mapfile-src">{'Load a mapping (optional)'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="file" id="sq-mapfile-src" name="MappingFile" accept=".json,application/json"{if is_set( $errors.MappingFile )} aria-invalid="true"{/if} />
<span class="a4-help">{'A mapping file (JSON) of another import or installation: its lines are used instead of those of the format.'|i18n( 'extension/sqliimport/edit' )}</span>
{if is_set( $errors.MappingFile )}<span class="a4-field-error">{$errors.MappingFile|wash}</span>{/if}
</div>
{include uri='design:sqliimport/edit/buttons.tpl' step='source' stay=1}
</form>
