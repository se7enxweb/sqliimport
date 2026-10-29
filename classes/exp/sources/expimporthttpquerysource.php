<?php
/**
 * File containing the expImportHttpQuerySource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What the sources have in common that send a query in a database's own language to its HTTP API (Neo4j, ArangoDB,
 * ClickHouse, Snowflake, InfluxDB): the snapshot written once per run (fetch(), one JSON object per line), the server
 * address and its host check against the source's own [<Name>SourceSettings] AllowedHosts[], the requests through
 * expImportPlatformHttp (size limit, time limit, TLS verified, credentials never sent to another host), a lexer that
 * takes string literals, quoted names and comments out of a query before it is checked, and the drivers PHP needs.
 *
 * A subclass names itself in NAME (messages) and SETTINGS (its settings section) and yields its records in rows().
 */
abstract class expImportHttpQuerySource extends expImportJsonSource
{
    /** The name in messages. */
    const NAME = 'Database';

    /** The settings section of sqliimport.ini with AllowedHosts[] and MaxPages. */
    const SETTINGS = 'SourceSettings';

    /** The prefix of the source's drivers in what the job queue's workers note (expImportSqlSource::recordWorkerPhp()). */
    const WORKER_PREFIX = 'http';

    /**
     * What drivers() sees of this PHP, for the tests: array( 'allow_url_fopen' => bool, 'openssl' => bool ).
     * null: this PHP.
     *
     * @var array|null
     */
    public static $environment = null;

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'nosql';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function variants()
    {
        return array();
    }

    /** The snapshot is always one record per line. */
    protected function format()
    {
        return 'ndjson';
    }

    /**
     * The records of the query, page by page.
     *
     * @param array $options the validated options of the import
     * @param int $limit the most records (the editor's sample); 0: all
     * @param array $used set to the names of the secrets used, for masking messages
     * @return Generator array
     * @throws expImportSourceException
     */
    abstract public static function rows( array $options, $limit, array &$used );

    /**
     * Runs the query and writes the records into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most records (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( static::rows( $options, (int)$limit, $used ), $tmp, (int)$limit );
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( static::NAME . ': the snapshot cannot be written' );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportSecrets::mask( $e->getMessage(), $used ) );
        }
        finally
        {
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /** @return mixed a setting of the source's section */
    public static function sourceSetting( $name, $default )
    {
        return expImportPlatformHttp::setting( static::SETTINGS, $name, $default );
    }

    /** @return int the most pages (requests for rows) of one run, [<Name>SourceSettings] MaxPages */
    protected static function maxPages()
    {
        return max( 1, (int)static::sourceSetting( 'MaxPages', 10000 ) );
    }

