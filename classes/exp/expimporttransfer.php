<?php
/**
 * File containing the expImportTransfer class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Import definitions as a JSON file, to move them between installations (staging to live), keep them with a project
 * or share them: "Download as JSON" of an import, "Export selected" of the dashboard, the export of the settings page,
 * and "Import definitions" (sqliimport/transfer), which reads such a file, shows what it would create and creates the
 * chosen imports disabled.
 *
 * A file (format sqliimport-definitions, version 2; version 1, the export of 2.0.0-alpha.1, is read too):
 *
 *   format, version, exported, generator
 *   secrets   the names of every secret the imports need (never a value)
 *   imports   one entry per import:
 *     identifier, name, description, enabled (as it was; an imported import starts disabled)
 *     source_type, source   origin and the options of the source type; for an upload only its original name, never
 *                           the stored file; an address without its user and password
 *     handler, options      the handler and its options
 *     target                parent_node_id, section_id, language, owner_id as before, and next to them parent,
 *                           section and owner as references (remote IDs, the path, the section identifier), which
 *                           are what another installation resolves; the IDs are the fallback
 *     behaviour, mapping    the mapping with its lines, translations, class_from and variants; its location names
 *                           its parent by reference too
 *     schedules             kind, time, interval, cron, time zone, catch-up, notify, active
 *     trigger               enabled, allow_push, secret (a secret's name), run_user (a reference); never the API
 *                           keys, the signing salt or the signing secret
 *     secrets, connections  the names of the secrets and the named connections this import needs
 *
 * The functions on arrays need no database (the unit tests use them on their own); the site's answers come from an
 * expImportTransferSite.
 */
class expImportTransfer
{
    const FORMAT = 'sqliimport-definitions';
    const VERSION = 2;
    /** The largest file read */
    const MAX_BYTES = 10485760;
    /** The most imports in one file */
    const MAX_IMPORTS = 500;
    /** Keys of the source configuration that are data of this installation, never exported */
    const SOURCE_DATA = array( 'file', 'manifest_file', 'handed_over_by' );
    /** Keys of the source configuration besides the options of the source type */
    const SOURCE_META = array( 'origin', 'url', 'path', 'original_name' );
    /** An option without a schema whose name looks like this may hold a secret value: left out of an export */
    const SECRET_LIKE = '/(pass|secret|token|api_?key|private|credential)/i';
    /** The name of a secret (expImportOptionSchema, type secret) */
    const SECRET_NAME = '/^[A-Za-z0-9_.:-]{1,100}$/';
    const IDENTIFIER = '/^[a-z0-9_-]{1,100}$/';

    /** @var expImportTransferSite|null */
    protected static $site = null;

    /** @return expImportTransferSite */
    public static function site()
    {
        return self::$site ?: ( self::$site = new expImportTransferSite() );
    }

    // ---- export ----------------------------------------------------------------------------------------------------

    /**
     * An import as stored (the export of the model with its schedules and trigger rows), for exportItem().
     *
     * @param expImportDefinition $import
     * @return array
     */
    public static function raw( expImportDefinition $import )
    {
        $raw = $import->export();
        $raw['enabled'] = (bool)$import->attribute( 'is_enabled' );
        $raw['def_version'] = (int)$import->attribute( 'def_version' );
        $raw['schedules'] = array();
        foreach ( $import->schedules() as $schedule )
            $raw['schedules'][] = array( 'kind' => (string)$schedule->attribute( 'kind' ), 'run_at' => (int)$schedule->attribute( 'run_at' ),
                                         'interval_minutes' => (int)$schedule->attribute( 'interval_minutes' ), 'cron_expr' => (string)$schedule->attribute( 'cron_expr' ),
                                         'timezone' => (string)$schedule->attribute( 'timezone' ), 'catch_up' => (string)$schedule->attribute( 'catch_up' ),
                                         'notify' => $schedule->notify(), 'is_active' => (int)$schedule->attribute( 'is_active' ) );
        $trigger = expImportTrigger::fetchByImport( $import->attribute( 'id' ) );
        $raw['trigger'] = $trigger ? array( 'is_enabled' => (int)$trigger->attribute( 'is_enabled' ), 'allow_push' => (int)$trigger->attribute( 'allow_push' ),
                                            'secret_ref' => (string)$trigger->attribute( 'secret_ref' ), 'run_user_id' => (int)$trigger->attribute( 'run_user_id' ),
                                            'sign_salt' => (string)$trigger->attribute( 'sign_salt' ) ) : null;
        return $raw;
    }

