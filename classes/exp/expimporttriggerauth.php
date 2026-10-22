<?php
/**
 * File containing the expImportTriggerAuth class, the expImportTriggerCallStore interface and its database store.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Where the trigger keeps its recent calls: the signatures already used (replay protection) and the calls counted by
 * the rate limit. The database store (sqliimport_trigger_call) in a request, an array in the unit tests.
 */
interface expImportTriggerCallStore
{
    /**
     * Records a call.
     *
     * @param int $importID
     * @param string $hash a SHA-256 hex digest that identifies the call (the signature's for a signed call)
     * @param string $kind trigger, status, fail
     * @param int $time
     * @return bool false when the import has a call with this hash already (a replayed signature)
     */
    public function add( $importID, $hash, $kind, $time );

    /** @return int the calls of a kind since a time */
    public function count( $importID, $kind, $since );

    /** Forgets the calls of an import before a time. */
    public function purge( $importID, $before );
}

/**
 * The calls of the trigger in sqliimport_trigger_call. The unique index (import_id, call_hash) refuses a signature
 * that two requests send at the same moment, so only one of them starts a run.
 */
class expImportTriggerDbCallStore implements expImportTriggerCallStore
{
    public function add( $importID, $hash, $kind, $time )
    {
        $db = expImport::db();
        $importID = (int)$importID;
        $hash = $db->escapeString( substr( preg_replace( '/[^a-f0-9]/', '', strtolower( (string)$hash ) ), 0, 64 ) );
        $rows = $db->arrayQuery( "SELECT id FROM sqliimport_trigger_call WHERE import_id = $importID AND call_hash = '$hash'" );
        if ( is_array( $rows ) && $rows )
            return false;
        $kind = $db->escapeString( substr( (string)$kind, 0, 20 ) );
        // a duplicate key error here is the same signature sent twice at once: the second one is a replay
        $ok = @$db->query( "INSERT INTO sqliimport_trigger_call ( import_id, call_hash, kind, created ) VALUES ( $importID, '$hash', '$kind', " . (int)$time . ' )' );
        return $ok !== false;
    }

    public function count( $importID, $kind, $since )
    {
        $db = expImport::db();
        $rows = $db->arrayQuery( 'SELECT COUNT(*) AS n FROM sqliimport_trigger_call WHERE import_id = ' . (int)$importID
                                 . " AND kind = '" . $db->escapeString( (string)$kind ) . "' AND created >= " . (int)$since );
        $row = is_array( $rows ) && isset( $rows[0] ) ? array_change_key_case( $rows[0], CASE_LOWER ) : array();
        return $row ? (int)reset( $row ) : 0;
    }

    public function purge( $importID, $before )
    {
        expImport::db()->query( 'DELETE FROM sqliimport_trigger_call WHERE import_id = ' . (int)$importID . ' AND created < ' . (int)$before );
    }
}

/**
 * The checks of the webhook / API trigger of an import, without a database (expImportTriggerEndpoint does the
 * request):
 *
 *   signed call    POST with X-Sqliimport-Timestamp and X-Sqliimport-Signature (or ?timestamp=&signature=):
 *                  HMAC-SHA256 with the import's signing secret over "POST\n/sqliimport/trigger/<identifier>\n
 *                  <timestamp>\n<SHA-256 hex of the body>", accepted for [TriggerSettings] SignatureWindowSeconds
 *                  around the server's time and only once (replay protection)
 *   API key        Authorization: Bearer <key> or X-Api-Key: <key>; sqk_<key id>_<43 characters>. Only an HMAC-SHA256
 *                  of the key with the server key (the pepper) is stored, found by its key id
 *
 * The signing secret is never stored: it is derived from the server key and a random salt of the import (rotating it
 * draws a new salt), or it is a secret of expImportSecrets by its name. The server key is the secret named by
 * [TriggerSettings] ServerKeySecret, else a random key the extension writes once to its storage directory
 * (var/<site>/storage/sqliimport/trigger.key, mode 0600), outside the database: a copy of the database alone gives
 * neither the signing secrets nor anything to check a key against.
 */
