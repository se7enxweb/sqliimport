<?php
/**
 * sqliimport/trigger/<identifier> (POST: start a run) and sqliimport/trigger/<identifier>/run/<run id> (GET: its
 * status): the webhook / API trigger of an import, for other systems (expImportTriggerEndpoint, doc/triggers.md).
 *
 * The view answers a machine, never a person: JSON without page layout or debug report, never cached, no CORS header
 * (a browser page of another site cannot read an answer), no session (it is released at once and nothing is written to
 * it, so there is no cookie and no form token: the caller proves itself with a signature or an API key of the import).
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var array $Params */
eZDebug::updateSettings( array( 'debug-enabled' => false ) );
if ( function_exists( 'session_status' ) && session_status() === PHP_SESSION_ACTIVE )
    session_write_close();

if ( !expImportSchema::installed() )
    $outcome = expImportTriggerEndpoint::answer( 503, 'unavailable' );
else
{
    $param = function ( $name ) use ( $Params )
    {
        return isset( $Params[$name] ) && $Params[$name] !== false && $Params[$name] !== null ? (string)$Params[$name] : '';
    };
    $endpoint = new expImportTriggerEndpoint();
    $outcome = $endpoint->handle( array(
        'method' => isset( $_SERVER['REQUEST_METHOD'] ) ? (string)$_SERVER['REQUEST_METHOD'] : 'GET',
        'identifier' => $param( 'Identifier' ),
        'action' => $param( 'Action' ),
        'run_id' => (int)$param( 'RunID' ),
        'server' => $_SERVER,
        'query' => $_GET,
        'body' => array( 'expImportTriggerEndpoint', 'readBody' ),
    ) );
}

// never inside a catch: eZExecution::cleanExit() ends the request (also in a persistent worker)
eZExecution::discardOutputBuffers();
if ( !headers_sent() )
{
    http_response_code( (int)$outcome['status'] );
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Cache-Control: no-store' );
    header( 'X-Content-Type-Options: nosniff' );
    foreach ( $outcome['headers'] as $name => $value )
        header( $name . ': ' . $value );
}
echo json_encode( $outcome['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
eZExecution::cleanExit();