    /**
     * An import as an entry of the file: references instead of IDs, the names of its secrets, no stored file, no key.
     *
     * @param array $raw raw()
     * @param expImportTransferSite $site
     * @return array
     */
    public static function exportItem( array $raw, expImportTransferSite $site )
    {
        $sources = $site->sources();
        $handlers = $site->handlers();
        $type = isset( $raw['source_type'] ) ? (string)$raw['source_type'] : 'csv';
        $handler = isset( $raw['handler'] ) ? (string)$raw['handler'] : 'mapping';
        $sourceSchema = isset( $sources[$type]['schema'] ) ? (array)$sources[$type]['schema'] : null;
        $handlerSchema = isset( $handlers[$handler]['schema'] ) && !empty( $handlers[$handler]['schema'] ) ? (array)$handlers[$handler]['schema'] : null;

        $source = isset( $raw['source'] ) && is_array( $raw['source'] ) ? $raw['source'] : array();
        $source = array_diff_key( $source, array_flip( self::SOURCE_DATA ) );
        $meta = array_intersect_key( $source, array_flip( self::SOURCE_META ) );
        if ( isset( $meta['url'] ) )
            $meta['url'] = self::withoutCredentials( (string)$meta['url'] );
        $source = $meta + self::cleanOptions( array_diff_key( $source, $meta ), $sourceSchema );
        $options = self::cleanOptions( isset( $raw['options'] ) && is_array( $raw['options'] ) ? $raw['options'] : array(), $handlerSchema );

        $target = isset( $raw['target'] ) && is_array( $raw['target'] ) ? $raw['target'] : array();
        $target += array( 'parent_node_id' => 0, 'section_id' => 0, 'language' => '', 'owner_id' => 0 );
        $target['parent'] = (int)$target['parent_node_id'] ? ( $site->nodeRef( $target['parent_node_id'] ) ?: array( 'node_id' => (int)$target['parent_node_id'] ) ) : null;
        $target['section'] = (int)$target['section_id'] ? ( $site->sectionRef( $target['section_id'] ) ?: array( 'id' => (int)$target['section_id'] ) ) : null;
        $target['owner'] = (int)$target['owner_id'] ? ( $site->userRef( $target['owner_id'] ) ?: array( 'object_id' => (int)$target['owner_id'] ) ) : null;

        $mapping = isset( $raw['mapping'] ) && is_array( $raw['mapping'] ) ? $raw['mapping'] : null;
        if ( $mapping !== null )
        {
            unset( $mapping['id'] );
            $location = isset( $mapping['location'] ) && is_array( $mapping['location'] ) ? $mapping['location'] : array();
            if ( !empty( $location['parent_node_id'] ) )
                $location['parent'] = $site->nodeRef( $location['parent_node_id'] ) ?: array( 'node_id' => (int)$location['parent_node_id'] );
            $mapping['location'] = $location;
        }

        $schedules = array();
        foreach ( isset( $raw['schedules'] ) && is_array( $raw['schedules'] ) ? $raw['schedules'] : array() as $s )
            if ( is_array( $s ) )
                $schedules[] = array( 'kind' => (string)( isset( $s['kind'] ) ? $s['kind'] : 'once' ), 'run_at' => (int)( isset( $s['run_at'] ) ? $s['run_at'] : 0 ),
                                      'interval_minutes' => (int)( isset( $s['interval_minutes'] ) ? $s['interval_minutes'] : 0 ),
                                      'cron_expr' => (string)( isset( $s['cron_expr'] ) ? $s['cron_expr'] : '' ),
                                      'timezone' => (string)( isset( $s['timezone'] ) && $s['timezone'] !== '' ? $s['timezone'] : 'UTC' ),
                                      'catch_up' => isset( $s['catch_up'] ) && $s['catch_up'] === 'skip' ? 'skip' : 'one',
                                      'notify' => array_values( array_filter( isset( $s['notify'] ) ? (array)$s['notify'] : array(), 'is_string' ) ),
                                      'active' => !empty( $s['is_active'] ) );

        $trigger = null;
        if ( isset( $raw['trigger'] ) && is_array( $raw['trigger'] ) )
        {
            $ref = isset( $raw['trigger']['secret_ref'] ) ? (string)$raw['trigger']['secret_ref'] : '';
            $runUser = isset( $raw['trigger']['run_user_id'] ) ? (int)$raw['trigger']['run_user_id'] : 0;
            // the names of what it needs; never sign_salt, sign_created or the keys
            $trigger = array( 'enabled' => !empty( $raw['trigger']['is_enabled'] ), 'allow_push' => !empty( $raw['trigger']['allow_push'] ),
                              'secret' => preg_match( self::SECRET_NAME, $ref ) ? $ref : '',
                              'run_user' => $runUser ? ( $site->userRef( $runUser ) ?: array( 'object_id' => $runUser ) ) : null );
        }

        $item = array(
            'identifier' => (string)( isset( $raw['identifier'] ) ? $raw['identifier'] : '' ),
            'name' => (string)( isset( $raw['name'] ) ? $raw['name'] : '' ),
            'description' => (string)( isset( $raw['description'] ) ? $raw['description'] : '' ),
            'enabled' => !empty( $raw['enabled'] ),
            'source_type' => $type,
            'source' => $source,
            'handler' => $handler,
            'options' => $options,
            'target' => $target,
            'behaviour' => isset( $raw['behaviour'] ) && is_array( $raw['behaviour'] ) ? $raw['behaviour'] : array(),
            'mapping' => $mapping,
            'schedules' => $schedules,
            'trigger' => $trigger,
        );
        $item['secrets'] = self::secretNames( $item, (array)$sourceSchema, (array)$handlerSchema );
        $item['connections'] = isset( $source['connection'] ) && is_string( $source['connection'] ) && $source['connection'] !== '' ? array( $source['connection'] ) : array();
        return $item;
    }

    /**
     * The options for an export: with a schema, its options only, and a secret option only when it holds a name; without
     * one (a 1.x handler's free options), every option whose name looks like a password or key is left out.
     *
     * @param array $options
     * @param array|null $schema
     * @return array
     */
    public static function cleanOptions( array $options, ?array $schema )
    {
        $out = array();
        foreach ( $options as $name => $value )
        {
            if ( $schema === null )
            {
                if ( !preg_match( self::SECRET_LIKE, (string)$name ) )
                    $out[$name] = $value;
                continue;
            }
            if ( !isset( $schema[$name] ) )
                continue;
            $type = isset( $schema[$name]['type'] ) ? $schema[$name]['type'] : 'string';
            if ( $type === 'secret' )
            {
                if ( is_string( $value ) && ( $value === '' || preg_match( self::SECRET_NAME, $value ) ) )
                    $out[$name] = $value;
                continue;
            }
            $out[$name] = $type === 'url' && is_string( $value ) ? self::withoutCredentials( $value ) : $value;
        }
        return $out;
    }

