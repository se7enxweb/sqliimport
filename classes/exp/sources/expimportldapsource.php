<?php
/**
 * File containing the expImportLdapSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The entries of an LDAP directory (OpenLDAP, Active Directory, 389 Directory Server ...): one record is one entry of
 * a search, its fields the DN (dn) and the entry's attributes by name.
 *
 * The source reads its data itself (origin "remote"): once per run fetch() binds (as a user or anonymously), searches
 * the base DN with the filter in the scope (sub, one or base), page by page with the paged results control
 * (RFC 2696) when a page size is set, and writes the entries into the run's snapshot, one JSON object per line.
 *
 * Only reading is possible:
 *   - the source binds and searches, nothing else (expImportLdapTransport has no method that writes)
 *   - the connection is encrypted: ldaps://, or ldap:// with StartTLS, and the server's certificate is checked; an
 *     unencrypted connection only to this server itself (localhost) and only when the import allows it
 *   - the password is the name of a secret (expImportSecrets), never stored in the import, and masked in every message
 *   - a directory server in a private network is refused like an address ([SourceSettings] AllowPrivateHosts) unless
 *     an administrator lists it in [LdapSourceSettings] AllowedHosts[] (the usual case: an intranet directory)
 *   - the filter is checked against the grammar of RFC 4515 before it is sent, the attribute names too
 *
 * Values: an attribute with one value is that value, one with several a list (or the values joined, option
 * multi_valued); binary attributes (jpegPhoto, objectGUID, objectSid, certificates, any value that is not UTF-8) are
 * written as base64:<data> or left out (option binary); objectGUID is also given as text (objectGUID_text). A
 * directory of people holds personal data. Password attributes (userPassword, unicodePwd ...) are never read into a
 * record.
 */
class expImportLdapSource extends expImportJsonSource
{
    /** Attributes that hold bytes, whatever their content (lower case). */
    const BINARY = array(
        'jpegphoto', 'photo', 'thumbnailphoto', 'thumbnaillogo', 'audio', 'objectguid', 'objectsid', 'sidhistory', 'tokengroups',
        'usercertificate', 'cacertificate', 'certificaterevocationlist', 'authorityrevocationlist', 'crosscertificatepair',
        'usersmimecertificate', 'userpkcs12', 'msexchmailboxguid', 'msexchmailboxsecuritydescriptor', 'ms-ds-consistencyguid',
        'ntsecuritydescriptor', 'logonhours', 'msds-generationid',
    );

    /** Attributes that hold passwords or keys (lower case): never read into a record, even when a server returns them. */
    const SECRET = array(
        'userpassword', 'unicodepwd', 'krbprincipalkey', 'sambantpassword', 'sambalmpassword', 'ntpwdhistory', 'lmpwdhistory',
        'supplementalcredentials', 'dbcspwd', 'ms-mcs-admpwd', 'mslaps-password', 'mslaps-encryptedpassword', 'authpassword',
    );

    /** @var callable|null makes the transport (tests): function () returning an expImportLdapTransport */
    public static $transportFactory = null;

    /** @var array|null what drivers() sees of this PHP (tests): array( 'ldap' => bool, 'paging' => bool ); null: this PHP */
    public static $environment = null;

    /** @var string|null the file of the worker's PHP (recordWorkerPhp()); null: in the storage directory */
    public static $workerFile = null;

    public static function identifier()
    {
        return 'ldap';
    }

    public static function label()
    {
        return 'LDAP directory';
    }

    public static function extensions()
    {
        return array();
    }

    public static function group()
    {
        return 'data';
    }

    public static function origins()
    {
        return array( 'remote' );
    }

