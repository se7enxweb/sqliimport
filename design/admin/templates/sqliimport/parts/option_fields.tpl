{* The fields of an option schema (expImportOptionSchema): one control per option, named <prefix>[<name>], with its
   label, help and error. Input: $schema, $values, $prefix (SourceOptions, HandlerOptions), $errors, $idprefix. *}
{foreach $schema as $name => $def}
{def $oid = concat( $idprefix, '-', $name )
     $type = first_set( $def.type, 'string' )
     $value = cond( is_set( $values[$name] ), $values[$name], first_set( $def.default, '' ) )
     $ekey = concat( $prefix, '_', $name )}
<div class="a4-field">
{if eq( $type, 'bool' )}
<label class="a4-check" for="{$oid}"><input type="hidden" name="{$prefix}[{$name}]" value="0" /><input type="checkbox" id="{$oid}" name="{$prefix}[{$name}]" value="1"{if $value} checked="checked"{/if} /> {first_set( $def.label, $name )|wash}</label>
{else}
<label for="{$oid}">{first_set( $def.label, $name )|wash}{if is_set( $def.required )}{if $def.required} <span class="a4-required-mark" aria-hidden="true">*</span>{/if}{/if}</label>
{if eq( $type, 'enum' )}
<select id="{$oid}" name="{$prefix}[{$name}]">
{foreach $def.values as $v => $l}<option value="{$v|wash}"{if eq( $v, $value )} selected="selected"{/if}>{$l|wash}</option>{/foreach}
</select>
{elseif or( eq( $type, 'text' ), eq( $type, 'list' ), eq( $type, 'json' ) )}
<textarea id="{$oid}" name="{$prefix}[{$name}]" rows="3">{if is_array( $value )}{if eq( $type, 'list' )}{$value|implode( "\n" )|wash}{else}{$value|json_encode|wash}{/if}{else}{$value|wash}{/if}</textarea>
{else}
<input type="{if or( eq( $type, 'int' ), eq( $type, 'node' ) )}number{elseif eq( $type, 'url' )}url{else}text{/if}" id="{$oid}" name="{$prefix}[{$name}]" value="{$value|wash}"{if is_set( $errors[$ekey] )} aria-invalid="true"{/if} />
{/if}
{/if}
{if is_set( $def.help )}<span class="a4-help">{$def.help|wash}</span>{/if}
{if is_set( $errors[$ekey] )}<span class="a4-field-error">{$errors[$ekey]|wash}</span>{/if}
</div>
{undef $oid $type $value $ekey}
{/foreach}