    /** @return string an address without a user and password in it */
    public static function withoutCredentials( $url )
    {
        return (string)preg_replace( '#^([a-z][a-z0-9+.-]*://)[^/@\s]*@#i', '$1', (string)$url );
    }

    /**
     * The names of the secrets an entry needs: the values of its secret options and the trigger's secret.
     *
     * @param array $item an entry
     * @param array $sourceSchema
     * @param array $handlerSchema
     * @return string[]
     */
    public static function secretNames( array $item, array $sourceSchema, array $handlerSchema )
    {
        $names = array();
        foreach ( array( array( $sourceSchema, isset( $item['source'] ) ? (array)$item['source'] : array() ),
                         array( $handlerSchema, isset( $item['options'] ) ? (array)$item['options'] : array() ) ) as $pair )
            foreach ( $pair[0] as $name => $def )
                if ( isset( $def['type'] ) && $def['type'] === 'secret' && isset( $pair[1][$name] ) && is_string( $pair[1][$name] ) && $pair[1][$name] !== '' )
                    $names[] = $pair[1][$name];
        if ( !empty( $item['trigger']['secret'] ) )
            $names[] = (string)$item['trigger']['secret'];
        $names = array_values( array_unique( array_filter( $names, function ( $n ) { return (bool)preg_match( expImportTransfer::SECRET_NAME, $n ); } ) ) );
        sort( $names );
        return $names;
    }

    /**
     * The file of one or more entries.
     *
     * @param array $items exportItem()
     * @param int $now
     * @return array
     */
    public static function document( array $items, $now )
    {
        $secrets = array();
        foreach ( $items as $item )
            $secrets = array_merge( $secrets, isset( $item['secrets'] ) ? (array)$item['secrets'] : array() );
        $secrets = array_values( array_unique( $secrets ) );
        sort( $secrets );
        return array( 'format' => self::FORMAT, 'version' => self::VERSION, 'exported' => gmdate( 'Y-m-d\TH:i:s\Z', (int)$now ),
                      'generator' => expImport::NAME, 'secrets' => $secrets, 'imports' => array_values( $items ) );
    }

    /** @return string the JSON text of a document */
    public static function encode( array $document )
    {
        return (string)json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    /** @return string the name of the downloaded file: sqliimport-import-<identifier>.json for one import */
    public static function filename( array $items, $now )
    {
        if ( count( $items ) === 1 && isset( $items[0]['identifier'] ) && preg_match( self::IDENTIFIER, (string)$items[0]['identifier'] ) )
            return 'sqliimport-import-' . $items[0]['identifier'] . '.json';
        return 'sqliimport-imports-' . gmdate( 'Ymd-His', (int)$now ) . '.json';
    }

    /**
     * The document of imports (the site's answers for the references).
     *
     * @param expImportDefinition[] $imports
     * @return array
     */
    public static function exportImports( array $imports )
    {
        $items = array();
        foreach ( $imports as $import )
            $items[] = self::exportItem( self::raw( $import ), self::site() );
        return self::document( $items, expImport::now() );
    }

    /**
     * Sends a document as a download and ends the request.
     *
     * @param array $document
     */
    public static function send( array $document )
    {
        $json = self::encode( $document );
        while ( ob_get_level() > 0 )
            ob_end_clean();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . self::filename( $document['imports'], expImport::now() ) . '"' );
        header( 'Content-Length: ' . strlen( $json ) );
        header( 'X-Content-Type-Options: nosniff' );
        echo $json;
        eZExecution::cleanExit();
    }

    // ---- reading a file ----------------------------------------------------------------------------------------------

    /**
     * Reads a file and checks its form; a file that is not one, of a newer version, or with a malformed entry is
     * refused as a whole.
     *
     * @param string $json
     * @return array the entries
     * @throws expImportValidationException
     */
    public static function parse( $json )
    {
        $json = (string)$json;
        if ( strlen( $json ) > self::MAX_BYTES )
            throw new expImportValidationException( array( 'DefinitionsFile' => 'the file is larger than 10 MB' ) );
        $data = json_decode( $json, true, 64 );
        if ( !is_array( $data ) )
            throw new expImportValidationException( array( 'DefinitionsFile' => 'the file is not JSON' ) );
        if ( !isset( $data['format'] ) || $data['format'] !== self::FORMAT )
            throw new expImportValidationException( array( 'DefinitionsFile' => 'the file is not an export of import definitions of 7x SQLI Import' ) );
        $version = isset( $data['version'] ) ? $data['version'] : null;
        if ( !is_int( $version ) || $version < 1 || $version > self::VERSION )
            throw new expImportValidationException( array( 'DefinitionsFile' => 'the file has version ' . ( is_scalar( $version ) ? $version : '?' )
                                                                                 . ', this 7x SQLI Import reads versions 1 to ' . self::VERSION ) );
        if ( !isset( $data['imports'] ) || !is_array( $data['imports'] ) || array_values( $data['imports'] ) !== $data['imports'] )
            throw new expImportValidationException( array( 'DefinitionsFile' => 'the file has no list of imports' ) );
        if ( !$data['imports'] )
            throw new expImportValidationException( array( 'DefinitionsFile' => 'the file has no import' ) );
        if ( count( $data['imports'] ) > self::MAX_IMPORTS )
            throw new expImportValidationException( array( 'DefinitionsFile' => 'the file has more than ' . self::MAX_IMPORTS . ' imports' ) );
        $errors = array();
        $seen = array();
        foreach ( $data['imports'] as $i => $item )
        {
            foreach ( self::checkItem( $item ) as $message )
                $errors['import_' . ( $i + 1 )] = 'import ' . ( $i + 1 ) . ': ' . $message;
            if ( is_array( $item ) && isset( $item['identifier'] ) && is_string( $item['identifier'] ) )
            {
                if ( isset( $seen[$item['identifier']] ) )
                    $errors['import_' . ( $i + 1 )] = 'import ' . ( $i + 1 ) . ': the identifier ' . $item['identifier'] . ' is in the file twice';
                $seen[$item['identifier']] = true;
            }
        }
        if ( $errors )
            throw new expImportValidationException( $errors );
        return $data['imports'];
    }

