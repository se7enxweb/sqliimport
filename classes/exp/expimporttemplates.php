<?php
/**
 * File containing the expImportTemplates class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Templates of imports: an import definition without its source data (no file, address or server path, no
 * schedules, trigger, run user or notification addresses) and with placeholders, from which "New import from a
 * template" (sqliimport/templates) makes a new import, disabled, for the person to finish in the editor.
 *
 * A template (format sqliimport-template, version 1):
 *   title, description, source_type
 *   placeholders  name => array( label, default, help, optional ): every {{name}} in the strings of the import is
 *                 replaced by the value given for it; name and identifier are always there
 *   secrets       the names of the secrets it needs (never a value)
 *   import        an entry as in a definitions file (expImportTransfer), with {{name}} and {{identifier}}
 *
 * Where they are: JSON files in var/<site>/storage/sqliimport/templates/ (made with "Save as template", deletable),
 * and the files extensions ship, read only: sqliimport.ini [TemplateSettings] Templates[] names an extension (its
 * share/templates/*.json) or a file relative to the installation's root. Files rather than a table: a template is
 * a document people copy between installations and keep in a repository, extensions ship them the same way, and the
 * tables need no change.
 */
class expImportTemplates
{
    const FORMAT = 'sqliimport-template';
    const VERSION = 1;
    /** The largest template read */
    const MAX_BYTES = 1048576;
    const PLACEHOLDER = '/\{\{([a-z][a-z0-9_]{0,49})\}\}/';
    /** Keys of the source configuration that are source data, never in a template */
    const SOURCE_DATA = array( 'file', 'manifest_file', 'handed_over_by', 'original_name', 'url', 'path' );

    // ---- on arrays -------------------------------------------------------------------------------------------------

    /**
     * A template from an entry of a definitions file (expImportTransfer::exportItem()).
     *
     * @param array $item
     * @param string $title
     * @param string $description
     * @return array
     */
    public static function fromItem( array $item, $title, $description = '' )
    {
        $identifier = isset( $item['identifier'] ) ? (string)$item['identifier'] : '';
        $import = array_diff_key( $item, array_flip( array( 'schedules', 'trigger', 'enabled', 'def_version' ) ) );
        $import['source'] = array_diff_key( isset( $item['source'] ) && is_array( $item['source'] ) ? $item['source'] : array(), array_flip( self::SOURCE_DATA ) );
        if ( isset( $import['target'] ) && is_array( $import['target'] ) )
        {
            unset( $import['target']['owner'] );
            $import['target']['owner_id'] = 0;
        }
        if ( isset( $import['behaviour'] ) && is_array( $import['behaviour'] ) )
            $import['behaviour']['notify_emails'] = array();
        // the strings of the import that are placeholders now; a {{ of the import itself would be read as one
        $import = self::mapStrings( $import, function ( $text ) { return str_replace( '{{', '{ {', $text ); } );
        $import['name'] = '{{name}}';
        $import['identifier'] = '{{identifier}}';
        if ( $identifier !== '' && isset( $import['mapping']['match']['pattern'] ) && is_string( $import['mapping']['match']['pattern'] ) )
            $import['mapping']['match']['pattern'] = str_replace( $identifier, '{{identifier}}', $import['mapping']['match']['pattern'] );
        return array(
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'title' => trim( (string)$title ),
            'description' => trim( (string)$description ),
            'source_type' => isset( $item['source_type'] ) ? (string)$item['source_type'] : '',
            'placeholders' => self::basePlaceholders(),
            'secrets' => isset( $item['secrets'] ) ? array_values( (array)$item['secrets'] ) : array(),
            'import' => $import,
        );
    }

    /** @return array the placeholders every template has */
    public static function basePlaceholders()
    {
        return array( 'name' => array( 'label' => 'Name', 'default' => '' ),
                      'identifier' => array( 'label' => 'Identifier', 'default' => '', 'optional' => true,
                                             'help' => 'Lower case letters, digits, _ and -; empty: made from the name' ) );
    }

