<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AiTestTransport.php';

/**
 * The AI assist (expImportAi) with a transport double instead of expai: what goes to the model (names and types only,
 * sample values only when the import opts in, and then masked), how answers are read (malformed ones rejected) and
 * checked (unknown attributes, fields, classes and transforms dropped), accepting into the form, the session cache,
 * the dry run that must follow accepted suggestions, and the calls of the expai transport against expai's code.
 */
class AiAssistTest extends TestCase
{
    /** @var AiTestTransport */
    protected $transport;

    protected function setUp(): void
    {
        $this->transport = new AiTestTransport();
        expImportAi::$transport = $this->transport;
        expImportAi::$useSession = false;
        expImportAi::forget();
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'AiSettings', 'Enabled', 'enabled' );
    }

    protected function tearDown(): void
    {
        expImportAi::$transport = null;
        expImportAi::$useSession = null;
        expImportAi::forget();
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'AiSettings', 'Enabled' );
    }

    /** @return array a sample with personal values */
    protected function sample()
    {
        return array(
            'fields' => array( 'id', 'headline', 'contact_mail', 'phone', 'account', 'published' ),
            'records' => array(
                array( 'flat' => array( 'id' => '17', 'headline' => 'Spring opening of the harbour', 'contact_mail' => 'anna.beispiel@example.org',
                                        'phone' => '+49 30 1234567', 'account' => 'DE89 3704 0044 0532 0130 00', 'published' => '2026-03-01' ) ),
                array( 'flat' => array( 'id' => '18', 'headline' => 'Summer market', 'contact_mail' => 'b.muster@example.net',
                                        'phone' => '(030) 765 43 21', 'account' => 'GB82WEST12345698765432', 'published' => '2026-06-15' ) ),
            ),
        );
    }

    /** @return array the attributes of a class */
    protected function attributes()
    {
        return array( array( 'identifier' => 'title', 'name' => 'Title', 'datatype' => 'ezstring' ),
                      array( 'identifier' => 'publish_date', 'name' => 'Publish date', 'datatype' => 'ezdate' ),
                      array( 'identifier' => 'email', 'name' => 'E-mail', 'datatype' => 'ezemail' ) );
    }

    public function testMaskingHidesEmailPhoneAndIbanButKeepsDatesAndShortNumbers(): void
    {
        $this->assertSame( 'write to [email] now', expImportAi::mask( 'write to anna.beispiel@example.org now' ) );
        $this->assertSame( 'call [phone]', expImportAi::mask( 'call +49 30 1234567' ) );
        $this->assertSame( '[phone]', expImportAi::mask( '(030) 765 43 21' ) );
        $this->assertSame( 'IBAN [iban]', expImportAi::mask( 'IBAN DE89 3704 0044 0532 0130 00' ) );
        $this->assertSame( '[iban]', expImportAi::mask( 'GB82WEST12345698765432' ) );
        $this->assertSame( '2026-03-01', expImportAi::mask( '2026-03-01' ) );
        $this->assertSame( '15.01.2026', expImportAi::mask( '15.01.2026' ) );
        $this->assertSame( 'order 4711 of 12 items', expImportAi::mask( 'order 4711 of 12 items' ) );
        $this->assertSame( 'card [phone]', expImportAi::mask( 'card 4111 1111 1111 1111' ) );
    }

    public function testTypesAreDetectedFromTheValues(): void
    {
        $this->assertSame( 'integer', expImportAi::detectType( array( '1', '22', '' ) ) );
        $this->assertSame( 'number', expImportAi::detectType( array( '1', '2,5' ) ) );
        $this->assertSame( 'date', expImportAi::detectType( array( '2026-03-01', '01.02.2026' ) ) );
        $this->assertSame( 'email', expImportAi::detectType( array( 'a@example.org' ) ) );
        $this->assertSame( 'url', expImportAi::detectType( array( 'https://example.org/x' ) ) );
        $this->assertSame( 'boolean', expImportAi::detectType( array( 'yes', 'no' ) ) );
        $this->assertSame( 'empty', expImportAi::detectType( array( '', ' ' ) ) );
        $this->assertSame( 'text', expImportAi::detectType( array( 'abc', '12' ) ) );
    }

    public function testByDefaultOnlyFieldNamesAndTypesAreSent(): void
    {
        $this->transport->answers[] = '{"attribute":"title","source":"headline","transforms":"trim","confidence":0.9,"reason":"same meaning"}';
        $result = expImportAi::suggestMapping( $this->attributes(), $this->sample(), false, array( 'import_id' => 5 ) );
        $text = $this->transport->lastText();
        foreach ( array( 'anna.beispiel', 'example.org', '1234567', 'DE89', 'GB82', 'Spring opening', 'Summer market', '2026-03-01', '[email]' ) as $value )
            $this->assertStringNotContainsString( $value, $text, "no sample value ($value) without the option" );
        $this->assertStringContainsString( '"name":"contact_mail","type":"email"', $text );
        $this->assertStringContainsString( '"name":"published","type":"date"', $text );
        $this->assertStringContainsString( '"identifier":"publish_date","name":"Publish date","datatype":"ezdate"', $text );
        $this->assertFalse( $result['samples'] );
        $this->assertSame( 'mapping', $this->transport->sent[0]['context']['purpose'] );
        $this->assertSame( 5, $this->transport->sent[0]['context']['import_id'] );
    }

    public function testWithTheOptionSamplesAreSentMasked(): void
    {
        $this->transport->answers[] = '{"attribute":"title","source":"headline","transforms":"","confidence":0.9,"reason":"x"}';
        $result = expImportAi::suggestMapping( $this->attributes(), $this->sample(), true, array() );
        $text = $this->transport->lastText();
        $this->assertStringContainsString( 'Spring opening of the harbour', $text, 'plain text values are sent with the option' );
        $this->assertStringContainsString( '[email]', $text );
        $this->assertStringContainsString( '[phone]', $text );
        $this->assertStringContainsString( '[iban]', $text );
        $this->assertStringContainsString( '2026-03-01', $text );
        foreach ( array( 'anna.beispiel', 'b.muster', '1234567', '765 43 21', 'DE89', 'GB82WEST' ) as $value )
            $this->assertStringNotContainsString( $value, $text, "$value is masked" );
        $this->assertTrue( $result['samples'] );
        $this->assertTrue( expImportAi::sendsSamples( array( 'ai_samples' => true ) ) );
        $this->assertFalse( expImportAi::sendsSamples( array() ), 'off by default' );
    }

    public function testSuggestionsAreValidated(): void
    {
        $this->transport->answers[] = implode( "\n", array(
            '```jsonl',
            '{"attribute":"title","source":"headline","transforms":"trim","confidence":0.92,"reason":"<b>Headline</b> is the title"}',
            '{"attribute":"publish_date","source":"published","transforms":"date(format=Y-m-d)","confidence":0.8,"reason":"a date"}',
            '{"attribute":"body","source":"headline","transforms":"","confidence":0.5,"reason":"no such attribute"}',
            '{"attribute":"email","source":"mail","transforms":"","confidence":0.9,"reason":"no such field"}',
            '{"attribute":"email","source":"contact_mail","transforms":"explode_universe","confidence":0.9,"reason":"no such transform"}',
            '{"attribute":"email","source":"contact_mail","transforms":"trim | lower(","confidence":0.9,"reason":"unreadable chain"}',
            '{"attribute":"email","source":"contact_mail","transforms":"","confidence":1.7,"reason":"confidence out of range"}',
            '{"attribute":"title","source":"id","transforms":"","confidence":0.3,"reason":"a weaker second line"}',
            '```',
        ) );
        $result = expImportAi::suggestMapping( $this->attributes(), $this->sample(), false, array() );
        $this->assertSame( array( 'title', 'publish_date' ), array_keys( $result['lines'] ) );
        $this->assertSame( 'headline', $result['lines']['title']['source'] );
        $this->assertSame( 'trim', $result['lines']['title']['transforms'] );
        $this->assertSame( 0.92, $result['lines']['title']['confidence'] );
        $this->assertSame( 'Headline is the title', $result['lines']['title']['reason'], 'markup is removed' );
        $this->assertSame( 'date(format=Y-m-d)', $result['lines']['publish_date']['transforms'] );
        $this->assertSame( 6, $result['dropped'] );
        $this->assertSame( 'test-model', $result['model'] );
    }

    /** @return array */
    public function malformedAnswers()
    {
        return array(
            'empty' => array( '' ),
            'prose' => array( 'Sure! Here is the mapping: title = headline' ),
            'prose after a line' => array( "{\"attribute\":\"title\",\"source\":\"headline\",\"confidence\":0.9}\nHope this helps." ),
            'a list instead of objects' => array( '["title","headline"]' ),
            'a broken object' => array( '{"attribute":"title","source":' ),
            'a number' => array( '42' ),
        );
    }

    public function testMalformedAnswersAreRejected(): void
    {
        // a loop, not a data provider: PHPUnit 9 to 13 read the same test (12 has no @dataProvider annotations)
        foreach ( $this->malformedAnswers() as $case => $c )
        {
            $this->transport->answers = array( $c[0] );
            try
            {
                expImportAi::suggestMapping( $this->attributes(), $this->sample(), false, array() );
                $this->fail( "$case: the answer was accepted" );
            }
            catch ( expImportAiException $e )
            {
                $this->addToAssertionCount( 1 );
            }
        }
    }

    public function testTooLongAnswersAreRejected(): void
    {
        $this->expectException( expImportAiException::class );
        expImportAi::parseLines( str_repeat( "{\"a\":1}\n", 30 ), 20 );
    }

    public function testClassSuggestionsAreTheBestThreeCandidates(): void
    {
        $this->transport->answers[] = implode( "\n", array(
            '{"class":"article","confidence":0.7,"reason":"headline and date"}',
            '{"class":"event","confidence":0.9,"reason":"dates and a place"}',
            '{"class":"spaceship","confidence":0.99,"reason":"not a class of the site"}',
            '{"class":"folder","confidence":0.2,"reason":"only a name"}',
            '{"class":"news","confidence":0.4,"reason":"short texts"}',
        ) );
        $candidates = array( 'article' => 'Article', 'event' => 'Event', 'folder' => 'Folder', 'news' => 'News' );
        $result = expImportAi::suggestClass( $candidates, $this->sample(), false, 'content', array() );
        $this->assertSame( array( 'event', 'article', 'news' ), array_column( $result['classes'], 'identifier' ) );
        $this->assertSame( 'Event', $result['classes'][0]['name'] );
        $this->assertSame( 1, $result['dropped'] );
        $this->assertStringNotContainsString( 'anna.beispiel', $this->transport->lastText() );
        $this->assertSame( 'class', $this->transport->sent[0]['context']['purpose'] );
        $request = expImportAi::classRequest( array( 'shopRow' => 'shopRow (shop_row)' ), array(), 'datatable' );
        $this->assertStringContainsString( 'data table', $request['system'] );
    }

    public function testExplainSendsTheMessageAndLinesButNoValuesWithoutTheOption(): void
    {
        $this->transport->answers[] = '{"explanation":"The date is not in the expected format.","fix":"Use date(format=d.m.Y)."}';
        $lines = expImportAi::linesFor( array( 'fields' => array(
            array( 'attribute' => 'publish_date', 'source' => 'published', 'transforms' => array( array( 'name' => 'date' ) ), 'default' => 'SECRET-DEFAULT', 'required' => true ),
            array( 'attribute' => 'title', 'source' => 'headline', 'transforms' => array(), 'default' => '', 'required' => false ),
        ) ), 'publish_date: cannot read the date for anna.beispiel@example.org' );
        $this->assertCount( 1, $lines, 'only the line the message names' );
        $result = expImportAi::explain( 'publish_date: cannot read the date for anna.beispiel@example.org', $lines, null, array( 'run_id' => 3 ) );
        $text = $this->transport->lastText();
        $this->assertStringContainsString( 'publish_date <- published | date (required)', $text );
        $this->assertStringContainsString( '[email]', $text, 'the message is masked' );
        $this->assertStringNotContainsString( 'anna.beispiel', $text );
        $this->assertStringNotContainsString( 'SECRET-DEFAULT', $text, 'no default values' );
        $this->assertStringNotContainsString( 'Values of the record', $text );
        $this->assertSame( 'Use date(format=d.m.Y).', $result['fix'] );
        $this->assertFalse( $result['samples'] );

        $this->transport->answers[] = '{"explanation":"x","fix":"y"}';
        expImportAi::explain( 'failed', $lines, array( 'published' => '31/02/2026', 'contact' => 'anna.beispiel@example.org' ), array() );
        $text = $this->transport->lastText();
        $this->assertStringContainsString( 'published: 31/02/2026', $text, 'values only with the option' );
        $this->assertStringContainsString( 'contact: [email]', $text );
        $this->assertSame( 2, count( expImportAi::linesFor( array( 'fields' => array(
            array( 'attribute' => 'intro', 'source' => 'teaser', 'transforms' => array() ), array( 'attribute' => 'body', 'source' => 'text', 'transforms' => array() ) ) ), 'a database error' ) ),
            'all lines when the message names none' );
    }

    public function testExplainRejectsAnAnswerWithoutAFix(): void
    {
        $this->transport->answers[] = '{"explanation":"something"}';
        $this->expectException( expImportAiException::class );
        expImportAi::explain( 'failed', array(), null, array() );
    }

    public function testTheSummaryUsesStatisticsWithoutRecordValues(): void
    {
        $run = array( 'status' => 'completed_with_errors', 'kind' => 'import', 'trigger' => 'manual', 'total' => 120, 'created' => 100, 'updated' => 10,
                      'unchanged' => 2, 'skipped' => 0, 'failed' => 8, 'processed' => 0, 'undone' => 0, 'queued_at' => 1000, 'started_at' => 1010,
                      'finished_at' => 1070, 'memory' => 12.5 );
        $stats = expImportAi::summaryStats( $run, array( "title: the value 'Anna Beispiel' is too long", "title: the value 'Bert Muster' is too long",
                                                          'email: anna.beispiel@example.org is invalid' ), array( 'title' => 4, 'price' => 9 ) );
        $this->assertSame( 60, $stats['duration_seconds'] );
        $this->assertSame( 10, $stats['wait_seconds'] );
        $this->assertSame( 500, $stats['ms_per_record'] );
        $this->assertSame( 8, $stats['counts']['failed'] );
        $this->assertSame( 2, $stats['top_errors'][0]['count'], 'the same error with other values counts once' );
        $this->assertSame( array( 'price' => 9, 'title' => 4 ), $stats['changed_fields'] );
        $this->transport->answers[] = '{"summary":"100 records were created, 8 failed on too long titles."}';
        $result = expImportAi::summarize( $stats, array( 'run_id' => 9 ) );
        $text = $this->transport->lastText();
        foreach ( array( 'Anna Beispiel', 'Bert Muster', 'anna.beispiel' ) as $value )
            $this->assertStringNotContainsString( $value, $text );
        $this->assertStringContainsString( '"failed": 8', $text );
        $this->assertSame( '100 records were created, 8 failed on too long titles.', $result['summary'] );
        $this->assertSame( 'summary', $this->transport->sent[0]['context']['purpose'] );
    }

    public function testAcceptingFillsTheFormLinesOnly(): void
    {
        $mapping = array( 'class' => 'article', 'fields' => array(
            array( 'source' => 'old', 'attribute' => 'title', 'transforms_text' => '', 'transforms' => array(), 'default' => 'keep', 'required' => true, 'language' => '' ),
            array( 'source' => 'title_de', 'attribute' => 'title', 'transforms_text' => '', 'transforms' => array(), 'default' => '', 'required' => false, 'language' => 'ger-DE' ),
        ) );
        $suggestions = array( 'title' => array( 'attribute' => 'title', 'source' => 'headline', 'transforms' => 'trim', 'confidence' => 0.9, 'reason' => '' ),
                              'publish_date' => array( 'attribute' => 'publish_date', 'source' => 'published', 'transforms' => 'date', 'confidence' => 0.8, 'reason' => '' ) );
        list( $one, $accepted ) = expImportAi::accept( $mapping, $suggestions, array( 'title' ) );
        $this->assertSame( array( 'title' ), $accepted );
        $this->assertSame( 'headline', $one['fields'][0]['source'] );
        $this->assertSame( 'trim', $one['fields'][0]['transforms_text'] );
        $this->assertSame( 'keep', $one['fields'][0]['default'], 'the default stays' );
        $this->assertSame( 'title_de', $one['fields'][1]['source'], 'translations stay' );
        $this->assertCount( 2, $one['fields'] );
        list( $all, $accepted ) = expImportAi::accept( $mapping, $suggestions );
        $this->assertSame( array( 'title', 'publish_date' ), $accepted );
        $this->assertSame( 'published', $all['fields'][2]['source'] );
        $this->assertSame( 'date', $all['fields'][2]['transforms_text'] );
    }

    public function testAcceptedSuggestionsNeedADryRunBeforeARealRun(): void
    {
        $this->assertFalse( expImportAi::needsDryRun( array(), array() ) );
        $this->assertTrue( expImportAi::needsDryRun( array( 'ai_dry_run_from' => 4 ), array() ) );
        $this->assertTrue( expImportAi::needsDryRun( array( 'ai_dry_run_from' => 4 ), array( 3 ) ), 'a dry run of an older version does not count' );
        $this->assertFalse( expImportAi::needsDryRun( array( 'ai_dry_run_from' => 4 ), array( 4 ) ) );
        $this->assertFalse( expImportAi::needsDryRun( array( 'ai_dry_run_from' => 4 ), array( 6 ) ) );
    }

    public function testAnswersAreKeptPerKey(): void
    {
        $k1 = expImportAi::key( 'mapping', 5, array( 'article', array( 'a', 'b' ) ) );
        $k2 = expImportAi::key( 'mapping', 5, array( 'article', array( 'a', 'c' ) ) );
        $this->assertNotSame( $k1, $k2 );
        $this->assertNull( expImportAi::cached( $k1 ) );
        expImportAi::remember( $k1, array( 'x' => 1 ) );
        $this->assertSame( array( 'x' => 1 ), expImportAi::cached( $k1 ) );
        $this->assertNull( expImportAi::cached( $k2 ) );
        for ( $i = 0; $i < 45; $i++ )
            expImportAi::remember( 'k' . $i, $i );
        $this->assertNull( expImportAi::cached( $k1 ), 'only the last 40 are kept' );
        $this->assertSame( 44, expImportAi::cached( 'k44' ) );
    }

    public function testNothingIsOfferedWhenSwitchedOffOrRefused(): void
    {
        $this->assertTrue( expImportAi::available() );
        $this->transport->refuse = 'You do not have access to Exponential Intelligence.';
        $this->assertFalse( expImportAi::available() );
        $this->assertSame( 'You do not have access to Exponential Intelligence.', expImportAi::refusal() );
        $this->transport->refuse = '';
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'AiSettings', 'Enabled', 'disabled' );
        $this->assertFalse( expImportAi::available() );
        try
        {
            expImportAi::summarize( array(), array() );
            $this->fail( 'a switched off assist sends nothing' );
        }
        catch ( expImportAiException $e )
        {
            $this->assertSame( array(), $this->transport->sent );
        }
    }

    public function testWithoutExpaiTheTransportRefuses(): void
    {
        if ( expImportAiExpaiTransport::installed() )
            $this->markTestSkipped( 'expai is loaded here' );
        $transport = new expImportAiExpaiTransport();
        $this->assertSame( 'Exponential Intelligence (expai) is not installed.', $transport->refusal( array() ) );
        $this->expectException( expImportAiException::class );
        $transport->send( array( 'system' => '', 'user' => '' ), array() );
    }

    /**
     * The expai transport calls expai's classes with the parameters their code declares (extension/expai of the
     * installation, or of SQ688_EXPAI_ROOT): a change of expai's API fails here, not as a fatal error of the editor.
     */
    public function testTheTransportFitsTheMethodsOfExpai(): void
    {
        $roots = array_filter( array( (string)getenv( 'SQ688_EXPAI_ROOT' ), dirname( __DIR__, 4 ) . '/alpha.se7enx.com/extension/expai', dirname( __DIR__, 4 ) . '/expai' ) );
        $root = '';
        foreach ( $roots as $r )
            if ( is_file( $r . '/classes/expaiconfig.php' ) )
            {
                $root = $r;
                break;
            }
        if ( $root === '' )
            $this->markTestSkipped( 'no expai here (set SQ688_EXPAI_ROOT to its directory)' );
        // file, class, method, the number of arguments the transport passes
        $calls = array(
            array( 'expaiconfig.php', 'instance', 0 ), array( 'expaiconfig.php', 'effectiveModel', 0 ), array( 'expaiconfig.php', 'mayUseModel', 1 ),
            array( 'expaiconfig.php', 'registry', 0 ), array( 'expaigate.php', 'switchedOff', 1 ), array( 'expaiproviderregistry.php', 'providerFor', 1 ),
            array( 'expaidatapolicy.php', 'forProvider', 2 ), array( 'expaidatapolicy.php', 'assertClass', 2 ), array( 'expaiwebbudget.php', 'assertFits', 3 ),
            array( 'budget/expaibudget.php', '__construct', 1 ), array( 'budget/expaibudget.php', 'estimate', 3 ), array( 'budget/expaibudget.php', 'estimateInputTokens', 1 ),
            array( 'budget/expaibudget.php', 'refusal', 1 ), array( 'expaiusagerecorder.php', '__construct', 2 ), array( 'expaiusagerecorder.php', 'send', 1 ),
            array( 'expaitext.php', 'stripReasoning', 1 ), array( 'expaiprovider.php', 'providerId', 0 ),
        );
        foreach ( $calls as $call )
        {
            list( $file, $method, $passed ) = $call;
            $code = (string)file_get_contents( $root . '/classes/' . $file );
            $this->assertSame( 1, preg_match( '/public\s+(?:static\s+)?function\s+' . preg_quote( $method, '/' ) . '\s*\(([^)]*)\)/', $code, $m ), "$file has $method()" );
            $params = trim( $m[1] ) === '' ? array() : explode( ',', $m[1] );
            $required = count( array_filter( $params, function ( $p ) { return strpos( $p, '=' ) === false; } ) );
            $this->assertGreaterThanOrEqual( $passed, count( $params ), "$method() takes $passed arguments" );
            $this->assertLessThanOrEqual( $passed, $required, "$method() needs no more than $passed arguments" );
        }
    }
}
