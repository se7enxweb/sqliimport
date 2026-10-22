<?php
/**
 * File containing the expImportCloudHttp class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The JSON requests of the sources that read a cloud database over its HTTPS API (Amazon DynamoDB, Firestore,
 * BigQuery, Firebase Realtime Database): the body encoded once, a request signed again for every try (AWS Signature
 * Version 4 holds the time), waiting and trying again after 429, 500, 502, 503, 504 and a throttling error of
 * DynamoDB, and the reason of an error as the service gives it (Google: error.message, AWS: __type and message,
 * Firebase: error). The requests go through expImportPlatformHttp (address checks, size and time limit), so a test
 * replaces the network with expImportPlatformHttp::$transport and the waiting with expImportApiHttp::$sleeper.
 */
class expImportCloudHttp
{
    /** How often a request is tried again. */
    const RETRIES = 4;

    /** The errors of DynamoDB (HTTP 400) that mean "slow down" rather than "wrong". */
    const THROTTLING = array( 'ThrottlingException', 'ProvisionedThroughputExceededException', 'RequestLimitExceeded', 'LimitExceededException' );

    /**
     * A JSON request.
     *
     * @param string $service the name of the service in messages (DynamoDB)
     * @param string $method GET or POST
     * @param string $url
     * @param array $headers name => value
     * @param array|stdClass|null $body encoded as JSON; null: no body
     * @param string[] $trusted hosts in a private network an administrator allowed
     * @param callable|null $sign function ( $method, $url, array $headers, string $body ) returning the headers to send
     * @return array the decoded answer
     * @throws expImportSourceException
     */
    public static function json( $service, $method, $url, array $headers, $body = null, array $trusted = array(), $sign = null )
    {
        $text = $body === null ? null : (string)json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $headers += array( 'Accept' => 'application/json' );
        if ( $text !== null && !isset( $headers['Content-Type'] ) )
            $headers['Content-Type'] = 'application/json';
        for ( $try = 0; ; $try++ )
        {
            $send = $sign ? (array)call_user_func( $sign, $method, $url, $headers, (string)$text ) : $headers;
            $answer = expImportPlatformHttp::request( $method, $url, $send, $text, $trusted );
            $status = (int)$answer['status'];
            $data = json_decode( (string)$answer['body'], true );
            if ( $status >= 200 && $status < 300 )
            {
                if ( !is_array( $data ) )
                {
                    // Firebase answers a path without data with null
                    if ( trim( (string)$answer['body'] ) === 'null' )
                        return array();
                    throw new expImportSourceException( "$service: " . expImportPlatformHttp::shown( $url ) . ' did not answer with JSON' );
                }
                return $data;
            }
            $type = self::errorType( $data );
            $again = in_array( $status, array( 429, 500, 502, 503, 504 ), true )
                  || ( $status === 400 && in_array( $type, self::THROTTLING, true ) );
            if ( $again && $try < self::RETRIES )
            {
                $wait = (float)expImportPlatformHttp::header( (array)$answer['headers'], 'Retry-After' );
                expImportApiHttp::sleep( $wait > 0 ? min( 60.0, $wait ) : (float)pow( 2, $try ) );
                continue;
            }
            $reason = self::reason( $data );
            if ( $status === 401 || $status === 403 )
                $reason = 'the access was refused (check the credentials and the rights of the account)' . ( $reason !== '' ? '; ' . $reason : '' );
            throw new expImportSourceException( "$service: " . expImportPlatformHttp::shown( $url ) . " answered $status" . ( $reason !== '' ? ': ' . substr( $reason, 0, 400 ) : '' ) );
        }
    }

    /** @return string the type of an AWS error (ResourceNotFoundException), '' for another answer */
    public static function errorType( $data )
    {
        if ( !is_array( $data ) || !isset( $data['__type'] ) )
            return '';
        $type = (string)$data['__type'];
        $hash = strrpos( $type, '#' );
        return $hash === false ? $type : (string)substr( $type, $hash + 1 );
    }

    /** @return string the reason an error answer gives, '' when it gives none */
    public static function reason( $data )
    {
        if ( !is_array( $data ) )
            return '';
        if ( isset( $data['__type'] ) )
        {
            $message = isset( $data['message'] ) ? (string)$data['message'] : ( isset( $data['Message'] ) ? (string)$data['Message'] : '' );
            return trim( self::errorType( $data ) . ': ' . $message, ': ' );
        }
        if ( isset( $data['error'] ) )
        {
            $error = $data['error'];
            if ( is_array( $error ) )
                return trim( ( isset( $error['status'] ) ? (string)$error['status'] . ': ' : '' ) . ( isset( $error['message'] ) ? (string)$error['message'] : '' ), ': ' );
            return trim( (string)$error . ( isset( $data['error_description'] ) ? ': ' . (string)$data['error_description'] : '' ) );
        }
        if ( isset( $data[0]['error'] ) && is_array( $data[0]['error'] ) )
            return self::reason( $data[0] );
        return '';
    }

    /**
     * The http and https rows of drivers() for a source that reads over HTTPS (with what the job queue's workers had,
     * noted under search.http and search.https: the same PHP streams).
     *
     * @param array|null $environment array( 'allow_url_fopen' => bool, 'openssl' => bool ) for the tests; null: this PHP
     * @param string $label what the https row reads (DynamoDB HTTPS API)
     * @return array
     */
    public static function drivers( $environment, $label )
    {
        $env = is_array( $environment ) ? $environment : array();
        $fopen = isset( $env['allow_url_fopen'] ) ? (bool)$env['allow_url_fopen'] : (bool)ini_get( 'allow_url_fopen' );
        $openssl = isset( $env['openssl'] ) ? (bool)$env['openssl'] : extension_loaded( 'openssl' );
        $list = array(
            'https' => array( 'label' => $label . ' (certificates verified)', 'via' => $fopen && $openssl ? 'PHP https stream (openssl)' : '',
                              'needs' => 'allow_url_fopen=On and the PHP extension openssl (RHEL and Debian: part of the php package) in the web server\'s PHP and the job queue\'s workers\' PHP' ),
        );
        return expImportSearchSource::withWorker( $list, 'search' );
    }

    /**
     * The decoded JSON of an option: an array, or a JSON text; '' and null give an empty array.
     *
     * @param mixed $value
     * @param string $what for the message
     * @return array
     * @throws expImportSourceException
     */
    public static function jsonOption( $value, $what )
    {
        if ( $value === null || $value === '' || $value === array() )
            return array();
        if ( is_string( $value ) )
        {
            if ( trim( $value ) === '' )
                return array();
            $value = json_decode( $value, true );
        }
        if ( !is_array( $value ) )
            throw new expImportSourceException( "$what must be a JSON object" );
        return $value;
    }
}