    /**
     * The form of a template.
     *
     * @param mixed $template
     * @return string[] what is wrong
     */
    public static function check( $template )
    {
        if ( !is_array( $template ) )
            return array( 'not a JSON object' );
        if ( !isset( $template['format'] ) || $template['format'] !== self::FORMAT )
            return array( 'not a template of 7x SQLI Import' );
        if ( !isset( $template['version'] ) || !is_int( $template['version'] ) || $template['version'] < 1 || $template['version'] > self::VERSION )
            return array( 'a template version this 7x SQLI Import cannot read' );
        $errors = array();
        if ( !isset( $template['title'] ) || !is_string( $template['title'] ) || trim( $template['title'] ) === '' || mb_strlen( $template['title'] ) > 200 )
            $errors[] = 'a title of 1 to 200 characters is needed';
        if ( !isset( $template['import'] ) || !is_array( $template['import'] ) )
            return array_merge( $errors, array( 'the template has no import' ) );
        $placeholders = isset( $template['placeholders'] ) ? $template['placeholders'] : array();
        if ( !is_array( $placeholders ) )
            return array_merge( $errors, array( 'placeholders must be an object' ) );
        foreach ( $placeholders as $name => $def )
            if ( !is_string( $name ) || !preg_match( '/^[a-z][a-z0-9_]{0,49}$/', $name ) || !is_array( $def ) )
                $errors[] = 'a placeholder needs a name of lower case letters, digits and _ and a definition';
        foreach ( self::usedPlaceholders( $template['import'] ) as $name )
            if ( !isset( $placeholders[$name] ) && !in_array( $name, array( 'name', 'identifier' ), true ) )
                $errors[] = "the placeholder {{{$name}}} is used but not declared";
        return $errors;
    }

    /** @return string[] the placeholders the strings of an array use */
    public static function usedPlaceholders( array $data )
    {
        $used = array();
        self::mapStrings( $data, function ( $text ) use ( &$used )
        {
            if ( preg_match_all( expImportTemplates::PLACEHOLDER, $text, $m ) )
                $used = array_merge( $used, $m[1] );
            return $text;
        } );
        return array_values( array_unique( $used ) );
    }

    /**
     * The placeholders of a template as the form asks for them: name => array( label, default, help, required ).
     *
     * @param array $template
     * @return array
     */
    public static function placeholders( array $template )
    {
        $declared = isset( $template['placeholders'] ) && is_array( $template['placeholders'] ) ? $template['placeholders'] : array();
        $out = array();
        $base = self::basePlaceholders();
        // name and identifier first, with what the template says of them
        foreach ( array_unique( array_merge( array_keys( $base ), array_keys( $declared ) ) ) as $name )
        {
            $def = isset( $declared[$name] ) && is_array( $declared[$name] ) ? $declared[$name] : array();
            if ( isset( $base[$name] ) )
                $def += $base[$name];
            $out[$name] = array( 'name' => (string)$name, 'label' => isset( $def['label'] ) ? (string)$def['label'] : (string)$name,
                                 'default' => isset( $def['default'] ) && is_scalar( $def['default'] ) ? (string)$def['default'] : '',
                                 'help' => isset( $def['help'] ) ? (string)$def['help'] : '',
                                 'required' => empty( $def['optional'] ) );
        }
        return $out;
    }