class expImportTriggerAuth
{
    const HEADER_TIMESTAMP = 'HTTP_X_SQLIIMPORT_TIMESTAMP';
    const HEADER_SIGNATURE = 'HTTP_X_SQLIIMPORT_SIGNATURE';
    const HEADER_FORMAT = 'HTTP_X_SQLIIMPORT_FORMAT';

    /** The pattern of an API key: sqk_<12 hex key id>_<32 random bytes, base64url> */
    const KEY_PATTERN = '/^sqk_([0-9a-f]{12})_([A-Za-z0-9_-]{43})$/';

    /** The content types of a pushed body and the file extensions they may be */
    const CONTENT_TYPES = array(
        'text/csv' => array( 'csv' ), 'application/csv' => array( 'csv' ), 'text/tab-separated-values' => array( 'tsv', 'csv' ),
        'application/json' => array( 'json' ), 'text/json' => array( 'json' ),
        'application/x-ndjson' => array( 'ndjson', 'jsonl' ), 'application/ndjson' => array( 'ndjson', 'jsonl' ),
        'application/jsonl' => array( 'jsonl', 'ndjson' ), 'application/x-jsonlines' => array( 'jsonl', 'ndjson' ),
        'application/xml' => array( 'xml' ), 'text/xml' => array( 'xml' ), 'application/rss+xml' => array( 'rss', 'xml' ),
        'application/atom+xml' => array( 'atom', 'xml' ), 'application/yaml' => array( 'yaml', 'yml' ), 'text/yaml' => array( 'yaml', 'yml' ),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => array( 'xlsx' ),
        'application/vnd.oasis.opendocument.spreadsheet' => array( 'ods' ),
    );

    /** @var string|null tests: a fixed server key */
    public static $serverKey = null;

    /**
     * The server key: the pepper of the API key hashes and the key the signing secrets are derived from.
     *
     * @return string
     * @throws expImportException when there is none and none can be written
     */
    public static function serverKey()
    {
        if ( self::$serverKey !== null )
            return (string)self::$serverKey;
        $ref = trim( (string)expImport::setting( 'TriggerSettings', 'ServerKeySecret', '' ) );
        if ( $ref !== '' )
        {
            $value = expImportSecrets::get( $ref );
            if ( $value === null || strlen( $value ) < 32 )
                throw new expImportException( "the server key of the trigger (secret '$ref' of [TriggerSettings] ServerKeySecret) is missing or shorter than 32 characters" );
            return $value;
        }
        $file = expImport::storageDirectory() . '/trigger.key';
        if ( !is_file( $file ) )
        {
            // 'x': two requests that create it at once do not overwrite each other; the loser reads the winner's key
            $fp = @fopen( $file, 'x' );
            if ( $fp )
            {
                @chmod( $file, 0600 );
                fwrite( $fp, bin2hex( random_bytes( 32 ) ) );
                fclose( $fp );
            }
        }
        $key = is_file( $file ) ? trim( (string)file_get_contents( $file ) ) : '';
        if ( strlen( $key ) < 32 )
            throw new expImportException( 'the server key of the trigger could not be written to the storage directory' );
        return $key;
    }

    /** @return string a new random salt of a signing secret (hex) */
    public static function newSalt()
    {
        return bin2hex( random_bytes( 32 ) );
    }

    /**
     * The signing secret of a salt: what the caller signs with (64 hex characters).
     *
     * @param string $salt
     * @param string|null $serverKey
     * @return string
     */
    public static function signingSecret( $salt, $serverKey = null )
    {
        return hash_hmac( 'sha256', 'sqliimport-trigger-signing:' . (string)$salt, $serverKey !== null ? (string)$serverKey : self::serverKey() );
    }

    /** @return string the path a signature covers (the same on every siteaccess and host) */
    public static function canonicalPath( $identifier )
    {
        return '/sqliimport/trigger/' . (string)$identifier;
    }

