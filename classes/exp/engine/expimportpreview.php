<?php
/**
 * File containing the expImportPreview class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The sample and the preview of the import editor, in the request itself (no job, no run stored):
 *
 *   sample()   the first records of the import's source as the source reads them, the field paths found and what
 *              was detected (delimiter, encoding, format)
 *   preview()  the first records through the import's mapping as a dry run: per record what would happen (created,
 *              updated, unchanged, skipped, failed) and the fields that would change, old and new value
 *
 * Both read a snapshot of the source in var/<site>/storage/sqliimport/preview/<import id>/. A 1.x handler reads its
 * own source and publishes as it reads, so it has neither (its dry run is refused).
 */
class expImportPreview
{
    /**
     * @param expImportDefinition $import
     * @param int $limit
     * @return array 'records' (list of array( ref, data )), 'fields' (paths), 'detected', 'columns' (the first level
     *               keys of the first records, for a table)
     * @throws expImportException
     */
    public static function sample( expImportDefinition $import, $limit = 20 )
    {
        $source = self::source( $import );
        $sample = $source->sample( max( 1, min( 100, (int)$limit ) ) );
        $records = array();
        $columns = array();
        foreach ( $sample['records'] as $record )
        {
            $records[] = array( 'ref' => $record->ref, 'data' => $record->data, 'flat' => self::flatten( $record->data ) );
            foreach ( array_keys( self::flatten( $record->data ) ) as $key )
                $columns[$key] = true;
        }
        return array( 'records' => $records, 'fields' => $sample['fields'], 'detected' => $sample['detected'],
                      'columns' => array_slice( array_keys( $columns ), 0, 30 ) );
    }

    /**
     * @param expImportDefinition $import
     * @param int $limit
     * @return array list of array( 'ref', 'outcome', 'message', 'object_id', 'remote_id', 'diff' )
     * @throws expImportException
     */
    public static function preview( expImportDefinition $import, $limit = 20 )
    {
        if ( $import->isLegacy() )
            throw new expImportException( 'a 1.x handler cannot run without publishing, so it has no preview' );
        $registry = expImportRegistry::instance();
        $registry->registerTransforms();
        $handler = $registry->handlerFor( $import );
        $run = new expImportRun( array( 'id' => 0, 'import_id' => (int)$import->attribute( 'id' ), 'run_kind' => 'dry_run', 'status' => 'running',
                                        'user_id' => expImport::currentUserID() ) );
        $log = new expImportLogger( 0 );
        $context = new expImportContext( $run, $import, $import->options(), $log );
        $handler->open( $context );
        $out = array();
        try
        {
            foreach ( self::source( $import )->records() as $record )
            {
                if ( count( $out ) >= max( 1, (int)$limit ) )
                    break;
                try
                {
                    $result = $handler->process( $record, $context );
                }
                catch ( Exception $e )
                {
                    $result = expImportRowResult::failed( $e->getMessage() );
                }
                $out[] = array( 'ref' => $record->ref, 'outcome' => $result->outcome, 'outcome_label' => expImportAdmin::label( 'outcome', $result->outcome ),
                                'message' => $result->message, 'object_id' => $result->objectID, 'remote_id' => $result->remoteID,
                                'diff' => $result->diff );
            }
        }
        finally
        {
            $handler->close( $context );
        }
        return $out;
    }

    /** @return expImportSourceInterface the import's source on its preview snapshot */
    protected static function source( expImportDefinition $import )
    {
        $registry = expImportRegistry::instance();
        $sources = $registry->sources();
        $type = (string)$import->attribute( 'source_type' );
        if ( !isset( $sources[$type] ) )
            throw new expImportException( "unknown source type '$type'" );
        list( $values, $errors ) = expImportOptionSchema::validate( $sources[$type]['schema'], $import->sourceConfig() );
        if ( $errors )
            throw new expImportValidationException( $errors );
        $values['file'] = expImportSourceFiles::snapshot( $import );
        return $registry->source( $type, $values );
    }

    /**
     * A record as path => text, for a table (lists joined, long texts cut).
     * @param array $data
     * @return array
     */
    public static function flatten( array $data )
    {
        $out = array();
        foreach ( expImportRecord::paths( $data ) as $path )
        {
            $value = expImportRecord::pathValue( $data, $path, '' );
            if ( is_array( $value ) )
                $value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
            $value = (string)$value;
            $out[$path] = mb_strlen( $value ) > 200 ? mb_substr( $value, 0, 200 ) . '…' : $value;
        }
        return $out;
    }
}
