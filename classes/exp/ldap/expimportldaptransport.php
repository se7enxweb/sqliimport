<?php
/**
 * File containing the expImportLdapTransport interface.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What the LDAP source needs of a directory server: connect, StartTLS, bind and search, nothing that writes. The PHP
 * extension ldap is one implementation (expImportLdapExtension); a test gives one that answers without a server.
 * Every method throws expImportSourceException on an error.
 */
interface expImportLdapTransport
{
    /**
     * Opens the connection (LDAPv3, no referrals followed).
     *
     * @param string $uri ldap://host:port or ldaps://host:port
     * @param int $timeout seconds for connecting and for each search
     */
    public function connect( $uri, $timeout );

    /** Starts TLS on an ldap:// connection (the certificate is checked). */
    public function startTls();

    /**
     * Binds as a user, or anonymously.
     *
     * @param string|null $dn null: anonymous
     * @param string|null $password
     */
    public function bind( $dn, $password );

    /**
     * One search (one page of it with the paged results control).
     *
     * @param string $base
     * @param string $filter
     * @param string[] $attributes empty: all user attributes
     * @param string $scope sub, one or base
     * @param int $sizeLimit 0: no limit of the client
     * @param int $pageSize 0: no paged results control
     * @param string $cookie the cookie of the previous page; '' for the first
     * @return array array( 'entries' => array of array( 'dn' => string, 'attributes' => array( name => string[] ) ),
     *               'cookie' => string the cookie of the next page, '' when there is none )
     */
    public function search( $base, $filter, array $attributes, $scope, $sizeLimit, $pageSize, $cookie );

    /** Closes the connection (unbind). */
    public function close();
}