    /**
     * An entry of a definitions file from a template and the values of its placeholders.
     *
     * @param array $template
     * @param array $values placeholder => value
     * @return array the entry (expImportTransfer::resolve() takes it)
     * @throws expImportValidationException placeholder => message
     */
    public static function fill( array $template, array $values )
    {
        $problems = self::check( $template );
        if ( $problems )
            throw new expImportValidationException( array( 'Template' => implode( '; ', $problems ) ) );
        $errors = array();
        $given = array();
        foreach ( self::placeholders( $template ) as $name => $def )
        {
            $value = isset( $values[$name] ) && is_scalar( $values[$name] ) ? trim( (string)$values[$name] ) : '';
            if ( $value === '' )
                $value = $def['default'];
            if ( strpos( $value, '{{' ) !== false || strpos( $value, "\0" ) !== false )
                $errors[$name] = $def['label'] . ': {{ is not allowed in a value';
            elseif ( $value === '' && $def['required'] )
                $errors[$name] = $def['label'] . ' is needed';
            $given[$name] = $value;
        }
        if ( !isset( $errors['name'] ) && mb_strlen( $given['name'] ) > 255 )
            $errors['name'] = 'Name: at most 255 characters';
        if ( $given['identifier'] === '' && $given['name'] !== '' )
            $given['identifier'] = substr( trim( (string)preg_replace( '/[^a-z0-9]+/', '_', strtolower( $given['name'] ) ), '_' ), 0, 80 ) ?: 'import';
        if ( !isset( $errors['identifier'] ) && $given['identifier'] !== '' && !preg_match( expImportTransfer::IDENTIFIER, $given['identifier'] ) )
            $errors['identifier'] = 'Identifier: lower case letters, digits, _ and -';
        if ( $errors )
            throw new expImportValidationException( $errors );
        $item = self::mapStrings( $template['import'], function ( $text ) use ( $given )
        {
            return (string)preg_replace_callback( expImportTemplates::PLACEHOLDER, function ( $m ) use ( $given )
            {
                return isset( $given[$m[1]] ) ? $given[$m[1]] : $m[0];
            }, $text );
        } );
        $left = self::usedPlaceholders( $item );
        if ( $left )
            throw new expImportValidationException( array( 'Template' => 'no value for {{' . implode( '}}, {{', $left ) . '}}' ) );
        $problems = expImportTransfer::checkItem( $item );
        if ( $problems )
            throw new expImportValidationException( array( 'Template' => implode( '; ', $problems ) ) );
        return $item;
    }

    /**
     * Applies a function to every string of an array, recursively (the keys stay).
     *
     * @param array $data
     * @param callable $fn function( string ): string
     * @return array
     */
    public static function mapStrings( array $data, callable $fn )
    {
        foreach ( $data as $key => $value )
        {
            if ( is_string( $value ) )
                $data[$key] = $fn( $value );
            elseif ( is_array( $value ) )
                $data[$key] = self::mapStrings( $value, $fn );
        }
        return $data;
    }