    public static function description()
    {
        return 'The entries of an LDAP directory (OpenLDAP, Active Directory, 389 Directory Server): one record is one entry '
             . 'of a search (base DN, filter, scope), its fields the DN and the attributes. Read-only (bind and search '
             . 'only), over ldaps:// or StartTLS; the password is the name of a secret in the settings; large results are '
             . 'read page by page. Needs the PHP extension ldap in the PHP of the job queue\'s workers. A directory of '
             . 'people holds personal data.';
    }

    public static function examples()
    {
        return array(
            array( 'label' => 'A snapshot of three entries of a staff directory (what a run writes): DN, names, e-mail addresses, a multi-valued attribute and a photo as base64',
                   'file' => 'ldap-staff.ndjson',
                   'options' => array( 'uri' => 'ldaps://ldap.example.org', 'base_dn' => 'ou=Staff,dc=example,dc=org' ) ),
            array( 'label' => 'The people of ou=Staff of ldap.example.org over ldaps://, bound as a reader, the password as the secret ldap_reader',
                   'options' => array( 'uri' => 'ldaps://ldap.example.org', 'bind_dn' => 'cn=reader,ou=Services,dc=example,dc=org',
                                       'password' => 'ldap_reader', 'base_dn' => 'ou=Staff,dc=example,dc=org',
                                       'filter' => '(&(objectClass=inetOrgPerson)(!(employeeType=former)))',
                                       'attributes' => array( 'uid', 'cn', 'givenName', 'sn', 'mail', 'title', 'telephoneNumber', 'jpegPhoto' ),
                                       'scope' => 'one', 'key' => 'uid' ) ),
            array( 'label' => 'The users of an Active Directory group over StartTLS, named by their objectGUID, the password as the secret ad_reader',
                   'options' => array( 'uri' => 'ldap://dc1.example.org', 'starttls' => true, 'bind_dn' => 'reader@example.org',
                                       'password' => 'ad_reader', 'base_dn' => 'dc=example,dc=org',
                                       'filter' => '(&(objectCategory=person)(objectClass=user)(memberOf=cn=Press,ou=Groups,dc=example,dc=org))',
                                       'attributes' => array( 'objectGUID', 'displayName', 'mail', 'department' ), 'key' => 'objectGUID_text' ) ),
        );
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        return array(
            'uri' => array( 'type' => 'string', 'label' => 'Server', 'required' => true, 'max' => 255,
                            'help' => 'ldaps://ldap.example.org (port 636) or ldap://ldap.example.org (port 389, with StartTLS); a private network only with [LdapSourceSettings] AllowedHosts[]' ),
            'starttls' => array( 'type' => 'bool', 'label' => 'StartTLS on ldap://', 'default' => true,
                                 'help' => 'ldap:// is encrypted with StartTLS; ldaps:// is encrypted from the start' ),
            'allow_plain' => array( 'type' => 'bool', 'label' => 'Allow an unencrypted connection to this server itself', 'default' => false,
                                    'help' => 'Only for ldap://localhost (127.0.0.1, ::1) without StartTLS; any other server needs ldaps:// or StartTLS' ),
            'bind_dn' => array( 'type' => 'string', 'label' => 'Bind DN', 'max' => 1024,
                                'help' => 'The user to bind as, e.g. cn=reader,ou=Services,dc=example,dc=org (Active Directory also reader@example.org); empty: anonymous' ),
            'password' => array( 'type' => 'secret', 'label' => 'Password (name of a secret)',
                                 'help' => 'The name of a secret of sqliimport.ini [SecretSettings] Secrets[] or env:VARIABLE, never the password; needed with a bind DN' ),
            'base_dn' => array( 'type' => 'string', 'label' => 'Base DN', 'required' => true, 'max' => 1024,
                                'help' => 'Where the search starts, e.g. ou=Staff,dc=example,dc=org' ),
            'filter' => array( 'type' => 'string', 'label' => 'Filter', 'default' => '(objectClass=*)', 'max' => 4096,
                               'help' => 'An LDAP filter (RFC 4515), e.g. (&(objectClass=inetOrgPerson)(mail=*)); special characters in values as \\2a, \\28, \\29, \\5c' ),
            'attributes' => array( 'type' => 'list', 'label' => 'Attributes',
                                   'help' => 'The attributes to read, one per line; empty: all user attributes. A listed attribute is in every record, also when an entry has none' ),
            'scope' => array( 'type' => 'enum', 'label' => 'Scope', 'default' => 'sub',
                              'values' => array( 'sub' => 'The base and everything below it', 'one' => 'The entries directly below the base', 'base' => 'The base entry only' ) ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 500, 'min' => 0, 'max' => 5000,
                                  'help' => 'Entries per page of the paged results control; 0: no paging (the server\'s size limit applies, often 1000 in Active Directory)' ),
            'size_limit' => array( 'type' => 'int', 'label' => 'Most entries', 'default' => 0, 'min' => 0, 'max' => 10000000,
                                   'help' => '0: all entries' ),
            'binary' => array( 'type' => 'enum', 'label' => 'Binary attributes', 'default' => 'base64',
                               'values' => array( 'base64' => 'As base64:<data> (a photo can be mapped to an image)', 'skip' => 'Left out' ),
                               'help' => 'jpegPhoto, objectGUID, objectSid, certificates and every value that is not UTF-8 text' ),
            'multi_valued' => array( 'type' => 'enum', 'label' => 'Attributes with several values', 'default' => 'array',
                                     'values' => array( 'array' => 'A list (one value: the value)', 'join' => 'The values joined by the separator' ) ),
            'separator' => array( 'type' => 'string', 'label' => 'Separator', 'default' => '; ', 'raw' => true, 'max' => 10,
                                  'help' => 'With joined values' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'dn',
                            'help' => 'The field that names an entry: dn, or an attribute such as uid, entryUUID or objectGUID_text' ),
        );
    }

