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
 * (type "secret") holds the NAME of one, and the value is read here when a run needs it.
 *
 * From Exponential 6.0.15 on, the kernel's secret store answers (expSecrets: encrypted values in exp_secret, set in
 * Setup > Secrets; secrets.ini; env:), and sqliimport.ini [SecretSettings] Secrets[] stays the tool's own fallback.
 * On an older kernel (or a Velocity worker started before the class existed):
 *
 *   env:<VARIABLE>   the environment variable of the PHP process (php-fpm pool, the worker's environment)
 *   <name>           sqliimport.ini [SecretSettings] Secrets[<name>]=<value>, set in the site's own settings
 *                    (settings/override/sqliimport.ini.append.php or a siteaccess), which are not part of a repository
 *
 * The administration shows the names only (Settings), never a value, and the logs and run pages never get one. New
 * secrets of the tool are stored under the scope "sqliimport" of the kernel store.
 */
class expImportSecrets
{
    /** The scope of this tool's secrets in the kernel store */
    const SCOPE = 'sqliimport';

    /** @var bool|null tests: false pretends there is no kernel store */
    public static $kernelStore = null;

    /** @return bool whether the kernel's secret store (expSecrets, Exponential 6.0.15) is there */
    public static function kernelStore()
    {
        if ( self::$kernelStore !== null )
            return self::$kernelStore;
        return class_exists( 'expSecrets' );
    }

    /** @return array the secrets of sqliimport.ini [SecretSettings] Secrets[] (name => value) */
    protected static function iniSecrets()
    {
        return (array)expImport::setting( 'SecretSettings', 'Secrets', array() );
    }

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
        if ( self::kernelStore() )
        {
            // env:, the kernel store, secrets.ini; then the tool's own INI secrets
            $value = expSecrets::get( $ref, self::iniSecrets() );
            return $value === null ? null : (string)$value;
        }
        if ( strpos( $ref, 'env:' ) === 0 )
        {
            $value = getenv( substr( $ref, 4 ) );
            return $value === false ? null : (string)$value;
        }
        $secrets = self::iniSecrets();
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
            throw new expImportSourceException( self::kernelStore()
                ? "$what: the secret '$ref' is not set (Setup > Secrets, scope sqliimport; or sqliimport.ini [SecretSettings] Secrets[$ref], or env:VARIABLE)"
                : "$what: the secret '$ref' is not set (sqliimport.ini [SecretSettings] Secrets[$ref], or env:VARIABLE)" );
        return $value;
    }

    /**
     * @return string[] the names of the secrets an import may name (the kernel store's of the scope sqliimport and the
     *                  settings'); never the values
     */
    public static function names()
    {
        $names = array_keys( self::iniSecrets() );
        if ( self::kernelStore() )
            $names = array_merge( array_keys( (array)expSecrets::names( self::SCOPE ) ), $names );
        return array_values( array_unique( array_map( 'strval', $names ) ) );
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
        $text = (string)$text;
        if ( self::kernelStore() )
        {
            // the kernel's mask also hides the rawurlencoded value; the INI values are given to it as well
            $values = array();
            foreach ( $refs as $ref )
            {
                $value = self::get( $ref );
                if ( $value !== null )
                    $values[(string)$ref] = $value;
            }
            return (string)expSecrets::mask( $text, $refs, $values );
        }
        foreach ( $refs as $ref )
        {
            $value = self::get( $ref );
            if ( $value !== null && strlen( $value ) >= 3 )
            {
                $text = str_replace( $value, '[secret ' . $ref . ']', $text );
                if ( rawurlencode( $value ) !== $value )
                    $text = str_replace( rawurlencode( $value ), '[secret ' . $ref . ']', $text );
            }
        }
        return $text;
    }

    /**
     * Where the values are set, for the Settings page: the kernel's Secrets page of this scope, or '' before 6.0.15.
     *
     * @return string a module path, e.g. 'secrets/list/(scope)/sqliimport'
     */
    public static function manageUrl()
    {
        return self::kernelStore() ? 'secrets/list/(scope)/' . self::SCOPE : '';
    }
}
