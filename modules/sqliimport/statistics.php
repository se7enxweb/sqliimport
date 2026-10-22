<?php
/**
 * sqliimport/statistics: the run history for review and tuning. A period (the last 7, 30, 90 or 365 days, or from/to),
 * filters (import, source type, user, which runs), the trends of runs, records, durations and failure rate, a table
 * per import (trend, average and 95th percentile duration, records per second, failure rate, most common errors),
 * the comparison with the period before or a chosen one, suggestions, and the table as CSV ((format)/csv).
 *
 * Every parameter comes as a view parameter (/(days)/7) or from the GET form (?days=7); both are checked by
 * expImportStatistics::page(). A GET that changes nothing.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$names = array( 'days', 'from', 'to', 'import', 'source', 'user', 'scope', 'compare', 'cfrom', 'cto', 'format' );
$params = array();
$userParameters = is_array( $Module->UserParameters ) ? $Module->UserParameters : array();
$http = eZHTTPTool::instance();
foreach ( $names as $name )
{
    $get = $http->hasGetVariable( $name ) ? $http->getVariable( $name ) : null;
    if ( is_string( $get ) && trim( $get ) !== '' )
        $params[$name] = trim( $get );
    elseif ( isset( $userParameters[$name] ) && (string)$userParameters[$name] !== '' )
        $params[$name] = trim( (string)$userParameters[$name] );
}

$tpl = eZTemplate::factory();
$installed = expImportSchema::installed();
$page = $installed ? expImportStatistics::page( $params ) : array();

if ( $installed && isset( $params['format'] ) && $params['format'] === 'csv' )
{
    while ( ob_get_level() > 0 )
        ob_end_clean();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . expImportStatistics::csvFileName( $page['period'] ) . '"' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Cache-Control: private, no-store' );
    $out = fopen( 'php://output', 'w' );
    expImportStatistics::writeCsv( $page, $out );
    fclose( $out );
    eZExecution::cleanExit();
}

// the address of the page with some parameters changed (view parameters, so a link is a plain GET)
$current = array();
if ( $installed )
{
    $current = array( 'import' => $page['filter']['import'] ?: '', 'source' => $page['filter']['source'], 'user' => $page['filter']['user'] ?: '',
                      'scope' => $page['filter']['scope'] !== 'production' ? $page['filter']['scope'] : '' );
    if ( $page['period']['custom'] )
        $current += array( 'from' => $page['period']['from_date'], 'to' => $page['period']['to_date'] );
    else
        $current['days'] = $page['period']['days'];
    if ( $page['compare'] === 'custom' && $page['other'] )
        $current += array( 'compare' => 'custom', 'cfrom' => $page['other']['from_date'], 'cto' => $page['other']['to_date'] );
    elseif ( $page['compare'] === 'none' )
        $current['compare'] = 'none';
}
$link = function ( array $set ) use ( $current )
{
    $url = '/sqliimport/statistics';
    foreach ( $set + $current as $name => $value )
        if ( (string)$value !== '' && preg_match( '/^[a-z0-9_-]{1,40}$/i', (string)$value ) )
            $url .= '/(' . $name . ')/' . $value;
    return $url;
};
$periodLinks = array();
if ( $installed )
{
    foreach ( expImportStatistics::PERIODS as $days )
        $periodLinks[] = array( 'days' => $days, 'url' => $link( array( 'days' => $days, 'from' => '', 'to' => '' ) ),
                                'current' => !$page['period']['custom'] && $page['period']['days'] === $days );
}

$tpl->setVariable( 'installed', $installed );
$tpl->setVariable( 'stats', $page );
$tpl->setVariable( 'period_links', $periodLinks );
$tpl->setVariable( 'csv_url', $installed ? $link( array( 'format' => 'csv' ) ) : '' );
$tpl->setVariable( 'here', $installed ? $link( array() ) : '/sqliimport/statistics' );

$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/statistics.tpl' );
$Result['path'] = array(
    array( 'url' => '/sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
    array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Statistics' ) ),
);
