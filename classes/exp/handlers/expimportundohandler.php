<?php
/**
 * File containing the expImportUndoHandler class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Takes the changes of an earlier run back (an undo run, run_kind undo). Its records are the rows of that run that
 * created or updated an object, newest first, so children go before their parents:
 *
 *   created  the object goes to the trash (all its locations), unless content that this run did not create was put
 *            below it since, or it changed after the run; it can be restored from the trash as any object
 *   updated  the version before the run is published again as a new version, unless the object changed after the
 *            run (its current version is not the one the run published) or the old version no longer exists
 *
 * What cannot be undone is skipped with the reason and counts as skipped; the rest counts as undone. An undo is
 * possible for [RunSettings] UndoDays days (the import's behaviour undo_days can say otherwise), and once per run.
 */
class expImportUndoHandler implements expImportHandlerInterface
{
    /** @var int[] the objects the undone run created (their children may go with them) */
    protected $created = array();

    public static function identifier()
    {
        return 'undo';
    }

    public static function label()
    {
        return 'Undo of a run';
    }

    public static function schema()
    {
        return array();
    }

    public function open( expImportContext $context )
    {
        $parent = (int)$context->run->attribute( 'parent_run_id' );
        $this->created = array();
        foreach ( (array)expImport::db()->arrayQuery( "SELECT object_id FROM sqliimport_run_row WHERE run_id = $parent AND outcome = 'created' AND object_id > 0" ) as $row )
            $this->created[(int)$row['object_id']] = true;
    }

