<?php
/**
 * File containing the expImportTriggerEndpoint class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What the view sqliimport/trigger answers (a machine, never a person; JSON, no session, no cookies, no CORS):
 *
 *   POST sqliimport/trigger/<identifier>              starts a run (signed call or API key); the body, when the
 *                                                     trigger allows data, is the run's snapshot
 *   GET  sqliimport/trigger/<identifier>/run/<run id> the status of a run of this import (API key only)
 *
 *   202  the run is queued: {"run_id", "status", "status_url" (API key holders)}
 *   200  the status of a run
 *   401  no credentials, a wrong key or signature, an expired timestamp, a signature used before
 *   403  the trigger has no run user allowed to run the import, the import is disabled, a signed call asks for a status
 *   404  no import of that identifier, or its trigger is off (the same answer: nothing tells them apart)
 *   405  another method (Allow: POST, or GET for a status)
 *   409  the import runs already (a run is queued or running)
 *   413  the body is larger than [TriggerSettings] MaxBodyMegabytes
 *   415  the body is not the import's format, or the import takes no data in the call
 *   429  over the rate limit of the import (Retry-After)
 *   503  the job queue is missing or the run could not be queued
 *
 * Every call that reaches an import is in the audit trail (system.import.trigger.*), without key, secret or signature.
 */
class expImportTriggerEndpoint
{
    /** @var expImportTriggerCallStore */
    protected $store;

    public function __construct( ?expImportTriggerCallStore $store = null )
    {
        $this->store = $store ?: new expImportTriggerDbCallStore();
    }

    /** @return int a setting of [TriggerSettings] */
    protected static function setting( $name, $default )
    {
        return (int)expImport::setting( 'TriggerSettings', $name, $default );
    }

    /** @return int the body limit in bytes */
    public static function maxBodyBytes()
    {
        return max( 1, self::setting( 'MaxBodyMegabytes', 20 ) ) * 1048576;
    }

    /**
     * Answers a request.
     *
     * @param array $request method, identifier, action ('' or 'run'), run_id, server ($_SERVER), query ($_GET),
     *                       body (callable( int $max ): array( string|null file, int length, bool too_large ))
     * @return array [ 'status' => int, 'body' => array, 'headers' => array ]
     */
    public function handle( array $request )
    {
        $method = strtoupper( (string)$request['method'] );
        $identifier = (string)$request['identifier'];
        $action = (string)$request['action'];
        $server = (array)$request['server'];
        $query = (array)$request['query'];
        $now = expImport::now();

        if ( expImport::setting( 'TriggerSettings', 'Trigger', 'enabled' ) !== 'enabled' )
            return self::answer( 404, 'not_found' );
        if ( !preg_match( '/^[a-z0-9_-]{1,100}$/', $identifier ) || !in_array( $action, array( '', 'run' ), true ) )
            return self::answer( 404, 'not_found' );
        $isStatus = $action === 'run';
        if ( $method !== ( $isStatus ? 'GET' : 'POST' ) )
            return self::answer( 405, 'method_not_allowed', array(), array( 'Allow' => $isStatus ? 'GET' : 'POST' ) );
        $import = expImportDefinition::fetchByIdentifier( $identifier );
        $trigger = $import ? expImportTrigger::fetchByImport( $import->attribute( 'id' ) ) : null;
        if ( !$import || !$trigger || !(int)$trigger->attribute( 'is_enabled' ) )
            return self::answer( 404, 'not_found' );
        $importID = (int)$import->attribute( 'id' );
        $audit = array( 'id' => $importID, 'import' => $identifier );

        $this->store->purge( $importID, $now - max( 600, 2 * self::setting( 'SignatureWindowSeconds', 300 ) ) );
        if ( expImportTriggerAuth::rateLimited( $this->store, $importID, 'fail', self::setting( 'FailedRequestsPerMinute', 20 ), $now ) )
            return self::answer( 429, 'rate_limited', array(), array( 'Retry-After' => '60' ) );

        // the body is read before the signature check, which covers it; never more than the limit
        $bodyFile = null;
        $bodyLength = 0;
        if ( !$isStatus )
        {
            list( $bodyFile, $bodyLength, $tooLarge ) = call_user_func( $request['body'], self::maxBodyBytes() );
            if ( $tooLarge )
                return $this->refuse( $importID, $audit, 413, 'body_too_large', $bodyFile );
        }

        // who calls: an API key, else a signature
        $key = expImportTriggerAuth::keyFromServer( $server );
        $keyRow = null;
        $signature = null;
        if ( $key !== null )
        {
            $keyRow = expImportTriggerKey::verify( $importID, $key );
            if ( !$keyRow )
                return $this->refuse( $importID, $audit, 401, 'invalid_key', $bodyFile );
            $audit['key'] = (string)$keyRow->attribute( 'key_id' );
        }
        else
        {
            list( $timestamp, $signature ) = expImportTriggerAuth::signatureFromRequest( $server, $query );
            if ( $timestamp === null && $signature === null )
                return $this->refuse( $importID, $audit, 401, 'missing_credentials', $bodyFile, array( 'WWW-Authenticate' => 'Bearer' ) );
            if ( $isStatus )
                return $this->refuse( $importID, $audit, 403, 'status_needs_api_key', $bodyFile );
            $secret = $trigger->signingSecret();
            $body = $bodyFile !== null ? (string)file_get_contents( $bodyFile ) : '';
            $check = expImportTriggerAuth::checkSignature( (string)$secret, $method, expImportTriggerAuth::canonicalPath( $identifier ), $timestamp, $signature,
                                                           $body, $now, self::setting( 'SignatureWindowSeconds', 300 ) );
            unset( $body );
            if ( $check !== 'ok' )
                return $this->refuse( $importID, $audit, 401, $check === 'expired' ? 'expired' : 'invalid_signature', $bodyFile );
            $audit['auth'] = 'signature';
        }

        $kind = $isStatus ? 'status' : 'trigger';
        $limit = $isStatus ? self::setting( 'StatusRequestsPerMinute', 60 ) : self::setting( 'RequestsPerMinute', 6 );
        if ( expImportTriggerAuth::rateLimited( $this->store, $importID, $kind, $limit, $now ) )
        {
            self::discard( $bodyFile );
            expImport::audit( 'trigger.limited', $audit );
            return self::answer( 429, 'rate_limited', array(), array( 'Retry-After' => '60' ) );
        }
        // the call counts; a signature counts once only (a second time is a replay)
        if ( $signature !== null )
        {
            if ( expImportTriggerAuth::replayed( $this->store, $importID, $signature, $now ) )
                return $this->refuse( $importID, $audit, 401, 'replayed', $bodyFile );
        }
        else
            $this->store->add( $importID, hash( 'sha256', random_bytes( 16 ) ), $kind, $now );
        if ( $keyRow )
        {
            $keyRow->setAttribute( 'last_used', $now );
            $keyRow->store( array( 'last_used' ) );
        }

        if ( $isStatus )
        {
            $run = expImportRun::fetch( (int)$request['run_id'] );
            if ( !$run || (int)$run->attribute( 'import_id' ) !== $importID )
                return self::answer( 404, 'not_found' );
            return array( 'status' => 200, 'body' => self::runStatus( $run ), 'headers' => array() );
        }
        return $this->start( $import, $trigger, $audit, $keyRow !== null, $bodyFile, $bodyLength, $server );
    }

