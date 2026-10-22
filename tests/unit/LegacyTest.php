<?php

use PHPUnit\Framework\TestCase;

/**
 * The pages of 1.x are part of the 2.x pages (expImportLegacy): every address of a 1.x view goes to the 2.x page that
 * does its work, with its ids and parameters; the values of the form of a 1.x scheduled import are checked as 1.x
 * checked them; and no template, menu or link points at a 1.x view any more.
 */
class LegacyTest extends TestCase
{
    private static function root(): string
    {
        return dirname( __DIR__, 2 );
    }

    /** @return array<string, array{0: string, 1: array, 2: array, 3: int, 4: string}> */
    public static function redirects(): array
    {
        return array(
            'items' => array( 'items', array(), array(), 0, '/sqliimport/list#a4-sqliimport-legacy' ),
            'items with offset' => array( 'items', array(), array( 'offset' => '20' ), 0, '/sqliimport/list/(legacyoffset)/20#a4-sqliimport-legacy' ),
            'items with a bad offset' => array( 'items', array(), array( 'offset' => '-5' ), 0, '/sqliimport/list#a4-sqliimport-legacy' ),
            'addimport' => array( 'addimport', array(), array(), 0, '/sqliimport/list#a4-sqliimport-legacy-request' ),
            'alterimport' => array( 'alterimport', array( 'Action' => 'cancel', 'ImportID' => 7 ), array(), 0, '/sqliimport/list#a4-sqliimport-legacy' ),
            'purgelist' => array( 'purgelist', array(), array(), 0, '/sqliimport/list#a4-sqliimport-legacy' ),
            'scheduledlist' => array( 'scheduledlist', array(), array( 'offset' => '10' ), 0, '/sqliimport/schedules#a4-sqliimport-legacy' ),
            'removescheduled' => array( 'removescheduled', array( 'ImportID' => 3 ), array(), 0, '/sqliimport/schedules#a4-sqliimport-legacy' ),
            'addscheduled new' => array( 'addscheduled', array(), array(), 0, '/sqliimport/schedules/(legacy)/new' ),
            'addscheduled id' => array( 'addscheduled', array( 'ScheduledImportID' => '12' ), array(), 0, '/sqliimport/schedules/(legacy)/12' ),
            'addscheduled migrated' => array( 'addscheduled', array( 'ScheduledImportID' => 12 ), array(), 40, '/sqliimport/edit/40/(step)/schedule' ),
            'addscheduled no id but migrated' => array( 'addscheduled', array(), array(), 40, '/sqliimport/schedules/(legacy)/new' ),
            'unknown' => array( 'nosuchview', array(), array(), 0, '/sqliimport/list' ),
        );
    }

    /** @dataProvider redirects */
    public function testEveryAddressOfA1xViewGoesToA2xPage( string $view, array $params, array $userParams, int $migrated, string $expected ): void
    {
        $this->assertSame( $expected, expImportLegacy::redirectFor( $view, $params, $userParams, $migrated ) );
    }

    public function testEvery1xViewOfTheModuleIsMapped(): void
    {
        $Module = null;
        $ViewList = array();
        $FunctionList = array();
        include self::root() . '/modules/sqliimport/module.php';
        $legacy = array( 'items', 'scheduledlist', 'addimport', 'addscheduled', 'alterimport', 'removescheduled', 'purgelist' );
        foreach ( $legacy as $view )
        {
            $this->assertArrayHasKey( $view, $ViewList, "the address sqliimport/$view stays" );
            $this->assertStringStartsWith( '/sqliimport/', expImportLegacy::redirectFor( $view ) );
            $this->assertNotSame( '/sqliimport/list', expImportLegacy::redirectFor( $view ), "sqliimport/$view has its own target" );
            // the view draws no page of its own
            $script = (string)file_get_contents( self::root() . '/modules/sqliimport/' . $ViewList[$view]['script'] );
            $this->assertStringNotContainsString( '->fetch(', $script, "sqliimport/$view renders no template" );
            $this->assertStringContainsString( 'expImportLegacy::redirectFor', $script );
        }
        $this->assertArrayHasKey( 'legacyoffset', $ViewList['list']['unordered_params'] );
        $this->assertArrayHasKey( 'legacy', $ViewList['schedules']['unordered_params'] );
    }

