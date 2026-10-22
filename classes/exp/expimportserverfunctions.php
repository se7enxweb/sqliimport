<?php
/**
 * File containing the expImportServerFunctions class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The ezjscore server functions of the Imports tab (ezjscore.ini [ezjscServer_sqliimport]), called by
 * javascript/sqliimport_admin.js through Exp.io:
 *
 *   sqliimport::runstatus::<run id>::<last log id>   the run's status, progress, counters and the log lines after
 *                                                     the last one the page has (the live run page)
 *
 * Read-only; POST with the form token, and the policy sqliimport/view (or a 1.x one).
 */
class expImportServerFunctions extends ezjscServerFunctions
{
    /** Refuses a call that is not a POST with the form token by a user who may see the imports. */
    protected static function guard()
    {
        if ( !isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' )
            throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'Only POST requests are answered.' ) );
        if ( class_exists( 'ezxFormToken' ) && ezxFormToken::isEnabled() )
        {
            $given = isset( $_POST['ezxform_token'] ) && is_scalar( $_POST['ezxform_token'] ) ? (string)$_POST['ezxform_token']
                   : ( isset( $_SERVER['HTTP_X_CSRF_TOKEN'] ) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '' );
            if ( $given === '' || !hash_equals( (string)ezxFormToken::getToken(), $given ) )
                throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The form token is missing or wrong. Please reload the page.' ) );
        }
        if ( !expImportAdmin::can( 'view' ) )
            throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'You may not see the imports.' ) );
    }

    /**
     * @param array $args run ID, the last log ID the page shows
     * @return array
     */
    public static function runstatus( $args )
    {
        self::guard();
        $run = expImportRun::fetch( isset( $args[0] ) ? (int)$args[0] : 0 );
        if ( !$run )
            throw new expImportException( ezpI18n::tr( 'extension/sqliimport', 'The run does not exist.' ) );
        $data = expImportAdmin::runRow( $run );
        $after = isset( $args[1] ) ? (int)$args[1] : 0;
        $log = array();
        foreach ( expImportRunLog::fetchByRun( $run->attribute( 'id' ), $after, array(), 0, 100 ) as $line )
        {
            $log[] = array( 'id' => (int)$line->attribute( 'id' ), 'time' => date( 'H:i:s', (int)$line->attribute( 'created' ) ),
                            'severity' => $line->attribute( 'severity' ), 'ref' => $line->attribute( 'row_ref' ), 'message' => $line->attribute( 'message' ) );
        }
        $data['log'] = $log;
        return $data;
    }
}
