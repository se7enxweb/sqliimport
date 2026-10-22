{* Editor step "mapping": the class, how a record finds its object, where new objects go, and the grid: one line per
   attribute of the class with its source field (the fields of the sample are offered), its transforms (one line:
   trim | date(format=d.m.Y)), a default and whether it is required. Auto-match fills the empty lines from the
   field names, without AI. *}
<form method="post" action={concat( $base, '/(step)/mapping' )|ezurl}>
{if ne( $sample_error, '' )}<p class="a4-notice is-warn">{'The sample of the source could not be read: %error'|i18n( 'extension/sqliimport/edit',, hash( '%error', $sample_error ) )|wash}</p>{/if}
<datalist id="sq-sample-fields">{if $sample}{foreach $sample.fields as $f}<option value="{$f|wash}"></option>{/foreach}{/if}</datalist>

<div class="a4-form is-wide a4-sqliimport-options">
<div class="a4-field">
<label for="sq-class">{'Content class'|i18n( 'extension/sqliimport/edit' )} <span class="a4-required-mark" aria-hidden="true">*</span></label>
<select id="sq-class" name="ClassIdentifier"{if is_set( $errors.ClassIdentifier )} aria-invalid="true"{/if}>
<option value="">{'Choose a class'|i18n( 'extension/sqliimport/edit' )}</option>
{foreach $classes as $c}<option value="{$c.identifier|wash}"{if eq( $mapping.class, $c.identifier )} selected="selected"{/if}>{$c.name|wash} ({$c.identifier|wash})</option>{/foreach}
</select>
<span class="a4-help">{'After a new class is chosen, save to see its attributes.'|i18n( 'extension/sqliimport/edit' )}</span>
</div>
{* a class per record (class_from, variants), from the format's preset or a mapping file: not edited in the grid,
   which is the mapping of every other record; kept when the grid is saved, unless removed here *}
{if and( is_set( $mapping.class_from ), is_set( $mapping.variants ) )}
<div class="a4-field a4-field-wide">
<p class="a4-notice is-info">{'Records choose their class by the field %field:'|i18n( 'extension/sqliimport/edit',, hash( '%field', $mapping.class_from ) )|wash}
{foreach $mapping.variants as $sq_mv_value => $sq_mv}<code>{$sq_mv_value|wash}</code> → {if is_set( $sq_mv.skip )}{'not imported'|i18n( 'extension/sqliimport/edit' )}{else}<code>{$sq_mv.class|wash}</code> ({'%n lines'|i18n( 'extension/sqliimport/edit',, hash( '%n', $sq_mv.fields|count ) )}){/if}{delimiter}; {/delimiter}{/foreach}.
{'Every other record uses the class and the lines below. Change the variants with a mapping file (download, edit, load).'|i18n( 'extension/sqliimport/edit' )}</p>
<label><input type="checkbox" name="RemoveVariants" value="1" /> {'Remove the class per record when saving'|i18n( 'extension/sqliimport/edit' )}</label>
</div>
{/if}
<div class="a4-field">
<label for="sq-mlang">{'Language of the fields'|i18n( 'extension/sqliimport/edit' )}</label>
<select id="sq-mlang" name="MappingLanguage">
<option value="">{'The language of the import'|i18n( 'extension/sqliimport/edit' )}</option>
{foreach $languages as $l}<option value="{$l.locale|wash}"{if eq( $mapping.language, $l.locale )} selected="selected"{/if}>{$l.name|wash}</option>{/foreach}
</select>
</div>
<fieldset class="a4-field a4-field-wide">
<legend>{'How a record finds its object'|i18n( 'extension/sqliimport/edit' )}</legend>
<div class="a4-radios">
<label><input type="radio" name="MatchType" value="remote_id"{if ne( $mapping.match.type, 'attribute' )} checked="checked"{/if} /> {'By a remote ID made from the record'|i18n( 'extension/sqliimport/edit' )}</label>
<label><input type="radio" name="MatchType" value="attribute"{if eq( $mapping.match.type, 'attribute' )} checked="checked"{/if} /> {'By the value of an attribute'|i18n( 'extension/sqliimport/edit' )}</label>
</div>
</fieldset>
<div class="a4-field">
<label for="sq-pattern">{'Remote ID pattern'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-pattern" name="MatchPattern" value="{if is_set( $mapping.match.pattern )}{$mapping.match.pattern|wash}{/if}" placeholder="news-{ldelim}id{rdelim}" />
<span class="a4-help">{'Fields of the record in braces, for example:'|i18n( 'extension/sqliimport/edit' )} <code>product-{ldelim}sku{rdelim}</code></span>
</div>
<div class="a4-field">
<label for="sq-mattr">{'Attribute and source field to match on'|i18n( 'extension/sqliimport/edit' )}</label>
<div class="a4-row">
<select id="sq-mattr" name="MatchAttribute" aria-label="{'Attribute'|i18n( 'extension/sqliimport/edit' )|wash}">
<option value="">–</option>
{foreach $grid as $g}<option value="{$g.attribute|wash}"{if and( is_set( $mapping.match.attribute ), eq( $mapping.match.attribute, $g.attribute ) )} selected="selected"{/if}>{$g.name|wash}</option>{/foreach}
</select>
<input type="text" name="MatchSource" list="sq-sample-fields" value="{if is_set( $mapping.match.source )}{$mapping.match.source|wash}{/if}" aria-label="{'Source field'|i18n( 'extension/sqliimport/edit' )|wash}" />
</div>
</div>
<div class="a4-field">
<label for="sq-lparent">{'Parent node of new objects'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="number" id="sq-lparent" name="LocationParentNodeID" value="{first_set( $mapping.location.parent_node_id, 0 )|wash}" min="0" />
</div>
<div class="a4-field">
<label for="sq-lfrom">{'Parent from a field (its remote ID)'|i18n( 'extension/sqliimport/edit' )}</label>
<div class="a4-row">
<input type="text" id="sq-lfrom" name="LocationParentFrom" list="sq-sample-fields" value="{first_set( $mapping.location.parent_from, '' )|wash}" aria-label="{'Field'|i18n( 'extension/sqliimport/edit' )|wash}" />
<input type="text" name="LocationParentPrefix" value="{first_set( $mapping.location.parent_prefix, '' )|wash}" placeholder="{'prefix'|i18n( 'extension/sqliimport/edit' )|wash}" aria-label="{'Prefix of the remote ID'|i18n( 'extension/sqliimport/edit' )|wash}" />
</div>
</div>
<div class="a4-field">
<label for="sq-lextra">{'More locations from a field'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="text" id="sq-lextra" name="LocationExtraParentsFrom" list="sq-sample-fields" value="{first_set( $mapping.location.extra_parents_from, '' )|wash}" />
</div>
</div>

{if $grid|count|gt( 0 )}
<section class="a4-section" aria-labelledby="sq-grid-title">
<div class="a4-section-head">
<h2 class="a4-h2" id="sq-grid-title">{'Attributes'|i18n( 'extension/sqliimport/edit' )}</h2>
<button type="submit" class="a4-btn a4-btn-small" name="AutoMatchButton" value="1"><span class="a4-icon a4-bi-magic" aria-hidden="true"></span> {'Auto-match by name'|i18n( 'extension/sqliimport/edit' )}</button>
</div>
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-labelledby="sq-grid-title">
<table class="a4-table is-compact a4-sqliimport-grid">
<thead><tr>
<th>{'Attribute'|i18n( 'extension/sqliimport/edit' )}</th>
<th>{'Source field'|i18n( 'extension/sqliimport/edit' )}</th>
<th>{'Transforms'|i18n( 'extension/sqliimport/edit' )}</th>
<th>{'Default'|i18n( 'extension/sqliimport/edit' )}</th>
<th>{'Required'|i18n( 'extension/sqliimport/edit' )}</th>
</tr></thead>
<tbody>
{foreach $grid as $g}
<tr{if is_set( $errors[concat( 'Fields_', $g.attribute )] )} class="is-error"{/if}>
<td><strong>{$g.name|wash}</strong>{if $g.is_required} <span class="a4-required-mark" title="{'required by the class'|i18n( 'extension/sqliimport/edit' )|wash}">*</span>{/if}<br /><span class="a4-meta">{$g.attribute|wash} · {$g.datatype|wash}</span></td>
<td><input type="text" class="a4-input" name="Fields[{$g.attribute|wash}][source]" list="sq-sample-fields" value="{$g.source|wash}" aria-label="{'Source field of %a'|i18n( 'extension/sqliimport/edit',, hash( '%a', $g.name ) )|wash}" /></td>
<td><input type="text" id="sq-f-{$g.attribute|wash}-transforms" class="a4-input a4-sqliimport-chain" name="Fields[{$g.attribute|wash}][transforms]" value="{$g.transforms|wash}"{if ne( $g.error, '' )} aria-invalid="true" aria-describedby="sq-f-{$g.attribute|wash}-error"{/if} aria-label="{'Transforms of %a'|i18n( 'extension/sqliimport/edit',, hash( '%a', $g.name ) )|wash}" placeholder="trim" />{if ne( $g.error, '' )}<span class="a4-field-error" id="sq-f-{$g.attribute|wash}-error">{$g.error|wash}</span>{/if}</td>
<td><input type="text" class="a4-input" name="Fields[{$g.attribute|wash}][default]" value="{$g.default|wash}" aria-label="{'Default of %a'|i18n( 'extension/sqliimport/edit',, hash( '%a', $g.name ) )|wash}" /></td>
<td><input type="checkbox" name="Fields[{$g.attribute|wash}][required]" value="1"{if $g.required} checked="checked"{/if} aria-label="{'%a is required'|i18n( 'extension/sqliimport/edit',, hash( '%a', $g.name ) )|wash}" /></td>
</tr>
{/foreach}
</tbody>
</table>
</div>
{if $translation_grid|count|gt( 0 )}
<section class="a4-section a4-sqliimport-translations" aria-labelledby="sq-tr-title">
<div class="a4-section-head"><h3 class="a4-h3" id="sq-tr-title">{'Translations by column'|i18n( 'extension/sqliimport/edit' )}</h3></div>
<p class="a4-meta">{'When the source has a column per language (title_de, title_fr), map it here: the object gets a translation in that language. Only translatable attributes are listed; an empty source leaves the language out.'|i18n( 'extension/sqliimport/edit' )}</p>
{foreach $translation_grid as $sq_tr}
<details class="a4-fold"{if gt( $sq_tr.count, 0 )} open="open"{/if}>
<summary>{$sq_tr.name|wash} <span class="a4-meta">{$sq_tr.locale|wash}{if gt( $sq_tr.count, 0 )} · {'%n fields'|i18n( 'extension/sqliimport/edit',, hash( '%n', $sq_tr.count ) )}{/if}</span></summary>
<div class="a4-sqliimport-scroll" tabindex="0" role="region" aria-label="{'Translation into %language'|i18n( 'extension/sqliimport/edit',, hash( '%language', $sq_tr.name ) )|wash}">
<table class="a4-table is-compact">
<thead><tr><th>{'Attribute'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Source field'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Transforms'|i18n( 'extension/sqliimport/edit' )}</th></tr></thead>
<tbody>
{foreach $sq_tr.lines as $sq_tl}
<tr>
<td>{$sq_tl.name|wash}<br /><span class="a4-meta">{$sq_tl.attribute|wash}</span></td>
<td><input type="text" name="Translations[{$sq_tr.locale|wash}][{$sq_tl.attribute|wash}][source]" value="{$sq_tl.source|wash}" aria-label="{'Source of %a in %l'|i18n( 'extension/sqliimport/edit',, hash( '%a', $sq_tl.name, '%l', $sq_tr.name ) )|wash}" /></td>
<td><input type="text" name="Translations[{$sq_tr.locale|wash}][{$sq_tl.attribute|wash}][transforms]" value="{$sq_tl.transforms|wash}" aria-label="{'Transforms of %a in %l'|i18n( 'extension/sqliimport/edit',, hash( '%a', $sq_tl.name, '%l', $sq_tr.name ) )|wash}" />
{if ne( $sq_tl.error, '' )}<span class="a4-field-error">{$sq_tl.error|wash}</span>{/if}</td>
</tr>
{/foreach}
</tbody>
</table>
</div>
</details>
{/foreach}
</section>
{/if}
<details class="a4-fold a4-sqliimport-transforms">
<summary>{'The transforms'|i18n( 'extension/sqliimport/edit' )}</summary>
<p class="a4-help">{'One line per attribute, steps separated by a vertical bar, arguments in parentheses, for example:'|i18n( 'extension/sqliimport/edit' )} <code>trim | date(format=d.m.Y, timezone=Europe/Berlin) | map(table={ldelim}"D":"Germany"{rdelim})</code></p>
<table class="a4-table is-compact"><thead><tr><th>{'Name'|i18n( 'extension/sqliimport/edit' )}</th><th>{'What it does'|i18n( 'extension/sqliimport/edit' )}</th><th>{'Arguments'|i18n( 'extension/sqliimport/edit' )}</th></tr></thead>
<tbody>{foreach $transforms as $tname => $t}<tr><td><code>{$tname|wash}</code></td><td>{$t.label|wash}</td><td>{foreach $t.schema as $an => $ad}<code>{$an|wash}</code>{delimiter}, {/delimiter}{/foreach}</td></tr>{/foreach}</tbody></table>
</details>
</section>
{/if}

{if $sample}
<details class="a4-fold">
<summary>{'The fields of the sample (%n records)'|i18n( 'extension/sqliimport/edit',, hash( '%n', $sample.records|count ) )}</summary>
<p>{foreach $sample.fields as $f}<code>{$f|wash}</code>{delimiter} {/delimiter}{/foreach}</p>
</details>
{/if}

{include uri='design:sqliimport/edit/buttons.tpl' step='mapping' stay=1}
</form>

<section class="a4-section a4-sqliimport-mapfile" aria-labelledby="sq-mapfile-title">
<div class="a4-section-head"><h3 class="a4-h3" id="sq-mapfile-title">{'Mapping file'|i18n( 'extension/sqliimport/edit' )}</h3></div>
<p class="a4-meta">{'A mapping moves between imports and installations as a JSON file: download this one, or load one to replace the lines above.'|i18n( 'extension/sqliimport/edit' )}</p>
<form class="a4-toolbar" method="post" action={concat( $base, '/(step)/mapping' )|ezurl} enctype="multipart/form-data">
<input type="hidden" name="Step" value="mapping" />
{if $import.mapping_id}<a class="a4-btn a4-btn-ghost" href={concat( '/sqliimport/mapping/', $import.id )|ezurl} download><span class="a4-icon a4-bi-download" aria-hidden="true"></span> {'Download mapping (JSON)'|i18n( 'extension/sqliimport/edit' )}</a>{/if}
<label class="a4-sr-only" for="sq-mapfile">{'Mapping file (JSON)'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="file" id="sq-mapfile" name="MappingFile" accept=".json,application/json"{if is_set( $errors.MappingFile )} aria-invalid="true"{/if} />
<button type="submit" class="a4-btn" name="LoadMappingButton" value="1"><span class="a4-icon a4-bi-upload" aria-hidden="true"></span> {'Load a mapping…'|i18n( 'extension/sqliimport/edit' )}</button>
{if is_set( $errors.MappingFile )}<span class="a4-field-error">{$errors.MappingFile|wash}</span>{/if}
</form>
</section>