    public function testNoMenuTemplateOrLinkPointsAtA1xView(): void
    {
        $found = array();
        $files = array( self::root() . '/settings/menu.ini.append.php', self::root() . '/settings/exphelp.ini.append.php' );
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::root() . '/design', FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file )
            if ( substr( $file->getFilename(), -4 ) === '.tpl' )
                $files[] = $file->getPathname();
        foreach ( $files as $path )
        {
            // template comments are not output
            $source = preg_replace( '/\{\*.*?\*\}/s', '', (string)file_get_contents( $path ) );
            if ( preg_match_all( '#sqliimport/(items|scheduledlist|addimport|addscheduled|alterimport|removescheduled|purgelist)\b#', $source, $m ) )
                $found[] = basename( $path ) . ': ' . implode( ', ', array_unique( $m[1] ) );
        }
        $this->assertSame( array(), $found, 'a link, form or menu entry to a 1.x view' );
        foreach ( array( 'items', 'scheduledlist', 'addimport', 'addscheduled', 'altererror' ) as $template )
            $this->assertFileDoesNotExist( self::root() . "/design/admin/templates/sqliimport/$template.tpl" );
    }

    public function testTheStartOfA1xScheduleIsCheckedAs1xDid(): void
    {
        $this->assertSame( mktime( 14, 30, 0, 10, 22, 2026 ), expImportLegacy::startTime( '2026-10-22', '14', '30' ) );
        $this->assertSame( mktime( 0, 0, 0, 1, 5, 2027 ), expImportLegacy::startTime( '2027-1-5', 0, 0 ) );
        $this->assertNull( expImportLegacy::startTime( '2026-02-30', 1, 0 ) );
        $this->assertNull( expImportLegacy::startTime( '22.10.2026', 1, 0 ) );
        $this->assertNull( expImportLegacy::startTime( '', 1, 0 ) );
        $this->assertNull( expImportLegacy::startTime( '2026-10-22', 24, 0 ) );
        $this->assertNull( expImportLegacy::startTime( '2026-10-22', 1, 60 ) );
        $this->assertNull( expImportLegacy::startTime( '2026-10-22', 'x', 0 ) );
    }

    public function testTheFrequencyOfA1xScheduleIsCheckedAs1xDid(): void
    {
        foreach ( array( 'none', 'hourly', 'daily', 'weekly', 'monthly' ) as $f )
            $this->assertSame( '', expImportLegacy::frequencyProblem( $f, 0 ) );
        $this->assertSame( '', expImportLegacy::frequencyProblem( 'manual', 5 ) );
        $this->assertSame( 'manual', expImportLegacy::frequencyProblem( 'manual', 4 ) );
        $this->assertSame( 'manual', expImportLegacy::frequencyProblem( 'manual', 'x' ) );
        $this->assertSame( 'frequency', expImportLegacy::frequencyProblem( 'yearly', 0 ) );
    }

    public function testTheStatusOfA1xRequest(): void
    {
        $this->assertSame( array( 'pending', 'running', 'failed', 'completed', 'cancelled', 'interrupted', 'unknown' ),
                           array_map( array( 'expImportLegacy', 'itemStatusCode' ), array( 0, 1, 2, 3, 4, 5, 9 ) ) );
        $this->assertSame( array( '', 'is-accent', 'is-bad', 'is-ok', '', 'is-warn', '' ),
                           array_map( array( 'expImportLegacy', 'itemStatusKind' ), array( 0, 1, 2, 3, 4, 5, 9 ) ) );
    }
}
