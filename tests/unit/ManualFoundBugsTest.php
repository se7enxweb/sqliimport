<?php

use PHPUnit\Framework\TestCase;

/**
 * The bugs the examples of the manual found (2.0-dev, 2026-10-23), each kept fixed:
 *  1 extra_parents_from made no extra location (added after the publish now, for a new and an existing object; undo
 *    removes them)
 *  2 a plain number into a price was stored as 0; 3 a price like 6.90 was "updated" on every run
 *  4 a dry run of a folder tree failed every child (the parents it would create count now)
 *  5 the role's Type limitation offered no 2.x handler (mapping)
 *  6 an undo run said "retry of #N"
 *  7 the CLI without -s failed in a siteaccess without the extension (one that has it is taken now)
 *  8 an Export CSV without manifest got a remote ID pattern without its remote-id column (duplicates)
 *  9 a run of the data table target offered Undo
 */
class ManualFoundBugsTest extends TestCase
{
    private static function root()
    {
        return dirname( __DIR__, 2 );
    }

    public function testExtraLocationsAreAddedAfterThePublish(): void
    {
        $this->assertSame( array( 7, 9 ), expImportMappingHandler::missingParents( array( 2, 5 ), array( 5, 7, 7, 9, 0 ) ) );
        $this->assertSame( array(), expImportMappingHandler::missingParents( array( 2, 7 ), array( 7 ) ) );
        $handler = (string)file_get_contents( self::root() . '/classes/exp/handlers/expimportmappinghandler.php' );
        $this->assertStringNotContainsString( '$content->addLocation(', $handler, 'never before the first publish' );
        $this->assertStringContainsString( '$extraNodes = self::addExtraLocations( $objectID, $extra );', $handler );
        $this->assertStringContainsString( 'eZContentOperationCollection::addAssignment(', $handler );
        $jobs = (string)file_get_contents( self::root() . '/classes/exp/engine/expimportjobhandler.php' );
        $this->assertStringContainsString( "'nodes_added' =>", $jobs, 'the undo record knows the added locations' );
        $undo = (string)file_get_contents( self::root() . '/classes/exp/handlers/expimportundohandler.php' );
        $this->assertStringContainsString( "\$record->get( 'nodes_added', array() )", $undo );
    }

    public function testPrices(): void
    {
        $this->assertSame( '12.5|3|1', expImportPrice::normalize( '12.50', '3', true ), 'a plain number gets the default VAT' );
        $this->assertSame( '12.5|3|0', expImportPrice::normalize( '12,50', 3, false ), 'decimal comma; VAT excluded' );
        $this->assertSame( '1234.5||1', expImportPrice::normalize( '1.234,50', '', true ) );
        $this->assertSame( '1234.5||1', expImportPrice::normalize( '1,234.50', '', true ) );
        $this->assertSame( '7||1', expImportPrice::normalize( 7, '', true ), 'a number of the record' );
        $this->assertSame( '6.9|1|1', expImportPrice::normalize( '6.90|1|1' ), 'the stored form: 6.90 is 6.9 as the kernel prints it' );
        $this->assertSame( expImportPrice::normalize( '6.9|1|1' ), expImportPrice::normalize( '6.90|1|1' ), 'so an unchanged price compares equal' );
        $this->assertSame( '', expImportPrice::normalize( '  ' ) );
        $this->assertSame( 'free', expImportPrice::normalize( 'free' ), 'no number: left for the kernel to refuse' );
        $handler = (string)file_get_contents( self::root() . '/classes/exp/handlers/expimportmappinghandler.php' );
        $this->assertStringContainsString( "\$plan['fields'] = self::normalizePrices( \$plan['fields'], (string)\$mapping['class'] );", $handler,
                                           'before the conflict rule and the unchanged check see the values' );
    }

    public function testADryRunCountsTheParentsItWouldCreate(): void
    {
        $context = ( new ReflectionClass( 'expImportContext' ) )->newInstanceWithoutConstructor();
        $context->run = new class { public function attribute( $name ) { return 0; } };
        $context->import = new class { public function attribute( $name ) { return 4242; } };
        $plan = new ReflectionMethod( 'expImportMappingHandler', 'planCreated' );
        $plan->setAccessible( true );
        $parent = new ReflectionMethod( 'expImportMappingHandler', 'plannedParent' );
        $parent->setAccessible( true );
        $this->assertSame( '', $parent->invoke( null, $context, array( array( 'remote_id' => 'doc-a' ) ) ) );
        $plan->invoke( null, $context, 'doc-a' );
        $this->assertSame( 'doc-a', $parent->invoke( null, $context, array( array( 'node' => 2 ), array( 'remote_id' => 'doc-a' ) ) ) );
        $this->assertSame( '', $parent->invoke( null, $context, array( array( 'remote_id' => 'doc-b' ) ) ) );
        $handler = (string)file_get_contents( self::root() . '/classes/exp/handlers/expimportmappinghandler.php' );
        $this->assertStringContainsString( "if ( !\$context->dryRun || !self::plannedParent( \$context, \$plan['parents'] ) )", $handler );
    }

