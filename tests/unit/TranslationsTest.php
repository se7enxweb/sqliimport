<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TranslationStrings.php';

/**
 * The translation files hold every text the extension translates (tests/TranslationStrings.php collects them from the
 * templates, the PHP code, the label maps, the settings, the catalogue and the option schemas), the way the kernel's
 * TranslationFilesValidTest checks its own files:
 *
 *  - every translations/<locale>/translation.ts validates against the schema the translator uses (a file that does
 *    not is not loaded at all, schemas/translation/ts.rng of the kernel, a copy in tests/fixtures)
 *  - ger-DE and untranslated have every text and nothing else; ger-DE translates each one and keeps its %placeholders
 *  - a translation call with a variable is one the collection knows what the variable can be
 */
class TranslationsTest extends TestCase
{
    /** @var array|null */
    protected static $texts = null;

    protected static function root(): string
    {
        return dirname( __DIR__, 2 );
    }

    protected static function texts(): array
    {
        if ( self::$texts === null )
            self::$texts = ( new SqliImportTranslationStrings( self::root() ) )->collect();
        return self::$texts;
    }

    protected static function messages( string $locale ): array
    {
        $messages = SqliImportTranslationStrings::load( self::root() . "/translations/$locale/translation.ts" );
        self::assertIsArray( $messages, "translations/$locale/translation.ts parses" );
        return $messages;
    }

    public static function locales(): array
    {
        $cases = array();
        foreach ( glob( dirname( __DIR__, 2 ) . '/translations/*/translation.ts' ) ?: array() as $file )
            $cases[basename( dirname( $file ) )] = array( basename( dirname( $file ) ) );
        return $cases;
    }

    public function testEveryFileValidatesAgainstTheTranslatorSchema(): void
    {
        // a loop, not a data provider: PHPUnit 9 to 13 read the same test (12 has no @dataProvider annotations)
        $locales = self::locales();
        $this->assertNotEmpty( $locales, 'translations/*/translation.ts exist' );
        foreach ( $locales as $c )
            $this->assertFileValidatesAgainstTheTranslatorSchema( $c[0] );
    }