    /** The snapshot is always one entry per line. */
    protected function format()
    {
        return 'ndjson';
    }

    // ---- checks ------------------------------------------------------------------------------------------------------

    /**
     * Whether a filter follows RFC 4515: (attr=value), presence (attr=*), substrings (attr=a*b*), ~=, >=, <=,
     * extensible matches (attr:dn:rule:=value), &, | and ! nested at most 32 deep; values with \XX escapes only.
     *
     * @param string $filter
     * @return string|null the reason it is refused, null when it is valid
     */
    public static function checkFilter( $filter )
    {
        $filter = trim( (string)$filter );
        if ( $filter === '' )
            return 'the filter is empty';
        if ( strlen( $filter ) > 4096 )
            return 'the filter is longer than 4096 characters';
        if ( preg_match( '/[\x00-\x08\x0A-\x1F\x7F]/', $filter ) )
            return 'the filter has control characters';
        if ( $filter[0] !== '(' )
            $filter = '(' . $filter . ')';
        $i = 0;
        $error = self::parseFilter( $filter, $i, 0 );
        if ( $error !== null )
            return $error;
        if ( $i !== strlen( $filter ) )
            return 'the filter has text after its last ")" (combine several with (&...) or (|...))';
        return null;
    }

    /** @return string the filter as it is sent: in brackets */
    public static function normalizeFilter( $filter )
    {
        $filter = trim( (string)$filter );
        if ( $filter === '' )
            return '(objectClass=*)';
        return $filter[0] === '(' ? $filter : '(' . $filter . ')';
    }

