<?php
/**
 * File containing the expImportDocumentHttp class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What the document stores read over HTTP have in common (CouchDB, the Couchbase Query service, Azure Cosmos DB): the
 * check of the server address (http or https, no credentials or query in it, a private network only when the
 * source's own AllowedHosts[] lists it), one request with its JSON answer and the server's reason for an error, the
 * snapshot written once per run with every secret masked in a message, and the driver list (PHP's http and https
 * streams, as the Elasticsearch source needs them).
 *
 * The requests go through expImportPlatformHttp (tests replace the network with expImportPlatformHttp::$transport):
 * answers are limited to [PlatformSettings] MaxResponseMegabytes, the time limit is [ImportSettings] StreamTimeout,
 * TLS certificates are verified and a redirect to another host loses the credentials.
 */
class expImportDocumentHttp
{
    /**
     * The server address of the options, checked.
     *
     * @param string $url the option
     * @param string $section the settings section with AllowedHosts[] (CouchDbSourceSettings ...)
     * @param string $label the product, for messages (CouchDB)
     * @return array the list ( string base address without a trailing slash, string[] trusted hosts )
     * @throws expImportSourceException
     */
    public static function base( $url, $section, $label )
    {
        $base = rtrim( trim( (string)$url ), '/' );
        $parts = parse_url( $base );
        if ( $base === '' || !$parts || !isset( $parts['scheme'], $parts['host'] ) || !in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) )
            throw new expImportSourceException( "$label: the server address must be an http or https address" );
        if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) )
            throw new expImportSourceException( "$label: the server address may not have a query, a fragment or credentials (use the options for them)" );
        if ( isset( $parts['path'] ) && ( strpos( $parts['path'], '..' ) !== false || strpos( $parts['path'], '%' ) !== false ) )
            throw new expImportSourceException( "$label: the path of the server address may not have .. or escaped characters" );
        $trusted = (array)expImportPlatformHttp::setting( $section, 'AllowedHosts', array() );
        $refusal = expImportSourceFiles::refuseHost( $parts['host'], $trusted );
        if ( $refusal !== null )
            throw new expImportSourceException( "$label: " . $refusal . " (or list it in sqliimport.ini [$section] AllowedHosts[])" );
        return array( $base, $trusted );
    }

    /**
     * One request and its JSON answer.
     *
     * @param string $label the product, for messages
     * @param string $method
     * @param string $url
     * @param array $headers name => value
     * @param string|null $body
     * @param string[] $trusted
     * @return array the list ( array the decoded answer, string[] the header lines of the answer )
     * @throws expImportSourceException an HTTP error with the server's reason, or an answer that is not JSON
     */
    public static function call( $label, $method, $url, array $headers, $body, array $trusted )
    {
        $headers += array( 'Accept' => 'application/json' );
        $answer = expImportPlatformHttp::request( $method, $url, $headers, $body, $trusted );
        $status = (int)$answer['status'];
        $data = json_decode( (string)$answer['body'], true );
        $shown = expImportPlatformHttp::shown( $url );
        if ( $status < 200 || $status >= 300 )
        {
            $reason = self::reason( $data );
            if ( $status === 401 || $status === 403 )
                $reason = 'the access was refused (check the user, the secret and the rights)' . ( $reason !== '' ? '; ' . $reason : '' );
            throw new expImportSourceException( "$label: $shown answered $status" . ( $reason !== '' ? ': ' . substr( $reason, 0, 300 ) : '' ) );
        }
        if ( !is_array( $data ) )
            throw new expImportSourceException( "$label: $shown did not answer with JSON" );
        return array( $data, isset( $answer['headers'] ) ? (array)$answer['headers'] : array() );
    }

    /**
     * The reason of an error answer as the stores give it: CouchDB {"error", "reason"}, the Couchbase Query service
     * {"errors": [{"code", "msg"}]}, Cosmos DB {"code", "message"}.
     *
     * @param mixed $data
     * @return string
     */
    public static function reason( $data )
    {
        if ( !is_array( $data ) )
            return '';
        if ( isset( $data['errors'][0] ) && is_array( $data['errors'][0] ) )
        {
            $first = $data['errors'][0];
            return trim( ( isset( $first['code'] ) ? $first['code'] . ': ' : '' ) . ( isset( $first['msg'] ) ? (string)$first['msg'] : ( isset( $first['message'] ) ? (string)$first['message'] : '' ) ), ': ' );
        }
        if ( isset( $data['error'] ) && is_string( $data['error'] ) )
            return trim( $data['error'] . ( isset( $data['reason'] ) && is_string( $data['reason'] ) ? ': ' . $data['reason'] : '' ), ': ' );
        if ( isset( $data['message'] ) && is_string( $data['message'] ) )
            return trim( ( isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] . ': ' : '' ) . strtok( $data['message'], "\r\n" ), ': ' );
        return '';
    }

    /**
     * Writes the records of a source into the snapshot once per run (through a .part file), every secret masked in
     * an error.
     *
     * @param string $label the product, for messages
     * @param callable $records function ( array &$used, array &$hidden ) returning the records (a Generator); it sets
     *                          the secrets it uses and other values to hide (a session cookie)
     * @param string $target
     * @param int $limit 0: all
     * @throws expImportSourceException
     */
    public static function snapshot( $label, callable $records, $target, $limit )
    {
        $tmp = $target . '.part';
        $used = array();
        $hidden = array();
        try
        {
            expImportPlatformHttp::writeNdjson( $records( $used, $hidden ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( "$label: the snapshot cannot be written" );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( self::mask( $e->getMessage(), $used, $hidden ) );
        }
        finally
        {
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * A message without secrets: the values of the secrets used (by their names) and other values to hide.
     *
     * @param string $text
     * @param string[] $used
     * @param string[] $hidden
     * @return string
     */
    public static function mask( $text, array $used, array $hidden = array() )
    {
        $text = expImportSecrets::mask( (string)$text, $used );
        foreach ( $hidden as $value )
            if ( strlen( (string)$value ) >= 6 )
                $text = str_replace( (string)$value, '[hidden]', $text );
        return $text;
    }

    /**
     * The header lines of an answer with a name.
     *
     * @param string[] $lines
     * @param string $name
     * @return string[] the values
     */
    public static function headers( array $lines, $name )
    {
        $out = array();
        foreach ( $lines as $line )
            if ( stripos( (string)$line, $name . ':' ) === 0 )
                $out[] = trim( substr( (string)$line, strlen( $name ) + 1 ) );
        return $out;
    }

    /**
     * A JSON option as given: '' or an array (validated by the schema), or JSON text.
     *
     * @param mixed $value
     * @param string $label
     * @param string $what for the message
     * @return mixed null when empty
     * @throws expImportSourceException
     */
    public static function jsonOption( $value, $label, $what )
    {
        if ( $value === null || $value === '' || $value === array() )
            return null;
        if ( !is_string( $value ) )
            return $value;
        if ( trim( $value ) === '' )
            return null;
        $data = json_decode( $value, true, 64 );
        if ( $data === null && strtolower( trim( $value ) ) !== 'null' )
            throw new expImportSourceException( "$label: the $what is not valid JSON: " . json_last_error_msg() );
        return $data;
    }

    /**
     * How this PHP reaches the stores: PHP's http and https streams, the same as the Elasticsearch source (and noted
     * for the job queue's workers under its names).
     *
     * @return array id => array( label, available, via, needs, worker, worker_php )
     */
    public static function drivers()
    {
        return expImportSearchSource::drivers();
    }
}
