<?php

use PHPUnit\Framework\TestCase;

/**
 * The transforms and the no-code mapping: validation (names, arguments, attributes), what a record becomes, the
 * auto-match without AI, and the typed options.
 */
class MappingTest extends TestCase
{
    protected function tearDown(): void
    {
        expImportTransforms::reset();
        expImportTransforms::$lookup = null;
        expImportTransforms::$download = null;
    }

    private static function record( array $data ): expImportRecord
    {
        return new expImportRecord( 'r1', 0, $data );
    }

    public function testTextTransforms(): void
    {
        $this->assertSame( 'Hello World', expImportTransforms::applyChain( '  hello WORLD ', array( 'trim', 'lower', 'title' ) ) );
        $this->assertSame( 'a & b', expImportTransforms::applyChain( '<p>a &amp; b</p>', array( 'strip_tags' ) ) );
        $this->assertSame( 'x-y', expImportTransforms::applyChain( 'x_y', array( array( 'name' => 'replace', 'args' => array( 'search' => '_', 'replace' => '-' ) ) ) ) );
        $this->assertSame( 'ab12', expImportTransforms::applyChain( 'ab-12', array( array( 'name' => 'regex_replace', 'args' => array( 'pattern' => '/[^a-z0-9]/', 'replace' => '' ) ) ) ) );
        $this->assertSame( 'cafe-creme', expImportTransforms::applyChain( 'Café Crème!', array( 'slug' ) ) );
        $this->assertSame( 'abc', expImportTransforms::applyChain( 'abcdef', array( array( 'name' => 'truncate', 'args' => array( 'length' => 3 ) ) ) ) );
    }

    public function testListAndLookupTableTransforms(): void
    {
        $this->assertSame( array( 'a', 'b', 'c' ), expImportTransforms::applyChain( 'a, b,,c', array( 'split' ) ) );
        $this->assertSame( 'a|b', expImportTransforms::applyChain( array( 'a', 'b' ), array( array( 'name' => 'join', 'args' => array( 'separator' => '|' ) ) ) ) );
        $this->assertSame( 'a', expImportTransforms::applyChain( array( 'a', 'b' ), array( 'first' ) ) );
        $map = array( array( 'name' => 'map', 'args' => array( 'table' => array( 'D' => 'Germany', 'F' => 'France' ) ) ) );
        $this->assertSame( 'Germany', expImportTransforms::applyChain( 'D', $map ) );
        $this->assertSame( 'X', expImportTransforms::applyChain( 'X', $map ), 'a value without an entry stays' );
        $this->assertSame( 'fallback', expImportTransforms::applyChain( '', array( array( 'name' => 'default', 'args' => array( 'value' => 'fallback' ) ) ) ) );
    }

    public function testRecordTransforms(): void
    {
        $record = self::record( array( 'first' => 'Ada', 'last' => 'Lovelace', 'year' => 1815 ) );
        $this->assertSame( 'Ada Lovelace', expImportTransforms::applyChain( 'Ada', array( array( 'name' => 'concat', 'args' => array( 'paths' => array( 'last' ) ) ) ), $record ) );
        $this->assertSame( 'Lovelace (1815)', expImportTransforms::applyChain( '', array( array( 'name' => 'template', 'args' => array( 'text' => '{last} ({year})' ) ) ), $record ) );
    }
    public function testADownloadThatCannotBeHadLeavesTheFieldEmpty(): void
    {
        expImportTransforms::$download = function ( $url, $kind ) { throw new expImportTransformException( "$kind: the host is not reachable" ); };
        expImportTransforms::$notices = array();
        $this->assertSame( '', expImportTransforms::applyChain( 'https://example.org/a.jpg', array( array( 'name' => 'image' ) ) ) );
        $this->assertSame( array( 'image: the host is not reachable (https://example.org/a.jpg)' ), expImportTransforms::$notices );
        $failed = false;
        try
        {
            expImportTransforms::applyChain( 'https://example.org/a.jpg', array( array( 'name' => 'image', 'args' => array( 'required' => true ) ) ) );
        }
        catch ( expImportTransformException $e )
        {
            $failed = true;
        }
        $this->assertTrue( $failed, 'a required image fails the record' );
        expImportTransforms::$download = null;
        expImportTransforms::$notices = array();
    }