    /**
     * One filter from position $i: "(" and/or/not/item ")".
     *
     * @param string $s
     * @param int $i moved past the filter
     * @param int $depth
     * @return string|null an error
     */
    protected static function parseFilter( $s, &$i, $depth )
    {
        if ( $depth > 32 )
            return 'the filter is nested more than 32 deep';
        $n = strlen( $s );
        while ( $i < $n && $s[$i] === ' ' )
            $i++;
        if ( $i >= $n || $s[$i] !== '(' )
            return 'a "(" is expected at position ' . ( $i + 1 );
        $i++;
        if ( $i >= $n )
            return 'the filter ends after "("';
        $c = $s[$i];
        if ( $c === '&' || $c === '|' || $c === '!' )
        {
            $i++;
            $count = 0;
            while ( true )
            {
                while ( $i < $n && $s[$i] === ' ' )
                    $i++;
                if ( $i >= $n )
                    return 'a ")" is missing';
                if ( $s[$i] === ')' )
                    break;
                $error = self::parseFilter( $s, $i, $depth + 1 );
                if ( $error !== null )
                    return $error;
                $count++;
            }
            if ( $count === 0 )
                return "\"($c)\" needs at least one filter";
            if ( $c === '!' && $count !== 1 )
                return '"(!...)" takes exactly one filter';
            $i++;
            return null;
        }
        $end = strpos( $s, ')', $i );
        if ( $end === false )
            return 'a ")" is missing';
        $item = substr( $s, $i, $end - $i );
        if ( strpos( $item, '(' ) !== false )
            return 'a "(" in a value must be written \\28';
        $error = self::checkItem( $item );
        if ( $error !== null )
            return $error;
        $i = $end + 1;
        return null;
    }

    /** @return string|null why a simple filter (attr op value) is not valid */
    protected static function checkItem( $item )
    {
        $attr = '(?:[A-Za-z][A-Za-z0-9-]*(?:;[A-Za-z0-9-]+)*|\d+(?:\.\d+)+)';
        $rule = '(?:[A-Za-z][A-Za-z0-9-]*|\d+(?:\.\d+)+)';
        if ( preg_match( '/^(' . $attr . ')(~=|>=|<=|=)(.*)$/s', $item, $m ) )
        {
            list( , , $op, $value ) = $m;
            if ( $op !== '=' && strpos( $value, '*' ) !== false )
                return "\"$item\": a * only works with = (presence and substrings)";
            if ( $op !== '=' && $value === '' )
                return "\"$item\": a value is expected";
        }
        elseif ( preg_match( '/^(' . $attr . ')?(:dn)?(?::(' . $rule . '))?:=(.*)$/is', $item, $m ) && ( $m[1] !== '' || ( isset( $m[3] ) && $m[3] !== '' ) ) )
        {
            $value = $m[4];
            if ( strpos( $value, '*' ) !== false )
                return "\"$item\": an extensible match takes no *";
        }
        else
            return "\"$item\" is not attribute=value (or ~=, >=, <=, :rule:=)";
        // escapes: a backslash and two hexadecimal digits
        if ( preg_match( '/\\\\(?![0-9A-Fa-f]{2})/', $value ) )
            return "\"$item\": a \\ must be followed by two hexadecimal digits (\\5c for a backslash)";
        return null;
    }

    /** @return string|null why an attribute name of the list is not valid */
    public static function checkAttribute( $name )
    {
        if ( preg_match( '/^(?:[A-Za-z][A-Za-z0-9-]*(?:;[A-Za-z0-9-]+)*|\d+(?:\.\d+)+|\*|\+)$/', (string)$name ) )
            return null;
        return "the attribute name '$name' is not valid";
    }

