<?php

use PHPUnit\Framework\TestCase;

/**
 * When the end of a run is told, by the import's setting.
 */
class NotifierTest extends TestCase
{
    public function testWhen(): void
    {
        $cases = array(
            array( 'errors', 'failed', true ), array( 'errors', 'completed_with_errors', true ), array( 'errors', 'completed', false ),
            array( 'failed', 'failed', true ), array( 'failed', 'completed_with_errors', false ),
            array( 'always', 'completed', true ), array( 'always', 'cancelled', true ), array( 'always', 'running', false ),
            array( 'never', 'failed', false ), array( 'unknown', 'failed', true ),
        );
        foreach ( $cases as $c )
            $this->assertSame( $c[2], expImportNotifier::shouldNotify( $c[0], $c[1] ), $c[0] . ' / ' . $c[1] );
        $this->assertSame( 'errors', expImportNotifier::kindOf( 'completed_with_errors' ) );
        $this->assertSame( 'failed', expImportNotifier::kindOf( 'failed' ) );
    }

    /**
     * The bell of the header: one header item of the kernel's slot (design.ini [AdminHeaderSettings] HeaderItems[]),
     * drawn with the kernel's parts/a4_header_item.tpl, its count and entries from the GET feed sqliimport::notices.
     */
    public function testTheHeaderBell(): void
    {
        $root = dirname( __DIR__, 2 );
        $ini = (string)file_get_contents( $root . '/settings/design.ini.append.php' );
        $this->assertMatchesRegularExpression( '/^\[AdminHeaderSettings\]\nHeaderItems\[\]=sqliimport\/header_bell\.tpl$/m', $ini );
        $tpl = (string)file_get_contents( $root . '/design/admin4/templates/sqliimport/header_bell.tpl' );
        $this->assertStringContainsString( "{include uri='design:parts/a4_header_item.tpl'", $tpl );
        $this->assertStringContainsString( "'ezjscore/call/sqliimport::notices::8'|ezurl( 'no' ), '?ContentType=json'", $tpl );
        $this->assertStringContainsString( "item_icon='bell'", $tpl );
        $source = (string)file_get_contents( $root . '/classes/exp/expimportserverfunctions.php' );
        $body = substr( $source, strpos( $source, 'public static function notices(' ) );
        $this->assertStringNotContainsString( 'self::guard()', $body, 'a GET from every page: no form token' );
        $this->assertStringContainsString( "expImportAdmin::can( 'view' )", $body );
    }
}