    public function testNumbersBooleansAndDates(): void
    {
        $this->assertSame( '1234.5', expImportTransforms::applyChain( '1.234,50', array( 'number' ) ) );
        $this->assertSame( '1234.50', expImportTransforms::applyChain( '1,234.50', array( array( 'name' => 'number', 'args' => array( 'decimals' => 2 ) ) ) ) );
        $this->assertSame( '3', expImportTransforms::applyChain( '2,6', array( 'int' ) ) );
        $this->assertSame( array( '1', '0', '1' ), expImportTransforms::applyChain( array( 'yes', 'no', 'Ja' ), array( 'bool' ) ) );
        $ts = expImportTransforms::applyChain( '21.10.2026', array( array( 'name' => 'date', 'args' => array( 'format' => 'd.m.Y', 'timezone' => 'Europe/Berlin' ) ) ) );
        $this->assertSame( (string)( new DateTime( '2026-10-21 00:00:00', new DateTimeZone( 'Europe/Berlin' ) ) )->getTimestamp(), $ts );
        $this->assertSame( '2026-10-21', expImportTransforms::applyChain( '2026-10-21T10:00:00Z', array( array( 'name' => 'date_format', 'args' => array( 'format' => 'Y-m-d' ) ) ) ) );
        $this->expectException( expImportTransformException::class );
        expImportTransforms::applyChain( '31/31/2026', array( array( 'name' => 'date', 'args' => array( 'format' => 'd/m/Y' ) ) ) );
    }

    public function testChainValidationNamesUnknownTransformsAndBadArguments(): void
    {
        $errors = expImportTransforms::validateChain( array( 'trim', 'eval', array( 'name' => 'truncate' ), array( 'name' => 'regex_replace', 'args' => array( 'pattern' => '(' ) ) ) );
        $this->assertCount( 3, $errors );
        $this->assertStringContainsString( "unknown transform 'eval'", $errors[0] );
        $this->assertStringContainsString( 'truncate', $errors[1] );
        $this->assertStringContainsString( 'regex_replace', $errors[2] );
    }

    public function testKernelTransformsUseTheEngineCallbacks(): void
    {
        expImportTransforms::$lookup = function ( $remoteID ) { return $remoteID === 'author-7' ? 42 : 0; };
        $this->assertSame( '42', expImportTransforms::applyChain( '7', array( array( 'name' => 'lookup', 'args' => array( 'prefix' => 'author-' ) ) ) ) );
        expImportTransforms::$download = function ( $url, $kind ) { return "/tmp/$kind-" . basename( $url ); };
        $this->assertSame( '/tmp/image-a.jpg', expImportTransforms::applyChain( 'https://example.invalid/a.jpg', array( 'image' ) ) );
        $this->expectException( expImportTransformException::class );
        expImportTransforms::applyChain( '8', array( array( 'name' => 'lookup', 'args' => array( 'prefix' => 'author-' ) ) ) );
    }

    public function testASiteTransformCanBeRegistered(): void
    {
        expImportTransforms::register( new class implements expImportTransformInterface {
            public function name() { return 'reverse'; }
            public function label() { return 'Reverses'; }
            public function schema() { return array(); }
            public function apply( $value, array $args, ?expImportRecord $record = null ) { return strrev( (string)$value ); }
        } );
        $this->assertTrue( expImportTransforms::exists( 'reverse' ) );
        $this->assertSame( 'cba', expImportTransforms::applyChain( 'abc', array( 'reverse' ) ) );
        $this->assertFalse( expImportTransforms::catalogue()['reverse']['builtin'] );
    }