    /** Starts the run of a call that passed every check. */
    protected function start( expImportDefinition $import, expImportTrigger $trigger, array $audit, $withKey, $bodyFile, $bodyLength, array $server )
    {
        $importID = (int)$import->attribute( 'id' );
        $pushed = '';
        if ( $bodyFile !== null && $bodyLength > 0 )
        {
            $sources = expImportRegistry::instance()->sources();
            $type = (string)$import->attribute( 'source_type' );
            $fileFormat = expImportTriggerAuth::takesData( isset( $sources[$type] ) ? $sources[$type] : null, $import->sourceConfig(), $import->isLegacy() );
            list( $status, $result ) = expImportTriggerAuth::checkBody( $bodyLength, self::maxBodyBytes(), (int)$trigger->attribute( 'allow_push' ) && $fileFormat,
                                                                        isset( $server['CONTENT_TYPE'] ) ? (string)$server['CONTENT_TYPE'] : ( isset( $server['HTTP_CONTENT_TYPE'] ) ? (string)$server['HTTP_CONTENT_TYPE'] : '' ),
                                                                        isset( $sources[$type] ) ? (array)$sources[$type]['extensions'] : array(),
                                                                        isset( $server[expImportTriggerAuth::HEADER_FORMAT] ) ? (string)$server[expImportTriggerAuth::HEADER_FORMAT] : '' );
            if ( $status )
            {
                self::discard( $bodyFile );
                expImport::audit( 'trigger.refused', $audit + array( 'reason' => $status === 413 ? 'body_too_large' : 'unsupported_body' ) );
                return self::answer( $status, $status === 413 ? 'body_too_large' : 'unsupported_media_type', array( 'message' => $result ) );
            }
            try
            {
                $pushed = expImportSourceFiles::storePushed( $bodyFile, $result );
            }
            catch ( expImportSourceException $e )
            {
                self::discard( $bodyFile );
                return self::answer( 503, 'unavailable' );
            }
        }
        else
            self::discard( $bodyFile );

        $user = eZUser::fetch( (int)$trigger->attribute( 'run_user_id' ) );
        if ( !$user || !expImportAdmin::can( 'run', (string)$import->attribute( 'handler' ), $user ) )
            return $this->drop( $pushed, $audit, 403, 'no_run_user' );
        if ( !(int)$import->attribute( 'is_enabled' ) )
            return $this->drop( $pushed, $audit, 403, 'import_disabled' );
        if ( expImportRun::fetchActive( $importID ) )
            return $this->drop( $pushed, $audit, 409, 'already_running' );
        try
        {
            $run = expImportRunner::start( $import, array( 'trigger' => 'rest', 'user_id' => (int)$user->attribute( 'contentobject_id' ), 'pushed' => $pushed ) );
        }
        catch ( expImportException $e )
        {
            // a run that another call started a moment ago
            if ( expImportRun::fetchActive( $importID ) )
                return $this->drop( $pushed, $audit, 409, 'already_running' );
            eZDebug::writeWarning( 'trigger of ' . $audit['import'] . ': ' . $e->getMessage(), __METHOD__ );
            return $this->drop( $pushed, $audit, 503, 'unavailable' );
        }
        $runID = (int)$run->attribute( 'id' );
        expImport::audit( 'trigger.start', $audit + array( 'run' => $runID, 'pushed' => $pushed !== '' ) );
        $body = array( 'run_id' => $runID, 'status' => (string)$run->attribute( 'status' ) );
        if ( $withKey )
            $body['status_url'] = self::statusUrl( (string)$import->attribute( 'identifier' ), $runID );
        return array( 'status' => 202, 'body' => $body, 'headers' => array() );
    }

