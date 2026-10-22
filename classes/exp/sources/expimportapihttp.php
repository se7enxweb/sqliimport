<?php
/**
 * File containing the expImportApiHttp class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The JSON requests of the sources that read a hosted service's API with a token (Airtable, Notion): a pause between
 * two requests to the same service (its rate limit), waiting and trying again when the service answers 429 (Too Many
 * Requests, after its Retry-After) or 502/503/504, the error text of the service's answer, and the snapshot written
 * atomically. The requests themselves go through expImportPlatformHttp (address checks, size limit, time limit), so a
 * test replaces the network with expImportPlatformHttp::$transport and the waiting with $sleeper.
 */
class expImportApiHttp
{
    /** How often a request is tried again after 429 or a gateway error. */
    const RETRIES = 4;

    /** @var callable|null replaces usleep() (tests): function ( float $seconds ) */
    public static $sleeper = null;

    /** @var callable|null replaces microtime( true ) (tests): function () returning float */
    public static $clock = null;

    /** @var array service => the time of its last request */
    protected static $last = array();

    /** Forgets the times of the last requests (tests). */
    public static function reset()
    {
        self::$last = array();
    }

    /** @return float now, in seconds */
    protected static function now()
    {
        return self::$clock ? (float)call_user_func( self::$clock ) : microtime( true );
    }

    /** Waits so many seconds. */
    public static function sleep( $seconds )
    {
        if ( $seconds <= 0 )
            return;
        if ( self::$sleeper )
        {
            call_user_func( self::$sleeper, (float)$seconds );
            return;
        }
        usleep( (int)round( $seconds * 1000000 ) );
    }

    /**
     * A JSON request to a service, within its rate limit.
     *
     * @param string $service the name of the service in messages (Airtable)
     * @param string $method GET or POST
     * @param string $url
     * @param array $headers name => value
     * @param string|null $body
     * @param float $interval the least time between two requests to the service, in seconds (Airtable: 0.2, five a second)
     * @param float $backoff the wait after a 429 without Retry-After, in seconds
     * @return array the decoded answer
     * @throws expImportSourceException
     */
    public static function json( $service, $method, $url, array $headers, $body, $interval, $backoff )
    {
        $headers += array( 'Accept' => 'application/json' );
        for ( $try = 0; ; $try++ )
        {
            $since = isset( self::$last[$service] ) ? self::now() - self::$last[$service] : $interval;
            if ( $since < $interval )
                self::sleep( $interval - $since );
            self::$last[$service] = self::now();
            $answer = expImportPlatformHttp::request( $method, $url, $headers, $body );
            $status = (int)$answer['status'];
            if ( ( $status === 429 || in_array( $status, array( 502, 503, 504 ), true ) ) && $try < self::RETRIES )
            {
                $after = expImportPlatformHttp::header( (array)$answer['headers'], 'Retry-After' );
                $wait = is_numeric( $after ) ? (float)$after : ( $status === 429 ? (float)$backoff : 2.0 * ( $try + 1 ) );
                self::sleep( min( 120.0, max( 0.0, $wait ) ) );
                continue;
            }
            $data = json_decode( (string)$answer['body'], true );
            if ( $status >= 200 && $status < 300 )
            {
                if ( !is_array( $data ) )
                    throw new expImportSourceException( "$service: the answer is not JSON" );
                return $data;
            }
            $detail = self::errorText( $data );
            $text = "$service answered $status";
            if ( $status === 401 || $status === 403 )
                $text .= ': the access was refused (check the token and what it may read)';
            elseif ( $status === 404 )
                $text .= ': not found (check the IDs, and that the token may read it)';
            elseif ( $status === 429 )
                $text .= ': too many requests, still after waiting';
            throw new expImportSourceException( $text . ( $detail !== '' ? ' (' . $detail . ')' : '' ) );
        }
    }

    /** @return string the error text of a service's answer (Airtable: error.message or error; Notion: message), short */
    protected static function errorText( $data )
    {
        if ( !is_array( $data ) )
            return '';
        $parts = array();
        if ( isset( $data['error'] ) && is_array( $data['error'] ) )
        {
            if ( isset( $data['error']['type'] ) && is_string( $data['error']['type'] ) )
                $parts[] = $data['error']['type'];
            if ( isset( $data['error']['message'] ) && is_string( $data['error']['message'] ) )
                $parts[] = $data['error']['message'];
        }
        elseif ( isset( $data['error'] ) && is_string( $data['error'] ) )
            $parts[] = $data['error'];
        if ( isset( $data['code'] ) && is_string( $data['code'] ) )
            $parts[] = $data['code'];
        if ( isset( $data['message'] ) && is_string( $data['message'] ) )
            $parts[] = $data['message'];
        return substr( implode( ': ', $parts ), 0, 300 );
    }

    /**
     * Writes records into the snapshot, one JSON object per line, into a part file renamed when complete.
     *
     * @param iterable $records arrays
     * @param string $target
     * @param int $limit 0: all
     * @param string $service for messages
     * @return int the number written
     * @throws expImportSourceException
     */
    public static function writeSnapshot( $records, $target, $limit, $service )
    {
        $tmp = $target . '.part';
        $out = fopen( $tmp, 'wb' );
        if ( !$out )
            throw new expImportSourceException( "$service: the snapshot cannot be written" );
        $n = 0;
        try
        {
            foreach ( $records as $record )
            {
                fwrite( $out, (string)json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n" );
                $n++;
                if ( $limit > 0 && $n >= $limit )
                    break;
            }
            fclose( $out );
            $out = null;
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( "$service: the snapshot cannot be written" );
        }
        finally
        {
            if ( $out )
                fclose( $out );
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
        return $n;
    }

    /**
     * A text without a token: by the name of its secret and the value itself.
     *
     * @param string $text
     * @param string $ref the secret's name
     * @param string $token its value
     * @return string
     */
    public static function mask( $text, $ref, $token )
    {
        $text = expImportSecrets::mask( (string)$text, $ref !== '' ? array( $ref ) : array() );
        if ( strlen( (string)$token ) >= 3 )
            $text = str_replace( (string)$token, '[token]', $text );
        return $text;
    }

    /**
     * The driver row of a source that reads an HTTPS API (drivers()): openssl and allow_url_fopen.
     *
     * @param string $label
     * @return array
     */
    public static function httpsDriver( $label )
    {
        $openssl = extension_loaded( 'openssl' );
        $fopen = (bool)ini_get( 'allow_url_fopen' );
        return array( 'label' => $label, 'available' => $openssl && $fopen, 'via' => $openssl && $fopen ? 'openssl, allow_url_fopen' : '',
                      'needs' => 'the PHP extension openssl and allow_url_fopen=On (RHEL and Debian: part of the PHP packages)',
                      'worker' => '', 'worker_php' => '' );
    }

    /**
     * The properties of a service's record with names that do not overwrite the record's own fields: a property
     * called like one of them gets the prefix field_.
     *
     * @param array $record the own fields (id ...)
     * @param array $fields name => value
     * @return array
     */
    public static function merge( array $record, array $fields )
    {
        foreach ( $fields as $name => $value )
        {
            $name = (string)$name;
            $record[array_key_exists( $name, $record ) ? 'field_' . $name : $name] = $value;
        }
        return $record;
    }
}
