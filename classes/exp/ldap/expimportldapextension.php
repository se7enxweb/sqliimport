<?php
/**
 * File containing the expImportLdapExtension class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The LDAP transport of the PHP extension ldap: LDAPv3, referrals not followed, the certificate of ldaps:// and
 * StartTLS demanded, searches with ldap_search (sub), ldap_list (one) or ldap_read (base) and, for a page size, the
 * paged results control (RFC 2696) through the controls parameter of PHP 7.3 and later. Only searches are made.
 */
class expImportLdapExtension implements expImportLdapTransport
{
    /** @var resource|object|null the connection (LDAP\Connection in PHP 8.1 and later) */
    protected $link = null;

    /** @var int */
    protected $timeout = 30;

    /** @return bool whether this PHP has the extension ldap */
    public static function available()
    {
        return function_exists( 'ldap_connect' );
    }

    /** @return bool whether this PHP can page a search (the controls of ldap_search, PHP 7.3 and later) */
    public static function canPage()
    {
        return self::available() && defined( 'LDAP_CONTROL_PAGEDRESULTS' ) && function_exists( 'ldap_parse_result' ) && PHP_VERSION_ID >= 70300;
    }

    public function connect( $uri, $timeout )
    {
        if ( !self::available() )
            throw new expImportSourceException( 'LDAP: this PHP has no extension ldap' );
        $this->timeout = max( 1, (int)$timeout );
        // the certificate is checked: before connecting for ldaps://, and on a new TLS context of this connection
        if ( defined( 'LDAP_OPT_X_TLS_REQUIRE_CERT' ) && defined( 'LDAP_OPT_X_TLS_DEMAND' ) )
            @ldap_set_option( null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND );
        $link = @ldap_connect( $uri );
        if ( !$link )
            throw new expImportSourceException( 'LDAP: the address cannot be used' );
        $this->link = $link;
        @ldap_set_option( $link, LDAP_OPT_PROTOCOL_VERSION, 3 );
        @ldap_set_option( $link, LDAP_OPT_REFERRALS, 0 );
        if ( defined( 'LDAP_OPT_NETWORK_TIMEOUT' ) )
            @ldap_set_option( $link, LDAP_OPT_NETWORK_TIMEOUT, $this->timeout );
        @ldap_set_option( $link, LDAP_OPT_TIMELIMIT, $this->timeout );
        if ( defined( 'LDAP_OPT_X_TLS_REQUIRE_CERT' ) && defined( 'LDAP_OPT_X_TLS_DEMAND' ) )
        {
            @ldap_set_option( $link, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND );
            if ( defined( 'LDAP_OPT_X_TLS_NEWCTX' ) )
                @ldap_set_option( $link, LDAP_OPT_X_TLS_NEWCTX, 0 );
        }
    }

    public function startTls()
    {
        if ( !@ldap_start_tls( $this->link ) )
            throw $this->error( 'StartTLS failed' );
    }

    public function bind( $dn, $password )
    {
        $ok = $dn === null || $dn === '' ? @ldap_bind( $this->link ) : @ldap_bind( $this->link, (string)$dn, (string)$password );
        if ( !$ok )
            throw $this->error( 'the bind failed' );
    }

    public function search( $base, $filter, array $attributes, $scope, $sizeLimit, $pageSize, $cookie )
    {
        $function = $scope === 'base' ? 'ldap_read' : ( $scope === 'one' ? 'ldap_list' : 'ldap_search' );
        $controls = array();
        if ( $pageSize > 0 && self::canPage() )
            $controls[] = array( 'oid' => LDAP_CONTROL_PAGEDRESULTS, 'iscritical' => false,
                                 'value' => array( 'size' => (int)$pageSize, 'cookie' => (string)$cookie ) );
        $result = $controls
                ? @$function( $this->link, $base, $filter, $attributes, 0, (int)$sizeLimit, $this->timeout, LDAP_DEREF_NEVER, $controls )
                : @$function( $this->link, $base, $filter, $attributes, 0, (int)$sizeLimit, $this->timeout, LDAP_DEREF_NEVER );
        if ( !$result )
        {
            // the size limit reached is not an error (ldap_search gives no result in some versions)
            if ( @ldap_errno( $this->link ) === 4 )
                return array( 'entries' => array(), 'cookie' => '' );
            throw $this->error( 'the search failed' );
        }
        $next = '';
        if ( function_exists( 'ldap_parse_result' ) )
        {
            $code = 0;
            $matched = $message = null;
            $referrals = $server = array();
            if ( @ldap_parse_result( $this->link, $result, $code, $matched, $message, $referrals, $server ) )
            {
                // 4: the size limit was reached, the entries until then are there
                if ( $code !== 0 && $code !== 4 )
                    throw new expImportSourceException( 'LDAP: the search failed: ' . ldap_err2str( $code ) . ( $message ? ' (' . $message . ')' : '' ) );
                if ( $controls && isset( $server[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ) )
                    $next = (string)$server[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'];
            }
        }
        $entries = array();
        for ( $entry = @ldap_first_entry( $this->link, $result ); $entry; $entry = @ldap_next_entry( $this->link, $entry ) )
        {
            $values = array();
            $names = @ldap_get_attributes( $this->link, $entry );
            for ( $i = 0, $n = is_array( $names ) && isset( $names['count'] ) ? (int)$names['count'] : 0; $i < $n; $i++ )
            {
                $name = (string)$names[$i];
                // the values as bytes (binary attributes included)
                $list = @ldap_get_values_len( $this->link, $entry, $name );
                if ( !is_array( $list ) )
                    continue;
                unset( $list['count'] );
                $values[$name] = array_values( array_map( 'strval', $list ) );
            }
            $entries[] = array( 'dn' => (string)@ldap_get_dn( $this->link, $entry ), 'attributes' => $values );
        }
        @ldap_free_result( $result );
        return array( 'entries' => $entries, 'cookie' => $next );
    }

    public function close()
    {
        if ( $this->link )
            @ldap_unbind( $this->link );
        $this->link = null;
    }

    /** @return expImportSourceException the last error of the connection */
    protected function error( $what )
    {
        $text = $this->link ? (string)@ldap_error( $this->link ) : '';
        $extended = '';
        if ( $this->link && defined( 'LDAP_OPT_DIAGNOSTIC_MESSAGE' ) && @ldap_get_option( $this->link, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended ) && is_string( $extended ) && $extended !== '' )
            $text .= ' (' . $extended . ')';
        return new expImportSourceException( 'LDAP: ' . $what . ( $text !== '' ? ': ' . $text : '' ) );
    }
}
