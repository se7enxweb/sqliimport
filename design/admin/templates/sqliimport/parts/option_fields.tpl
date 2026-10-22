{* The fields of an option schema (expImportOptionSchema): one control per option, named <prefix>[<name>], with its
   label, help and error. Input: $schema, $values, $prefix (SourceOptions, HandlerOptions), $errors, $idprefix. An option with "hidden" (set
   by the engine, e.g. the manifest of a handed-over file) has no control. *}
{foreach $schema as $sq_of_name => $sq_of_def}{if is_set( $sq_of_def.hidden )|not}
{def $sq_of_oid = concat( $idprefix, '-', $sq_of_name )
     $sq_of_type = first_set( $sq_of_def.type, 'string' )
     $sq_of_value = cond( is_set( $values[$sq_of_name] ), $values[$sq_of_name], first_set( $sq_of_def.default, '' ) )
     $sq_of_ekey = concat( $prefix, '_', $sq_of_name )}
<div class="a4-field">
{if eq( $sq_of_type, 'bool' )}
<label class="a4-check" for="{$sq_of_oid}"><input type="hidden" name="{$prefix}[{$sq_of_name}]" value="0" /><input type="checkbox" id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]" value="1"{if $sq_of_value} checked="checked"{/if} /> {first_set( $sq_of_def.label, $sq_of_name )|wash}</label>
{else}
<label for="{$sq_of_oid}">{first_set( $sq_of_def.label, $sq_of_name )|wash}{if is_set( $sq_of_def.required )}{if $sq_of_def.required} <span class="a4-required-mark" aria-hidden="true">*</span>{/if}{/if}</label>
{if eq( $sq_of_type, 'enum' )}
<select id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]">
{foreach $sq_of_def.values as $sq_of_v => $sq_of_l}<option value="{$sq_of_v|wash}"{if eq( $sq_of_v, $sq_of_value )} selected="selected"{/if}>{$sq_of_l|wash}</option>{/foreach}
</select>
{elseif or( eq( $sq_of_type, 'text' ), eq( $sq_of_type, 'list' ), eq( $sq_of_type, 'json' ) )}
<textarea id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]" rows="3">{if is_array( $sq_of_value )}{if eq( $sq_of_type, 'list' )}{$sq_of_value|implode( "\n" )|wash}{else}{$sq_of_value|json_encode|wash}{/if}{else}{$sq_of_value|wash}{/if}</textarea>
{else}
<input type="{if or( eq( $sq_of_type, 'int' ), eq( $sq_of_type, 'node' ) )}number{elseif eq( $sq_of_type, 'url' )}url{else}text{/if}" id="{$sq_of_oid}" name="{$prefix}[{$sq_of_name}]" value="{$sq_of_value|wash}"{if is_set( $errors[$sq_of_ekey] )} aria-invalid="true"{/if} />
{/if}
{/if}
{if is_set( $sq_of_def.help )}<span class="a4-help">{$sq_of_def.help|wash}</span>{/if}
{if is_set( $errors[$sq_of_ekey] )}<span class="a4-field-error">{$errors[$sq_of_ekey]|wash}</span>{/if}
</div>
{undef $sq_of_oid $sq_of_type $sq_of_value $sq_of_ekey}
{/if}
{/foreach}
