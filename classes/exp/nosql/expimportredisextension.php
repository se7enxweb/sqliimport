<?php
/**
 * File containing the expImportRedisExtension class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The Redis source's connection through the PHP extension redis (phpredis, driver "phpredis"). The commands go out
 * with rawCommand(), so the answers have the same form as those of expImportRedisClient; status answers are read
 * as text (OPT_REPLY_LITERAL). Only AUTH and SELECT (in open()) and the commands of READ_COMMANDS are ever sent.
 */
class expImportRedisExtension implements expImportRedisTransport
{
    /** @var object|null Redis */
    protected $redis = null;

    /** @return bool whether this PHP has the extension */
    public static function available()
    {
        return class_exists( 'Redis' ) && method_exists( 'Redis', 'rawCommand' );
    }

    public function open( $host, $port, $tls, $user, $password, $database, $timeout )
    {
        if ( !self::available() )
            throw new expImportSourceException( 'Redis: this PHP has no redis extension' );
        $class = 'Redis';
        try
        {
            $redis = new $class();
            $address = ( $tls ? 'tls://' : '' ) . ( strpos( (string)$host, ':' ) !== false ? '[' . $host . ']' : (string)$host );
            $context = $tls ? array( 'stream' => array( 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => (string)$host ) ) : array();
            $ok = $context ? $redis->connect( $address, (int)$port, (float)$timeout, null, 0, (float)$timeout, $context )
                           : $redis->connect( $address, (int)$port, (float)$timeout, null, 0, (float)$timeout );
            if ( !$ok )
                throw new expImportSourceException( "Redis: no connection to $host:$port" );
            if ( defined( 'Redis::OPT_REPLY_LITERAL' ) )
                $redis->setOption( (int)constant( 'Redis::OPT_REPLY_LITERAL' ), true );
            $this->redis = $redis;
            if ( (string)$password !== '' )
                $this->raw( (string)$user !== '' ? array( 'AUTH', (string)$user, (string)$password ) : array( 'AUTH', (string)$password ) );
            if ( (int)$database !== 0 )
                $this->raw( array( 'SELECT', (string)(int)$database ) );
        }
        catch ( expImportSourceException $e )
        {
            throw $e;
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( 'Redis: ' . $e->getMessage() );
        }
    }

    public function command( array $args )
    {
        $name = strtoupper( (string)reset( $args ) );
        if ( !in_array( $name, self::READ_COMMANDS, true ) )
            throw new expImportSourceException( "Redis: the command $name is not one of the read-only commands of the source" );
        return $this->raw( $args );
    }

    /**
     * @param array $args
     * @return mixed the answer; a nil answer as null
     * @throws expImportSourceException
     */
    protected function raw( array $args )
    {
        if ( !$this->redis )
            throw new expImportSourceException( 'Redis: not connected' );
        try
        {
            $this->redis->clearLastError();
            $answer = call_user_func_array( array( $this->redis, 'rawCommand' ), array_map( 'strval', $args ) );
            if ( $answer === false )
            {
                $error = $this->redis->getLastError();
                if ( $error !== null && $error !== false && $error !== '' )
                    throw new expImportSourceException( 'Redis: ' . $error );
                return null;
            }
            return $answer;
        }
        catch ( expImportSourceException $e )
        {
            throw $e;
        }
        catch ( Exception $e )
        {
            throw new expImportSourceException( 'Redis: ' . $e->getMessage() );
        }
    }

    public function driver()
    {
        return 'phpredis';
    }

    public function close()
    {
        if ( $this->redis )
        {
            try
            {
                $this->redis->close();
            }
            catch ( Exception $e )
            {
            }
        }
        $this->redis = null;
    }
}
