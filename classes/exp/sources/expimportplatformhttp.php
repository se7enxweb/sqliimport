<?php
/**
 * File containing the expImportPlatformHttp class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The HTTP requests of the sources that read a site's API themselves (Drupal JSON:API, another Exponential site).
 * Every address goes through expImportSourceFiles::refuseUrl() first (http and https only, no private networks unless
 * allowed), answers are limited to [PlatformSettings] MaxResponseMegabytes, redirects are followed by hand (at most
 * three, each address checked again, and credentials are never sent to another host), and the time limit is
 * [ImportSettings] StreamTimeout.
 *
 * Tests replace the network with $transport: a callable( string $method, string $url, array $headers, ?string $body )
 * returning array( 'status' => int, 'headers' => array of "Name: value" lines, 'body' => string ).
 */
class expImportPlatformHttp
{
    /** @var callable|null replaces the network (tests) */
    public static $transport = null;

    /**
     * A setting of sqliimport.ini, or the default without a running installation (unit tests).
     *
     * @param string $section
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function setting( $section, $name, $default )
    {
        if ( !class_exists( 'eZINI' ) )
            return $default;
        return expImport::setting( $section, $name, $default );
    }

    /**
     * One request.
     *
     * @param string $method GET or POST
     * @param string $url
     * @param array $headers name => value
     * @param string|null $body
     * @param string[] $trusted hosts in a private network an administrator allowed for this source
     * @return array status, headers, body
     * @throws expImportSourceException
     */
    public static function request( $method, $url, array $headers = array(), $body = null, array $trusted = array() )
    {
        $lines = array();
        foreach ( $headers as $name => $value )
            $lines[] = $name . ': ' . $value;
        if ( self::$transport )
            return call_user_func( self::$transport, $method, $url, $lines, $body );
        $host = (string)parse_url( $url, PHP_URL_HOST );
        for ( $hop = 0; $hop <= 3; $hop++ )
        {
            $refusal = expImportSourceFiles::refuseUrl( $url, $trusted );
            if ( $refusal !== null )
                throw new expImportSourceException( $refusal );
            $answer = self::send( $method, $url, $lines, $body );
            $location = self::header( $answer['headers'], 'Location' );
            if ( $answer['status'] < 300 || $answer['status'] >= 400 || $location === '' )
                return $answer;
            $url = self::resolve( $url, $location );
            if ( strcasecmp( (string)parse_url( $url, PHP_URL_HOST ), $host ) !== 0 )
                $lines = array_values( array_filter( $lines, function ( $line ) { return stripos( $line, 'Authorization:' ) !== 0; } ) );
            if ( $answer['status'] !== 307 && $answer['status'] !== 308 )
            {
                $method = 'GET';
                $body = null;
            }
        }
        throw new expImportSourceException( 'too many redirects' );
    }

    /**
     * A JSON answer of a request, decoded; an error for an HTTP error or an answer that is not JSON.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string|null $body
     * @return array
     * @throws expImportSourceException
     */
    public static function json( $method, $url, array $headers = array(), $body = null )
    {
        $answer = self::request( $method, $url, $headers + array( 'Accept' => 'application/json' ), $body );
        $shown = self::shown( $url );
        if ( $answer['status'] === 401 || $answer['status'] === 403 )
            throw new expImportSourceException( "$shown answered {$answer['status']}: the access was refused (check the authentication)" );
        if ( $answer['status'] < 200 || $answer['status'] >= 300 )
            throw new expImportSourceException( "$shown answered {$answer['status']}" );
        $data = json_decode( (string)$answer['body'], true );
        if ( !is_array( $data ) )
            throw new expImportSourceException( "$shown did not answer with JSON" );
        return $data;
    }

    /** @return string an address without user, password and query, for messages */
    public static function shown( $url )
    {
        $parts = parse_url( (string)$url );
        if ( !$parts || !isset( $parts['host'] ) )
            return '(address)';
        return ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
             . ( isset( $parts['path'] ) ? $parts['path'] : '' );
    }

    /**
     * Whether an address (a "next" link of an answer) is on the same scheme, host and port as the base address, so
     * a source never follows a link of an answer to another site with its credentials.
     *
     * @param string $url
     * @param string $base
     * @return bool
     */
    public static function sameSite( $url, $base )
    {
        $a = parse_url( (string)$url );
        $b = parse_url( (string)$base );
        if ( !$a || !$b || !isset( $a['host'], $b['host'], $a['scheme'], $b['scheme'] ) )
            return false;
        return strtolower( $a['scheme'] ) === strtolower( $b['scheme'] ) && strtolower( $a['host'] ) === strtolower( $b['host'] )
            && ( isset( $a['port'] ) ? $a['port'] : null ) === ( isset( $b['port'] ) ? $b['port'] : null );
    }