    private static function mapping(): array
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'news-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'title', 'transforms' => array( 'trim' ), 'required' => true ),
                array( 'source' => 'tags', 'attribute' => 'tags' ),
                array( 'source' => 'missing', 'attribute' => 'intro', 'default' => 'none' ),
                array( 'source' => 'titel', 'attribute' => 'title', 'language' => 'ger-DE' ),
            ),
            'location' => array( 'parent_node_id' => 2, 'parent_from' => 'category', 'parent_prefix' => 'cat-' ),
        );
    }

    public function testValidateAcceptsAGoodMappingAndChecksTheClass(): void
    {
        $this->assertSame( array(), expImportMapper::validate( self::mapping() ) );
        $errors = expImportMapper::validate( self::mapping(), array( 'title' => 'ezstring', 'intro' => 'ezxmltext' ) );
        $this->assertSame( array( "line 2: the class has no attribute 'tags'" ), $errors );
    }

    public function testValidateNamesWhatIsMissing(): void
    {
        $errors = expImportMapper::validate( array( 'class' => '', 'match' => array( 'type' => 'remote_id', 'pattern' => 'fixed' ),
                                                    'fields' => array( array( 'attribute' => 'a' ), array( 'source' => 'x', 'attribute' => 'a' ) ) ) );
        $this->assertContains( 'choose the content class', $errors );
        $this->assertContains( 'the remote ID pattern needs at least one {field} of the record, e.g. news-{id}, or {_ref} for the reference of the record', $errors );
        $this->assertContains( "line 1: give a source field or a default value for 'a'", $errors );
        $this->assertContains( "line 2: the attribute 'a' is mapped twice", $errors );
        $this->assertNotEmpty( preg_grep( '/where new objects are placed/', $errors ) );
    }

    public function testTheReferenceOfTheRecordInAPattern(): void
    {
        $m = self::mapping();
        $m['match'] = array( 'type' => 'remote_id', 'pattern' => 'imp-{_ref}' );
        $this->assertSame( 'imp-r1', expImportMapper::apply( $m, self::record( array( 'title' => 'x' ) ) )['remote_id'] );
    }

    public function testApplyTurnsARecordIntoFieldsRemoteIdAndParents(): void
    {
        $out = expImportMapper::apply( self::mapping(), self::record( array( 'id' => 5, 'title' => ' Hi ', 'tags' => array( 'a', 'b' ), 'titel' => 'Hallo', 'category' => 'sport' ) ) );
        $this->assertSame( 'news-5', $out['remote_id'] );
        $this->assertSame( array( 'title' => 'Hi', 'tags' => 'a|b', 'intro' => 'none' ), $out['fields'][''] );
        $this->assertSame( array( 'title' => 'Hallo' ), $out['fields']['ger-DE'] );
        $this->assertSame( array( array( 'remote_id' => 'cat-sport' ) ), $out['parents'] );

        $out = expImportMapper::apply( self::mapping(), self::record( array( 'id' => 6, 'title' => 'x' ) ) );
        $this->assertSame( array( array( 'node' => 2 ) ), $out['parents'], 'without a parent field the default parent node is used' );
    }

    public function testApplyFailsARecordWithoutItsRequiredValues(): void
    {
        try
        {
            expImportMapper::apply( self::mapping(), self::record( array( 'id' => 5, 'title' => '  ' ) ) );
            $this->fail( 'a required empty attribute must fail the record' );
        }
        catch ( expImportTransformException $e )
        {
            $this->assertStringContainsString( "'title'", $e->getMessage() );
        }
        $this->expectException( expImportTransformException::class );
        expImportMapper::apply( self::mapping(), self::record( array( 'title' => 'no id' ) ) );
    }

    public function testMatchOnAnAttribute(): void
    {
        $mapping = self::mapping();
        $mapping['match'] = array( 'type' => 'attribute', 'attribute' => 'isbn', 'source' => 'isbn' );
        $out = expImportMapper::apply( $mapping, self::record( array( 'isbn' => '978-3', 'title' => 't' ) ) );
        $this->assertSame( array( 'isbn', '978-3' ), $out['match'] );
        $this->assertSame( '', $out['remote_id'] );
    }

    public function testAutoMatchByName(): void
    {
        $lines = expImportMapper::autoMatch( array( 'Title', 'body_html', 'Publish Date', 'misc' ),
                                             array( 'title' => array( 'name' => 'Title', 'datatype' => 'ezstring' ),
                                                    'body' => array( 'name' => 'Body', 'datatype' => 'ezxmltext' ),
                                                    'publish_date' => array( 'name' => 'Publish date', 'datatype' => 'ezdatetime' ) ) );
        $byAttr = array();
        foreach ( $lines as $line )
            $byAttr[$line['attribute']] = $line;
        $this->assertSame( 'Title', $byAttr['title']['source'] );
        $this->assertSame( 1.0, $byAttr['title']['confidence'] );
        $this->assertSame( 'body_html', $byAttr['body']['source'] );
        $this->assertSame( 'richtext', $byAttr['body']['transforms'][0]['name'] );
        $this->assertSame( 'Publish Date', $byAttr['publish_date']['source'] );
    }

    public function testOptionSchemaValidatesAndNormalises(): void
    {
        $schema = array(
            'parent' => array( 'type' => 'node', 'required' => true ),
            'limit' => array( 'type' => 'int', 'default' => 100, 'min' => 1, 'max' => 1000 ),
            'debug' => array( 'type' => 'bool' ),
            'mode' => array( 'type' => 'enum', 'values' => array( 'a' => 'A', 'b' => 'B' ), 'default' => 'a' ),
            'url' => array( 'type' => 'url' ),
            'token' => array( 'type' => 'secret' ),
        );
        $this->assertSame( array(), expImportOptionSchema::check( $schema ) );
        list( $values, $errors ) = expImportOptionSchema::validate( $schema, array( 'parent' => '43', 'debug' => 'on', 'extra' => 'dropped' ) );
        $this->assertSame( array(), $errors );
        $this->assertSame( array( 'parent' => 43, 'limit' => 100, 'debug' => true, 'mode' => 'a', 'url' => '', 'token' => '' ), $values );
        list( , $errors ) = expImportOptionSchema::validate( $schema, array( 'limit' => '5000', 'mode' => 'c', 'url' => 'ftp://x', 'token' => 'sk-live key!' ) );
        $this->assertSame( array( 'parent', 'limit', 'mode', 'url', 'token' ), array_keys( $errors ) );
    }

    public function testLegacyOptionTextKeepsEqualsSignsInValues(): void
    {
        $this->assertSame( array( 'csv' => 'a=b.csv', 'debug' => '1' ), expImportOptionSchema::fromLegacyText( "csv=a=b.csv\ndebug=1\nnonsense" ) );
        $this->assertSame( array( 'x' => '1', 'y' => '2' ), expImportOptionSchema::fromLegacyText( 'x=1|y=2' ) );
    }
}
