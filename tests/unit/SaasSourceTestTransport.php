<?php

/**
 * A scripted network for the tests of the sources that read a hosted service's API (Supabase, Baserow, Salesforce,
 * HubSpot): expImportPlatformHttp::$transport answers the requests in turn and notes them, expImportApiHttp waits on
 * the test's clock.
 */
trait SaasSourceTestTransport
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests made: method, url, headers (lines), body */
    protected $requests = array();

    /** @var float[] the waits asked for */
    protected $slept = array();

    /** @var float the clock of the test */
    protected $now = 1000.0;

    protected function startTransport( string $name ): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-' . $name . 'test-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        expImportApiHttp::reset();
        expImportApiHttp::$sleeper = function ( $seconds )
        {
            $this->slept[] = round( $seconds, 3 );
            $this->now += $seconds;
        };
        expImportApiHttp::$clock = function ()
        {
            return $this->now;
        };
    }

    protected function stopTransport(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportApiHttp::$sleeper = null;
        expImportApiHttp::$clock = null;
        expImportApiHttp::reset();
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
    }

    /**
     * A transport that answers the requests in turn.
     *
     * @param array[] $answers status, body (array: JSON), header lines
     */
    protected function script( array $answers ): void
    {
        $this->requests = array();
        expImportPlatformHttp::$transport = function ( $method, $url, array $headers, $body ) use ( &$answers )
        {
            $this->requests[] = array( $method, $url, $headers, $body );
            $this->now += 0.01;
            $answer = array_shift( $answers );
            if ( $answer === null )
                throw new LogicException( 'more requests than scripted: ' . $url );
            return array( 'status' => $answer[0], 'headers' => isset( $answer[2] ) ? $answer[2] : array(),
                          'body' => is_array( $answer[1] ) ? json_encode( $answer[1] ) : (string)$answer[1] );
        };
    }

    /** @return array[] the records of a snapshot */
    protected function snapshot( string $file ): array
    {
        $out = array();
        foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line )
            $out[] = json_decode( $line, true );
        return $out;
    }

    /** @return string the value of a header line of a request */
    protected function header( array $request, string $name ): string
    {
        foreach ( $request[2] as $line )
            if ( stripos( $line, $name . ':' ) === 0 )
                return trim( substr( $line, strlen( $name ) + 1 ) );
        return '';
    }

    /** Asserts the shape of a test() result and of drivers(). */
    protected function assertResultAndDrivers( array $result, array $drivers ): void
    {
        foreach ( array( 'ok', 'columns', 'rows', 'driver', 'message' ) as $key )
            $this->assertArrayHasKey( $key, $result );
        $this->assertNotEmpty( $drivers );
        foreach ( $drivers as $row )
            foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
                $this->assertArrayHasKey( $key, $row );
    }

    /** Reads an example snapshot like a run's and returns its records. */
    protected function readExample( string $class, array $example ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( call_user_func( array( $class, 'schema' ) ), $example['options'] );
        $this->assertSame( array(), $errors, $example['label'] );
        $source = new $class( $values + array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $example['file'] ) );
        return iterator_to_array( $source->records(), false );
    }
}