    /**
     * An address relative to another one.
     *
     * @param string $base
     * @param string $relative
     * @return string
     */
    public static function resolve( $base, $relative )
    {
        $relative = trim( (string)$relative );
        if ( $relative === '' || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $relative ) )
            return $relative;
        $b = parse_url( (string)$base );
        $root = ( isset( $b['scheme'] ) ? $b['scheme'] : 'https' ) . '://' . ( isset( $b['host'] ) ? $b['host'] : '' ) . ( isset( $b['port'] ) ? ':' . $b['port'] : '' );
        if ( strpos( $relative, '//' ) === 0 )
            return ( isset( $b['scheme'] ) ? $b['scheme'] : 'https' ) . ':' . $relative;
        if ( $relative[0] === '/' )
            return $root . $relative;
        $path = isset( $b['path'] ) ? preg_replace( '#/[^/]*$#', '/', $b['path'] ) : '/';
        return $root . $path . $relative;
    }

    /** @return string the value of a header of an answer, '' when it has none */
    public static function header( array $lines, $name )
    {
        foreach ( $lines as $line )
            if ( stripos( (string)$line, $name . ':' ) === 0 )
                return trim( substr( (string)$line, strlen( $name ) + 1 ) );
        return '';
    }

    /**
     * The request on the network, without following redirects.
     *
     * @return array status, headers, body
     */
    protected static function send( $method, $url, array $lines, $body )
    {
        $timeout = max( 5, (int)self::setting( 'ImportSettings', 'StreamTimeout', 50 ) );
        $max = max( 1, (int)self::setting( 'PlatformSettings', 'MaxResponseMegabytes', 20 ) ) * 1048576;
        $options = array( 'method' => $method, 'timeout' => $timeout, 'follow_location' => 0, 'ignore_errors' => true,
                          'user_agent' => '7x SQLI Import', 'header' => implode( "\r\n", $lines ), 'protocol_version' => 1.1 );
        $options['header'] .= ( $options['header'] !== '' ? "\r\n" : '' ) . 'Connection: close';
        if ( $body !== null )
            $options['content'] = (string)$body;
        $context = stream_context_create( array( 'http' => $options ) );
        $in = @fopen( $url, 'rb', false, $context );
        if ( !$in )
            throw new expImportSourceException( 'the address could not be read: ' . self::shown( $url ) );
        $meta = stream_get_meta_data( $in );
        $headers = isset( $meta['wrapper_data'] ) ? (array)$meta['wrapper_data'] : array();
        $data = '';
        while ( !feof( $in ) )
        {
            $chunk = fread( $in, 65536 );
            if ( $chunk === false )
                break;
            $data .= $chunk;
            if ( strlen( $data ) > $max )
            {
                fclose( $in );
                throw new expImportSourceException( 'the answer of ' . self::shown( $url ) . ' is larger than [PlatformSettings] MaxResponseMegabytes' );
            }
        }
        fclose( $in );
        $status = 0;
        foreach ( $headers as $line )
            if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', (string)$line, $m ) )
                $status = (int)$m[1];
        return array( 'status' => $status, 'headers' => $headers, 'body' => $data );
    }

    /**
     * Writes records as NDJSON (the snapshot of a remote source).
     *
     * @param iterable $records arrays
     * @param string $target
     * @param int $limit 0: all
     * @return int the number written
     */
    public static function writeNdjson( $records, $target, $limit = 0 )
    {
        $out = fopen( $target, 'wb' );
        if ( !$out )
            throw new expImportSourceException( 'the snapshot cannot be written' );
        $n = 0;
        try
        {
            foreach ( $records as $record )
            {
                fwrite( $out, json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n" );
                $n++;
                if ( $limit > 0 && $n >= $limit )
                    break;
            }
        }
        finally
        {
            fclose( $out );
        }
        return $n;
    }

    /**
     * The Authorization header of an option set: none, basic (user + password secret) or bearer (token secret).
     *
     * @param array $options auth, user, password_secret, token_secret
     * @param string[] $used the secrets used, for masking messages
     * @return array name => value
     */
    public static function authHeaders( array $options, array &$used )
    {
        $auth = isset( $options['auth'] ) ? (string)$options['auth'] : 'none';
        if ( $auth === 'basic' )
        {
            $ref = isset( $options['password_secret'] ) ? (string)$options['password_secret'] : '';
            $used[] = $ref;
            return array( 'Authorization' => 'Basic ' . base64_encode( ( isset( $options['user'] ) ? (string)$options['user'] : '' ) . ':'
                                                                       . expImportSecrets::required( $ref, 'the password' ) ) );
        }
        if ( $auth === 'bearer' )
        {
            $ref = isset( $options['token_secret'] ) ? (string)$options['token_secret'] : '';
            $used[] = $ref;
            return array( 'Authorization' => 'Bearer ' . expImportSecrets::required( $ref, 'the token' ) );
        }
        return array();
    }
}
