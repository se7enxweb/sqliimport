<?php
/**
 * File containing the expImportFunctionCollection class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** The template fetch functions of the module sqliimport (modules/sqliimport/function_definition.php). */
class expImportFunctionCollection
{
    /** @return array array( 'result' => the latest runs as arrays ) */
    public static function fetchRecentRuns( $limit = 5 )
    {
        if ( !expImportSchema::installed() || !expImportAdmin::can( 'view' ) )
            return array( 'result' => array() );
        $out = array();
        foreach ( expImportRun::fetchList( array(), 0, max( 1, min( 50, (int)$limit ) ) ) as $run )
            $out[] = expImportAdmin::runRow( $run );
        return array( 'result' => $out );
    }

    /** @return array array( 'result' => int ) */
    public static function fetchFailedRuns24h()
    {
        if ( !expImportSchema::installed() || !expImportAdmin::can( 'view' ) )
            return array( 'result' => 0 );
        $figures = expImportAdmin::figures();
        return array( 'result' => (int)$figures['failed_24h'] );
    }

    /**
     * The current user's unread notices of runs that ended (expImportNotifier), for a bell in the header.
     *
     * @param int $limit
     * @return array array( 'result' => array( 'count' => int, 'items' => list ) )
     */
    public static function fetchNotices( $limit = 10 )
    {
        if ( !expImportSchema::installed() || !expImportAdmin::can( 'view' ) )
            return array( 'result' => array( 'count' => 0, 'items' => array() ) );
        $userID = expImport::currentUserID();
        return array( 'result' => array( 'count' => expImportNotifier::unseenCount( $userID ),
                                         'items' => expImportNotifier::notices( $userID, true, max( 1, min( 50, (int)$limit ) ) ) ) );
    }
}