    public function process( expImportRecord $record, expImportContext $context )
    {
        $objectID = (int)$record->get( 'object_id', 0 );
        $object = $objectID ? eZContentObject::fetch( $objectID ) : null;
        $outcome = (string)$record->get( 'outcome', '' );
        if ( !$object instanceof eZContentObject )
            return expImportRowResult::skipped( 'the object no longer exists', $objectID );
        if ( (int)$object->attribute( 'status' ) === eZContentObject::STATUS_ARCHIVED )
            return expImportRowResult::skipped( 'the object is in the trash already', $objectID );
        $current = (int)$object->attribute( 'current_version' );
        $after = (int)$record->get( 'version_after', 0 );
        if ( $after && $current !== $after )
            return expImportRowResult::skipped( "the object changed after the run (version $current, the run published $after)", $objectID );

        if ( $outcome === 'created' )
        {
            $nodes = array();
            foreach ( $object->assignedNodes() as $node )
            {
                if ( !$node instanceof eZContentObjectTreeNode )
                    continue;
                foreach ( $node->subTree( array( 'Limitation' => array(), 'LoadDataMap' => false, 'AsObject' => false ) ) as $child )
                {
                    if ( !isset( $this->created[(int)$child['contentobject_id']] ) )
                        return expImportRowResult::skipped( 'content the run did not create is below it', $objectID );
                }
                $nodes[] = (int)$node->attribute( 'node_id' );
            }
            if ( $context->dryRun )
                return new expImportRowResult( expImportRowResult::UNDONE, 'dry run: would go to the trash' );
            if ( $nodes )
                eZContentObjectTreeNode::removeSubtrees( $nodes, true );
            $result = new expImportRowResult( expImportRowResult::UNDONE, 'moved to the trash' );
            $result->objectID = $objectID;
            $result->versionBefore = $current;
            return $result;
        }

        // updated: publish the version before the run again
        $before = (int)$record->get( 'version_before', 0 );
        if ( !$before || !eZContentObjectVersion::fetchVersion( $before, $objectID ) )
            return expImportRowResult::skipped( "the version $before before the run no longer exists", $objectID );
        if ( $context->dryRun )
            return new expImportRowResult( expImportRowResult::UNDONE, "dry run: version $before would be published again" );
        $db = expImport::db();
        $db->begin();
        $version = $object->copyRevertTo( $before );
        $db->commit();
        $operation = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $objectID, 'version' => $version ) );
        if ( !is_array( $operation ) || !isset( $operation['status'] ) || (int)$operation['status'] !== eZModuleOperationInfo::STATUS_CONTINUE )
            return expImportRowResult::failed( "version $before could not be published again" );
        // the locations the run added to this object (extra_parents_from) go again; never its main location
        $removed = 0;
        $main = (int)$object->mainNodeID();
        $added = array();
        foreach ( (array)$record->get( 'nodes_added', array() ) as $nodeID )
            if ( (int)$nodeID !== $main && eZContentObjectTreeNode::fetch( (int)$nodeID, false, false ) )
                $added[] = (int)$nodeID;
        if ( $added )
        {
            eZContentObjectTreeNode::removeSubtrees( $added, false );
            $removed = count( $added );
        }
        eZContentObject::clearCache( array( $objectID ) );
        $result = new expImportRowResult( expImportRowResult::UNDONE, "version $before published again as version $version"
                                                                    . ( $removed ? ", $removed location(s) the run added removed" : '' ) );
        $result->objectID = $objectID;
        $result->versionBefore = $current;
        $result->versionAfter = (int)$version;
        return $result;
    }

    public function close( expImportContext $context )
    {
        $this->created = array();
    }

    /**
     * Whether the runs of a handler can be undone: not the data table target, nor a handler whose class has a static
     * undoable() returning false.
     *
     * @param string $handler
     * @return bool
     */
    public static function handlerUndoable( $handler )
    {
        if ( $handler === 'datatable' )
            return false;
        if ( $handler === 'mapping' )
            return true;
        try
        {
            $handlers = expImportRegistry::instance()->handlers();
        }
        catch ( Throwable $e )
        {
            return true;
        }
        if ( isset( $handlers[$handler]['class'] ) && method_exists( $handlers[$handler]['class'], 'undoable' ) )
            return (bool)call_user_func( array( $handlers[$handler]['class'], 'undoable' ) );
        return true;
    }

    /**
     * Whether a run can be undone now, and why not.
     *
     * @param expImportRun $run
     * @return string|null the reason it cannot, null when it can
     */
    public static function refusal( expImportRun $run )
    {
        if ( !$run->isFinal() )
            return 'the run has not ended yet';
        if ( !in_array( $run->attribute( 'run_kind' ), array( 'import', 'retry' ), true ) )
            return 'only an import run (or a retry) can be undone';
        if ( !(int)$run->attribute( 'created_count' ) && !(int)$run->attribute( 'updated_count' ) )
            return 'the run created and updated nothing';
        $summary = $run->summaryArray();
        if ( !empty( $summary['undone_by'] ) )
        {
            $undo = expImportRun::fetch( $summary['undone_by'] );
            if ( $undo && $undo->attribute( 'status' ) !== expImportRun::STATUS_FAILED && $undo->attribute( 'status' ) !== expImportRun::STATUS_CANCELLED )
                return 'the run was undone by run #' . (int)$summary['undone_by'];
        }
        $import = $run->import();
        // undo works on content; the rows a run wrote into a data table (the target "Rows of a data table") are no
        // content, and a handler of a site may say so too (a static undoable() that returns false)
        if ( $import && !self::handlerUndoable( (string)$import->attribute( 'handler' ) ) )
            return 'the rows of this target are not content and cannot be undone; run the import again with the old data';
        $days = (int)expImport::setting( 'RunSettings', 'UndoDays', 30 );
        if ( $import )
        {
            $behaviour = $import->behaviour();
            if ( isset( $behaviour['undo_days'] ) && $behaviour['undo_days'] !== '' && (int)$behaviour['undo_days'] >= 0 )
                $days = (int)$behaviour['undo_days'];
        }
        if ( $days <= 0 || !empty( $summary['undo_expired'] ) || (int)$run->attribute( 'finished_at' ) < expImport::now() - $days * 86400 )
            return 'the time to undo the run is over (' . $days . ' days)';
        return null;
    }
}
