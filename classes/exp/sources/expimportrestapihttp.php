<?php
/**
 * File containing the expImportRestApiHttp class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The requests of the REST API source (rest_api) on the network. Like expImportPlatformHttp (address checks, size
 * limit, time limit), with two differences that matter for a source that sends credentials of its own choosing in
 * any header or parameter:
 *   - a redirect is followed only on the same scheme, host and port (at most three); a redirect to another site is
 *     an error, so no API key, cookie or bearer token is ever sent to a host the import did not name
 *   - without allow_url_fopen the request goes through curl (the PHP extension), with the same checks
 *
 * Tests replace the network with expImportPlatformHttp::$transport.
 */
class expImportRestApiHttp extends expImportPlatformHttp
{
    /**
     * One request; redirects followed on the same site only.
     *
     * @param string $method GET or POST
     * @param string $url
     * @param array $headers name => value
     * @param string|null $body
     * @param string[] $trusted hosts in a private network an administrator allowed for this source
     * @return array status, headers, body
     * @throws expImportSourceException
     */
    public static function call( $method, $url, array $headers = array(), $body = null, array $trusted = array() )
    {
        $lines = array();
        foreach ( $headers as $name => $value )
            $lines[] = $name . ': ' . $value;
        if ( expImportPlatformHttp::$transport )
            return call_user_func( expImportPlatformHttp::$transport, $method, $url, $lines, $body );
        $first = $url;
        for ( $hop = 0; $hop <= 3; $hop++ )
        {
            $refusal = expImportSourceFiles::refuseUrl( $url, $trusted );
            if ( $refusal !== null )
                throw new expImportSourceException( $refusal );
            $answer = self::once( $method, $url, $lines, $body );
            $location = self::header( $answer['headers'], 'Location' );
            if ( $answer['status'] < 300 || $answer['status'] >= 400 || $location === '' )
                return $answer;
            $next = self::resolve( $url, $location );
            if ( !self::sameSite( $next, $first ) )
                throw new expImportSourceException( self::shown( $url ) . ' redirects to another site (' . self::shown( $next ) . '); it is not followed, the credentials stay with the address given' );
            $url = $next;
            if ( $answer['status'] !== 307 && $answer['status'] !== 308 )
            {
                $method = 'GET';
                $body = null;
            }
        }
        throw new expImportSourceException( 'too many redirects' );
    }

    /** @return string which way requests go: streams, curl or '' (none) */
    public static function via()
    {
        if ( extension_loaded( 'openssl' ) && ini_get( 'allow_url_fopen' ) )
            return 'streams';
        if ( function_exists( 'curl_init' ) )
            return 'curl';
        return '';
    }

    /**
     * One request on the network without following redirects: PHP's streams, or curl without allow_url_fopen.
     *
     * @param string $method
     * @param string $url
     * @param string[] $lines the header lines
     * @param string|null $body
     * @return array status, headers, body
     * @throws expImportSourceException
     */
    protected static function once( $method, $url, array $lines, $body )
    {
        $via = self::via();
        if ( $via === 'streams' )
            return parent::send( $method, $url, $lines, $body );
        if ( $via === '' )
            throw new expImportSourceException( 'this server cannot make HTTPS requests: it needs openssl with allow_url_fopen=On, or the PHP extension curl' );
        $timeout = max( 5, (int)self::setting( 'ImportSettings', 'StreamTimeout', 50 ) );
        $max = max( 1, (int)self::setting( 'PlatformSettings', 'MaxResponseMegabytes', 20 ) ) * 1048576;
        $data = '';
        $headers = array();
        $tooLarge = false;
        $curl = curl_init( $url );
        $options = array(
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => '7x SQLI Import',
            CURLOPT_HEADERFUNCTION => function ( $handle, $line ) use ( &$headers )
            {
                if ( trim( $line ) !== '' )
                    $headers[] = rtrim( $line, "\r\n" );
                return strlen( $line );
            },
            CURLOPT_WRITEFUNCTION => function ( $handle, $chunk ) use ( &$data, &$tooLarge, $max )
            {
                $data .= $chunk;
                if ( strlen( $data ) > $max )
                {
                    $tooLarge = true;
                    return 0;
                }
                return strlen( $chunk );
            },
        );
        if ( defined( 'CURLOPT_PROTOCOLS' ) && defined( 'CURLPROTO_HTTPS' ) && defined( 'CURLPROTO_HTTP' ) )
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS | CURLPROTO_HTTP;
        if ( $body !== null )
            $options[CURLOPT_POSTFIELDS] = (string)$body;
        curl_setopt_array( $curl, $options );
        $ok = curl_exec( $curl );
        $status = (int)curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
        if ( $tooLarge )
            throw new expImportSourceException( 'the answer of ' . self::shown( $url ) . ' is larger than [PlatformSettings] MaxResponseMegabytes' );
        if ( $ok === false )
            throw new expImportSourceException( 'the address could not be read: ' . self::shown( $url ) );
        return array( 'status' => $status, 'headers' => $headers, 'body' => $data );
    }
}
