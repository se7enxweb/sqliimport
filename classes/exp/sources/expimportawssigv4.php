<?php
/**
 * File containing the expImportAwsSigV4 class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * AWS Signature Version 4 for a request to an AWS service (no SDK): the canonical request, the string to sign, the
 * signing key derived from the secret access key, date, region and service, and the Authorization header. The secret
 * access key never leaves this class: only the signature (an HMAC) is sent.
 *
 * @see https://docs.aws.amazon.com/IAM/latest/UserGuide/reference_sigv-create-signed-request.html
 */
class expImportAwsSigV4
{
    const ALGORITHM = 'AWS4-HMAC-SHA256';

    /** @var callable|null replaces time() (tests): function () returning int */
    public static $clock = null;

    /**
     * The headers of a signed request: the given ones plus Host, X-Amz-Date, X-Amz-Security-Token (with a session
     * token) and Authorization.
     *
     * @param string $method
     * @param string $url
     * @param array $headers name => value (Content-Type, X-Amz-Target ...); every one is signed
     * @param string $body
     * @param string $accessKeyId
     * @param string $secretAccessKey
     * @param string $sessionToken '' for none
     * @param string $region eu-central-1
     * @param string $service dynamodb
     * @param int|null $time a Unix time; null: now
     * @return array name => value
     */
    public static function sign( $method, $url, array $headers, $body, $accessKeyId, $secretAccessKey, $sessionToken, $region, $service, $time = null )
    {
        if ( $time === null )
            $time = self::$clock ? (int)call_user_func( self::$clock ) : time();
        $amzDate = gmdate( 'Ymd\THis\Z', (int)$time );
        $date = substr( $amzDate, 0, 8 );
        $parts = parse_url( (string)$url );
        $host = isset( $parts['host'] ) ? strtolower( (string)$parts['host'] ) : '';
        if ( isset( $parts['port'] ) )
            $host .= ':' . $parts['port'];
        unset( $headers['Authorization'], $headers['authorization'] );
        $headers['Host'] = $host;
        $headers['X-Amz-Date'] = $amzDate;
        if ( (string)$sessionToken !== '' )
            $headers['X-Amz-Security-Token'] = (string)$sessionToken;
        list( $canonical, $signed ) = self::canonicalRequest( $method, $url, $headers, (string)$body );
        $scope = $date . '/' . $region . '/' . $service . '/aws4_request';
        $toSign = self::ALGORITHM . "\n" . $amzDate . "\n" . $scope . "\n" . hash( 'sha256', $canonical );
        $signature = hash_hmac( 'sha256', $toSign, self::signingKey( $secretAccessKey, $date, $region, $service ) );
        $headers['Authorization'] = self::ALGORITHM . ' Credential=' . $accessKeyId . '/' . $scope . ', SignedHeaders=' . $signed . ', Signature=' . $signature;
        return $headers;
    }

    /**
     * The canonical request and the list of signed headers.
     *
     * @param string $method
     * @param string $url
     * @param array $headers name => value, Host and X-Amz-Date included
     * @param string $body
     * @return array ( string canonical request, string signed headers )
     */
    public static function canonicalRequest( $method, $url, array $headers, $body )
    {
        $parts = parse_url( (string)$url );
        $path = isset( $parts['path'] ) && $parts['path'] !== '' ? (string)$parts['path'] : '/';
        $segments = array_map( function ( $segment ) { return rawurlencode( rawurldecode( $segment ) ); }, explode( '/', $path ) );
        $query = array();
        if ( isset( $parts['query'] ) && $parts['query'] !== '' )
            foreach ( explode( '&', (string)$parts['query'] ) as $pair )
            {
                $kv = explode( '=', $pair, 2 );
                $query[] = array( rawurlencode( rawurldecode( $kv[0] ) ), rawurlencode( rawurldecode( isset( $kv[1] ) ? $kv[1] : '' ) ) );
            }
        usort( $query, function ( $a, $b ) { return $a[0] === $b[0] ? strcmp( $a[1], $b[1] ) : strcmp( $a[0], $b[0] ); } );
        $canonicalHeaders = array();
        foreach ( $headers as $name => $value )
            $canonicalHeaders[strtolower( trim( (string)$name ) )] = trim( (string)preg_replace( '/\s+/', ' ', (string)$value ) );
        ksort( $canonicalHeaders, SORT_STRING );
        $lines = '';
        foreach ( $canonicalHeaders as $name => $value )
            $lines .= $name . ':' . $value . "\n";
        $signed = implode( ';', array_keys( $canonicalHeaders ) );
        $canonical = strtoupper( (string)$method ) . "\n" . implode( '/', $segments ) . "\n"
                   . implode( '&', array_map( function ( $kv ) { return $kv[0] . '=' . $kv[1]; }, $query ) ) . "\n"
                   . $lines . "\n" . $signed . "\n" . hash( 'sha256', (string)$body );
        return array( $canonical, $signed );
    }

    /**
     * The signing key: HMAC chain of the secret over date, region, service and "aws4_request".
     *
     * @return string binary
     */
    public static function signingKey( $secretAccessKey, $date, $region, $service )
    {
        $key = hash_hmac( 'sha256', (string)$date, 'AWS4' . $secretAccessKey, true );
        $key = hash_hmac( 'sha256', (string)$region, $key, true );
        $key = hash_hmac( 'sha256', (string)$service, $key, true );
        return hash_hmac( 'sha256', 'aws4_request', $key, true );
    }
}