    /**
     * The server of the options, checked: scheme, host, port and whether the connection is encrypted.
     *
     * @param array $options
     * @return array the list ( string uri, string host, bool starttls )
     * @throws expImportSourceException
     */
    public static function server( array $options )
    {
        $given = isset( $options['uri'] ) ? trim( (string)$options['uri'] ) : '';
        if ( $given === '' )
            throw new expImportSourceException( 'LDAP: the server is missing' );
        if ( !preg_match( '~^(?:(ldaps?)://)?(\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9._-]+)(?::(\d{1,5}))?/?$~i', $given, $m ) )
            throw new expImportSourceException( 'LDAP: the server must be ldaps://host[:port] or ldap://host[:port] (one server, nothing after it)' );
        $scheme = $m[1] !== '' ? strtolower( $m[1] ) : 'ldaps';
        $host = $m[2];
        $port = isset( $m[3] ) && $m[3] !== '' ? (int)$m[3] : ( $scheme === 'ldaps' ? 636 : 389 );
        if ( $port < 1 || $port > 65535 )
            throw new expImportSourceException( 'LDAP: the port is not valid' );
        $bare = trim( $host, '[]' );
        $starttls = false;
        if ( $scheme === 'ldap' )
        {
            $starttls = !isset( $options['starttls'] ) || !empty( $options['starttls'] );
            $local = in_array( strtolower( $bare ), array( 'localhost', '127.0.0.1', '::1' ), true );
            if ( !$starttls && !( $local && !empty( $options['allow_plain'] ) ) )
                throw new expImportSourceException( 'LDAP: the connection must be encrypted: use ldaps:// or StartTLS (an unencrypted connection only to localhost, when the import allows it)' );
        }
        $refusal = expImportSourceFiles::refuseHost( $bare, (array)expImport::setting( 'LdapSourceSettings', 'AllowedHosts', array() ) );
        if ( $refusal !== null )
            throw new expImportSourceException( 'LDAP: ' . $refusal . ' (or list it in sqliimport.ini [LdapSourceSettings] AllowedHosts[])' );
        return array( $scheme . '://' . $host . ':' . $port, $bare, $starttls );
    }

    /** @return string|null why a DN is not usable (empty, control characters, no attribute=value) */
    protected static function checkDn( $dn, $what )
    {
        if ( preg_match( '/[\x00-\x1F\x7F]/', (string)$dn ) )
            return "LDAP: the $what has control characters";
        if ( strpos( (string)$dn, '=' ) === false && !( $what === 'bind DN' && preg_match( '/^[^@\s]+@[A-Za-z0-9.-]+$/', (string)$dn ) ) )
            return "LDAP: the $what is not a DN (attribute=value,...)";
        return null;
    }

    // ---- reading -----------------------------------------------------------------------------------------------------

    /** @return expImportLdapTransport the transport of the extension ldap, or the one of a test */
    public static function transport()
    {
        if ( self::$transportFactory )
            return call_user_func( self::$transportFactory );
        return new expImportLdapExtension();
    }

    /**
     * Searches and writes the entries into the snapshot, one JSON object per line.
     *
     * @param array $options the validated options of the import
     * @param string $target the snapshot file
     * @param int $limit the most entries (the editor's sample); 0: all
     * @throws expImportSourceException
     */
    public static function fetch( array $options, $target, $limit )
    {
        $tmp = $target . '.part';
        $out = fopen( $tmp, 'wb' );
        if ( !$out )
            throw new expImportSourceException( 'LDAP: the snapshot cannot be written' );
        try
        {
            self::entries( $options, (int)$limit, function ( array $record ) use ( $out )
            {
                fwrite( $out, (string)json_encode( $record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) . "\n" );
            } );
            fclose( $out );
            $out = null;
            if ( !rename( $tmp, $target ) )
                throw new expImportSourceException( 'LDAP: the snapshot cannot be written' );
        }
        finally
        {
            if ( $out )
                fclose( $out );
            if ( is_file( $tmp ) )
                @unlink( $tmp );
        }
    }

