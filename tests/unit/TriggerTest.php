<?php

use PHPUnit\Framework\TestCase;

/**
 * The calls of a trigger in an array (the database store in a request): the unit tests count and replay without a
 * database.
 */
class TriggerTestCallStore implements expImportTriggerCallStore
{
    /** @var array list of array( import, hash, kind, time ) */
    public $calls = array();

    public function add( $importID, $hash, $kind, $time )
    {
        foreach ( $this->calls as $call )
            if ( $call[0] === (int)$importID && $call[1] === (string)$hash )
                return false;
        $this->calls[] = array( (int)$importID, (string)$hash, (string)$kind, (int)$time );
        return true;
    }

    public function count( $importID, $kind, $since )
    {
        $n = 0;
        foreach ( $this->calls as $call )
            if ( $call[0] === (int)$importID && $call[2] === (string)$kind && $call[3] >= (int)$since )
                $n++;
        return $n;
    }

    public function purge( $importID, $before )
    {
        $this->calls = array_values( array_filter( $this->calls, function ( $call ) use ( $importID, $before )
        {
            return $call[0] !== (int)$importID || $call[3] >= (int)$before;
        } ) );
    }
}

/**
 * The webhook / API trigger (expImportTriggerAuth): signatures (good, wrong, expired, replayed, tampered), API keys
 * (format, hash, verification), credentials from the request, the rate limit with a fixed time, the checks of a body
 * that brings the data, and the rules of its admin form and view. No database.
 */
class TriggerTest extends TestCase
{
    const KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    const NOW = 1790000000;

    protected function setUp(): void
    {
        expImportTriggerAuth::$serverKey = self::KEY;
    }

    protected function tearDown(): void
    {
        expImportTriggerAuth::$serverKey = null;
    }

    private function secret(): string
    {
        return expImportTriggerAuth::signingSecret( str_repeat( 'ab', 32 ) );
    }

    private function check( string $secret, ?string $ts, ?string $sig, string $body = '{"a":1}', int $now = self::NOW, string $path = '/sqliimport/trigger/books' ): string
    {
        return expImportTriggerAuth::checkSignature( $secret, 'POST', $path, $ts, $sig, $body, $now, 300 );
    }

    // ---- signatures ---------------------------------------------------------------------------------------------