    /**
     * The form of one entry.
     *
     * @param mixed $item
     * @return string[] what is wrong
     */
    public static function checkItem( $item )
    {
        if ( !is_array( $item ) )
            return array( 'not an object' );
        $errors = array();
        if ( !isset( $item['identifier'] ) || !is_string( $item['identifier'] ) || !preg_match( self::IDENTIFIER, $item['identifier'] ) )
            $errors[] = 'the identifier takes 1 to 100 lower case letters, digits, _ and -';
        if ( !isset( $item['name'] ) || !is_string( $item['name'] ) || trim( $item['name'] ) === '' || mb_strlen( $item['name'] ) > 255 )
            $errors[] = 'a name of 1 to 255 characters is needed';
        foreach ( array( 'description', 'source_type', 'handler' ) as $key )
            if ( isset( $item[$key] ) && !is_string( $item[$key] ) )
                $errors[] = "$key must be a text";
        if ( isset( $item['source_type'] ) && is_string( $item['source_type'] ) && !preg_match( '/^[a-z0-9_]{1,50}$/', $item['source_type'] ) )
            $errors[] = 'the source type is not a source type identifier';
        if ( isset( $item['handler'] ) && is_string( $item['handler'] ) && !preg_match( '/^[A-Za-z0-9_.-]{1,100}$/', $item['handler'] ) )
            $errors[] = 'the handler is not a handler identifier';
        foreach ( array( 'source', 'options', 'target', 'behaviour' ) as $key )
            if ( isset( $item[$key] ) && !is_array( $item[$key] ) )
                $errors[] = "$key must be an object";
        foreach ( array( 'mapping', 'trigger' ) as $key )
            if ( isset( $item[$key] ) && !is_array( $item[$key] ) )
                $errors[] = "$key must be an object or null";
        if ( isset( $item['mapping']['fields'] ) && !is_array( $item['mapping']['fields'] ) )
            $errors[] = 'the lines of the mapping must be a list';
        if ( isset( $item['schedules'] ) && ( !is_array( $item['schedules'] ) || array_values( $item['schedules'] ) !== $item['schedules'] ) )
            $errors[] = 'schedules must be a list';
        foreach ( array( 'secrets', 'connections' ) as $key )
            if ( isset( $item[$key] ) && ( !is_array( $item[$key] ) || array_filter( $item[$key], function ( $n ) { return !is_string( $n ); } ) ) )
                $errors[] = "$key must be a list of names";
        return $errors;
    }

    /**
     * Validates options against a schema. A required option without a value is a warning (the import is created
     * disabled and the editor asks for it); a value of the wrong kind is an error.
     *
     * @param array $schema
     * @param array $input
     * @return array ( values, errors name => message, warnings name => message )
     */
    public static function validateOptions( array $schema, array $input )
    {
        list( $values, $errors ) = expImportOptionSchema::validate( $schema, $input );
        $hard = $soft = array();
        foreach ( $errors as $name => $message )
        {
            $given = array_key_exists( $name, $input ) ? $input[$name] : null;
            $empty = $given === null || $given === '' || ( is_array( $given ) && !$given );
            if ( $empty && !empty( $schema[$name]['required'] ) )
                $soft[$name] = $message;
            else
                $hard[$name] = $message;
        }
        return array( $values, $hard, $soft );
    }

