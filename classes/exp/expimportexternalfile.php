<?php
/**
 * File containing the expImportExternalFile class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A file another tool hands to Imports: "Re-import this file" of an export, the file of a CIE export. The tool links
 * to
 *
 *   sqliimport/edit/new/(source)/<format>/(from)/<tool>/(job)/<job id>
 *
 * and this class asks the tool for the job's file. It gets one only when the current user may download that job by
 * the tool's own rules, so a link never shows anyone a file they could not download from the tool itself. The file
 * (and an export's column manifest next to it) is copied into the uploads of Imports and waits in the session until
 * the source step is saved: nothing is imported before the import's steps.
 *
 * Tools: xrowextract (XrowExtractReimport::source(): a finished class export the user may see) and bccie (bccieJob:
 * the user's own background export). Another tool adds itself in sqliimport.ini [HandoverSettings] Tools[] with a
 * class implementing a static fileOf( $jobID, eZUser $user ) that returns array( path, name, manifest ) or null.
 */
class expImportExternalFile
{
    const SESSION = 'SQLIImportHandover';

    /**
     * The file of a tool's job for a user, or null when the tool, the job or the user's right to it is not there.
     *
     * @param string $toolID xrowextract, bccie or a tool of [HandoverSettings] Tools[]
     * @param string $jobID
     * @param eZUser $user
     * @return array|null array( 'path', 'name', 'manifest' (path or null) )
     */
    public static function fromTool( $toolID, $jobID, eZUser $user )
    {
        $toolID = (string)$toolID;
        $jobID = (string)$jobID;
        if ( $jobID === '' || !preg_match( '/^[A-Za-z0-9_.-]{1,100}$/', $jobID ) )
            return null;
        switch ( $toolID )
        {
            case 'xrowextract':
                if ( !class_exists( 'XrowExtractReimport' ) || !class_exists( 'XrowExtractJob' ) || !XrowExtractJob::isValidID( $jobID ) )
                    return null;
                $access = $user->hasAccessTo( 'xrowextract', 'import' );
                if ( $access['accessWord'] === 'no' && $user->hasAccessTo( 'sqliimport', 'manage' )['accessWord'] === 'no' )
                    return null;
                $source = XrowExtractReimport::source( XrowExtractJob::load( $jobID ), (string)$user->attribute( 'login' ), XrowExtractJob::allowAllJobs() );
                return $source ? array( 'path' => $source['path'], 'name' => $source['name'], 'manifest' => $source['manifest'] ) : null;
            case 'bccie':
                if ( !class_exists( 'bccieJob' ) || !bccieJob::isID( $jobID ) || $user->hasAccessTo( 'bccie', 'read' )['accessWord'] === 'no' )
                    return null;
                // accessible(): only the user who started the export, while that user can still read the object
                $path = bccieJob::accessible( $jobID ) ? bccieJob::exportFile( $jobID ) : false;
                if ( !$path || !is_file( $path ) || substr( $path, -4 ) === '.slk' )
                    return null;
                return array( 'path' => $path, 'name' => 'cie-export-' . substr( $jobID, 0, 8 ) . '.csv', 'manifest' => null );
        }
        foreach ( (array)expImport::setting( 'HandoverSettings', 'Tools', array() ) as $line )
        {
            list( $id, $class ) = array_pad( explode( ';', (string)$line, 2 ), 2, '' );
            if ( $id === $toolID && $class !== '' && class_exists( $class ) && method_exists( $class, 'fileOf' ) )
            {
                $file = $class::fileOf( $jobID, $user );
                if ( is_array( $file ) && isset( $file['path'], $file['name'] ) && is_file( $file['path'] ) )
                    return array( 'path' => $file['path'], 'name' => basename( $file['name'] ), 'manifest' => isset( $file['manifest'] ) ? $file['manifest'] : null );
            }
        }
        return null;
    }

    /**
     * Takes a tool's file over: copied into the uploads (its manifest too) and kept in the session for the source
     * step of a new import.
     *
     * @param string $toolID
     * @param string $jobID
     * @param string[] $extensions the file name extensions the chosen format accepts
     * @return array|null the hand-over (file, original_name, manifest, tool, job), null when there is no such file
     */
    public static function handOver( $toolID, $jobID, array $extensions )
    {
        $file = self::fromTool( $toolID, $jobID, eZUser::currentUser() );
        if ( !$file )
            return null;
        $stored = expImportSourceFiles::storeUpload( $file['path'], $file['name'], $extensions );
        $manifest = '';
        if ( !empty( $file['manifest'] ) && is_file( $file['manifest'] ) )
        {
            $manifest = $stored . '.manifest.json';
            if ( !copy( $file['manifest'], expImport::storageDirectory( 'uploads' ) . '/' . $manifest ) )
                $manifest = '';
        }
        $handover = array( 'file' => $stored, 'original_name' => basename( $file['name'] ), 'manifest' => $manifest,
                           'tool' => (string)$toolID, 'job' => (string)$jobID );
        eZHTTPTool::instance()->setSessionVariable( self::SESSION, $handover );
        expImport::audit( 'import.handover', array( 'tool' => (string)$toolID, 'job' => (string)$jobID, 'file' => $handover['original_name'] ) );
        return $handover;
    }

    /** @return array|null the file waiting for the source step of a new import */
    public static function pending()
    {
        $http = eZHTTPTool::instance();
        $value = $http->hasSessionVariable( self::SESSION ) ? $http->sessionVariable( self::SESSION ) : null;
        return is_array( $value ) && isset( $value['file'] ) ? $value + array( 'manifest' => '', 'tool' => '', 'job' => '', 'original_name' => '' ) : null;
    }

    /** Forgets the waiting file (it became an import's source, or another hand-over replaced it). */
    public static function forget()
    {
        eZHTTPTool::instance()->removeSessionVariable( self::SESSION );
    }
}
