<?php
/**
 * File containing the expImportRowResult class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What a handler did with one record: the outcome, the object, its version before and after, the nodes it added and
 * a message. The engine stores it as a sqliimport_run_row and adds it to the run's counters.
 */
class expImportRowResult
{
    const CREATED = 'created';
    const UPDATED = 'updated';
    const UNCHANGED = 'unchanged';
    const SKIPPED = 'skipped';
    const FAILED = 'failed';
    /** A 1.x handler processed the record; what it did is not known */
    const PROCESSED = 'processed';

    /** @var string */
    public $outcome;

    /** @var int */
    public $objectID = 0;

    /** @var string */
    public $remoteID = '';

    /** @var int */
    public $versionBefore = 0;

    /** @var int */
    public $versionAfter = 0;

    /** @var int[] node IDs the run added */
    public $nodesAdded = array();

    /** @var string */
    public $message = '';

    /** @var array what a dry run would change: attribute => array( old, new ) */
    public $diff = array();

    public function __construct( $outcome, $message = '' )
    {
        $this->outcome = (string)$outcome;
        $this->message = (string)$message;
    }

    public static function created( $objectID, $versionAfter = 1, $message = '' )
    {
        $r = new self( self::CREATED, $message );
        $r->objectID = (int)$objectID;
        $r->versionAfter = (int)$versionAfter;
        return $r;
    }

    public static function updated( $objectID, $versionBefore, $versionAfter, $message = '' )
    {
        $r = new self( self::UPDATED, $message );
        $r->objectID = (int)$objectID;
        $r->versionBefore = (int)$versionBefore;
        $r->versionAfter = (int)$versionAfter;
        return $r;
    }

    public static function unchanged( $objectID, $version = 0, $message = '' )
    {
        $r = new self( self::UNCHANGED, $message );
        $r->objectID = (int)$objectID;
        $r->versionBefore = $r->versionAfter = (int)$version;
        return $r;
    }

    public static function skipped( $message, $objectID = 0 )
    {
        $r = new self( self::SKIPPED, $message );
        $r->objectID = (int)$objectID;
        return $r;
    }

    public static function failed( $message )
    {
        return new self( self::FAILED, $message );
    }

    /** @return bool */
    public function isValid()
    {
        return in_array( $this->outcome, array( self::CREATED, self::UPDATED, self::UNCHANGED, self::SKIPPED, self::FAILED, self::PROCESSED ), true );
    }
}