    /**
     * An entry as it would be stored on this site: the columns, the mapping, schedules and trigger with the IDs of
     * this site, and what is missing here (warnings) or cannot be created (errors).
     *
     * @param array $item an entry (parse())
     * @param expImportTransferSite $site
     * @return array
     */
    public static function resolve( array $item, expImportTransferSite $site )
    {
        $errors = $warnings = array();
        $sources = $site->sources();
        $handlers = $site->handlers();
        $type = isset( $item['source_type'] ) && $item['source_type'] !== '' ? (string)$item['source_type'] : 'csv';
        $legacy = $type === 'legacy';
        $handler = isset( $item['handler'] ) && $item['handler'] !== '' ? (string)$item['handler'] : 'mapping';

        // the source
        $source = isset( $item['source'] ) && is_array( $item['source'] ) ? array_diff_key( $item['source'], array_flip( self::SOURCE_DATA ) ) : array();
        $config = array();
        $sourceSchema = array();
        if ( !$legacy && !isset( $sources[$type] ) )
            $errors[] = "this site has no source type $type";
        elseif ( !$legacy )
        {
            $sourceSchema = (array)$sources[$type]['schema'];
            if ( isset( $sources[$type]['status'] ) && $sources[$type]['status'] !== 'enabled' )
                $warnings[] = "the source type $type is switched off on this site";
            $meta = array_intersect_key( $source, array_flip( self::SOURCE_META ) );
            list( $values, $hard, $soft ) = self::validateOptions( $sourceSchema, array_diff_key( $source, $meta ) );
            foreach ( $hard as $message )
                $errors[] = "source option $message";
            foreach ( $soft as $message )
                $warnings[] = "source option $message";
            $origins = !empty( $sources[$type]['origins'] ) ? array_values( (array)$sources[$type]['origins'] ) : array( 'upload', 'url', 'path' );
            $origin = isset( $meta['origin'] ) && in_array( $meta['origin'], $origins, true ) ? (string)$meta['origin'] : $origins[0];
            if ( isset( $meta['origin'] ) && $meta['origin'] !== $origin )
                $warnings[] = 'the source type ' . $type . ' cannot read from ' . (string)$meta['origin'] . ' here: ' . $origin . ' is used';
            $config = array( 'origin' => $origin );
            if ( $origin === 'upload' )
            {
                $name = isset( $meta['original_name'] ) && is_string( $meta['original_name'] ) ? basename( $meta['original_name'] ) : '';
                $warnings[] = $name !== '' ? "upload the file $name again in the editor (files are not part of an export)" : 'upload the source file in the editor';
            }
            elseif ( $origin === 'url' )
            {
                $config['url'] = isset( $meta['url'] ) ? self::withoutCredentials( trim( (string)$meta['url'] ) ) : '';
                $problem = $config['url'] === '' ? 'no address is given' : $site->urlProblem( $config['url'] );
                if ( $problem !== null )
                    $warnings[] = 'the address: ' . $problem;
            }
            elseif ( $origin === 'path' )
            {
                $config['path'] = isset( $meta['path'] ) ? trim( (string)$meta['path'] ) : '';
                $problem = $config['path'] === '' ? 'no path is given' : $site->pathProblem( $config['path'] );
                if ( $problem !== null )
                    $warnings[] = 'the server path: ' . $problem;
            }
            $config += $values;
            if ( !empty( $config['connection'] ) && is_string( $config['connection'] ) && !$site->hasConnection( $config['connection'] ) )
                $warnings[] = 'the named connection ' . $config['connection'] . ' is not set up on this site';
        }

        // the handler and its options
        $options = array();
        $handlerSchema = array();
        if ( !isset( $handlers[$handler] ) )
            $errors[] = "this site has no handler $handler";
        elseif ( $legacy !== (bool)$handlers[$handler]['legacy'] )
            $errors[] = $legacy ? "$handler is not a 1.x handler on this site" : "$handler is a 1.x handler: its source type must be legacy";
        else
        {
            if ( isset( $handlers[$handler]['status'] ) && $handlers[$handler]['status'] === 'disabled' )
                $warnings[] = "the handler $handler is switched off on this site";
            $given = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : array();
            if ( $handlers[$handler]['legacy'] )
            {
                foreach ( $given as $name => $value )
                    if ( is_string( $name ) && is_scalar( $value ) )
                        $options[$name] = (string)$value;
            }
            elseif ( !empty( $handlers[$handler]['schema'] ) )
            {
                $handlerSchema = (array)$handlers[$handler]['schema'];
                list( $options, $hard, $soft ) = self::validateOptions( $handlerSchema, $given );
                foreach ( $hard as $message )
                    $errors[] = "handler option $message";
                foreach ( $soft as $message )
                    $warnings[] = "handler option $message";
            }
        }

        // the target, by reference first
        $t = isset( $item['target'] ) && is_array( $item['target'] ) ? $item['target'] : array();
        $target = array( 'parent_node_id' => 0, 'section_id' => 0, 'language' => isset( $t['language'] ) && is_string( $t['language'] ) ? $t['language'] : '', 'owner_id' => 0 );
        $parentRef = isset( $t['parent'] ) && is_array( $t['parent'] ) ? $t['parent'] : ( !empty( $t['parent_node_id'] ) ? array( 'node_id' => (int)$t['parent_node_id'] ) : null );
        $parentFound = true;
        if ( $parentRef )
        {
            $target['parent_node_id'] = $site->resolveNode( $parentRef );
            if ( !$target['parent_node_id'] )
            {
                $parentFound = false;
                $target['parent_node_id'] = $site->defaultParent();
                $warnings[] = 'the parent node ' . self::describeRef( $parentRef ) . ' is not on this site: the default parent node ' . $target['parent_node_id'] . ' is used';
            }
        }
        $sectionRef = isset( $t['section'] ) && is_array( $t['section'] ) ? $t['section'] : ( !empty( $t['section_id'] ) ? array( 'id' => (int)$t['section_id'] ) : null );
        if ( $sectionRef )
        {
            $target['section_id'] = $site->resolveSection( $sectionRef );
            if ( !$target['section_id'] )
                $warnings[] = 'the section ' . ( isset( $sectionRef['identifier'] ) ? (string)$sectionRef['identifier'] : '#' . ( isset( $sectionRef['id'] ) ? (int)$sectionRef['id'] : 0 ) )
                            . ' is not on this site: the section of the parent is used';
        }
        if ( $target['language'] !== '' && !$site->hasLanguage( $target['language'] ) )
            $warnings[] = 'this site has no language ' . $target['language'];
        $ownerRef = isset( $t['owner'] ) && is_array( $t['owner'] ) ? $t['owner'] : ( !empty( $t['owner_id'] ) ? array( 'object_id' => (int)$t['owner_id'] ) : null );
        if ( $ownerRef )
        {
            $target['owner_id'] = $site->resolveUser( $ownerRef );
            if ( !$target['owner_id'] )
                $warnings[] = 'the user the import runs as is not on this site: it runs as the user who starts it';
        }

        // the mapping
        $mapping = null;
        $class = '';
        $classFound = null;
        if ( isset( $item['mapping'] ) && is_array( $item['mapping'] ) && !$legacy )
        {
            $mapping = array_intersect_key( $item['mapping'], array_flip( array( 'class', 'language', 'match', 'fields', 'location', 'class_from', 'variants' ) ) );
            $mapping += array( 'class' => '', 'language' => '', 'match' => array(), 'fields' => array(), 'location' => array() );
            $class = is_string( $mapping['class'] ) ? $mapping['class'] : '';
            $location = is_array( $mapping['location'] ) ? $mapping['location'] : array();
            $locationRef = isset( $location['parent'] ) && is_array( $location['parent'] ) ? $location['parent']
                         : ( !empty( $location['parent_node_id'] ) ? array( 'node_id' => (int)$location['parent_node_id'] ) : null );
            unset( $location['parent'] );
            if ( $locationRef )
            {
                $node = $site->resolveNode( $locationRef );
                if ( !$node )
                {
                    $node = $target['parent_node_id'] ?: $site->defaultParent();
                    if ( $parentFound || !$parentRef || $locationRef != $parentRef )
                        $warnings[] = 'the parent node of the mapping ' . self::describeRef( $locationRef ) . ' is not on this site: node ' . $node . ' is used';
                }
                $location['parent_node_id'] = $node;
            }
            $mapping['location'] = $location;
            $attributes = $class !== '' ? $site->classAttributes( $class ) : null;
            $classFound = $attributes !== null;
            if ( $class !== '' && !$classFound )
                $warnings[] = "this site has no class $class";
            foreach ( expImportMapper::validate( $mapping, $attributes ) as $message )
                $warnings[] = 'mapping: ' . $message;
            foreach ( expImportMapper::validateVariants( $mapping, function ( $identifier ) use ( $site ) { return $site->classAttributes( $identifier ); } ) as $message )
                $warnings[] = 'mapping: ' . $message;
        }
        elseif ( $handler === 'mapping' && !$legacy )
            $warnings[] = 'the import has no mapping yet: make it in the editor';

        // the schedules (paused) and the trigger (switched off, without keys)
        $schedules = array();
        foreach ( isset( $item['schedules'] ) && is_array( $item['schedules'] ) ? $item['schedules'] : array() as $n => $s )
        {
            if ( !is_array( $s ) )
                continue;
            $calc = array( 'kind' => (string)( isset( $s['kind'] ) ? $s['kind'] : '' ), 'run_at' => (int)( isset( $s['run_at'] ) ? $s['run_at'] : 0 ),
                           'interval_minutes' => (int)( isset( $s['interval_minutes'] ) ? $s['interval_minutes'] : 0 ),
                           'cron_expr' => (string)( isset( $s['cron_expr'] ) ? $s['cron_expr'] : '' ),
                           'timezone' => (string)( isset( $s['timezone'] ) && $s['timezone'] !== '' ? $s['timezone'] : 'UTC' ) );
            $scheduleErrors = $site->scheduleErrors( $calc );
            if ( $scheduleErrors )
            {
                $warnings[] = 'schedule ' . ( $n + 1 ) . ' is left out: ' . implode( '; ', $scheduleErrors );
                continue;
            }
            $notify = array_values( array_filter( isset( $s['notify'] ) ? (array)$s['notify'] : array(), function ( $a ) { return is_string( $a ) && filter_var( $a, FILTER_VALIDATE_EMAIL ); } ) );
            $schedules[] = $calc + array( 'catch_up' => isset( $s['catch_up'] ) && $s['catch_up'] === 'skip' ? 'skip' : 'one', 'notify' => $notify );
        }
        $trigger = null;
        if ( isset( $item['trigger'] ) && is_array( $item['trigger'] ) )
        {
            $secret = isset( $item['trigger']['secret'] ) && is_string( $item['trigger']['secret'] ) && preg_match( self::SECRET_NAME, $item['trigger']['secret'] ) ? $item['trigger']['secret'] : '';
            $runUser = isset( $item['trigger']['run_user'] ) && is_array( $item['trigger']['run_user'] ) ? $site->resolveUser( $item['trigger']['run_user'] ) : 0;
            if ( !empty( $item['trigger']['run_user'] ) && !$runUser )
                $warnings[] = 'the user a triggered run runs as is not on this site: choose one in the editor';
            $trigger = array( 'allow_push' => !empty( $item['trigger']['allow_push'] ), 'secret_ref' => $secret, 'run_user_id' => $runUser );
        }

        // the secrets: the names in the file and the ones its options name; never a value
        $names = self::secretNames( array( 'source' => $config, 'options' => $options, 'trigger' => array( 'secret' => $trigger ? $trigger['secret_ref'] : '' ) ), $sourceSchema, $handlerSchema );
        foreach ( isset( $item['secrets'] ) && is_array( $item['secrets'] ) ? $item['secrets'] : array() as $name )
            if ( is_string( $name ) && preg_match( self::SECRET_NAME, $name ) )
                $names[] = $name;
        $names = array_values( array_unique( $names ) );
        sort( $names );
        $secrets = array();
        foreach ( $names as $name )
        {
            $secrets[$name] = $site->hasSecret( $name );
            if ( !$secrets[$name] )
                $warnings[] = "the secret $name is not set on this site";
        }

        return array(
            'import' => array(
                'name' => trim( (string)$item['name'] ),
                'description' => isset( $item['description'] ) ? (string)$item['description'] : '',
                'source_type' => $legacy ? 'legacy' : $type,
                'source_config' => $legacy ? array() : $config,
                'handler' => $handler,
                'target' => $target,
                'behaviour' => self::behaviour( isset( $item['behaviour'] ) && is_array( $item['behaviour'] ) ? $item['behaviour'] : array() ),
                'options' => $options,
            ),
            'mapping' => $mapping,
            'schedules' => $schedules,
            'trigger' => $trigger,
            'class' => $class,
            'class_found' => $classFound,
            'secrets' => $secrets,
            'warnings' => array_values( array_unique( $warnings ) ),
            'errors' => array_values( array_unique( $errors ) ),
        );
    }

