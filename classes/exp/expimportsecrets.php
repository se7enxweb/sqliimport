<?php
/**
 * File containing the expImportSecrets class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
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
        // every value this tool can read (the kernel store, secrets.ini, env:, sqliimport.ini) by its name first; then
        // the kernel's own mask for what it knows besides (it names only the store's and secrets.ini's secrets)
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
        return self::kernelStore() ? (string)expSecrets::mask( $text, $refs ) : $text;
    }

    /** @var bool|null tests: whether the current user may store secrets */
    public static $canStore = null;

    /**
     * Whether the editor offers "New value" for a secret option: the kernel store is there with its table, and the
     * current user has the policy secrets/manage.
     *
     * @return bool
     */
    public static function canStore()
    {
        if ( self::$canStore !== null )
            return self::$canStore;
        if ( !self::kernelStore() || !expSecrets::installed() || !class_exists( 'eZUser' ) )
            return false;
        $access = eZUser::currentUser()->hasAccessTo( 'secrets', 'manage' );
        return isset( $access['accessWord'] ) && $access['accessWord'] !== 'no';
    }

    /**
     * Stores a value typed into the editor in the kernel store, scope sqliimport.
     *
     * @param string $name the name the import's option gives
     * @param string $value
     * @param string $description
     * @return true|string true, or the reason it was not stored (for the field's error; never the value)
     */
    public static function store( $name, $value, $description = '' )
    {
        $name = trim( (string)$name );
        if ( !self::canStore() )
            return 'Only a user with the policy secrets/manage can store a secret here (Setup > Secrets).';
        if ( $name === '' )
            return 'Give the secret a name in this field, then type its value below.';
        if ( strpos( $name, 'env:' ) === 0 || !expSecrets::validName( $name ) )
            return "The name '$name' cannot hold a stored secret (letters, digits, _ . - ; not env:).";
        if ( !expSecrets::set( $name, (string)$value, self::SCOPE, (string)$description ) )
            return 'The secret could not be stored (Setup > Secrets shows why: the key or the table).';
        return true;
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