    /**
     * The editor's "Test connection": connects, binds, reads the first entry and reports its fields, or why it failed
     * (never a password). Nothing is stored.
     *
     * @param array $options the validated options of the import
     * @return array array( 'ok' => bool, 'columns' => string[], 'rows' => int, 'driver' => string, 'message' => string )
     */
    public static function test( array $options )
    {
        $first = null;
        try
        {
            self::entries( array_merge( $options, array( 'page_size' => 0 ) ), 1, function ( array $record ) use ( &$first )
            {
                if ( $first === null )
                    $first = $record;
            } );
            if ( $first === null )
                return array( 'ok' => true, 'columns' => array(), 'rows' => 0, 'driver' => 'ldap', 'message' => 'connected and bound; the search finds no entry' );
            return array( 'ok' => true, 'columns' => array_map( 'strval', array_keys( $first ) ), 'rows' => 1, 'driver' => 'ldap',
                          'message' => 'connected and bound, the first entry has these fields' );
        }
        catch ( expImportSourceException $e )
        {
            return array( 'ok' => false, 'columns' => array(), 'rows' => 0, 'driver' => 'ldap', 'message' => $e->getMessage() );
        }
    }

    /**
     * Connects, binds, searches and hands each entry as a record to $each.
     *
     * @param array $options
     * @param int $limit the most entries; 0: all (or the option size_limit)
     * @param callable $each function ( array $record )
     * @return int the number of entries
     * @throws expImportSourceException
     */
    protected static function entries( array $options, $limit, callable $each )
    {
        list( $uri, , $starttls ) = self::server( $options );
        $base = isset( $options['base_dn'] ) ? trim( (string)$options['base_dn'] ) : '';
        if ( $base === '' )
            throw new expImportSourceException( 'LDAP: the base DN is missing' );
        if ( ( $error = self::checkDn( $base, 'base DN' ) ) !== null )
            throw new expImportSourceException( $error );
        $filter = isset( $options['filter'] ) && trim( (string)$options['filter'] ) !== '' ? (string)$options['filter'] : '(objectClass=*)';
        if ( ( $error = self::checkFilter( $filter ) ) !== null )
            throw new expImportSourceException( 'LDAP: ' . $error );
        $filter = self::normalizeFilter( $filter );
        $attributes = array();
        foreach ( isset( $options['attributes'] ) ? (array)$options['attributes'] : array() as $name )
        {
            $name = trim( (string)$name );
            if ( $name === '' )
                continue;
            if ( ( $error = self::checkAttribute( $name ) ) !== null )
                throw new expImportSourceException( 'LDAP: ' . $error );
            $attributes[] = $name;
        }
        $scope = isset( $options['scope'] ) && in_array( $options['scope'], array( 'sub', 'one', 'base' ), true ) ? (string)$options['scope'] : 'sub';
        $dn = isset( $options['bind_dn'] ) ? trim( (string)$options['bind_dn'] ) : '';
        $ref = isset( $options['password'] ) ? trim( (string)$options['password'] ) : '';
        if ( $dn !== '' && ( $error = self::checkDn( $dn, 'bind DN' ) ) !== null )
            throw new expImportSourceException( $error );
        if ( $dn !== '' && $ref === '' )
            throw new expImportSourceException( 'LDAP: a bind DN needs the name of the secret with its password' );
        $password = $dn !== '' ? expImportSecrets::required( $ref, 'LDAP password' ) : '';
        $sizeLimit = isset( $options['size_limit'] ) ? max( 0, (int)$options['size_limit'] ) : 0;
        if ( $limit > 0 )
            $sizeLimit = $sizeLimit > 0 ? min( $sizeLimit, $limit ) : $limit;
        $pageSize = isset( $options['page_size'] ) ? max( 0, (int)$options['page_size'] ) : 500;
        $timeout = max( 1, (int)expImport::setting( 'LdapSourceSettings', 'Timeout', 30 ) );
        $transport = self::transport();
        $n = 0;
        try
        {
            $transport->connect( $uri, $timeout );
            if ( $starttls )
                $transport->startTls();
            $transport->bind( $dn !== '' ? $dn : null, $dn !== '' ? $password : null );
            $cookie = '';
            do
            {
                $page = $transport->search( $base, $filter, $attributes, $scope, $sizeLimit, $pageSize, $cookie );
                foreach ( isset( $page['entries'] ) ? (array)$page['entries'] : array() as $entry )
                {
                    $each( self::record( (array)$entry, $options, $attributes ) );
                    $n++;
                    if ( $sizeLimit > 0 && $n >= $sizeLimit )
                        return $n;
                }
                $cookie = isset( $page['cookie'] ) ? (string)$page['cookie'] : '';
            }
            while ( $pageSize > 0 && $cookie !== '' );
            return $n;
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( self::maskText( $e->getMessage(), $ref, $password ) );
        }
        finally
        {
            $transport->close();
        }
    }