    /** @return array the behaviour of an entry with the values the editor allows */
    public static function behaviour( array $b )
    {
        $out = array();
        if ( isset( $b['chunk_size'] ) )
            $out['chunk_size'] = max( 1, min( 5000, (int)$b['chunk_size'] ) );
        $out['update_policy'] = isset( $b['update_policy'] ) && in_array( $b['update_policy'], array( 'update', 'create_only', 'update_only', 'skip_existing' ), true ) ? $b['update_policy'] : 'update';
        $out['trash_policy'] = isset( $b['trash_policy'] ) && in_array( $b['trash_policy'], array( 'restore', 'recreate', 'skip' ), true ) ? $b['trash_policy'] : 'skip';
        $out['max_rows'] = isset( $b['max_rows'] ) ? max( 0, (int)$b['max_rows'] ) : 0;
        $out['conflict_policy'] = isset( $b['conflict_policy'] ) && in_array( $b['conflict_policy'], array( 'overwrite', 'skip', 'fill_empty', 'merge' ), true ) ? $b['conflict_policy'] : 'overwrite';
        $out['undo_days'] = isset( $b['undo_days'] ) && ( $b['undo_days'] === '' || ctype_digit( (string)$b['undo_days'] ) ) ? (string)$b['undo_days'] : '';
        if ( isset( $b['notify_when'] ) && is_string( $b['notify_when'] ) )
            $out['notify_when'] = $b['notify_when'];
        if ( isset( $b['notify_starter'] ) )
            $out['notify_starter'] = (bool)$b['notify_starter'];
        $out['notify_emails'] = array_slice( array_values( array_filter( isset( $b['notify_emails'] ) ? (array)$b['notify_emails'] : array(),
                                                                         function ( $a ) { return is_string( $a ) && filter_var( $a, FILTER_VALIDATE_EMAIL ); } ) ), 0, 20 );
        return $out;
    }

