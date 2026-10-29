<?php
/**
 * File containing the expImportGoogleAuth class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The access token of a Google Cloud service account, for the sources that read Firestore, BigQuery and the Firebase
 * Realtime Database: the service account's JSON key is the value of a secret (expImportSecrets, by name), a JWT signed
 * with its private key (RS256, openssl_sign) is exchanged at Google's token endpoint for an access token (the OAuth 2.0
 * JWT bearer grant, RFC 7523), and the token is kept for the rest of the run (until a minute before it expires).
 *
 * The JWT is only ever sent to https://oauth2.googleapis.com/token (a token_uri of the key file pointing elsewhere is
 * not followed). Each source asks for the narrowest scope its API accepts; what the account may do is decided by its
 * IAM roles (roles/datastore.viewer, roles/bigquery.dataViewer and roles/bigquery.jobUser,
 * roles/firebasedatabase.viewer). The key, the JWT and the token are masked in every message (mask()).
 */
class expImportGoogleAuth
{
    /** The token endpoint; the only address a JWT is sent to. */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** How long a JWT asks to be valid, in seconds (Google allows at most an hour). */
    const LIFETIME = 3600;

    /** @var callable|null replaces time() (tests): function () returning int */
    public static $clock = null;

    /** @var array cache key => array( 'token' => string, 'expires' => int ) */
    protected static $tokens = array();

    /** Forgets the tokens of this run (tests). */
    public static function reset()
    {
        self::$tokens = array();
    }

    /** @return int now */
    protected static function now()
    {
        return self::$clock ? (int)call_user_func( self::$clock ) : time();
    }

    /**
     * The service account key of a secret: the decoded JSON key file Google gives.
     *
     * @param string $ref the name of the secret
     * @param string $service for messages (Firestore)
     * @return array with client_email and private_key
     * @throws expImportSourceException
     */
    public static function key( $ref, $service )
    {
        $json = expImportSecrets::required( $ref, "$service: the service account key" );
        $key = json_decode( $json, true );
        if ( !is_array( $key ) || !isset( $key['client_email'], $key['private_key'] ) || !is_string( $key['private_key'] ) )
            throw new expImportSourceException( "$service: the secret '$ref' is not the JSON key of a service account (with client_email and private_key)" );
        if ( isset( $key['type'] ) && $key['type'] !== 'service_account' )
            throw new expImportSourceException( "$service: the secret '$ref' is a key of the type " . substr( (string)$key['type'], 0, 40 ) . ', not service_account' );
        return $key;
    }

    /**
     * The signed JWT of the grant.
     *
     * @param array $key the service account key
     * @param string $scope the scopes, separated by spaces
     * @param int $now
     * @return string
     * @throws expImportSourceException
     */
    public static function jwt( array $key, $scope, $now )
    {
        if ( !function_exists( 'openssl_sign' ) )
            throw new expImportSourceException( 'Google: this PHP has no openssl extension, which signs the service account\'s token request' );
        $header = array( 'alg' => 'RS256', 'typ' => 'JWT' );
        if ( isset( $key['private_key_id'] ) )
            $header['kid'] = (string)$key['private_key_id'];
        $claims = array( 'iss' => (string)$key['client_email'], 'scope' => (string)$scope, 'aud' => self::TOKEN_URL,
                         'iat' => (int)$now, 'exp' => (int)$now + self::LIFETIME );
        $input = self::base64url( (string)json_encode( $header, JSON_UNESCAPED_SLASHES ) ) . '.' . self::base64url( (string)json_encode( $claims, JSON_UNESCAPED_SLASHES ) );
        $private = @openssl_pkey_get_private( (string)$key['private_key'] );
        if ( !$private )
            throw new expImportSourceException( 'Google: the private key of the service account cannot be read' );
        $signature = '';
        if ( !openssl_sign( $input, $signature, $private, OPENSSL_ALGO_SHA256 ) )
            throw new expImportSourceException( 'Google: the token request cannot be signed' );
        return $input . '.' . self::base64url( $signature );
    }

    /** @return string base64url without padding */
    public static function base64url( $data )
    {
        return rtrim( strtr( base64_encode( (string)$data ), '+/', '-_' ), '=' );
    }

    /**
     * The access token of a service account for scopes, from the token endpoint or this run's cache.
     *
     * @param string $ref the name of the secret with the key
     * @param string $scope the scopes, separated by spaces
     * @param string $service for messages
     * @return string
     * @throws expImportSourceException
     */
    public static function accessToken( $ref, $scope, $service )
    {
        $key = self::key( $ref, $service );
        $cache = sha1( (string)$key['client_email'] . "\n" . (isset( $key['private_key_id'] ) ? (string)$key['private_key_id'] : '') . "\n" . $scope );
        $now = self::now();
        if ( isset( self::$tokens[$cache] ) && self::$tokens[$cache]['expires'] - 60 > $now )
            return self::$tokens[$cache]['token'];
        $jwt = self::jwt( $key, $scope, $now );
        $body = 'grant_type=' . rawurlencode( 'urn:ietf:params:oauth:grant-type:jwt-bearer' ) . '&assertion=' . rawurlencode( $jwt );
        $answer = expImportPlatformHttp::request( 'POST', self::TOKEN_URL, array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json' ), $body );
        $data = json_decode( (string)$answer['body'], true );
        if ( (int)$answer['status'] !== 200 || !is_array( $data ) || !isset( $data['access_token'] ) || !is_string( $data['access_token'] ) )
        {
            $reason = expImportCloudHttp::reason( $data );
            throw new expImportSourceException( "$service: Google refused the service account's token request (" . (int)$answer['status'] . ')'
                                                . ( $reason !== '' ? ': ' . substr( str_replace( $jwt, '[jwt]', $reason ), 0, 300 ) : '' ) );
        }
        self::$tokens[$cache] = array( 'token' => $data['access_token'], 'expires' => $now + ( isset( $data['expires_in'] ) ? (int)$data['expires_in'] : 3600 ) );
        return $data['access_token'];
    }

    /**
     * A message with every secret of the source, the private key and every access token of this run masked.
     *
     * @param string $text
     * @param string[] $refs the secrets used
     * @return string
     */
    public static function mask( $text, array $refs )
    {
        $text = expImportSecrets::mask( (string)$text, $refs );
        foreach ( self::$tokens as $entry )
            if ( strlen( $entry['token'] ) >= 8 )
                $text = str_replace( $entry['token'], '[access token]', $text );
        foreach ( $refs as $ref )
        {
            $value = expImportSecrets::get( $ref );
            $key = $value !== null ? json_decode( $value, true ) : null;
            if ( is_array( $key ) )
                foreach ( array( 'private_key', 'private_key_id' ) as $name )
                    if ( isset( $key[$name] ) && is_string( $key[$name] ) && strlen( $key[$name] ) >= 8 )
                        $text = str_replace( array( $key[$name], str_replace( "\n", '\\n', $key[$name] ) ), '[secret ' . $ref . ']', $text );
        }
        return $text;
    }
}
