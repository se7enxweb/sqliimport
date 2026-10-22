<?php
/**
 * File containing the expImportMapper class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The no-code mapping: which source field fills which attribute of a content class, how a record finds the object it
 * updates, and where a new object is placed. validate() checks a mapping (against the class's attributes when they are
 * given), apply() turns one record into what the mapping handler publishes. Both are pure, so they are tested without
 * a database; the mapping handler (expImportMappingHandler) does the publishing.
 *
 * A mapping as an array:
 *   class        the content class identifier
 *   language     the language of the fields (empty: the site's default)
 *   match        how a record finds its object:
 *                  array( 'type' => 'remote_id', 'pattern' => 'news-{id}' )   the remote ID, from fields of the record
 *                  array( 'type' => 'attribute', 'attribute' => 'isbn', 'source' => 'isbn' )  an attribute's value
 *   fields       list of array( 'source' => path, 'attribute' => identifier, 'transforms' => chain, 'default' => text,
 *                'required' => bool, 'language' => locale (empty: the mapping's) )
 *   location     array( 'parent_node_id' => int, 'parent_from' => path (a record field with the parent's remote ID),
 *                'parent_prefix' => text put before it, 'extra_parents_from' => path (a list of remote IDs of more
 *                parents) )
 */
class expImportMapper
{
    /**
     * Checks a mapping.
     *
     * @param array $mapping
     * @param array|null $attributes identifier => datatype string of the class, when known
     * @return string[] errors
     */
    public static function validate( array $mapping, ?array $attributes = null )
    {
        $errors = array();
        $class = isset( $mapping['class'] ) ? (string)$mapping['class'] : '';
        if ( !preg_match( '/^[a-z0-9_]+$/i', $class ) )
            $errors[] = 'choose the content class';
        $match = isset( $mapping['match'] ) && is_array( $mapping['match'] ) ? $mapping['match'] : array();
        $type = isset( $match['type'] ) ? $match['type'] : '';
        if ( $type === 'remote_id' )
        {
            if ( !isset( $match['pattern'] ) || !preg_match( '/\{[^}]+\}/', (string)$match['pattern'] ) )
                $errors[] = 'the remote ID pattern needs at least one {field} of the record, e.g. news-{id}, or {_ref} for the reference of the record';
        }
        elseif ( $type === 'attribute' )
        {
            if ( empty( $match['attribute'] ) || empty( $match['source'] ) )
                $errors[] = 'matching on an attribute needs the attribute and the source field';
            elseif ( $attributes !== null && !isset( $attributes[$match['attribute']] ) )
                $errors[] = "the class has no attribute '{$match['attribute']}' to match on";
        }
        else
            $errors[] = 'choose how a record finds its object (remote ID or attribute)';

        $fields = isset( $mapping['fields'] ) && is_array( $mapping['fields'] ) ? $mapping['fields'] : array();
        if ( !$fields )
            $errors[] = 'map at least one field';
        $seen = array();
        foreach ( $fields as $i => $field )
        {
            $n = $i + 1;
            $attribute = isset( $field['attribute'] ) ? (string)$field['attribute'] : '';
            if ( $attribute === '' )
            {
                $errors[] = "line $n: choose the attribute";
                continue;
            }
            if ( $attributes !== null && !isset( $attributes[$attribute] ) )
                $errors[] = "line $n: the class has no attribute '$attribute'";
            $language = isset( $field['language'] ) ? (string)$field['language'] : '';
            $key = $language . '/' . $attribute;
            if ( isset( $seen[$key] ) )
                $errors[] = "line $n: the attribute '$attribute' is mapped twice";
            $seen[$key] = true;
            $source = isset( $field['source'] ) ? (string)$field['source'] : '';
            $default = isset( $field['default'] ) ? (string)$field['default'] : '';
            $chain = isset( $field['transforms'] ) && is_array( $field['transforms'] ) ? $field['transforms'] : array();
            if ( $source === '' && $default === '' && !$chain )
                $errors[] = "line $n: give a source field or a default value for '$attribute'";
            foreach ( expImportTransforms::validateChain( $chain ) as $error )
                $errors[] = "line $n: $error";
        }
        $location = isset( $mapping['location'] ) && is_array( $mapping['location'] ) ? $mapping['location'] : array();
        if ( empty( $location['parent_node_id'] ) && empty( $location['parent_from'] ) )
            $errors[] = 'choose where new objects are placed (a parent node, or a field with the parent\'s remote ID)';
        return $errors;
    }