    /** @return string a reference for a message: its path or remote ID and its node ID */
    public static function describeRef( array $ref )
    {
        $parts = array();
        if ( !empty( $ref['path'] ) )
            $parts[] = (string)$ref['path'];
        elseif ( !empty( $ref['remote_id'] ) )
            $parts[] = 'remote ID ' . (string)$ref['remote_id'];
        if ( !empty( $ref['node_id'] ) )
            $parts[] = '#' . (int)$ref['node_id'];
        return $parts ? implode( ' ', $parts ) : '?';
    }

    /**
     * An identifier no import has: the identifier itself, else with _2, _3 ...
     *
     * @param string $identifier
     * @param callable $taken function( string $identifier ): bool
     * @return string
     */
    public static function freeIdentifier( $identifier, callable $taken )
    {
        $identifier = (string)$identifier;
        if ( !$taken( $identifier ) )
            return $identifier;
        $base = substr( $identifier, 0, 94 );
        for ( $n = 2; $n < 100000; $n++ )
            if ( !$taken( $base . '_' . $n ) )
                return $base . '_' . $n;
        return $base . '_' . substr( md5( uniqid( '', true ) ), 0, 5 );
    }

    /**
     * What a file would do on this site: per entry new or an existing identifier, what it resolves to, warnings and
     * errors, and the choices (new: created, with a free identifier when it is taken; replace: the existing import of
     * that identifier takes the file's definition; skip).
     *
     * @param array $items parse()
     * @param expImportTransferSite $site
     * @return array
     */
    public static function plan( array $items, expImportTransferSite $site )
    {
        $plan = array();
        $taken = function ( $identifier ) use ( $site ) { return $site->importId( $identifier ) > 0; };
        foreach ( array_values( $items ) as $i => $item )
        {
            $resolved = self::resolve( $item, $site );
            $existing = $site->importId( $item['identifier'] );
            $choices = $resolved['errors'] ? array( 'skip' ) : ( $existing ? array( 'new', 'replace', 'skip' ) : array( 'new', 'skip' ) );
            $plan[] = array(
                'index' => $i,
                'identifier' => (string)$item['identifier'],
                'name' => $resolved['import']['name'],
                'source_type' => $resolved['import']['source_type'],
                'handler' => $resolved['import']['handler'],
                'existing_id' => $existing,
                'new_identifier' => $existing ? self::freeIdentifier( $item['identifier'], $taken ) : (string)$item['identifier'],
                'class' => $resolved['class'],
                'class_found' => $resolved['class_found'],
                'parent_node_id' => (int)$resolved['import']['target']['parent_node_id'],
                'schedules' => count( $resolved['schedules'] ),
                'trigger' => $resolved['trigger'] !== null,
                'secrets' => $resolved['secrets'],
                'warnings' => $resolved['warnings'],
                'errors' => $resolved['errors'],
                'choices' => $choices,
                'choice' => $resolved['errors'] ? 'skip' : ( $existing ? 'skip' : 'new' ),
                'resolved' => $resolved,
            );
        }
        return $plan;
    }

    // ---- storing -------------------------------------------------------------------------------------------------

