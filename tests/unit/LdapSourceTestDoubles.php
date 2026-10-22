<?php
/**
 * The test double of LdapSourceTest: a directory in memory that pages its entries like the paged results control.
 */
class LdapSourceTestTransport implements expImportLdapTransport
{
    /** @var array[] the entries: dn, attributes */
    public $entries;

    /** @var string[] what was called, in order */
    public $calls = array();

    /** @var array[] the arguments of every search */
    public $searches = array();

    /** @var string|null an error bind() throws */
    public $failBind = null;

    /** @var bool */
    public $closed = false;

    public function __construct( array $entries )
    {
        $this->entries = $entries;
    }

    public function connect( $uri, $timeout )
    {
        $this->calls[] = 'connect ' . $uri;
    }

    public function startTls()
    {
        $this->calls[] = 'starttls';
    }

    public function bind( $dn, $password )
    {
        $this->calls[] = 'bind ' . ( $dn === null ? '(anonymous)' : $dn . ' ' . $password );
        if ( $this->failBind !== null )
            throw new expImportSourceException( $this->failBind );
    }

    public function search( $base, $filter, array $attributes, $scope, $sizeLimit, $pageSize, $cookie )
    {
        $this->searches[] = array( $base, $filter, $attributes, $scope, $sizeLimit, $pageSize, $cookie );
        $start = $cookie === '' ? 0 : (int)$cookie;
        $size = $pageSize > 0 ? $pageSize : count( $this->entries );
        $page = array_slice( $this->entries, $start, $size );
        $next = $pageSize > 0 && $start + $size < count( $this->entries ) ? (string)( $start + $size ) : '';
        return array( 'entries' => $page, 'cookie' => $next );
    }

    public function close()
    {
        $this->closed = true;
    }
}