    /**
     * An entry as a record: dn and the attributes, binary values as base64:<data> or left out, several values as a
     * list or joined.
     *
     * @param array $entry dn, attributes (name => string[])
     * @param array $options binary, multi_valued, separator
     * @param string[] $requested the attributes asked for (each is in the record, null when the entry has none)
     * @return array
     */
    public static function record( array $entry, array $options = array(), array $requested = array() )
    {
        $skip = isset( $options['binary'] ) && $options['binary'] === 'skip';
        $join = isset( $options['multi_valued'] ) && $options['multi_valued'] === 'join';
        $separator = isset( $options['separator'] ) ? (string)$options['separator'] : '; ';
        $record = array( 'dn' => isset( $entry['dn'] ) ? (string)$entry['dn'] : '' );
        foreach ( $requested as $name )
            if ( $name !== '*' && $name !== '+' && !in_array( explode( ';', strtolower( $name ) )[0], self::SECRET, true ) )
                $record[$name] = null;
        $attributes = isset( $entry['attributes'] ) && is_array( $entry['attributes'] ) ? $entry['attributes'] : array();
        foreach ( $attributes as $name => $values )
        {
            $name = (string)$name;
            if ( in_array( explode( ';', strtolower( $name ) )[0], self::SECRET, true ) )
            {
                unset( $record[$name] );
                continue;
            }
            // the name as it was asked for, when the server writes it in another case
            foreach ( $requested as $asked )
                if ( strcasecmp( $asked, $name ) === 0 )
                    $name = $asked;
            if ( strcasecmp( $name, 'dn' ) === 0 )
                $name = 'attribute_dn';
            $values = array_values( array_map( 'strval', is_array( $values ) ? $values : array( $values ) ) );
            $binary = self::isBinary( $name, $values );
            // the text form of an objectGUID, also when the binary value is left out (a key that stays when a user is renamed)
            if ( strcasecmp( $name, 'objectGUID' ) === 0 && isset( $values[0] ) && strlen( $values[0] ) === 16 )
                $record[$name . '_text'] = self::guid( $values[0] );
            if ( $binary && $skip )
            {
                unset( $record[$name] );
                continue;
            }
            if ( $binary )
                $values = array_map( function ( $v ) { return 'base64:' . base64_encode( $v ); }, $values );
            if ( count( $values ) === 1 )
                $record[$name] = $values[0];
            elseif ( !$values )
                $record[$name] = null;
            else
                $record[$name] = $join ? implode( $separator, $values ) : $values;
        }
        return $record;
    }

    /** @return bool whether an attribute holds bytes: by its name, its ;binary option or a value that is not UTF-8 */
    public static function isBinary( $name, array $values )
    {
        $parts = explode( ';', strtolower( (string)$name ) );
        if ( in_array( $parts[0], self::BINARY, true ) || in_array( 'binary', array_slice( $parts, 1 ), true ) )
            return true;
        foreach ( $values as $value )
            if ( !preg_match( '//u', (string)$value ) || preg_match( '/[\x00-\x08\x0E-\x1F]/', (string)$value ) )
                return true;
        return false;
    }