    /**
     * What a record becomes under a mapping.
     *
     * @param array $mapping
     * @param expImportRecord $record
     * @return array 'remote_id' (string, '' when matched on an attribute), 'match' (array( attribute, value ) or null),
     *               'fields' (language => attribute => value; language '' = the mapping's), 'parents' (list of
     *               array( 'node' => id ) or array( 'remote_id' => ... ))
     * @throws expImportTransformException a required value is missing, a transform failed
     */
    public static function apply( array $mapping, expImportRecord $record )
    {
        $out = array( 'remote_id' => '', 'match' => null, 'fields' => array(), 'parents' => array() );
        $match = $mapping['match'];
        if ( $match['type'] === 'remote_id' )
        {
            $missing = array();
            $remoteID = preg_replace_callback( '/\{([^}]+)\}/', function ( $m ) use ( $record, &$missing )
            {
                // {_ref}: the record's own reference (its key field, else its position in the source)
                $v = $m[1] === '_ref' ? $record->ref : $record->get( $m[1], '' );
                $v = is_array( $v ) ? '' : trim( (string)$v );
                if ( $v === '' )
                    $missing[] = $m[1];
                return $v;
            }, (string)$match['pattern'] );
            if ( $missing )
                throw new expImportTransformException( 'the remote ID needs ' . implode( ', ', $missing ) . ', which the record does not have' );
            if ( strlen( $remoteID ) > 100 )
                $remoteID = substr( $remoteID, 0, 60 ) . '-' . md5( $remoteID );
            $out['remote_id'] = $remoteID;
        }
        else
        {
            $value = $record->get( $match['source'], '' );
            $value = is_array( $value ) ? '' : trim( (string)$value );
            if ( $value === '' )
                throw new expImportTransformException( "the match field '{$match['source']}' is empty" );
            $out['match'] = array( $match['attribute'], $value );
        }

        foreach ( $mapping['fields'] as $field )
        {
            $source = isset( $field['source'] ) ? (string)$field['source'] : '';
            $value = $source !== '' ? $record->get( $source, '' ) : '';
            $chain = isset( $field['transforms'] ) && is_array( $field['transforms'] ) ? $field['transforms'] : array();
            if ( $chain )
                $value = expImportTransforms::applyChain( $value, $chain, $record );
            $empty = $value === null || $value === '' || $value === array();
            if ( $empty && isset( $field['default'] ) && $field['default'] !== '' )
            {
                $value = (string)$field['default'];
                $empty = false;
            }
            if ( $empty && !empty( $field['required'] ) )
                throw new expImportTransformException( "the required attribute '{$field['attribute']}' has no value" );
            if ( is_array( $value ) )
                $value = implode( '|', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
            $language = isset( $field['language'] ) ? (string)$field['language'] : '';
            $out['fields'][$language][(string)$field['attribute']] = $value === null ? '' : (string)$value;
        }

        $location = isset( $mapping['location'] ) && is_array( $mapping['location'] ) ? $mapping['location'] : array();
        $prefix = isset( $location['parent_prefix'] ) ? (string)$location['parent_prefix'] : '';
        if ( !empty( $location['parent_from'] ) )
        {
            $parent = $record->get( $location['parent_from'], '' );
            $parent = is_array( $parent ) ? '' : trim( (string)$parent );
            if ( $parent !== '' )
                $out['parents'][] = array( 'remote_id' => $prefix . $parent );
        }
        if ( !$out['parents'] && !empty( $location['parent_node_id'] ) )
            $out['parents'][] = array( 'node' => (int)$location['parent_node_id'] );
        if ( !empty( $location['extra_parents_from'] ) )
        {
            $more = $record->get( $location['extra_parents_from'], array() );
            foreach ( is_array( $more ) ? $more : explode( ',', (string)$more ) as $remote )
            {
                $remote = trim( (string)$remote );
                if ( $remote !== '' )
                    $out['parents'][] = array( 'remote_id' => $prefix . $remote );
            }
        }
        if ( !$out['parents'] )
            throw new expImportTransformException( 'the record names no parent and the mapping has no default parent node' );
        return $out;
    }

    /**
     * Proposes a mapping line for each attribute whose identifier or name matches a source field (no AI): the
     * normalised names are compared, exact first, then one containing the other.
     *
     * @param string[] $paths the source fields
     * @param array $attributes identifier => array( 'name' => ..., 'datatype' => ... )
     * @return array list of mapping lines with 'confidence' (1.0 exact, 0.6 partial)
     */
    public static function autoMatch( array $paths, array $attributes )
    {
        $norm = function ( $s )
        {
            return preg_replace( '/[^a-z0-9]/', '', strtolower( expImportTransforms::ascii( (string)$s ) ) );
        };
        $out = array();
        $used = array();
        foreach ( $attributes as $identifier => $info )
        {
            $names = array( $norm( $identifier ), $norm( isset( $info['name'] ) ? $info['name'] : '' ) );
            $best = null;
            $score = 0;
            foreach ( $paths as $path )
            {
                if ( isset( $used[$path] ) )
                    continue;
                $leaf = $norm( substr( (string)$path, (int)strrpos( '.' . $path, '.' ) ) );
                $full = $norm( $path );
                foreach ( array_filter( $names, 'strlen' ) as $name )
                {
                    if ( $name === $leaf || $name === $full )
                        $s = 1.0;
                    elseif ( strlen( $leaf ) > 2 && ( strpos( $name, $leaf ) !== false || strpos( $leaf, $name ) !== false ) )
                        $s = 0.6;
                    else
                        continue;
                    if ( $s > $score )
                    {
                        $score = $s;
                        $best = $path;
                    }
                }
            }
            if ( $best !== null )
            {
                $used[$best] = true;
                $line = array( 'source' => $best, 'attribute' => $identifier, 'transforms' => array(), 'confidence' => $score );
                $datatype = isset( $info['datatype'] ) ? $info['datatype'] : '';
                if ( $datatype === 'ezxmltext' || $datatype === 'ezrichtext' )
                    $line['transforms'][] = array( 'name' => 'richtext' );
                elseif ( $datatype === 'ezdate' || $datatype === 'ezdatetime' )
                    $line['transforms'][] = array( 'name' => 'date' );
                elseif ( $datatype === 'ezimage' )
                    $line['transforms'][] = array( 'name' => 'image' );
                elseif ( $datatype === 'ezboolean' )
                    $line['transforms'][] = array( 'name' => 'bool' );
                $out[] = $line;
            }
        }
        return $out;
    }
}
