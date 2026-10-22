<?php
/**
 * File containing the expImportSecrets class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Secrets of sources (database passwords, mailbox passwords, API keys). An import never stores a secret: its option
 * (type "secret") holds the NAME of one, and the value is read here when a run needs it:
 *
 *   env:<VARIABLE>   the environment variable of the PHP process (php-fpm pool, the worker's environment)
 *   <name>           sqliimport.ini [SecretSettings] Secrets[<name>]=<value>, set in the site's own settings
 *                    (settings/override/sqliimport.ini.append.php or a siteaccess), which are not part of a repository
 *
 * The administration shows the names only (Settings), never a value, and the logs and run pages never get one.
 */
class expImportSecrets
{
    /**
     * The value of a secret, or null when there is none of that name.
     *
     * @param string $ref
     * @return string|null
     */
    public static function get( $ref )
    {
        $ref = trim( (string)$ref );
        if ( $ref === '' || !preg_match( '/^[A-Za-z0-9_.:-]+$/', $ref ) )
            return null;
        if ( strpos( $ref, 'env:' ) === 0 )
        {
            $value = getenv( substr( $ref, 4 ) );
            return $value === false ? null : (string)$value;
        }
        $secrets = (array)expImport::setting( 'SecretSettings', 'Secrets', array() );
        return isset( $secrets[$ref] ) ? (string)$secrets[$ref] : null;
    }

    /**
     * The value of a secret a source needs; an error naming the secret (not its value) when it is missing.
     *
     * @param string $ref
     * @param string $what what it is for, for the message
     * @return string
     * @throws expImportSourceException
     */
    public static function required( $ref, $what )
    {
        $value = self::get( $ref );
        if ( $value === null )
            throw new expImportSourceException( "$what: the secret '$ref' is not set (sqliimport.ini [SecretSettings] Secrets[$ref], or env:VARIABLE)" );
        return $value;
    }

    /** @return string[] the names of the secrets set in the settings (for the Settings page; never the values) */
    public static function names()
    {
        return array_keys( (array)expImport::setting( 'SecretSettings', 'Secrets', array() ) );
    }

    /**
     * A text with every known secret value replaced by its name, for messages that might quote a connection error.
     *
     * @param string $text
     * @param string[] $refs the secrets a source used
     * @return string
     */
    public static function mask( $text, array $refs )
    {
        foreach ( $refs as $ref )
        {
            $value = self::get( $ref );
            if ( $value !== null && strlen( $value ) >= 3 )
                $text = str_replace( $value, '[secret ' . $ref . ']', (string)$text );
        }
        return (string)$text;
    }
}