    public function testAGoodSignatureIsAccepted(): void
    {
        $secret = $this->secret();
        $sig = expImportTriggerAuth::sign( $secret, 'POST', '/sqliimport/trigger/books', (string)self::NOW, '{"a":1}' );
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $sig );
        $this->assertSame( 'ok', $this->check( $secret, (string)self::NOW, $sig ) );
        $this->assertSame( 'ok', $this->check( $secret, (string)self::NOW, 'sha256=' . $sig ), 'the sha256= prefix is accepted' );
        $this->assertSame( 'ok', $this->check( $secret, (string)self::NOW, strtoupper( $sig ) ), 'hex in capitals' );
        // the string that is signed: method, path, timestamp and the SHA-256 of the body, one per line
        $this->assertSame( "POST\n/sqliimport/trigger/books\n" . self::NOW . "\n" . hash( 'sha256', '{"a":1}' ),
                           expImportTriggerAuth::stringToSign( 'post', '/sqliimport/trigger/books', self::NOW, '{"a":1}' ) );
        $this->assertSame( hash_hmac( 'sha256', expImportTriggerAuth::stringToSign( 'POST', '/sqliimport/trigger/books', self::NOW, '{"a":1}' ), $secret ), $sig );
    }

    public function testAWrongSignatureIsRefused(): void
    {
        $secret = $this->secret();
        $sig = expImportTriggerAuth::sign( $secret, 'POST', '/sqliimport/trigger/books', (string)self::NOW, '{"a":1}' );
        $this->assertSame( 'bad', $this->check( expImportTriggerAuth::signingSecret( str_repeat( 'cd', 32 ) ), (string)self::NOW, $sig ), 'another secret' );
        $this->assertSame( 'bad', $this->check( $secret, (string)self::NOW, $sig, '{"a":2}' ), 'another body' );
        $this->assertSame( 'bad', $this->check( $secret, (string)self::NOW, $sig, '{"a":1}', self::NOW, '/sqliimport/trigger/other' ), 'another import' );
        $this->assertSame( 'bad', $this->check( $secret, (string)( self::NOW - 1 ), $sig ), 'another timestamp' );
        $this->assertSame( 'bad', expImportTriggerAuth::checkSignature( $secret, 'GET', '/sqliimport/trigger/books', (string)self::NOW, $sig, '{"a":1}', self::NOW ), 'another method' );
        $this->assertSame( 'bad', $this->check( '', (string)self::NOW, $sig ), 'no secret' );
        $this->assertSame( 'malformed', $this->check( $secret, (string)self::NOW, 'xyz' ) );
        $this->assertSame( 'malformed', $this->check( $secret, 'yesterday', $sig ) );
        $this->assertSame( 'missing', $this->check( $secret, null, $sig ) );
        $this->assertSame( 'missing', $this->check( $secret, (string)self::NOW, '' ) );
    }

    public function testAnExpiredOrFutureTimestampIsRefused(): void
    {
        $secret = $this->secret();
        $old = (string)( self::NOW - 301 );
        $this->assertSame( 'expired', $this->check( $secret, $old, expImportTriggerAuth::sign( $secret, 'POST', '/sqliimport/trigger/books', $old, '{"a":1}' ) ) );
        $future = (string)( self::NOW + 301 );
        $this->assertSame( 'expired', $this->check( $secret, $future, expImportTriggerAuth::sign( $secret, 'POST', '/sqliimport/trigger/books', $future, '{"a":1}' ) ) );
        $edge = (string)( self::NOW - 300 );
        $this->assertSame( 'ok', $this->check( $secret, $edge, expImportTriggerAuth::sign( $secret, 'POST', '/sqliimport/trigger/books', $edge, '{"a":1}' ) ) );
    }

    public function testASignatureCountsOnlyOnce(): void
    {
        $store = new TriggerTestCallStore();
        $sig = expImportTriggerAuth::sign( $this->secret(), 'POST', '/sqliimport/trigger/books', (string)self::NOW, '' );
        $this->assertFalse( expImportTriggerAuth::replayed( $store, 7, $sig, self::NOW ) );
        $this->assertTrue( expImportTriggerAuth::replayed( $store, 7, $sig, self::NOW + 5 ), 'the same signature again is a replay' );
        $this->assertTrue( expImportTriggerAuth::replayed( $store, 7, 'sha256=' . $sig, self::NOW + 5 ), 'also with the prefix' );
        $this->assertFalse( expImportTriggerAuth::replayed( $store, 8, $sig, self::NOW + 5 ), 'another import has its own calls' );
        // the store never keeps the signature itself
        foreach ( $store->calls as $call )
            $this->assertNotSame( $sig, $call[1] );
    }

    public function testTheSigningSecretIsDerivedFromTheServerKeyAndASalt(): void
    {
        $salt = expImportTriggerAuth::newSalt();
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $salt );
        $this->assertNotSame( $salt, expImportTriggerAuth::newSalt() );
        $secret = expImportTriggerAuth::signingSecret( $salt );
        $this->assertSame( $secret, expImportTriggerAuth::signingSecret( $salt ), 'the same secret for the same salt' );
        $this->assertNotSame( $secret, expImportTriggerAuth::signingSecret( expImportTriggerAuth::newSalt() ), 'rotating changes it' );
        $this->assertNotSame( $secret, expImportTriggerAuth::signingSecret( $salt, str_repeat( 'f', 64 ) ), 'another server key changes it' );
        $this->assertStringNotContainsString( $salt, $secret );
        $this->assertSame( '/sqliimport/trigger/books', expImportTriggerAuth::canonicalPath( 'books' ) );
    }

    public function testTheSignatureComesFromTheHeadersOrTheSignedUrl(): void
    {
        $this->assertSame( array( '1', 'abc' ), expImportTriggerAuth::signatureFromRequest(
            array( 'HTTP_X_SQLIIMPORT_TIMESTAMP' => '1', 'HTTP_X_SQLIIMPORT_SIGNATURE' => 'abc' ), array( 'timestamp' => '2', 'signature' => 'def' ) ) );
        $this->assertSame( array( '2', 'def' ), expImportTriggerAuth::signatureFromRequest( array(), array( 'timestamp' => '2', 'signature' => 'def' ) ) );
        $this->assertSame( array( null, null ), expImportTriggerAuth::signatureFromRequest( array(), array( 'timestamp' => array( 'x' ) ) ) );
    }

    // ---- API keys -----------------------------------------------------------------------------------------------

    public function testAKeyIsStoredOnlyAsItsHash(): void
    {
        list( $key, $id ) = expImportTriggerAuth::newKey();
        $this->assertMatchesRegularExpression( expImportTriggerAuth::KEY_PATTERN, $key );
        $this->assertSame( $id, expImportTriggerAuth::keyId( $key ) );
        $hash = expImportTriggerAuth::hashKey( $key );
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $hash );
        $this->assertStringNotContainsString( substr( $key, 17 ), $hash );
        $this->assertNotSame( hash( 'sha256', $key ), $hash, 'peppered, not a plain SHA-256' );
        $this->assertTrue( expImportTriggerAuth::verifyKey( $key, $hash ) );
        list( $other ) = expImportTriggerAuth::newKey();
        $this->assertNotSame( $key, $other );
        $this->assertFalse( expImportTriggerAuth::verifyKey( $other, $hash ), 'another key' );
        $this->assertFalse( expImportTriggerAuth::verifyKey( substr( $key, 0, -1 ) . ( substr( $key, -1 ) === 'A' ? 'B' : 'A' ), $hash ), 'one character changed' );
        $this->assertFalse( expImportTriggerAuth::verifyKey( $key, $hash, str_repeat( 'e', 64 ) ), 'another server key' );
        $this->assertFalse( expImportTriggerAuth::verifyKey( 'not-a-key', expImportTriggerAuth::hashKey( 'not-a-key' ) ), 'a malformed key never passes' );
        $this->assertFalse( expImportTriggerAuth::verifyKey( $key, '' ) );
        $this->assertNull( expImportTriggerAuth::keyId( 'sqk_zz_abc' ) );
    }

    public function testTheKeyComesFromBearerOrXApiKey(): void
    {
        $this->assertSame( 'k1', expImportTriggerAuth::keyFromServer( array( 'HTTP_AUTHORIZATION' => 'Bearer k1', 'HTTP_X_API_KEY' => 'k2' ) ) );
        $this->assertSame( 'k1', expImportTriggerAuth::keyFromServer( array( 'REDIRECT_HTTP_AUTHORIZATION' => 'bearer  k1 ' ) ) );
        $this->assertSame( 'k2', expImportTriggerAuth::keyFromServer( array( 'HTTP_AUTHORIZATION' => 'Basic eDp5', 'HTTP_X_API_KEY' => ' k2 ' ) ) );
        $this->assertNull( expImportTriggerAuth::keyFromServer( array( 'HTTP_AUTHORIZATION' => 'Basic eDp5' ) ) );
        $this->assertNull( expImportTriggerAuth::keyFromServer( array() ) );
    }

    // ---- rate limit ---------------------------------------------------------------------------------------------

    public function testTheRateLimitCountsTheLastMinute(): void
    {
        $store = new TriggerTestCallStore();
        for ( $i = 0; $i < 6; $i++ )
            $store->add( 3, hash( 'sha256', 'call' . $i ), 'trigger', self::NOW + $i );
        $this->assertTrue( expImportTriggerAuth::rateLimited( $store, 3, 'trigger', 6, self::NOW + 30 ) );
        $this->assertFalse( expImportTriggerAuth::rateLimited( $store, 3, 'trigger', 7, self::NOW + 30 ) );
        $this->assertFalse( expImportTriggerAuth::rateLimited( $store, 3, 'status', 6, self::NOW + 30 ), 'each kind has its own limit' );
        $this->assertFalse( expImportTriggerAuth::rateLimited( $store, 4, 'trigger', 6, self::NOW + 30 ), 'each import has its own limit' );
        $this->assertTrue( expImportTriggerAuth::rateLimited( $store, 3, 'trigger', 6, self::NOW + 59 ) );
        $this->assertFalse( expImportTriggerAuth::rateLimited( $store, 3, 'trigger', 6, self::NOW + 60 ), 'the first call left the window' );
        $this->assertFalse( expImportTriggerAuth::rateLimited( $store, 3, 'trigger', 0, self::NOW ), '0: no limit' );
        $store->purge( 3, self::NOW + 3 );
        $this->assertSame( 3, $store->count( 3, 'trigger', 0 ) );
    }

    // ---- data in the body ---------------------------------------------------------------------------------------

    public function testABodyMustFitTheFormatAndTheLimit(): void
    {
        $csv = array( 'csv', 'tsv', 'txt' );
        $this->assertSame( array( 0, 'csv' ), expImportTriggerAuth::checkBody( 100, 1000, true, 'text/csv; charset=utf-8', $csv ) );
        $this->assertSame( 413, expImportTriggerAuth::checkBody( 1001, 1000, true, 'text/csv', $csv )[0] );
        $this->assertSame( 415, expImportTriggerAuth::checkBody( 100, 1000, false, 'text/csv', $csv )[0], 'the trigger takes no data' );
        $this->assertSame( 415, expImportTriggerAuth::checkBody( 100, 1000, true, 'application/json', $csv )[0], 'not the import\'s format' );
        $this->assertSame( 415, expImportTriggerAuth::checkBody( 100, 1000, true, 'multipart/form-data; boundary=x', $csv )[0] );
        $this->assertSame( 415, expImportTriggerAuth::checkBody( 100, 1000, true, '', $csv )[0], 'no content type' );
        $json = array( 'json', 'jsonl', 'ndjson' );
        $this->assertSame( 'json', expImportTriggerAuth::bodyExtension( 'application/json', $json ) );
        $this->assertSame( 'ndjson', expImportTriggerAuth::bodyExtension( 'application/x-ndjson', $json ) );
        $this->assertSame( 'jsonl', expImportTriggerAuth::bodyExtension( 'application/jsonl', $json ) );
        $this->assertSame( 'jsonl', expImportTriggerAuth::bodyExtension( 'application/x-ndjson', array( 'json', 'jsonl' ) ) );
        $this->assertSame( 'xml', expImportTriggerAuth::bodyExtension( 'application/rss+xml', array( 'xml', 'rdf' ) ) );
        // X-Sqliimport-Format names the extension (a spreadsheet, a format without a content type of its own)
        $this->assertSame( 'xlsx', expImportTriggerAuth::bodyExtension( 'application/octet-stream', array( 'xlsx', 'ods' ), 'XLSX' ) );
        $this->assertNull( expImportTriggerAuth::bodyExtension( 'text/csv', $csv, 'php' ), 'only an extension of the format' );
        $this->assertNull( expImportTriggerAuth::bodyExtension( 'text/csv', $csv, '../x' ) );
    }

    public function testOnlyAnImportThatReadsAFileTakesData(): void
    {
        $csv = array( 'extensions' => array( 'csv' ), 'origins' => array( 'upload', 'url', 'path' ) );
        $remote = array( 'extensions' => array( 'ndjson' ), 'origins' => array( 'remote' ) );
        $this->assertTrue( expImportTriggerAuth::takesData( $csv, array( 'origin' => 'url' ) ) );
        $this->assertTrue( expImportTriggerAuth::takesData( $csv, array() ) );
        $this->assertFalse( expImportTriggerAuth::takesData( $csv, array( 'origin' => 'remote' ) ) );
        $this->assertFalse( expImportTriggerAuth::takesData( $remote, array( 'origin' => 'remote' ) ), 'a database source reads itself' );
        $this->assertFalse( expImportTriggerAuth::takesData( $csv, array(), true ), 'a 1.x handler reads itself' );
        $this->assertFalse( expImportTriggerAuth::takesData( null, array() ) );
    }

    public function testAnErrorAnswerHasNoDetailsOfTheImport(): void
    {
        $answer = expImportTriggerEndpoint::answer( 429, 'rate_limited', array(), array( 'Retry-After' => '60' ) );
        $this->assertSame( array( 'status' => 429, 'body' => array( 'error' => 'rate_limited' ), 'headers' => array( 'Retry-After' => '60' ) ), $answer );
    }

    // ---- schema, view and form -------------------------------------------------------------------------------------

    public function testTheTriggerHasItsTables(): void
    {
        $schema = expImportSchema::definition();
        foreach ( array( 'sqliimport_trigger', 'sqliimport_trigger_key', 'sqliimport_trigger_call' ) as $table )
            $this->assertArrayHasKey( $table, $schema );
        $this->assertSame( 'unique', $schema['sqliimport_trigger']['indexes']['sqliimport_trig_import']['type'] );
        $this->assertSame( array( 'import_id', 'call_hash' ), $schema['sqliimport_trigger_call']['indexes']['sqliimport_tcall_hash']['fields'] );
        $this->assertSame( 'unique', $schema['sqliimport_trigger_key']['indexes']['sqliimport_tkey_id']['type'] );
        // a key is never stored, only its hash
        $this->assertArrayNotHasKey( 'key', $schema['sqliimport_trigger_key']['fields'] );
        $this->assertArrayNotHasKey( 'secret', $schema['sqliimport_trigger']['fields'] );
        foreach ( array( 'mysql', 'postgresql', 'sqlite', 'oracle' ) as $engine )
            $this->assertStringContainsString( 'sqliimport_trigger_key', (string)file_get_contents( dirname( __DIR__, 2 ) . "/sql/$engine/schema.sql" ), $engine );
    }

    public function testTheViewNeedsNoSessionAndNoPolicy(): void
    {
        $root = dirname( __DIR__, 2 );
        $Module = $ViewList = $FunctionList = null;
        include $root . '/modules/sqliimport/module.php';
        $this->assertArrayHasKey( 'trigger', $ViewList );
        $this->assertArrayNotHasKey( 'functions', $ViewList['trigger'] );
        $site = (string)file_get_contents( $root . '/settings/site.ini.append.php' );
        $this->assertStringContainsString( 'PolicyOmitList[]=sqliimport/trigger', $site );
        $this->assertStringContainsString( 'AnonymousAccessList[]=sqliimport/trigger', $site );
        $view = (string)file_get_contents( $root . '/modules/sqliimport/trigger.php' );
        $this->assertStringContainsString( 'session_write_close', $view );
        $this->assertStringNotContainsString( 'Access-Control-Allow', $view, 'no CORS header' );
        $this->assertStringContainsString( 'Cache-Control: no-store', $view );
    }

    public function testTheTriggerFormShowsASecretOnlyOnce(): void
    {
        $tpl = (string)file_get_contents( dirname( __DIR__, 2 ) . '/design/admin/templates/sqliimport/edit/trigger.tpl' );
        $source = preg_replace( '/\{\*.*?\*\}/s', '', $tpl );
        $this->assertDoesNotMatchRegularExpression( '/<style\b|\sstyle\s*=|<script\b|\son[a-z]+\s*=/i', $source );
        // every action is a POST of the schedule step with its own TriggerAction
        preg_match_all( '/<form\b[^>]*>/', $source, $forms );
        $this->assertCount( substr_count( $source, 'name="TriggerAction"' ), $forms[0] );
        foreach ( $forms[0] as $form )
            $this->assertStringContainsString( 'method="post"', $form );
        $this->assertSame( 4, substr_count( $source, 'name="Step" value="schedule"' ) );
        // the only secret printed is the one revealed in this answer; the stored trigger has none to print
        $this->assertSame( 1, preg_match_all( '/\$trigger_revealed\.value/', $source ) );
        $this->assertDoesNotMatchRegularExpression( '/\$trigger\.(sign_salt|key_hash|secret)\b/', $source );
        // rotating and revoking ask first
        $this->assertSame( 2, substr_count( $source, 'data-a4-confirm=' ) );
    }
}
