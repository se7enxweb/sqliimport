<?php
/**
 * File containing the expImportDataTables class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The data tables of Exponential 6.0.15 (Setup > Data tables) as 7x SQLI Import offers them: the catalogue's group
 * "Data tables" (import into a table, a new table from an import's fields, content and tables in sync) and the link
 * "New data table from its fields" of an import. Everything is the kernel's: this class only says whether it is
 * there, whether the user may use it, and its addresses. On an older kernel nothing is offered.
 */
class expImportDataTables
{
    /** @var array|null tests: what the kernel has and the user may (writer, wizard, sync) */
    public static $kernel = null;

    /** @return array writer, wizard, sync => bool: what this kernel can do and the current user may use */
    public static function abilities()
    {
        if ( self::$kernel !== null )
            return self::$kernel + array( 'writer' => false, 'wizard' => false, 'sync' => false );
        $may = function ( $module, $function )
        {
            if ( !class_exists( 'eZUser' ) )
                return false;
            $access = eZUser::currentUser()->hasAccessTo( $module, $function );
            return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
        };
        return array(
            'writer' => class_exists( 'expDataTableWriter' ) && class_exists( 'expDataTableCatalogue' ),
            'wizard' => class_exists( 'expModuleTableWizard' ) && $may( 'setup', 'setup' ),
            'sync' => class_exists( 'expDataSync' ) && $may( 'datatable', 'sync' ),
        );
    }

    /** @return bool whether the catalogue shows the group "Data tables" */
    public static function available()
    {
        $a = self::abilities();
        return $a['writer'] || $a['wizard'] || $a['sync'];
    }

    /** @return string the module table wizard with an import's fields */
    public static function wizardUrl( $importID = 0 )
    {
        return 'setup/moduletable/(source)/sqliimport' . ( (int)$importID > 0 ? '/(ref)/' . (int)$importID : '' );
    }

    /**
     * The kernel's form of a new synchronisation, prefilled with a data table and a content class.
     *
     * @param string $class a persistent class
     * @param string $contentClass a content class identifier
     * @return string
     */
    public static function syncUrl( $class = '', $contentClass = '' )
    {
        $url = 'datatable/sync/new';
        if ( preg_match( '/^[A-Za-z_][A-Za-z0-9_]{0,99}$/', (string)$class ) )
            $url .= '/(class)/' . $class;
        if ( preg_match( '/^[a-z0-9_]{1,100}$/', (string)$contentClass ) )
            $url .= '/(content_class)/' . $contentClass;
        return $url;
    }

    /** @return array identifier => name of the content classes (for the sync cards) */
    public static function contentClasses()
    {
        if ( !class_exists( 'eZContentClass' ) )
            return array();
        $out = array();
        foreach ( (array)eZContentClass::fetchList( eZContentClass::VERSION_STATUS_DEFINED, true ) as $class )
            $out[(string)$class->attribute( 'identifier' )] = (string)$class->attribute( 'name' );
        asort( $out );
        return $out;
    }

    /** @return array class => label of the data tables (persistent classes with a table) */
    public static function tables()
    {
        $out = array();
        foreach ( expImportDataTableHandler::classes() as $class => $entry )
            $out[$class] = $class . ' (' . $entry['table'] . ')';
        ksort( $out );
        return $out;
    }

    /** @return array what the catalogue's group needs */
    public static function forCatalogue()
    {
        $a = self::abilities();
        return array( 'available' => self::available(), 'writer' => $a['writer'], 'wizard' => $a['wizard'], 'sync' => $a['sync'],
                      'tables' => $a['writer'] || $a['sync'] ? self::tables() : array(),
                      'content_classes' => $a['sync'] ? self::contentClasses() : array(),
                      'wizard_url' => self::wizardUrl(), 'syncs_url' => 'datatable/syncs', 'conflicts_url' => 'datatable/conflicts' );
    }
}