    /** @return string a file name made of a title */
    public static function slug( $title )
    {
        $slug = trim( (string)preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string)$title ) ), '-' );
        return substr( $slug !== '' ? $slug : 'template', 0, 60 );
    }

    /**
     * Reads one template file.
     *
     * @param string $path
     * @return array|null the template, null when the file is not one (a notice in the debug output)
     */
    public static function readFile( $path )
    {
        if ( !is_file( $path ) || filesize( $path ) > self::MAX_BYTES )
            return null;
        $template = json_decode( (string)file_get_contents( $path ), true, 64 );
        $problems = self::check( $template );
        if ( $problems )
        {
            if ( class_exists( 'eZDebug' ) )
                eZDebug::writeNotice( basename( $path ) . ' is not a template: ' . implode( '; ', $problems ), __METHOD__ );
            return null;
        }
        return $template;
    }

    /**
     * The templates of some places.
     *
     * @param array $places arrays of origin (site, or the name of an extension), files (paths), deletable
     * @return array id => array( id, origin, deletable, path, title, description, source_type, secrets, template )
     */
    public static function scan( array $places )
    {
        $out = array();
        foreach ( $places as $place )
        {
            foreach ( $place['files'] as $path )
            {
                $template = self::readFile( $path );
                if ( $template === null )
                    continue;
                $slug = self::slug( basename( $path, '.json' ) );
                $id = $place['origin'] === 'site' ? 'site-' . $slug : 'ext-' . $place['origin'] . '-' . $slug;
                if ( isset( $out[$id] ) )
                    continue;
                $out[$id] = array( 'id' => $id, 'origin' => (string)$place['origin'], 'deletable' => !empty( $place['deletable'] ), 'path' => $path,
                                   'title' => (string)$template['title'], 'description' => isset( $template['description'] ) ? (string)$template['description'] : '',
                                   'source_type' => isset( $template['source_type'] ) ? (string)$template['source_type'] : '',
                                   'secrets' => isset( $template['secrets'] ) ? array_values( array_filter( (array)$template['secrets'], 'is_string' ) ) : array(),
                                   'placeholders' => self::placeholders( $template ), 'template' => $template );
            }
        }
        uasort( $out, function ( $a, $b ) { return strcasecmp( $a['title'], $b['title'] ); } );
        return $out;
    }

    // ---- on the site -------------------------------------------------------------------------------------------------

    /** @return string the directory of this site's templates */
    public static function directory()
    {
        return expImport::storageDirectory( 'templates' );
    }

    /** @return array the places templates are read from: this site's, then those of the settings */
    public static function places()
    {
        $places = array( array( 'origin' => 'site', 'files' => glob( self::directory() . '/*.json' ) ?: array(), 'deletable' => true ) );
        $root = class_exists( 'eZSys' ) ? rtrim( (string)eZSys::rootDir(), '/' ) : '';
        $extensionDir = class_exists( 'eZExtension' ) ? eZExtension::baseDirectory() : 'extension';
        foreach ( (array)expImport::setting( 'TemplateSettings', 'Templates', array() ) as $entry )
        {
            $entry = trim( (string)$entry );
            if ( $entry === '' )
                continue;
            if ( substr( $entry, -5 ) === '.json' )
            {
                // a file relative to the installation's root, never outside it
                if ( strpos( $entry, '..' ) !== false || $entry[0] === '/' )
                    continue;
                $parts = explode( '/', $entry );
                $places[] = array( 'origin' => count( $parts ) > 2 && $parts[0] === 'extension' ? self::slug( $parts[1] ) : 'settings',
                                   'files' => array( ( $root !== '' ? $root . '/' : '' ) . $entry ), 'deletable' => false );
            }
            elseif ( preg_match( '/^[A-Za-z0-9_-]+$/', $entry ) )
                $places[] = array( 'origin' => $entry, 'files' => glob( $extensionDir . '/' . $entry . '/share/templates/*.json' ) ?: array(), 'deletable' => false );
        }
        return $places;
    }

    /** @return array every template of this site, id => entry (scan()) */
    public static function all()
    {
        return self::scan( self::places() );
    }

    /** @return array|null one template by its ID */
    public static function load( $id )
    {
        $all = self::all();
        return isset( $all[(string)$id] ) ? $all[(string)$id] : null;
    }

    /** @return array source type => number of templates, for the catalogue */
    public static function countBySource()
    {
        $out = array();
        foreach ( self::all() as $entry )
            if ( $entry['source_type'] !== '' )
                $out[$entry['source_type']] = ( isset( $out[$entry['source_type']] ) ? $out[$entry['source_type']] : 0 ) + 1;
        return $out;
    }

    /**
     * Stores a template of this site (a new file; an existing title gets -2, -3 ...).
     *
     * @param array $template
     * @return string its ID
     */
    public static function save( array $template )
    {
        $problems = self::check( $template );
        if ( $problems )
            throw new expImportValidationException( array( 'Template' => implode( '; ', $problems ) ) );
        $dir = self::directory();
        $slug = self::slug( $template['title'] );
        $name = $slug;
        for ( $n = 2; is_file( $dir . '/' . $name . '.json' ); $n++ )
            $name = $slug . '-' . $n;
        $template['saved'] = gmdate( 'Y-m-d\TH:i:s\Z', expImport::now() );
        if ( class_exists( 'eZFile' ) )
            eZFile::create( $name . '.json', $dir, expImportTransfer::encode( $template ), true );
        else
            file_put_contents( $dir . '/' . $name . '.json', expImportTransfer::encode( $template ) );
        return 'site-' . self::slug( $name );
    }

    /** @return bool whether a template of this site was deleted (shipped ones cannot be) */
    public static function delete( $id )
    {
        $entry = self::load( $id );
        if ( !$entry || !$entry['deletable'] || dirname( $entry['path'] ) !== self::directory() )
            return false;
        return @unlink( $entry['path'] );
    }
}
