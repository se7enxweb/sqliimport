{* The fields of an option schema (expImportOptionSchema): one control per option, named <prefix>[<name>], with its
   label, help and error. Input: $schema, $values, $prefix (SourceOptions, HandlerOptions), $errors, $idprefix. An option with "hidden" (set
   by the engine, e.g. the manifest of a handed-over file) has no control. Label, help and the labels of an enum are
   translated in the context extension/sqliimport/options (translations/<locale>/translation.ts). *}
{foreach $schema as $sq_of_name => $sq_of_def}{if is_set( $sq_of_def.hidden )|not}
{def $sq_of_oid = concat( $idprefix, '-', $sq_of_name )
     $sq_of_type = first_set( $sq_of_def.type, 'string' )
     $sq_of_value = cond( is_set( $values[$sq_of_name] ), $values[$sq_of_name], first_set( $sq_of_def.default, '' ) )
     $sq_of_ekey = concat( $prefix, '_', $sq_of_name )
     $sq_of_label = first_set( $sq_of_def.label, $sq_of_name )|i18n( 'extension/sqliimport/options' )}
<div class="a4-field">
{if eq( $sq_of_type, 'bool' )}
<label class="a4-check" for="{$sq_of_oid}"><input type="hidden" name="{$prefix}[{$sq_of_name}]" value="0" /><input type="checkbox" id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]" value="1"{if $sq_of_value} checked="checked"{/if} /> {$sq_of_label|wash}</label>
{else}
<label for="{$sq_of_oid}">{$sq_of_label|wash}{if is_set( $sq_of_def.required )}{if $sq_of_def.required} <span class="a4-required-mark" aria-hidden="true">*</span>{/if}{/if}</label>
{if eq( $sq_of_type, 'enum' )}
<select id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]"{if is_set( $errors[$sq_of_ekey] )} aria-invalid="true" aria-describedby="{$sq_of_oid}-error"{/if}>
{foreach $sq_of_def.values as $sq_of_v => $sq_of_l}<option value="{$sq_of_v|wash}"{if eq( $sq_of_v, $sq_of_value )} selected="selected"{/if}>{$sq_of_l|i18n( 'extension/sqliimport/options' )|wash}</option>{/foreach}
</select>
{elseif or( eq( $sq_of_type, 'text' ), eq( $sq_of_type, 'list' ), eq( $sq_of_type, 'json' ) )}
<textarea id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]" rows="3"{if is_set( $errors[$sq_of_ekey] )} aria-invalid="true" aria-describedby="{$sq_of_oid}-error"{/if}>{if is_array( $sq_of_value )}{if eq( $sq_of_type, 'list' )}{$sq_of_value|implode( "\n" )|wash}{else}{$sq_of_value|json_encode|wash}{/if}{else}{$sq_of_value|wash}{/if}</textarea>
{else}
<input type="{if or( eq( $sq_of_type, 'int' ), eq( $sq_of_type, 'node' ) )}number{elseif eq( $sq_of_type, 'url' )}url{else}text{/if}" id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]" value="{$sq_of_value|wash}"{if is_set( $errors[$sq_of_ekey] )} aria-invalid="true" aria-describedby="{$sq_of_oid}-error"{/if} />
{/if}
{/if}
{if is_set( $sq_of_def.help )}<span class="a4-help">{$sq_of_def.help|i18n( 'extension/sqliimport/options' )|wash}</span>{/if}
{* a secret option: its value can be typed here and goes into the kernel's secret store under the name above (for a
   user with secrets/manage, Exponential 6.0.15); the field is always empty, a stored value is never shown *}
{if and( eq( $sq_of_type, 'secret' ), is_set( $can_store_secrets ) )}{if $can_store_secrets}
<label for="{$sq_of_oid}-new">{'New value (stored in Setup, Secrets under this name)'|i18n( 'extension/sqliimport/edit' )}</label>
<input type="password" id="{$sq_of_oid}-new" name="NewSecret[{$sq_of_oid}]" value="" autocomplete="new-password" spellcheck="false" />
<span class="a4-help">{'Leave it empty to keep the stored value. It is never shown again, not even after an error.'|i18n( 'extension/sqliimport/edit' )}</span>
{/if}{/if}
{if is_set( $errors[$sq_of_ekey] )}<span class="a4-field-error" id="{$sq_of_oid}-error">{$errors[$sq_of_ekey]|wash}</span>{/if}
</div>
{undef $sq_of_oid $sq_of_type $sq_of_value $sq_of_ekey $sq_of_label}
{/if}
{/foreach}