    /** @return string an objectGUID of Active Directory as text (the first three groups little-endian) */
    public static function guid( $bytes )
    {
        $hex = bin2hex( (string)$bytes );
        $swap = function ( $h ) { return implode( '', array_reverse( str_split( $h, 2 ) ) ); };
        return $swap( substr( $hex, 0, 8 ) ) . '-' . $swap( substr( $hex, 8, 4 ) ) . '-' . $swap( substr( $hex, 12, 4 ) ) . '-'
             . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
    }

    /** @return string a message without the password (by its secret name, and the value itself) */
    protected static function maskText( $text, $ref, $password )
    {
        $text = expImportSecrets::mask( (string)$text, $ref !== '' ? array( $ref ) : array() );
        if ( strlen( (string)$password ) >= 3 )
            $text = str_replace( (string)$password, '[password]', $text );
        return $text;
    }

    // ---- drivers -----------------------------------------------------------------------------------------------------

    /** @return array ldap => bool, paging => bool of this PHP (or of a test) */
    protected static function environment()
    {
        if ( is_array( self::$environment ) )
            return array( 'ldap' => !empty( self::$environment['ldap'] ), 'paging' => !empty( self::$environment['paging'] ) );
        return array( 'ldap' => expImportLdapExtension::available(), 'paging' => expImportLdapExtension::canPage() );
    }

    /**
     * What this PHP can read a directory with (the editor's and the catalogue's driver list), what the host needs
     * without it, and what the job queue's workers last had (recordWorkerPhp()).
     *
     * @return array ldap => array( 'label', 'available' => bool, 'via' => string, 'needs' => string,
     *               'worker' => yes, no or '' (not known), 'worker_php' => string )
     */
    public static function drivers()
    {
        $env = self::environment();
        $worker = self::workerPhp();
        return array(
            'ldap' => array( 'label' => 'LDAP directory', 'available' => $env['ldap'],
                             'via' => $env['ldap'] ? 'ldap' . ( $env['paging'] ? ' (paged results)' : ' (no paged results: PHP 7.3 or later is needed)' ) : '',
                             'needs' => 'the PHP extension ldap (RHEL: php-ldap; Debian: php-ldap; Plesk: the ldap extension of the PHP version)',
                             'worker' => $worker ? ( !empty( $worker['ldap'] ) ? 'yes' : 'no' ) : '',
                             'worker_php' => $worker ? (string)$worker['php'] . ' ' . (string)$worker['version'] : '' ),
        );
    }

    /** @return string the file that holds what the workers' PHP has */
    protected static function workerFile()
    {
        return self::$workerFile !== null ? self::$workerFile : expImport::storageDirectory( 'state' ) . '/ldap-worker-php.json';
    }

    /**
     * Notes the PHP of a job queue worker and whether it has the extension ldap (called by the job handler), so the
     * editor running in another PHP can tell whether runs will work. Written only when it changed, or once a day.
     */
    public static function recordWorkerPhp()
    {
        try
        {
            $file = self::workerFile();
            $now = array( 'php' => PHP_BINARY, 'version' => PHP_VERSION, 'ldap' => self::environment()['ldap'] );
            $old = is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : null;
            if ( is_array( $old ) && isset( $old['at'] ) && array_diff_key( $old, array( 'at' => 1 ) ) == $now && time() - (int)$old['at'] < 86400 )
                return;
            $now['at'] = time();
            file_put_contents( $file . '.part', json_encode( $now, JSON_UNESCAPED_SLASHES ) );
            rename( $file . '.part', $file );
        }
        catch ( Throwable $e )
        {
            // only a hint for the editor
        }
    }

    /** @return array|null what recordWorkerPhp() noted: php, version, ldap, at */
    public static function workerPhp()
    {
        try
        {
            $file = self::workerFile();
        }
        catch ( Throwable $e )
        {
            return null;
        }
        $data = is_file( $file ) ? json_decode( (string)file_get_contents( $file ), true ) : null;
        return is_array( $data ) && isset( $data['php'], $data['version'] ) && array_key_exists( 'ldap', $data ) ? $data : null;
    }
}
