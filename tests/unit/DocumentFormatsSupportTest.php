<?php

use PHPUnit\Framework\TestCase;

/**
 * What the document formats of another extension (ezodf 7) need: files of the run (run:<path>), pictures of a
 * converted document kept in the rich text, a class per record (folders and documents in one import), the chunks of a
 * run one after the other, and examples in the declaring extension's share/samples.
 */
class DocumentFormatsSupportTest extends TestCase
{
    /** @var string */
    private $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sq-docformats-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir . '/docs/originals', 0700, true );
        file_put_contents( $this->dir . '/docs/image1.png', 'png' );
        file_put_contents( $this->dir . '/docs/originals/report.pdf', str_repeat( 'x', 2000 ) );
        file_put_contents( $this->dir . '/../sq-outside-' . getmypid() . '.txt', 'secret' );
    }

    protected function tearDown(): void
    {
        @unlink( $this->dir . '/../sq-outside-' . getmypid() . '.txt' );
        @unlink( $this->dir . '/docs/link' );
        @unlink( $this->dir . '/docs/image1.png' );
        @unlink( $this->dir . '/docs/originals/report.pdf' );
        @rmdir( $this->dir . '/docs/originals' );
        @rmdir( $this->dir . '/docs' );
        @rmdir( $this->dir );
        expImportTransforms::$mediaObject = null;
        expImportTransforms::$notices = array();
    }

    public function testFilesOfTheRun(): void
    {
        $this->assertSame( realpath( $this->dir . '/docs/image1.png' ), expImportSourceFiles::runFile( $this->dir, 'docs/image1.png', 'image', 1000 ) );
        $this->assertSame( realpath( $this->dir . '/docs/image1.png' ), expImportSourceFiles::runFile( $this->dir, '/docs/image1.png', 'image', 1000 ), 'a leading slash is below the run' );
        $refused = array( '', '../sq-outside-' . getmypid() . '.txt', 'docs/../../sq-outside-' . getmypid() . '.txt', 'docs/./image1.png',
                          "docs/image1.png\0", 'docs', 'docs/missing.png' );
        if ( @symlink( $this->dir . '/../sq-outside-' . getmypid() . '.txt', $this->dir . '/docs/link' ) )
            $refused[] = 'docs/link';
        foreach ( $refused as $rel )
        {
            try
            {
                expImportSourceFiles::runFile( $this->dir, $rel, 'image', 1000 );
                $this->fail( 'refused: ' . json_encode( $rel ) );
            }
            catch ( expImportTransformException $e )
            {
                $this->assertStringStartsWith( 'image: ', $e->getMessage() );
            }
        }
        try
        {
            expImportSourceFiles::runFile( $this->dir, 'docs/originals/report.pdf', 'file', 1000 );
            $this->fail( 'too large' );
        }
        catch ( expImportTransformException $e )
        {
            $this->assertStringContainsString( 'larger than', $e->getMessage() );
        }
        $source = (string)file_get_contents( dirname( __DIR__, 2 ) . '/classes/exp/engine/expimportsourcefiles.php' );
        $this->assertStringContainsString( "if ( strpos( \$url, 'run:' ) === 0 )\n            return self::runAsset(", $source, 'image, file and media_object take run: like source:' );
    }

    public function testTheRichTextKeepsThePicturesOfTheRun(): void
    {
        $made = array();
        expImportTransforms::$mediaObject = function ( $url, array $args ) use ( &$made )
        {
            if ( strpos( $url, 'missing' ) !== false )
                throw new expImportTransformException( 'media_object: the run has no file' );
            $made[] = array( $url, $args['name_value'], $args['parent'] );
            return 40 + count( $made );
        };
        $html = '<p>Intro</p><p><img src="run:docs/3f/image1.png" alt="Sales &amp; costs"></p>'
              . "<img alt='Logo' src='source:media/logo.png' />"
              . '<img src="https://example.invalid/x.png" alt="remote">'
              . '<img src="run:docs/missing.png" alt="gone">';
        $out = expImportTransforms::richtextImages( $html, array( 'images' => 'embed', 'image_parent' => 77, 'image_size' => 'large' ) );
        $this->assertSame( '<p>Intro</p><p><img id="eZObject_41" src="" alt="large" /></p><img id="eZObject_42" src="" alt="large" />'
                         . '<img src="https://example.invalid/x.png" alt="remote">', $out );
        $this->assertSame( array( array( 'run:docs/3f/image1.png', 'Sales & costs', 77 ), array( 'source:media/logo.png', 'Logo', 77 ) ), $made );
        $this->assertCount( 1, expImportTransforms::$notices, 'a picture that cannot be had is left out with a note' );
        $this->assertSame( '<p>A</p>', expImportTransforms::richtextImages( '<p>A<img src="run:docs/3f/image1.png"></p>', array( 'images' => 'drop' ) ), 'images=drop leaves them out' );
        // a dry run has no media object maker: the pictures are left out, nothing is made
        expImportTransforms::$mediaObject = null;
        $this->assertSame( '<p></p>', expImportTransforms::richtextImages( '<p><img src="run:a.png"></p>', array() ) );
        $catalogue = expImportTransforms::catalogue();
        $this->assertSame( 'embed', $catalogue['richtext']['schema']['images']['default'] );
    }

    public function testAClassPerRecord(): void
    {
        $mapping = array(
            'class' => 'article', 'language' => '',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'doc-{id}' ),
            'fields' => array( array( 'source' => 'title', 'attribute' => 'title' ), array( 'source' => 'html', 'attribute' => 'body' ) ),
            'location' => array( 'parent_node_id' => 2, 'parent_from' => 'parent_path', 'parent_prefix' => 'doc-' ),
            'class_from' => 'kind',
            'variants' => array( 'folder' => array( 'class' => 'folder', 'fields' => array( array( 'source' => 'title', 'attribute' => 'name' ) ) ),
                                 'note' => array( 'skip' => true ) ),
        );
        $this->assertSame( array(), expImportMapper::validate( $mapping ) );
        $folder = expImportMapper::variantFor( $mapping, new expImportRecord( 'r1', 0, array( 'id' => 'a', 'kind' => 'folder', 'title' => 'Reports', 'parent_path' => '' ) ) );
        $this->assertSame( 'folder', $folder['class'] );
        $this->assertSame( 'name', $folder['fields'][0]['attribute'] );
        $this->assertSame( 'doc-{id}', $folder['match']['pattern'], 'match and location stay the mapping\'s' );
        $this->assertArrayNotHasKey( 'variants', $folder );
        $plan = expImportMapper::apply( $folder, new expImportRecord( 'r1', 0, array( 'id' => 'a', 'kind' => 'folder', 'title' => 'Reports', 'parent_path' => '' ) ) );
        $this->assertSame( 'doc-a', $plan['remote_id'] );
        $this->assertSame( array( array( 'node' => 2 ) ), $plan['parents'] );
        $document = expImportMapper::variantFor( $mapping, new expImportRecord( 'r2', 1, array( 'id' => 'b', 'kind' => 'document', 'parent_path' => 'a' ) ) );
        $this->assertSame( 'article', $document['class'] );
        $this->assertNull( expImportMapper::variantFor( $mapping, new expImportRecord( 'r3', 2, array( 'kind' => 'note' ) ) ) );
        // without class_from the mapping is used as it is
        $plain = $mapping;
        unset( $plain['class_from'], $plain['variants'] );
        $this->assertSame( $plain, expImportMapper::variantFor( $plain, new expImportRecord( 'r1', 0, array( 'kind' => 'folder' ) ) ) );

        $broken = $mapping;
        $broken['variants']['folder']['fields'][] = array( 'source' => '', 'attribute' => 'short_name' );
        $broken['variants']['file'] = 'x';
        unset( $broken['class_from'] );
        $this->assertContains( 'variants need the field that chooses them (class_from)', expImportMapper::validate( $broken ) );
        $broken['class_from'] = 'kind';
        $errors = expImportMapper::validate( $broken );
        $this->assertContains( "variant 'file': give a class or skip", $errors );
        $this->assertNotEmpty( preg_grep( "/^variant 'folder': line \\d+: give a source field/", $errors ) );
        $errors = expImportMapper::validateVariants( $mapping, function ( $class ) { return $class === 'article' ? array( 'title' => 'ezstring', 'body' => 'ezxmltext' ) : null; } );
        $this->assertSame( array( "variant 'folder': the class 'folder' does not exist" ), $errors );

        $model = (string)file_get_contents( dirname( __DIR__, 2 ) . '/classes/exp/model/expimportmapping.php' );
        $this->assertStringContainsString( "array_diff_key( \$location, array( 'class_from' => 1, 'variants' => 1 ) )", $model, 'stored with the location rule' );
        $editor = (string)file_get_contents( dirname( __DIR__, 2 ) . '/classes/exp/expimporteditor.php' );
        $this->assertStringContainsString( "'class', 'language', 'match', 'fields', 'class_from', 'variants'", $editor, 'mapping files carry the variants' );
        $this->assertStringContainsString( "!\$this->post( 'RemoveVariants' )", $editor, 'saving the grid keeps them' );
    }

    /** The next chunk of a run is queued when the current one has ended, so chunks never run side by side */
    public function testTheChunksOfARunFollowEachOther(): void
    {
        $handler = (string)file_get_contents( dirname( __DIR__, 2 ) . '/classes/exp/engine/expimportjobhandler.php' );
        $this->assertSame( 1, substr_count( $handler, 'expQueue::enqueue(' ), 'only queueNext() queues a chunk' );
        $this->assertStringContainsString( "'resource' => expImportRunner::resource( \$import )", $handler, 'and in the import\'s own resource' );
    }

    public function testExamplesOfAnotherExtension(): void
    {
        $root = $this->dir;
        mkdir( $root . '/extension/ezodf/share/samples/zip', 0700, true );
        file_put_contents( $root . '/extension/ezodf/share/samples/sample.docx', 'docx' );
        file_put_contents( $root . '/extension/ezodf/share/samples/zip/test.zip', 'zip' );
        mkdir( $root . '/settings', 0700 );
        file_put_contents( $root . '/settings/secret.ini', 'x' );
        try
        {
            $this->assertSame( $root . '/extension/ezodf/share/samples/sample.docx', expImport::examplePath( array( 'file' => 'sample.docx', 'extension' => 'ezodf' ), $root ) );
            $this->assertSame( $root . '/extension/ezodf/share/samples/zip/test.zip', expImport::examplePath( array( 'path' => 'extension/ezodf/share/samples/zip/test.zip' ), $root ) );
            foreach ( array( array( 'path' => 'settings/secret.ini' ), array( 'path' => 'extension/ezodf/share/../../../settings/secret.ini' ),
                             array( 'file' => '../../../settings/secret.ini', 'extension' => 'ezodf' ), array( 'file' => 'sample.docx', 'extension' => '../x' ),
                             array( 'file' => 'missing.docx', 'extension' => 'ezodf' ), array() ) as $example )
                $this->assertSame( '', expImport::examplePath( $example, $root ), json_encode( $example ) );
            $this->assertSame( expImport::exampleDirectory() . '/cie-testimonials.csv', expImport::examplePath( array( 'file' => 'cie-testimonials.csv' ), $root ), 'this extension\'s own examples' );
        }
        finally
        {
            @unlink( $root . '/settings/secret.ini' );
            @rmdir( $root . '/settings' );
            @unlink( $root . '/extension/ezodf/share/samples/zip/test.zip' );
            @rmdir( $root . '/extension/ezodf/share/samples/zip' );
            @unlink( $root . '/extension/ezodf/share/samples/sample.docx' );
            foreach ( array( '/extension/ezodf/share/samples', '/extension/ezodf/share', '/extension/ezodf', '/extension' ) as $d )
                @rmdir( $root . $d );
        }
    }
}
