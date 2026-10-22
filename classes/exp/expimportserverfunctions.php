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
 *   sqliimport::notices::<count>                       the current user's unread notices, for the header's bell
 *
 * Read-only; runstatus needs a POST with the form token, notices (the user's own) a GET; both the policy
 * sqliimport/view (or a 1.x one).
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

    /**
     * The feed of the header's bell (design/admin4/templates/sqliimport/header_bell.tpl, the kernel's
     * parts/a4_header_item.tpl): the current user's unread notices of runs that ended, as the header item reads them.
     * Read-only and only the caller's own notices, so a GET is answered (the header asks on every page); the policy
     * sqliimport/view is checked.
     *
     * @param array $args the number of entries (1 to 20, 8 by default)
     * @return array array( 'count' => int, 'label' => string, 'items' => list of array( text, url, meta ) )
     */
    public static function notices( $args )
    {
        $label = ezpI18n::tr( 'extension/sqliimport', 'Import notifications' );
        if ( !expImportAdmin::can( 'view' ) )
            return array( 'count' => 0, 'label' => $label, 'items' => array() );
        $limit = isset( $args[0] ) ? max( 1, min( 20, (int)$args[0] ) ) : 8;
        $userID = expImport::currentUserID();
        $count = expImportNotifier::unseenCount( $userID );
        $kinds = array( 'failed' => ezpI18n::tr( 'extension/sqliimport', 'failed' ),
                        'errors' => ezpI18n::tr( 'extension/sqliimport', 'completed with errors' ),
                        'cancelled' => ezpI18n::tr( 'extension/sqliimport', 'cancelled' ) );
        $locale = eZLocale::instance();
        $items = array();
        foreach ( expImportNotifier::notices( $userID, true, $limit ) as $n )
        {
            $url = $n['url'];
            eZURI::transformURI( $url );
            $items[] = array( 'text' => ezpI18n::tr( 'extension/sqliimport', 'Run #%run', null, array( '%run' => $n['run_id'] ) )
                                        . ( $n['import'] !== '' ? ' · ' . $n['import'] : '' ),
                              'url' => $url,
                              'meta' => ( isset( $kinds[$n['kind']] ) ? $kinds[$n['kind']] : ezpI18n::tr( 'extension/sqliimport', 'completed' ) )
                                        . ' · ' . $locale->formatShortDateTime( $n['created'] ) );
        }
        if ( $count > 0 )
            $label = ezpI18n::tr( 'extension/sqliimport', 'Import notifications: %count new', null, array( '%count' => $count ) );
        return array( 'count' => $count, 'label' => $label, 'items' => $items );
    }
}
