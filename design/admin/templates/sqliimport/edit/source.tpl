{* Editor step "source": name, source type, origin (upload, address, server file) and the source's options. *}
<form class="a4-form is-wide" method="post" action={concat( $base, '/(step)/source' )|ezurl} enctype="multipart/form-data">
<div class="a4-field">
<label for="sq-name">{'Name'|i18n( 'extension/sqliimport/edit' )} <span class="a4-required-mark" aria-hidden="true">*</span></label>
<input type="text" id="sq-name" name="Name" value="{if $import}{$import.name|wash}{/if}" required="required" maxlength="255"{if is_set( $errors.Name )} aria-invalid="true"{/if} />
</div>
<div class="a4-field">
<label for="sq-identifier">{'Identifier'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-identifier" name="Identifier" value="{if $import}{$import.identifier|wash}{/if}" maxlength="100" />
<span class="a4-help">{'Names the import on the command line (ext:sqliimport:run followed by the identifier). Empty: made from the name.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
<div class="a4-field a4-field-wide">
<label for="sq-description">{'Description'|i18n( 'extension/sqliimport/edit' )}</label>
<textarea id="sq-description" name="Description" rows="2">{if $import}{$import.description|wash}{/if}</textarea>
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
{def $legacy_handlers = array()}{foreach $handlers as $hid => $h}{if and( $h.legacy, eq( $h.status, 'enabled' ) )}{set $legacy_handlers = $legacy_handlers|append( hash( 'id', $hid, 'label', $h.label ) )}{/if}{/foreach}
{if $legacy_handlers|count|gt( 0 )}
<label><input type="radio" name="SourceType" value="legacy"{if and( $import, $import.is_legacy )} checked="checked"{/if} /> {'A 1.x handler that reads its own source'|i18n( 'extension/sqliimport/edit' )}</label>
{/if}
</div>
</fieldset>

<fieldset class="a4-field a4-field-wide">
<legend>{'Where the data comes from'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-radios">
<label><input type="radio" name="Origin" value="upload"{if or( is_unset( $source.origin ), eq( $source.origin, 'upload' ) )} checked="checked"{/if} /> {'An uploaded file'|i18n( 'extension/sqliimport/edit' )}</label>
<label><input type="radio" name="Origin" value="url"{if and( is_set( $source.origin ), eq( $source.origin, 'url' ) )} checked="checked"{/if} /> {'An address (http or https), read at every run'|i18n( 'extension/sqliimport/edit' )}</label>
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
{if $legacy_handlers|count|gt( 0 )}
<div class="a4-field">
<label for="sq-legacy">{'1.x handler'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-legacy" name="LegacyHandler">
{foreach $legacy_handlers as $lh}<option value="{$lh.id|wash}"{if and( $import, eq( $import.handler, $lh.id ) )} selected="selected"{/if}>{$lh.label|wash}</option>{/foreach}
</select>
</div>
{/if}
{undef $legacy_handlers}

{def $type = $source_type}
{if is_set( $sources[$type] )}
<fieldset class="a4-field a4-field-wide">
<legend>{'Options of the source (%type)'|i18n( 'extension/sqliimport/edit',, hash( '%type', $sources[$type].label ) )|wash}</legend>
<div class="a4-form is-wide a4-sqliimport-options">
{include uri='design:sqliimport/parts/option_fields.tpl' schema=$sources[$type].schema values=$source prefix='SourceOptions' errors=$errors idprefix='sq-so'}
</div>
<span class="a4-help">{'The options of another source type are shown after it is saved.'|i18n( 'extension/sqliimport/edit' )}</span>
</fieldset>
{/if}
{undef $type}

{include uri='design:sqliimport/edit/buttons.tpl' step='source' stay=1}
</form>