    protected function assertFileValidatesAgainstTheTranslatorSchema( string $locale ): void
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $this->assertTrue( $dom->load( self::root() . "/translations/$locale/translation.ts" ), "$locale parses" );
        $previous = libxml_use_internal_errors( true );
        libxml_clear_errors();
        $valid = $dom->relaxNGValidate( self::root() . '/tests/fixtures/ts.rng' );
        $errors = array();
        foreach ( array_slice( libxml_get_errors(), 0, 5 ) as $error )
            $errors[] = 'line ' . $error->line . ': ' . trim( $error->message );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        $this->assertTrue( $valid, "translations/$locale/translation.ts does not validate, so it is not loaded:\n" . implode( "\n", $errors ) );
    }

    public function testTheCollectionFindsTheTexts(): void
    {
        $texts = self::texts();
        // one text of each way a text is found
        $this->assertArrayHasKey( 'Save and continue', $texts['extension/sqliimport/edit'], 'a template' );
        $this->assertArrayHasKey( 'Give the import a name.', $texts['extension/sqliimport/edit'], 'ezpI18n::tr()' );
        $this->assertArrayHasKey( 'Completed with errors', $texts['extension/sqliimport/status'], 'expImportAdmin::labels()' );
        $this->assertArrayHasKey( 'Manage your imports', $texts['extension/sqliimport/menu'], 'menu.ini' );
        $this->assertArrayHasKey( 'Import management', $texts['kernel/navigationpart'], 'the navigation part' );
        $this->assertArrayHasKey( 'Import into this folder…', $texts['kernel/transfer'], 'the transfer tool' );
        $this->assertArrayHasKey( 'CSV and delimited text', $texts['extension/sqliimport/catalogue'], 'a format' );
        $this->assertArrayHasKey( 'Lower case', $texts['extension/sqliimport/catalogue'], 'a transform' );
        $this->assertArrayHasKey( 'Delimiter', $texts['extension/sqliimport/options'], 'an option label' );
        $this->assertArrayHasKey( 'Semicolon', $texts['extension/sqliimport/options'], 'an enum label' );
        $this->assertArrayHasKey( '%label is required', $texts['extension/sqliimport/options'], 'expImportOptionSchema::tr()' );
        $this->assertArrayHasKey( 'map at least one field', $texts['extension/sqliimport/mapping'], 'expImportMapper::tr()' );
        $this->assertArrayHasKey( 'daily at %time (%zone)', $texts['extension/sqliimport/schedule'], 'expImport::tr()' );
        $this->assertArrayHasKey( 'Records per second', $texts['extension/sqliimport/statistics'], 'a metric' );
        $this->assertArrayNotHasKey( 'design/admin/parts/user/menu', $texts, 'the kernel translates its own contexts' );
        $this->assertGreaterThan( 1500, array_sum( array_map( 'count', $texts ) ) );
    }

    public function testEveryCallWithAVariableIsKnown(): void
    {
        $strings = new SqliImportTranslationStrings( self::root() );
        $strings->collect();
        $this->assertSame( array(), $strings->nonLiteral, 'a translation of a variable needs its texts in TranslationStrings (labels, catalogue or dynamic)' );
    }

    public function testTheTextsOfVariablesAreInTheCode(): void
    {
        $code = (string)file_get_contents( self::root() . '/classes/sqliimportitem.php' );
        foreach ( array_keys( self::texts()['extension/sqliimport/type'] ) as $text )
            if ( !in_array( $text, array( 'Scheduled', 'Immediate' ), true ) )
                $this->assertStringContainsString( "'$text'", $code, "the 1.x status $text" );
        $code = (string)file_get_contents( self::root() . '/classes/exp/expimportstatistics.php' );
        foreach ( array( 'Runs', 'Records processed', 'Failed records', 'Failure rate of records', 'Successful runs', 'Average duration',
                         'Duration, 95th percentile', 'Records per second' ) as $text )
            $this->assertStringContainsString( "array( '$text', ", $code, "the metric $text" );
    }

    public function testGermanHasEveryTextTranslated(): void
    {
        list( $missing, $extra, $wrong ) = SqliImportTranslationStrings::compare( self::texts(), self::messages( 'ger-DE' ), true );
        $this->assertSame( array(), array_slice( $missing, 0, 20 ), count( $missing ) . ' texts are not in translations/ger-DE/translation.ts' );
        $this->assertSame( array(), array_slice( $wrong, 0, 20 ), count( $wrong ) . ' texts of ger-DE are untranslated or lose a placeholder' );
        $this->assertSame( array(), array_slice( $extra, 0, 20 ), count( $extra ) . ' messages of ger-DE are used by no code' );
    }

    public function testUntranslatedListsEveryText(): void
    {
        $messages = self::messages( 'untranslated' );
        list( $missing, $extra ) = SqliImportTranslationStrings::compare( self::texts(), $messages, false );
        $this->assertSame( array(), array_slice( $missing, 0, 20 ), count( $missing ) . ' texts are not in translations/untranslated/translation.ts' );
        $this->assertSame( array(), array_slice( $extra, 0, 20 ), count( $extra ) . ' messages of untranslated are used by no code' );
        foreach ( $messages as $context => $sources )
            foreach ( $sources as $source => $m )
                $this->assertSame( array( '', 'unfinished' ), array( $m['translation'], $m['type'] ), "untranslated: $context | $source" );
    }

    public function testPlaceholdersAreReadAsTheTranslatorReadsThem(): void
    {
        $this->assertSame( array( '%done', '%percent', '%total' ), SqliImportTranslationStrings::placeholders( '%done of %total records, %percent%' ) );
        $this->assertSame( array( '%name', '%share' ), SqliImportTranslationStrings::placeholders( '%share % of the runs of %name' ) );
        list( , , $wrong ) = SqliImportTranslationStrings::compare( array( 'c' => array( 'Run #%id' => 'x' ) ),
            array( 'c' => array( 'Run #%id' => array( 'translation' => 'Lauf #%nr', 'type' => '' ) ) ), true );
        $this->assertCount( 1, $wrong, 'a lost placeholder is found' );
    }

    public function testTheOptionMessagesStayEnglishWithoutTheKernel(): void
    {
        list( , $errors ) = expImportOptionSchema::validate( array( 'n' => array( 'type' => 'int', 'label' => 'Count', 'required' => true ),
                                                                    'm' => array( 'type' => 'int', 'label' => 'Size', 'max' => 3 ) ),
                                                             array( 'm' => '9' ) );
        $this->assertSame( array( 'n' => 'Count is required', 'm' => 'Size: at most 3' ), $errors );
    }
}