    /**
     * Creates an import from a resolved entry, or puts it in place of an existing one: disabled, with its mapping,
     * its schedules paused and its trigger switched off (the keys of a replaced import stay, none are made).
     *
     * @param array $resolved resolve()
     * @param string $identifier
     * @param int $replaceID the import to replace, 0 for a new one
     * @return expImportDefinition
     */
    public static function store( array $resolved, $identifier, $replaceID = 0 )
    {
        $db = expImport::db();
        $db->begin();
        $row = $resolved['import'];
        $columns = array( 'name' => $row['name'], 'description' => $row['description'], 'source_type' => $row['source_type'],
                          'source_config' => expImport::json( $row['source_config'] ), 'handler' => $row['handler'],
                          'target_json' => expImport::json( $row['target'] ), 'behaviour_json' => expImport::json( $row['behaviour'] ),
                          'options_json' => expImport::json( $row['options'] ), 'is_enabled' => 0 );
        $import = $replaceID ? expImportDefinition::fetch( (int)$replaceID ) : null;
        if ( $import )
        {
            foreach ( $columns as $key => $value )
                $import->setAttribute( $key, $value );
            $import->storeChange();
            $db->query( 'DELETE FROM sqliimport_schedule WHERE import_id = ' . (int)$import->attribute( 'id' ) );
        }
        else
        {
            $import = expImportDefinition::create( array( 'identifier' => (string)$identifier ) + $columns );
            $import->store();
        }
        $id = (int)$import->attribute( 'id' );
        if ( $resolved['mapping'] !== null )
        {
            $existing = (int)$import->attribute( 'mapping_id' ) ? expImportMapping::fetch( $import->attribute( 'mapping_id' ) ) : null;
            $mapping = expImportMapping::saveArray( $resolved['mapping'], $id, $existing ?: null );
            $import->setAttribute( 'mapping_id', (int)$mapping->attribute( 'id' ) );
            $import->store();
        }
        self::storeSchedules( $id, $resolved['schedules'] );
        if ( $resolved['trigger'] !== null )
        {
            $trigger = expImportTrigger::forImport( $id );
            $trigger->setAttribute( 'is_enabled', 0 );
            $trigger->setAttribute( 'allow_push', $resolved['trigger']['allow_push'] ? 1 : 0 );
            $trigger->setAttribute( 'secret_ref', (string)$resolved['trigger']['secret_ref'] );
            $trigger->setAttribute( 'run_user_id', (int)$resolved['trigger']['run_user_id'] );
            $trigger->setAttribute( 'modified', expImport::now() );
            $trigger->setAttribute( 'modifier_id', expImport::currentUserID() );
            $trigger->store();
        }
        $db->commit();
        return $import;
    }

    /**
     * Schedules for an import, paused (they run only after the person resumes them).
     *
     * @param int $importID
     * @param array $schedules arrays of kind, run_at, interval_minutes, cron_expr, timezone, catch_up, notify
     */
    public static function storeSchedules( $importID, array $schedules )
    {
        $now = expImport::now();
        foreach ( $schedules as $s )
        {
            $schedule = new expImportSchedule( array( 'import_id' => (int)$importID, 'created' => $now, 'creator_id' => expImport::currentUserID(), 'last_run' => 0 ) );
            foreach ( array( 'kind', 'run_at', 'interval_minutes', 'cron_expr', 'timezone', 'catch_up' ) as $key )
                $schedule->setAttribute( $key, $s[$key] );
            $schedule->setAttribute( 'notify_json', expImport::json( isset( $s['notify'] ) ? (array)$s['notify'] : array() ) );
            $schedule->setAttribute( 'modified', $now );
            $schedule->computeNext( $now );
            $schedule->setAttribute( 'is_active', 0 );
            $schedule->store();
        }
    }

    /**
     * Carries out a plan with the person's choices.
     *
     * @param array $plan plan()
     * @param array $choices index => new, replace or skip
     * @return array ( expImportDefinition[] created or replaced, int skipped )
     */
    public static function apply( array $plan, array $choices )
    {
        $done = array();
        $skipped = 0;
        $taken = function ( $identifier ) { return expImportDefinition::fetchByIdentifier( $identifier ) !== null; };
        foreach ( $plan as $entry )
        {
            $choice = isset( $choices[$entry['index']] ) ? (string)$choices[$entry['index']] : $entry['choice'];
            if ( !in_array( $choice, $entry['choices'], true ) || $choice === 'skip' )
            {
                $skipped++;
                continue;
            }
            $replace = 0;
            if ( $choice === 'replace' )
            {
                $existing = expImportDefinition::fetchByIdentifier( $entry['identifier'] );
                if ( !$existing || !expImportAdmin::can( 'edit', (string)$existing->attribute( 'handler' ) ) || expImportRun::fetchActive( $existing->attribute( 'id' ) ) )
                {
                    $skipped++;
                    continue;
                }
                $replace = (int)$existing->attribute( 'id' );
            }
            if ( !expImportAdmin::can( 'edit', (string)$entry['handler'] ) )
            {
                $skipped++;
                continue;
            }
            $identifier = $replace ? $entry['identifier'] : self::freeIdentifier( $entry['identifier'], $taken );
            $import = self::store( $entry['resolved'], $identifier, $replace );
            expImport::audit( $replace ? 'import.change' : 'import.create',
                              array( 'id' => (int)$import->attribute( 'id' ), 'identifier' => (string)$import->attribute( 'identifier' ),
                                     'from' => 'definitions file', 'replaced' => (bool)$replace, 'warnings' => count( $entry['warnings'] ) ) );
            $done[] = $import;
        }
        expImport::audit( 'definitions.import', array( 'imported' => count( $done ), 'skipped' => $skipped ) );
        return array( $done, $skipped );
    }
}