    /** @return string the address of the status of a run */
    public static function statusUrl( $identifier, $runID )
    {
        $url = 'sqliimport/trigger/' . $identifier . '/run/' . (int)$runID;
        if ( class_exists( 'eZURI' ) )
            eZURI::transformURI( $url, false, 'full' );
        return $url;
    }

    /** @return array the status of a run as a caller sees it (no user, no paths) */
    public static function runStatus( expImportRun $run )
    {
        $row = expImportAdmin::runRow( $run );
        $out = array();
        foreach ( array( 'id', 'status', 'kind', 'trigger', 'percent', 'total', 'done', 'created', 'updated', 'unchanged', 'skipped', 'failed',
                         'processed', 'undone', 'queued_at', 'started_at', 'finished_at', 'is_final' ) as $name )
            $out[$name] = $row[$name];
        $out['run_id'] = $out['id'];
        unset( $out['id'] );
        return $out;
    }

    /** A refused call: counted for the limit of failures, audited, its body thrown away. */
    protected function refuse( $importID, array $audit, $status, $error, $bodyFile, array $headers = array() )
    {
        self::discard( $bodyFile );
        $this->store->add( $importID, hash( 'sha256', random_bytes( 16 ) ), 'fail', expImport::now() );
        expImport::audit( 'trigger.refused', $audit + array( 'reason' => $error ) );
        return self::answer( $status, $error, array(), $headers );
    }

    /** A call refused after its data was kept: the data goes. */
    protected function drop( $pushed, array $audit, $status, $error )
    {
        if ( $pushed !== '' )
            self::discard( expImport::storageDirectory( 'pushed' ) . '/' . basename( $pushed ) );
        expImport::audit( 'trigger.refused', $audit + array( 'reason' => $error ) );
        return self::answer( $status, $error );
    }

    /** Removes a temporary body file. */
    protected static function discard( $file )
    {
        if ( $file !== null && $file !== '' && is_file( $file ) )
            @unlink( $file );
    }

    /** @return array an error answer */
    public static function answer( $status, $error, array $extra = array(), array $headers = array() )
    {
        return array( 'status' => (int)$status, 'body' => array( 'error' => (string)$error ) + $extra, 'headers' => $headers );
    }

    /**
     * Reads the request body into a temporary file of the extension's storage, never more than $max bytes.
     *
     * @param int $max
     * @param string $input php://input, or a file in the tests
     * @return array [ string|null file (null: empty body), int length, bool too_large ]
     */
    public static function readBody( $max, $input = 'php://input' )
    {
        $length = isset( $_SERVER['CONTENT_LENGTH'] ) ? $_SERVER['CONTENT_LENGTH'] : ( isset( $_SERVER['HTTP_CONTENT_LENGTH'] ) ? $_SERVER['HTTP_CONTENT_LENGTH'] : null );
        if ( is_numeric( $length ) && (int)$length > $max )
            return array( null, (int)$length, true );
        $in = @fopen( $input, 'rb' );
        if ( !$in )
            return array( null, 0, false );
        $file = expImport::storageDirectory( 'pushed' ) . '/incoming-' . bin2hex( random_bytes( 8 ) );
        $out = @fopen( $file, 'xb' );
        if ( !$out )
        {
            fclose( $in );
            return array( null, 0, false );
        }
        $copied = (int)stream_copy_to_stream( $in, $out, $max + 1 );
        fclose( $in );
        fclose( $out );
        if ( $copied > $max )
            return array( $file, $copied, true );
        if ( $copied === 0 )
        {
            @unlink( $file );
            return array( null, 0, false );
        }
        return array( $file, $copied, false );
    }
}
