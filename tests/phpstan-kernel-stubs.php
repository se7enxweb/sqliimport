<?php
/**
 * Declarations of the kernel and extension types the 2.x code extends or implements, for PHPStan only (they come from
 * Exponential, ezcomponents and expqueue in an installation; this file is never loaded there).
 */
interface expAuditTaxonomyBranch { public function events(); }
interface expQueueHandler { public function run( expQueueJob $job, expQueueContext $context ); }
abstract class expQueueHandlerBase implements expQueueHandler { }
class expQueueJob { }
class expQueueContext { }
#[AllowDynamicProperties]
class eZPersistentObject { public function __construct( $row = null ) {} }
class ezcBaseException extends Exception { }
abstract class ezcBaseOptions implements ArrayAccess { protected $properties; }
class ezjscServerFunctions { }
// Exponential 6.0.15 (#691): the kernel secret store and the data table layer, used only behind class_exists()
class expSecrets
{
    /** @return string|null */
    public static function get( $ref, array $fallback = array() ) { return null; }
    /** @return array */
    public static function names( $scope = null ) { return array(); }
    /** @return string */
    public static function mask( $text, array $refs, array $values = array() ) { return ''; }
    /** @return bool */
    public static function installed( $fresh = false ) { return false; }
    /** @return bool */
    public static function validName( $name ) { return false; }
    /** @return bool */
    public static function set( $name, $value, $scope = '', $description = null ) { return false; }
}
interface expDataTableFieldSource
{
    public function dataTableSourceName();
    public function dataTableReferences();
    public function dataTableFields( array $options );
    public function dataTableSourceLabel( array $options );
}
class expDataTableWriter
{
    public function __construct( $class, array $options = array() ) {}
    /** @return array */
    public function write( array $rows ) { return array(); }
}
class expDataTableCatalogue
{
    /** @return expDataTableCatalogue */
    public static function instance() { return new self(); }
    /** @return array */
    public function classes() { return array(); }
}
class expDataSync
{
    /** @return bool */
    public static function installed() { return false; }
}
class expDataConnections
{
    /** @return array */
    public static function all() { return array(); }
    /** @return array|null */
    public static function get( $identifier ) { return null; }
    /** @return eZDBInterface */
    public static function db( $identifier ) { return eZDB::instance(); }
}