    public function testTheTypeLimitationOffersTheHandlersOf2x(): void
    {
        $ids = array_column( SQLIImportUtils::fetchHandlerLimitationList(), 'id' );
        $this->assertContains( 'mapping', $ids, 'a role can be limited to the no-code mapping' );
    }

    public function testAnUndoRunNamesWhatItUndoes(): void
    {
        $tpl = (string)file_get_contents( self::root() . '/design/admin/templates/sqliimport/run.tpl' );
        $this->assertStringContainsString( "{if eq( \$run.kind, 'undo' )}{'undo of'|i18n( 'extension/sqliimport' )}{else}{'retry of'|i18n( 'extension/sqliimport' )}{/if}", $tpl );
    }

    public function testTheCommandLineFindsTheSiteAccessOfTheExtension(): void
    {
        $dir = sys_get_temp_dir() . '/sq-cli-' . getmypid() . '-' . mt_rand();
        foreach ( array( 'settings/siteaccess/site', 'settings/siteaccess/admintest', 'settings/siteaccess/admin', 'settings/override' ) as $d )
            mkdir( "$dir/$d", 0700, true );
        file_put_contents( "$dir/settings/siteaccess/site/site.ini.append.php", "<?php /*\n[ExtensionSettings]\nActiveAccessExtensions[]=ezodf\n*/ ?>" );
        file_put_contents( "$dir/settings/siteaccess/admintest/site.ini.append.php", "<?php /*\n[ExtensionSettings]\nActiveAccessExtensions[]=sqliimport\n*/ ?>" );
        file_put_contents( "$dir/settings/siteaccess/admin/site.ini.append.php", "<?php /*\n[ExtensionSettings]\nActiveAccessExtensions[]=sqliimport\n*/ ?>" );
        try
        {
            $this->assertSame( 'admin', expImportCommand::pickSiteAccess( array( 'run.php', 'my_import' ), $dir ), 'admin first' );
            $this->assertNull( expImportCommand::pickSiteAccess( array( 'run.php', '-s', 'site' ), $dir ), 'a given -s is kept' );
            $this->assertNull( expImportCommand::pickSiteAccess( array( 'run.php', '--siteaccess=site' ), $dir ) );
            unlink( "$dir/settings/siteaccess/admin/site.ini.append.php" );
            $this->assertSame( 'admintest', expImportCommand::pickSiteAccess( array( 'run.php' ), $dir ) );
            unlink( "$dir/settings/siteaccess/admintest/site.ini.append.php" );
            $this->assertFalse( expImportCommand::pickSiteAccess( array( 'run.php' ), $dir ), 'none: a clear message' );
            file_put_contents( "$dir/settings/override/site.ini.append.php", "<?php /*\n[ExtensionSettings]\nActiveExtensions[]=sqliimport\n*/ ?>" );
            $this->assertNull( expImportCommand::pickSiteAccess( array( 'run.php' ), $dir ), 'active everywhere: nothing to add' );
        }
        finally
        {
            foreach ( array( 'settings/override/site.ini.append.php', 'settings/siteaccess/site/site.ini.append.php' ) as $f )
                @unlink( "$dir/$f" );
            foreach ( array( 'settings/siteaccess/site', 'settings/siteaccess/admintest', 'settings/siteaccess/admin', 'settings/siteaccess', 'settings/override', 'settings', '' ) as $d )
                @rmdir( rtrim( "$dir/$d", '/' ) );
        }
    }

    public function testAnExportCsvWithoutManifestMatchesItsRemoteIds(): void
    {
        $preset = expImportXrowextractSource::presetFromColumns( 'folder', array( 'name', 'short-description', 'object-id', 'remote-id', 'class', 'object-name', 'language' ) );
        $this->assertSame( array( 'type' => 'remote_id', 'pattern' => '{remote-id}' ), $preset['match'], 'the remote-id column of Export' );
        $this->assertSame( array( 'name' => 'name', 'short-description' => 'short_description' ), array_column( $preset['fields'], 'attribute', 'source' ),
                           'attributes in their identifier form; the bookkeeping columns of the export are no attributes' );
        $this->assertSame( '{Remote ID}', expImportXrowextractSource::presetFromColumns( 'folder', array( 'Remote ID', 'name' ) )['match']['pattern'] );
        $this->assertSame( '{remote_id}', expImportXrowextractSource::presetFromColumns( 'folder', array( 'remote_id', 'name' ) )['match']['pattern'] );
    }

    public function testTheDataTableTargetIsNotUndone(): void
    {
        $this->assertFalse( expImportUndoHandler::handlerUndoable( 'datatable' ) );
        $this->assertFalse( expImportDataTableHandler::undoable() );
        $this->assertTrue( expImportUndoHandler::handlerUndoable( 'mapping' ) );
    }
}