    /** @return string what is signed */
    public static function stringToSign( $method, $path, $timestamp, $body )
    {
        return strtoupper( (string)$method ) . "\n" . (string)$path . "\n" . (string)$timestamp . "\n" . hash( 'sha256', (string)$body );
    }

    /** @return string the signature (hex) */
    public static function sign( $secret, $method, $path, $timestamp, $body )
    {
        return hash_hmac( 'sha256', self::stringToSign( $method, $path, $timestamp, $body ), (string)$secret );
    }

    /**
     * Checks a signature (not whether it was used before: replayed()).
     *
     * @param string $secret
     * @param string $method
     * @param string $path canonicalPath()
     * @param string|null $timestamp Unix seconds as sent
     * @param string|null $signature "sha256=<hex>" or "<hex>"
     * @param string $body
     * @param int $now
     * @param int $window seconds before and after now
     * @return string ok, missing, malformed, expired, bad
     */
    public static function checkSignature( $secret, $method, $path, $timestamp, $signature, $body, $now, $window = 300 )
    {
        if ( $timestamp === null || $signature === null || $timestamp === '' || $signature === '' )
            return 'missing';
        $signature = strtolower( trim( (string)$signature ) );
        if ( strpos( $signature, 'sha256=' ) === 0 )
            $signature = substr( $signature, 7 );
        if ( !preg_match( '/^[0-9]{1,12}$/', (string)$timestamp ) || !preg_match( '/^[0-9a-f]{64}$/', $signature ) )
            return 'malformed';
        if ( abs( (int)$now - (int)$timestamp ) > max( 1, (int)$window ) )
            return 'expired';
        if ( (string)$secret === '' )
            return 'bad';
        return hash_equals( self::sign( $secret, $method, $path, $timestamp, $body ), $signature ) ? 'ok' : 'bad';
    }

    /**
     * Records a signature that was found good; true when it was used before (a replay).
     *
     * @return bool
     */
    public static function replayed( expImportTriggerCallStore $store, $importID, $signature, $now )
    {
        $signature = strtolower( trim( (string)$signature ) );
        if ( strpos( $signature, 'sha256=' ) === 0 )
            $signature = substr( $signature, 7 );
        return !$store->add( $importID, hash( 'sha256', 'signature:' . $signature ), 'trigger', $now );
    }