    /**
     * The server address of the options after its checks: http or https, no query, fragment or credentials, and a host
     * outside private networks unless the source's settings list it.
     *
     * @param array $options
     * @param string $option the name of the option
     * @return array the list ( string address without a trailing /, string[] trusted hosts )
     * @throws expImportSourceException
     */
    public static function baseUrl( array $options, $option = 'base_url' )
    {
        $base = rtrim( isset( $options[$option] ) ? trim( (string)$options[$option] ) : '', '/' );
        $parts = parse_url( $base );
        if ( $base === '' || !$parts || !isset( $parts['scheme'], $parts['host'] ) || !in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) )
            throw new expImportSourceException( static::NAME . ': the server address must be an http or https address' );
        if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) )
            throw new expImportSourceException( static::NAME . ': the server address may not have a query, a fragment or credentials (use the options of the authentication)' );
        $trusted = static::checkHost( $parts['host'] );
        return array( $base, $trusted );
    }

    /**
     * Refuses a host in a private or reserved network unless the source's settings list it.
     *
     * @param string $host
     * @return string[] the trusted hosts of the settings
     * @throws expImportSourceException
     */
    public static function checkHost( $host )
    {
        $trusted = (array)static::sourceSetting( 'AllowedHosts', array() );
        $refusal = expImportSourceFiles::refuseHost( $host, $trusted );
        if ( $refusal !== null )
            throw new expImportSourceException( static::NAME . ': ' . $refusal . ' (or list it in sqliimport.ini [' . static::SETTINGS . '] AllowedHosts[])' );
        return $trusted;
    }

    /**
     * One request; an error with the server's reason (reason()) for an HTTP error.
     *
     * @param string $method
     * @param string $url
     * @param array $headers name => value
     * @param string|null $body
     * @param string[] $trusted
     * @return array status, headers, body
     * @throws expImportSourceException
     */
    protected static function send( $method, $url, array $headers, $body, array $trusted )
    {
        $answer = expImportPlatformHttp::request( $method, $url, $headers, $body, $trusted );
        $status = (int)$answer['status'];
        if ( $status < 200 || $status >= 300 )
        {
            $reason = static::reason( (string)$answer['body'] );
            if ( $status === 401 || $status === 403 )
                $reason = 'the access was refused (check the authentication and the rights of the user)' . ( $reason !== '' ? '; ' . $reason : '' );
            throw new expImportSourceException( static::NAME . ': ' . expImportPlatformHttp::shown( $url ) . " answered $status" . ( $reason !== '' ? ': ' . substr( $reason, 0, 300 ) : '' ) );
        }
        return $answer;
    }

    /**
     * A JSON request and its decoded answer.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param mixed $body encoded as JSON; null: none
     * @param string[] $trusted
     * @return array
     * @throws expImportSourceException
     */
    protected static function json( $method, $url, array $headers, $body, array $trusted )
    {
        $headers += array( 'Accept' => 'application/json' );
        if ( $body !== null )
            $headers += array( 'Content-Type' => 'application/json' );
        $answer = static::send( $method, $url, $headers, $body === null ? null : (string)json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), $trusted );
        $data = json_decode( (string)$answer['body'], true );
        if ( !is_array( $data ) )
            throw new expImportSourceException( static::NAME . ': ' . expImportPlatformHttp::shown( $url ) . ' did not answer with JSON' );
        return $data;
    }

    /**
     * The reason of an error answer for a message (the server's error text).
     *
     * @param string $body
     * @return string
     */
    protected static function reason( $body )
    {
        $data = json_decode( (string)$body, true );
        if ( is_array( $data ) )
        {
            foreach ( array( 'errorMessage', 'message', 'error' ) as $key )
                if ( isset( $data[$key] ) && is_string( $data[$key] ) )
                    return $data[$key];
            if ( isset( $data['errors'][0]['message'] ) )
                return ( isset( $data['errors'][0]['code'] ) ? $data['errors'][0]['code'] . ': ' : '' ) . (string)$data['errors'][0]['message'];
        }
        return trim( preg_replace( '/\s+/', ' ', strip_tags( substr( (string)$body, 0, 300 ) ) ) );
    }

    // ---- the query lexer ---------------------------------------------------------------------------------------------

    /**
     * A query with its string literals, quoted names and comments taken out, so a check of its words sees only the
     * code. A string literal becomes "\0<n>\0", a quoted name "\1<n>\1" (n: its index in the literals), a comment a
     * space. Anything the lexer is not sure of stays in the text (so it is checked, never hidden).
     *
     * @param string $text
     * @param array $spec quotes => array( char => array( string|name, bool backslash escapes, bool a doubled quote is
     *                    one ) ), line => string[] (line comment marks), block => bool (/* ... *&#47;), dollar => bool
     *                    or plain ($$...$$ and $tag$...$tag$ strings; plain: $$ only), regex => bool (Flux /.../ after an operator or a bracket).
     *                    Each language states its own rules: a lexer that ended a literal where the server does not
     *                    could hide code from the check.
     * @return array the list ( string stripped text, string[] literals )
     * @throws expImportSourceException an unclosed string, name or comment
     */
    public static function strip( $text, array $spec )
    {
        $text = (string)$text;
        $quotes = isset( $spec['quotes'] ) ? (array)$spec['quotes'] : array();
        $lines = isset( $spec['line'] ) ? (array)$spec['line'] : array();
        $block = !empty( $spec['block'] );
        $dollar = empty( $spec['dollar'] ) ? false : ( $spec['dollar'] === 'plain' ? 'plain' : true );
        $regex = !empty( $spec['regex'] );
        $out = '';
        $literals = array();
        $n = strlen( $text );
        $i = 0;
        while ( $i < $n )
        {
            $c = $text[$i];
            foreach ( $lines as $mark )
                if ( substr_compare( $text, $mark, $i, strlen( $mark ) ) === 0 )
                {
                    $end = strpos( $text, "\n", $i );
                    $i = $end === false ? $n : $end;
                    $out .= ' ';
                    continue 2;
                }
            if ( $block && $c === '/' && $i + 1 < $n && $text[$i + 1] === '*' )
            {
                $end = strpos( $text, '*/', $i + 2 );
                if ( $end === false )
                    throw new expImportSourceException( static::NAME . ': the query has an unclosed comment' );
                $i = $end + 2;
                $out .= ' ';
                continue;
            }
            if ( $dollar && $c === '$' && preg_match( $dollar === 'plain' ? '/\G\$\$/' : '/\G\$([A-Za-z_][A-Za-z0-9_]*)?\$/', $text, $m, 0, $i ) )
            {
                $end = strpos( $text, $m[0], $i + strlen( $m[0] ) );
                if ( $end === false )
                    throw new expImportSourceException( static::NAME . ': the query has an unclosed $$ string' );
                $literals[] = (string)substr( $text, $i + strlen( $m[0] ), $end - $i - strlen( $m[0] ) );
                $out .= "\0" . ( count( $literals ) - 1 ) . "\0";
                $i = $end + strlen( $m[0] );
                continue;
            }
            if ( $regex && $c === '/' && preg_match( '/(=~|!~|[(,\[:=])\s*$/', $out ) )
            {
                $j = $i + 1;
                while ( $j < $n && $text[$j] !== '/' && $text[$j] !== "\n" )
                    $j += $text[$j] === '\\' ? 2 : 1;
                if ( $j >= $n || $text[$j] !== '/' )
                    throw new expImportSourceException( static::NAME . ': the query has an unclosed regular expression' );
                $literals[] = (string)substr( $text, $i + 1, $j - $i - 1 );
                $out .= "\0" . ( count( $literals ) - 1 ) . "\0";
                $i = $j + 1;
                continue;
            }
            if ( isset( $quotes[$c] ) )
            {
                list( $kind, $escapes, $doubled ) = array_values( (array)$quotes[$c] ) + array( 'string', false, false );
                $value = '';
                $j = $i + 1;
                while ( true )
                {
                    if ( $j >= $n )
                        throw new expImportSourceException( static::NAME . ': the query has an unclosed ' . ( $kind === 'name' ? 'quoted name' : 'string' ) );
                    $d = $text[$j];
                    if ( $escapes && $d === '\\' )
                    {
                        $value .= $j + 1 < $n ? $text[$j + 1] : '';
                        $j += 2;
                        continue;
                    }
                    if ( $d === $c )
                    {
                        if ( $doubled && $j + 1 < $n && $text[$j + 1] === $c )
                        {
                            $value .= $c;
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $value .= $d;
                    $j++;
                }
                $literals[] = $value;
                $mark = $kind === 'name' ? "\1" : "\0";
                $out .= $mark . ( count( $literals ) - 1 ) . $mark;
                $i = $j + 1;
                continue;
            }
            $out .= $c;
            $i++;
        }
        return array( $out, $literals );
    }

    /**
     * The words of a stripped query in upper case that are not a property or a member (right after a dot), in their
     * order. A key of a map ({set: 1}) counts as a word: such a query is refused rather than a write let through.
     *
     * @param string $stripped
     * @return string[]
     */
    public static function words( $stripped )
    {
        preg_match_all( '/(?<![.\w$\x00\x01])([A-Za-z_][A-Za-z0-9_]*)(?![\w$])/', (string)$stripped, $m, PREG_OFFSET_CAPTURE );
        $words = array();
        foreach ( $m[1] as $match )
            $words[] = strtoupper( $match[0] );
        return $words;
    }

    /**
     * The first word of a list in a stripped query.
     *
     * @param string $stripped
     * @param string[] $forbidden upper case
     * @return string|null
     */
    public static function forbiddenWord( $stripped, array $forbidden )
    {
        foreach ( static::words( $stripped ) as $word )
            if ( in_array( $word, $forbidden, true ) )
                return $word;
        return null;
    }

    /**
     * Whether a stripped query has more after a ;, i.e. more than one statement.
     *
     * @param string $stripped
     * @return bool
     */
    public static function hasSecondStatement( $stripped )
    {
        $pos = strpos( (string)$stripped, ';' );
        return $pos !== false && trim( substr( (string)$stripped, $pos + 1 ), " \t\r\n;" ) !== '';
    }

    // ---- values ------------------------------------------------------------------------------------------------------

    /**
     * Nested values as dotted names (expImportSearchSource::flatten()): author.name; a list of plain values stays a
     * list, a list of objects is numbered (images.0.url).
     *
     * @param array $data
     * @param string $prefix
     * @return array
     */
    public static function flatten( array $data, $prefix = '' )
    {
        return expImportSearchSource::flatten( $data, $prefix );
    }

    /**
     * A whole number of a server's text (an integer outside PHP's range stays a string).
     *
     * @param string $text
     * @return int|string
     */
    public static function integer( $text )
    {
        $text = trim( (string)$text );
        if ( preg_match( '/^-?\d+$/', $text ) && (string)(int)$text === ltrim( $text, '+' ) )
            return (int)$text;
        return $text;
    }

    // ---- drivers -----------------------------------------------------------------------------------------------------

    /**
     * How this PHP reaches the server: PHP's http and https streams (which the source uses), with what the host needs
     * for a missing one and what the job queue's workers last had.
     *
     * @return array id => array( 'label', 'available' => bool, 'via' => string, 'needs' => string,
     *               'worker' => yes, no or '' (not known), 'worker_php' => string )
     */
    public static function drivers()
    {
        return expImportSearchSource::withWorker( static::localDrivers(), static::WORKER_PREFIX );
    }

    /**
     * The drivers of this PHP without what the workers had (also what recordWorkerPhp() notes).
     *
     * @return array id => array( label, via, needs )
     */
    public static function localDrivers()
    {
        $env = is_array( static::$environment ) ? static::$environment : array();
        $fopen = isset( $env['allow_url_fopen'] ) ? (bool)$env['allow_url_fopen'] : (bool)ini_get( 'allow_url_fopen' );
        $openssl = isset( $env['openssl'] ) ? (bool)$env['openssl'] : extension_loaded( 'openssl' );
        return array(
            'http' => array( 'label' => 'http:// addresses', 'via' => $fopen ? 'PHP http stream' : '',
                             'needs' => 'allow_url_fopen=On in the php.ini of the web server and of the job queue\'s workers' ),
            'https' => array( 'label' => 'https:// addresses (certificates verified)', 'via' => $fopen && $openssl ? 'PHP https stream (openssl)' : '',
                              'needs' => 'allow_url_fopen=On and the PHP extension openssl (RHEL and Debian: part of the php package)' ),
        );
    }
}
