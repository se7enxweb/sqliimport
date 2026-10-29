<?php
/**
 * File containing the expImportCassandraExtension class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The Cassandra/ScyllaDB source's connection through the PHP extension cassandra of DataStax (Cassandra\Cluster,
 * Cassandra\SimpleStatement). Only the import's checked SELECT is executed, with a page size and the paging state
 * token of the previous page; the values are converted to the PHP values of expImportCassandraTransport.
 */
class expImportCassandraExtension implements expImportCassandraTransport
{
    /** @var object|null Cassandra\Session */
    protected $session = null;

    /** @return bool whether this PHP has the extension */
    public static function available()
    {
        return class_exists( 'Cassandra' ) && class_exists( 'Cassandra\SimpleStatement' );
    }

    /** @return string the version of the extension, '' without it */
    public static function version()
    {
        return self::available() ? (string)phpversion( 'cassandra' ) : '';
    }

    public function open( array $hosts, $tls, $user, $password, $keyspace, $timeout )
    {
        if ( !self::available() )
            throw new expImportSourceException( 'Cassandra: this PHP has no cassandra extension' );
        try
        {
            $builder = call_user_func( array( 'Cassandra', 'cluster' ) );
            $points = array();
            $port = 9042;
            foreach ( $hosts as $host )
            {
                $points[] = (string)$host[0];
                $port = (int)$host[1];
            }
            $builder = $builder->withContactPoints( implode( ',', $points ) )->withPort( $port )
                               ->withConnectTimeout( (float)$timeout )->withRequestTimeout( (float)$timeout )->withPersistentSessions( false );
            if ( (string)$user !== '' )
                $builder = $builder->withCredentials( (string)$user, (string)$password );
            if ( $tls )
            {
                $ssl = call_user_func( array( 'Cassandra', 'ssl' ) )->withVerifyFlags( constant( 'Cassandra::VERIFY_PEER_CERT' ) | constant( 'Cassandra::VERIFY_PEER_IDENTITY' ) )->build();
                $builder = $builder->withSSL( $ssl );
            }
            $cluster = $builder->build();
            $this->session = $cluster->connect( (string)$keyspace !== '' ? (string)$keyspace : null );
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( 'Cassandra: ' . $e->getMessage() );
        }
    }

    public function query( $cql, $consistency, $pageSize, $pagingState )
    {
        if ( !$this->session )
            throw new expImportSourceException( 'Cassandra: not connected' );
        if ( !preg_match( '/^\s*SELECT\b/i', (string)$cql ) )
            throw new expImportSourceException( 'Cassandra: only a SELECT is sent' );
        try
        {
            $class = 'Cassandra\SimpleStatement';
            $options = array( 'page_size' => (int)$pageSize, 'consistency' => constant( 'Cassandra::CONSISTENCY_' . $consistency ) );
            if ( $pagingState !== null && $pagingState !== '' )
                $options['paging_state_token'] = (string)$pagingState;
            $rows = $this->session->execute( new $class( (string)$cql ), $options );
            $out = array();
            $columns = array();
            foreach ( $rows as $row )
            {
                $record = array();
                foreach ( (array)$row as $name => $value )
                    $record[(string)$name] = self::value( $value );
                if ( !$columns )
                    $columns = array_keys( $record );
                $out[] = $record;
            }
            $state = $rows->isLastPage() ? null : $rows->pagingStateToken();
            return array( 'columns' => $columns, 'rows' => $out, 'paging_state' => $state === null || $state === '' ? null : (string)$state );
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( 'Cassandra: ' . $e->getMessage() );
        }
    }

    /**
     * A value of the extension as the PHP value of expImportCassandraTransport.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value( $value )
    {
        if ( !is_object( $value ) )
            return $value;
        $class = get_class( $value );
        switch ( $class )
        {
            case 'Cassandra\Bigint':
            case 'Cassandra\Smallint':
            case 'Cassandra\Tinyint':
                $text = (string)$value->value();
                return (string)(int)$text === $text ? (int)$text : $text;
            case 'Cassandra\Float':
                return (float)$value->value();
            case 'Cassandra\Varint':
            case 'Cassandra\Decimal':
            case 'Cassandra\Uuid':
            case 'Cassandra\Timeuuid':
            case 'Cassandra\Inet':
                return (string)$value;
            case 'Cassandra\Blob':
                return base64_encode( (string)$value->toBinaryString() );
            case 'Cassandra\Timestamp':
                $ms = (int)round( (float)$value->microtime( true ) * 1000 );
                $seconds = intdiv( $ms, 1000 );
                $rest = $ms - $seconds * 1000;
                if ( $rest < 0 )
                {
                    $seconds--;
                    $rest += 1000;
                }
                return gmdate( 'Y-m-d\TH:i:s', $seconds ) . ( $rest ? sprintf( '.%03d', $rest ) : '' ) . 'Z';
            case 'Cassandra\Date':
                return gmdate( 'Y-m-d', (int)$value->seconds() );
            case 'Cassandra\Time':
                $ns = (int)(string)$value;
                $seconds = intdiv( $ns, 1000000000 );
                $fraction = $ns % 1000000000;
                return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 )
                     . ( $fraction ? '.' . rtrim( sprintf( '%09d', $fraction ), '0' ) : '' );
            case 'Cassandra\Collection':
            case 'Cassandra\Set':
            case 'Cassandra\Tuple':
                return array_values( array_map( array( __CLASS__, 'value' ), (array)$value->values() ) );
            case 'Cassandra\Map':
                $out = new stdClass();
                $values = (array)$value->values();
                foreach ( array_values( (array)$value->keys() ) as $i => $key )
                {
                    $k = self::value( $key );
                    $out->{is_scalar( $k ) ? (string)$k : (string)json_encode( $k )} = isset( $values[$i] ) ? self::value( $values[$i] ) : null;
                }
                return $out;
            case 'Cassandra\UserTypeValue':
                return array_map( array( __CLASS__, 'value' ), (array)$value->values() );
        }
        return method_exists( $value, '__toString' ) ? (string)$value : null;
    }

    public function driver()
    {
        return 'extension';
    }

    public function close()
    {
        if ( $this->session && method_exists( $this->session, 'close' ) )
        {
            try
            {
                $this->session->close();
            }
            catch ( Exception $e )
            {
            }
        }
        $this->session = null;
    }
}