    /**
     * A new API key.
     *
     * @return array [ string key, string key_id ] the key is shown once and never stored
     */
    public static function newKey()
    {
        $id = bin2hex( random_bytes( 6 ) );
        return array( 'sqk_' . $id . '_' . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ), $id );
    }

    /** @return string|null the key id of a well-formed key */
    public static function keyId( $key )
    {
        return preg_match( self::KEY_PATTERN, (string)$key, $m ) ? $m[1] : null;
    }

    /** @return string what is stored of a key (HMAC-SHA256 with the server key as pepper) */
    public static function hashKey( $key, $pepper = null )
    {
        return hash_hmac( 'sha256', (string)$key, $pepper !== null ? (string)$pepper : self::serverKey() );
    }

    /** @return bool whether a key is the one of a stored hash */
    public static function verifyKey( $key, $hash, $pepper = null )
    {
        if ( self::keyId( $key ) === null || !preg_match( '/^[0-9a-f]{64}$/', (string)$hash ) )
            return false;
        return hash_equals( (string)$hash, self::hashKey( $key, $pepper ) );
    }

    /**
     * The API key of a request: Authorization: Bearer <key>, else X-Api-Key.
     *
     * @param array $server $_SERVER
     * @return string|null
     */
    public static function keyFromServer( array $server )
    {
        foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $name )
            if ( isset( $server[$name] ) && preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', (string)$server[$name], $m ) )
                return $m[1];
        if ( isset( $server['HTTP_X_API_KEY'] ) && trim( (string)$server['HTTP_X_API_KEY'] ) !== '' )
            return trim( (string)$server['HTTP_X_API_KEY'] );
        return null;
    }

    /**
     * The timestamp and signature of a signed call: the headers, else the query (?timestamp=&signature=, the signed URL).
     *
     * @param array $server $_SERVER
     * @param array $query $_GET
     * @return array [ string|null timestamp, string|null signature ]
     */
    public static function signatureFromRequest( array $server, array $query )
    {
        $ts = isset( $server[self::HEADER_TIMESTAMP] ) ? (string)$server[self::HEADER_TIMESTAMP] : null;
        $sig = isset( $server[self::HEADER_SIGNATURE] ) ? (string)$server[self::HEADER_SIGNATURE] : null;
        if ( $ts === null && $sig === null )
        {
            $ts = isset( $query['timestamp'] ) && is_scalar( $query['timestamp'] ) ? (string)$query['timestamp'] : null;
            $sig = isset( $query['signature'] ) && is_scalar( $query['signature'] ) ? (string)$query['signature'] : null;
        }
        return array( $ts, $sig );
    }

    /**
     * Whether an import has used up a kind of calls within the last window.
     *
     * @return bool
     */
    public static function rateLimited( expImportTriggerCallStore $store, $importID, $kind, $limit, $now, $window = 60 )
    {
        $limit = (int)$limit;
        if ( $limit <= 0 )
            return false;
        return $store->count( $importID, $kind, (int)$now - (int)$window + 1 ) >= $limit;
    }

    /**
     * The file extension a pushed body is stored with, from its content type (or X-Sqliimport-Format), among the
     * extensions of the import's format.
     *
     * @param string $contentType
     * @param string[] $extensions the format's
     * @param string $format X-Sqliimport-Format, '' when not sent
     * @return string|null
     */
    public static function bodyExtension( $contentType, array $extensions, $format = '' )
    {
        $extensions = array_map( 'strtolower', $extensions );
        $format = strtolower( trim( (string)$format ) );
        if ( $format !== '' )
            return preg_match( '/^[a-z0-9]{1,8}$/', $format ) && in_array( $format, $extensions, true ) ? $format : null;
        $type = strtolower( trim( explode( ';', (string)$contentType )[0] ) );
        if ( !isset( self::CONTENT_TYPES[$type] ) )
            return null;
        foreach ( self::CONTENT_TYPES[$type] as $ext )
            if ( in_array( $ext, $extensions, true ) )
                return $ext;
        return null;
    }

    /**
     * Whether an import can take its data from a call: its format reads files (it can be uploaded) and the import
     * does not read a remote source itself; a 1.x handler reads by itself.
     *
     * @param array|null $source the format's entry of expImportRegistry::sources()
     * @param array $config the import's source configuration
     * @param bool $legacy
     * @return bool
     */
    public static function takesData( $source, array $config, $legacy = false )
    {
        if ( $legacy || !is_array( $source ) || empty( $source['extensions'] ) )
            return false;
        $origins = isset( $source['origins'] ) ? (array)$source['origins'] : array( 'upload', 'url', 'path' );
        $origin = isset( $config['origin'] ) ? (string)$config['origin'] : 'upload';
        return in_array( 'upload', $origins, true ) && $origin !== 'remote';
    }

    /**
     * Whether a pushed body may become the snapshot of a run.
     *
     * @param int $length bytes
     * @param int $maxBytes [TriggerSettings] MaxBodyMegabytes
     * @param bool $pushAllowed the trigger allows data and the import reads a file (not a remote source)
     * @param string $contentType
     * @param string[] $extensions the import's format's
     * @param string $format X-Sqliimport-Format
     * @return array [ int status (0: fine, 413, 415), string extension or the reason ]
     */
    public static function checkBody( $length, $maxBytes, $pushAllowed, $contentType, array $extensions, $format = '' )
    {
        if ( (int)$length > (int)$maxBytes )
            return array( 413, 'the body is larger than [TriggerSettings] MaxBodyMegabytes' );
        if ( !$pushAllowed )
            return array( 415, 'this import takes no data in the call: its trigger does not allow it or its source is not a file' );
        $ext = self::bodyExtension( $contentType, $extensions, $format );
        if ( $ext === null )
            return array( 415, 'the content type is not one of the import\'s format (' . implode( ', ', $extensions ) . ')' );
        return array( 0, $ext );
    }
}
