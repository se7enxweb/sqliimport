<?php
/**
 * File containing the expImport class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The small shared helpers of the 2.x engine: time, JSON, settings, the storage directory and the audit trail.
 */
class expImport
{
    /** The product name */
    const NAME = '7x SQLI Import';

    /** @var int|null a fixed "now" for tests */
    public static $now = null;

    /** @return int */
    public static function now()
    {
        return self::$now !== null ? (int)self::$now : time();
    }

    /** @return eZDBInterface */
    public static function db()
    {
        return eZDB::instance();
    }

    /** @return eZINI */
    public static function ini()
    {
        return eZINI::instance( 'sqliimport.ini' );
    }

    /**
     * A setting of sqliimport.ini with a default.
     *
     * @param string $section
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function setting( $section, $name, $default = null )
    {
        $ini = self::ini();
        return $ini->hasVariable( $section, $name ) ? $ini->variable( $section, $name ) : $default;
    }

    /** @return string JSON of a value ('' for null) */
    public static function json( $value )
    {
        if ( $value === null )
            return '';
        $json = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
        return $json === false ? '' : $json;
    }

    /** @return array the array of a JSON text (an empty array for anything else) */
    public static function decode( $text )
    {
        if ( is_array( $text ) )
            return $text;
        $text = (string)$text;
        if ( $text === '' )
            return array();
        $data = json_decode( $text, true );
        return is_array( $data ) ? $data : array();
    }

    /** @return string the directory of the example files of the formats (share/examples of the extension) */
    public static function exampleDirectory()
    {
        return dirname( __DIR__, 2 ) . '/share/examples';
    }

    /**
     * The extension's directory below the site's storage directory (var/<site>/storage/sqliimport/<sub>), created on
     * demand. Uploads, URL snapshots and reports live there, outside the web root's served paths.
     *
     * @param string $sub
     * @return string
     */
    public static function storageDirectory( $sub = '' )
    {
        $dir = eZSys::storageDirectory() . '/sqliimport' . ( $sub !== '' ? '/' . trim( $sub, '/' ) : '' );
        if ( !is_dir( $dir ) )
            eZDir::mkdir( $dir, false, true );
        return $dir;
    }

    /** @return bool whether the job queue (expqueue) is installed and has its tables */
    public static function queueReady()
    {
        return class_exists( 'expQueue' ) && expQueue::installed();
    }

    /** @var bool false switches the audit events off (tests) */
    public static $auditEnabled = true;

    /**
     * An event of the audit trail (system.import.<action>, expImportAuditBranch), when the kernel has one.
     *
     * @param string $action e.g. run.start, import.create
     * @param array $data
     */
    public static function audit( $action, array $data )
    {
        if ( !self::$auditEnabled || !class_exists( 'expAuditHook' ) )
            return;
        try
        {
            expAuditHook::emit( 'system.import.' . $action, array( 'object' => array( 'type' => 'import' ) + $data, 'after' => $data ) );
        }
        catch ( Throwable $e )
        {
        }
    }

    /**
     * The eZPersistentObject field list of a table, from expImportSchema (one place defines the columns).
     *
     * @param string $table
     * @return array
     */
    public static function persistentFields( $table )
    {
        static $cache = array();
        if ( isset( $cache[$table] ) )
            return $cache[$table];
        $schema = expImportSchema::definition();
        $fields = array();
        foreach ( $schema[$table]['fields'] as $name => $def )
        {
            $int = in_array( $def['type'], array( 'int', 'auto_increment', 'smallint', 'tinyint' ), true );
            $fields[$name] = array( 'name' => $name, 'datatype' => $int ? 'integer' : 'string',
                                    'default' => $def['type'] === 'auto_increment' ? null : ( $int ? (int)$def['default'] : (string)$def['default'] ),
                                    'required' => $def['type'] !== 'auto_increment' );
        }
        return $cache[$table] = $fields;
    }

    /** @return int the current user's ID */
    public static function currentUserID()
    {
        return (int)eZUser::currentUserID();
    }
}
