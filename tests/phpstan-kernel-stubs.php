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
