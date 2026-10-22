<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ImapSourceTestDoubles.php';

/**
 * The IMAP source without a mail server: the message parser on fixture messages (MIME headers, charsets, transfer
 * encodings, attachments), fetch() with a transport that answers from fixtures (read-only unless asked, the newest
 * messages for a sample, the password masked), and the client of this extension against a scripted server.
 */
class ImapSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-imaptest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
    }

    protected function tearDown(): void
    {
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        putenv( 'SQ688_IMAP_TEST_PASSWORD' );
    }

    protected static function fixture( $name )
    {
        return (string)file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $name );
    }

    public function testHeaderDecoding(): void
    {
        $cases = array(
            '=?ISO-8859-1?Q?J=FCrgen_M=FCller?=' => 'Jürgen Müller',
            '=?UTF-8?B?VGVlLcOcYmVyc2ljaHQgMjAyNg==?=' => 'Tee-Übersicht 2026',
            '=?utf-8?q?a?= =?utf-8?q?b?=' => 'ab',
            'Plain =?utf-8?q?=C3=A4?= text' => 'Plain ä text',
            '=?UTF-8*de?Q?Gr=C3=BC=C3=9Fe?=' => 'Grüße',
            '=?windows-1252?Q?=80_5?=' => '€ 5',
            "Caf\xE9" => 'Café',
            'Already UTF-8: Grüße' => 'Already UTF-8: Grüße',
            '' => '',
        );
        foreach ( $cases as $in => $out )
            $this->assertSame( $out, expImportMailMessage::decodeHeader( (string)$in ), (string)$in );
        $this->assertSame( array( 'attachment', array( 'filename' => 'Grüntee Liste.pdf' ) ),
                           expImportMailMessage::parameters( "attachment; filename*0*=utf-8''Gr%C3%BCntee%20; filename*1=\"Liste.pdf\"" ) );
        $this->assertSame( '2026-10-13T09:15:00+02:00', expImportMailMessage::date( 'Tue, 13 Oct 2026 09:15:00 +0200 (CEST)' ) );
        $this->assertSame( '', expImportMailMessage::date( 'not a date at all' ) );
    }

    public function testAddressLists(): void
    {
        $this->assertSame( array( array( 'name' => 'Tea Team, Sales', 'address' => 'sales@example.net' ),
                                  array( 'name' => 'Info desk', 'address' => 'info@example.net' ) ),
                           expImportMailMessage::addresses( '"Tea Team, Sales" <sales@example.net>, info@example.net (Info desk)' ) );
        $this->assertSame( array( array( 'name' => '', 'address' => 'anna@example.com' ), array( 'name' => '', 'address' => 'ben@example.com' ) ),
                           expImportMailMessage::addresses( 'Readers: anna@example.com, ben@example.com;' ) );
        $this->assertSame( array(), expImportMailMessage::addresses( 'undisclosed-recipients:;' ) );
        $this->assertSame( array( array( 'name' => 'Jürgen Müller', 'address' => 'press@example.org' ) ),
                           expImportMailMessage::addresses( '=?ISO-8859-1?Q?J=FCrgen_M=FCller?= <press@example.org>' ) );
    }

    public function testAMultipartMessage(): void
    {
        foreach ( array( 'LF' => self::fixture( 'mail-multipart.eml' ),
                         'CRLF' => str_replace( "\n", "\r\n", self::fixture( 'mail-multipart.eml' ) ) ) as $endings => $raw )
        {
            $m = expImportMailMessage::parse( $raw );
            $this->assertSame( '20261013091500.4711@example.org', $m['message_id'], $endings );
            $this->assertSame( '20261012080000.1000@example.net', $m['in_reply_to'] );
            $this->assertSame( array( 'name' => 'Jürgen Müller', 'address' => 'press@example.org' ), $m['from'] );
            $this->assertSame( 'sales@example.net', $m['to'][0]['address'] );
            $this->assertCount( 2, $m['to'] );
            $this->assertCount( 2, $m['cc'] );
            $this->assertSame( 'Tee-Übersicht 2026 und Preise', $m['subject'], $endings );
            $this->assertSame( '2026-10-13T09:15:00+02:00', $m['date'] );
            $this->assertStringStartsWith( 'Grüße aus dem Teehaus. Die neue Sorte ist da und sehr beliebt.', $m['text'], $endings );
            $this->assertStringContainsString( 'Ein zweiter Absatz', $m['text'] );
            $this->assertSame( 'Grüße aus dem Teehaus. Die neue Sorte ist da und sehr beliebt. Zweite Zeile des ersten Absatzes.', $m['summary'], $endings );
            $this->assertStringContainsString( '<p>Grüße aus dem <b>Teehaus</b>.</p>', $m['html'] );
            $this->assertSame( $m['html'], $m['body'], 'the body is the HTML when there is one' );
            $this->assertSame( array( array( 'name' => 'Preisliste Grüntee.pdf', 'mime' => 'application/pdf', 'size' => 9 ),
                                      array( 'name' => 'Teeé logo.png', 'mime' => 'image/png', 'size' => 8 ) ), $m['attachments'], $endings );
            $this->assertSame( 2, $m['attachment_count'] );
            $this->assertStringNotContainsString( 'JVBERi', json_encode( $m ), 'no attachment content in the record' );
        }
    }

    public function testAnHtmlOnlyMessage(): void
    {
        $m = expImportMailMessage::parse( self::fixture( 'mail-html-only.eml' ) );
        $this->assertSame( 'Plain subject with ä in the middle', $m['subject'] );
        $this->assertSame( array( 'name' => '', 'address' => 'newsletter@example.com' ), $m['from'] );
        $this->assertSame( array(), $m['to'] );
        $this->assertSame( "Autumn teas\n\nFirst line\nsecond line\n\nÜbersicht & mehr", $m['text'] );
        $this->assertSame( 'Autumn teas', $m['summary'] );
        $this->assertStringStartsWith( 'sha1-', $m['message_id'], 'a message without Message-ID gets one from its content' );
        $this->assertSame( $m['message_id'], expImportMailMessage::parse( self::fixture( 'mail-html-only.eml' ) )['message_id'], 'the same every time' );
        $this->assertSame( "<p>a &amp; b</p>\n<p>c<br>\nd</p>", expImportMailMessage::textToHtml( "a & b\n\nc\nd" ) );
    }

    public function testFetchWritesTheSnapshotWithoutChangingTheMailbox(): void
    {
        putenv( 'SQ688_IMAP_TEST_PASSWORD=imap-test-pass' );
        $transport = new ImapSourceTestTransport( array( 101 => self::fixture( 'mail-multipart.eml' ), 102 => self::fixture( 'mail-html-only.eml' ) ) );
        $target = $this->dir . '/source.ndjson';
        $options = array( 'host' => 'imap.example.org', 'port' => 993, 'encryption' => 'ssl', 'user' => 'press@example.org',
                          'password' => 'env:SQ688_IMAP_TEST_PASSWORD', 'mailbox' => 'INBOX', 'search' => 'UNSEEN', 'days' => 0,
                          'mark_seen' => false, 'attachments' => true, 'key' => 'message_id' );
        expImportImapSource::fetchWith( $transport, $options, $target, 0 );
        $this->assertSame( array( 'imap.example.org', 993, 'ssl', 'press@example.org', 'imap-test-pass', 'INBOX', true ), $transport->opened, 'opened read-only' );
        $this->assertSame( array( 'UNSEEN' ), $transport->searches );
        $this->assertSame( array(), $transport->seen );
        $this->assertTrue( $transport->closed );
        $source = new expImportImapSource( array( 'file' => $target, 'key' => 'message_id' ) );
        $records = array();
        foreach ( $source->records() as $record )
            $records[] = $record;
        $this->assertCount( 2, $records );
        $this->assertSame( '20261013091500.4711@example.org', $records[0]->ref );
        $this->assertSame( 101, $records[0]->data['uid'] );
        $this->assertSame( 'Jürgen Müller', $records[0]->data['from']['name'] );
        $this->assertSame( 'Preisliste Grüntee.pdf', $records[0]->data['attachments'][0]['name'] );
        $this->assertFalse( $records[0]->data['truncated'] );

        // the sample of the editor: the newest messages; marking as seen only when asked; the days as SINCE
        $transport = new ImapSourceTestTransport( array( 101 => self::fixture( 'mail-multipart.eml' ), 102 => self::fixture( 'mail-html-only.eml' ) ) );
        expImportImapSource::fetchWith( $transport, array( 'mark_seen' => true, 'days' => 7, 'attachments' => false, 'search' => '' ) + $options, $target, 1 );
        $lines = file( $target, FILE_IGNORE_NEW_LINES );
        $this->assertCount( 1, $lines );
        $last = json_decode( $lines[0], true );
        $this->assertSame( 102, $last['uid'] );
        $this->assertArrayNotHasKey( 'attachments', $last );
        $this->assertFalse( $transport->opened[6], 'opened for writing only to mark as seen' );
        $this->assertSame( array( 102 ), $transport->seen );
        $this->assertMatchesRegularExpression( '/^ALL SINCE \d{1,2}-[A-Z][a-z]{2}-\d{4}$/', $transport->searches[0] );
    }

    public function testErrorsNeverShowThePassword(): void
    {
        putenv( 'SQ688_IMAP_TEST_PASSWORD=imap-test-pass' );
        $transport = new ImapSourceTestTransport( array() );
        $transport->failOpen = 'IMAP: login failed for press@example.org with imap-test-pass';
        try
        {
            expImportImapSource::fetchWith( $transport, array( 'host' => 'imap.example.org', 'user' => 'press@example.org',
                                                               'password' => 'env:SQ688_IMAP_TEST_PASSWORD' ), $this->dir . '/source.ndjson', 0 );
            $this->fail( 'the error reaches the caller' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringNotContainsString( 'imap-test-pass', $e->getMessage() );
            $this->assertStringContainsString( '[secret env:SQ688_IMAP_TEST_PASSWORD]', $e->getMessage() );
        }
        $this->assertTrue( $transport->closed );
        $this->assertFileDoesNotExist( $this->dir . '/source.ndjson.part' );
        // a missing secret is named, and nothing connects
        $transport = new ImapSourceTestTransport( array() );
        try
        {
            expImportImapSource::fetchWith( $transport, array( 'host' => 'imap.example.org', 'password' => 'sq688_no_such_secret' ), $this->dir . '/source.ndjson', 0 );
            $this->fail( 'a missing secret is an error' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( "secret 'sq688_no_such_secret'", $e->getMessage() );
        }
        $this->assertNull( $transport->opened );
        // a server in a private network
        try
        {
            expImportImapSource::fetch( array( 'host' => '192.168.1.5', 'password' => 'x' ), $this->dir . '/source.ndjson', 0 );
            $this->fail( 'a private host is refused' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'ImapSourceSettings', $e->getMessage() );
        }
    }

    public function testTheClientSpeaksImap(): void
    {
        $raw = str_replace( "\n", "\r\n", self::fixture( 'mail-html-only.eml' ) );
        $client = new ImapSourceTestScriptedClient( array(
            '* OK IMAP4rev1 ready',
            'A001' => array( 'A001 OK LOGIN completed' ),
            'A002' => array( '* 3 EXISTS', '* OK [READ-ONLY] examined', 'A002 OK [READ-ONLY] EXAMINE completed' ),
            'A003' => array( '* SEARCH 7 3 12', 'A003 OK SEARCH completed' ),
            'A004' => array( '* 2 FETCH (UID 12 RFC822.SIZE ' . strlen( $raw ) . ')', 'A004 OK' ),
            'A005' => array( '* 2 FETCH (UID 12 BODY[] {' . strlen( $raw ) . '}', $raw, ')', 'A005 OK FETCH completed' ),
            'A006' => array( 'A006 BAD unknown mailbox "Sent"' ),
            'A007' => array( '* BYE', 'A007 OK LOGOUT completed' ),
        ) );
        $client->open( 'imap.example.org', 993, 'ssl', 'press@example.org', 'pa"ss', 'INBOX', true );
        $this->assertSame( array( 'A001 LOGIN "press@example.org" "pa\\"ss"', 'A002 EXAMINE "INBOX"' ), array_slice( $client->sent, 0, 2 ), 'quoted, read-only' );
        $this->assertSame( array( 3, 7, 12 ), $client->search( 'UNSEEN' ) );
        $this->assertSame( strlen( $raw ), $client->size( 12 ) );
        $this->assertSame( $raw, $client->message( 12 ), 'the literal is read byte for byte' );
        try
        {
            $client->command( 'SELECT "Sent"' );
            $this->fail( 'a BAD answer is an error' );
        }
        catch ( expImportSourceException $e )
        {
            $this->assertStringContainsString( 'unknown mailbox', $e->getMessage() );
        }
        $client->close();
        $this->assertSame( array( 'A003 UID SEARCH UNSEEN', 'A004 UID FETCH 12 (RFC822.SIZE)', 'A005 UID FETCH 12 (BODY.PEEK[])', 'A006 SELECT "Sent"', 'A007 LOGOUT' ),
                           array_slice( $client->sent, 2 ) );
        foreach ( array( "ALL\r\nA9 DELETE INBOX", 'TEXT {5}', "UNS\0EEN", "FROM \"J\xFCrgen\"" ) as $bad )
        {
            try
            {
                expImportImapClient::criteria( $bad );
                $this->fail( 'refused: ' . $bad );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( 'printable ASCII', $e->getMessage() );
            }
        }
        $this->assertSame( 'Entw&APw-rfe', expImportImapClient::mailboxName( 'Entwürfe' ) );
        $this->assertSame( 'A&-B', expImportImapClient::mailboxName( 'A&B' ) );
    }
}
