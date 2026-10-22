<?php

use PHPUnit\Framework\TestCase;

/**
 * YAML and TOML: the parser and the data source.
 */
class YamlTest extends TestCase
{
    public function testYamlStructures(): void
    {
        $yaml = "# c\nlist:\n- a\n- b: 1\n  c: [x, 'y z']\nmap:\n  nested:\n    deep: \"q: r\"\n  flag: true\n  none: ~\n  num: 1.5\n  yes: y\nfolded: >\n  one\n  two\n\n  three\nkeep: |-\n  a\n    b\n";
        $data = expImportYaml::parse( $yaml );
        $this->assertSame( array( 'a', array( 'b' => 1, 'c' => array( 'x', 'y z' ) ) ), $data['list'], 'a sequence at its key\'s indentation' );
        $this->assertSame( 'q: r', $data['map']['nested']['deep'] );
        $this->assertTrue( $data['map']['flag'] );
        $this->assertNull( $data['map']['none'] );
        $this->assertSame( 1.5, $data['map']['num'] );
        $this->assertSame( 'y', $data['map']['yes'], 'YAML 1.2: y is text' );
        $this->assertSame( "one two\nthree\n", $data['folded'] );
        $this->assertSame( "a\n  b", $data['keep'] );
        $this->assertSame( array( array( 'id' => 1 ), array( 'id' => 2 ) ), expImportYaml::parse( "---\n- id: 1\n- id: 2\n...\n- id: 3" ) );
    }

    public function testBadIndentation(): void
    {
        $this->expectException( expImportSourceException::class );
        expImportYaml::parse( "a:\n  b: 1\n    c: 2" );
    }

    public function testToml(): void
    {
        $data = expImportYaml::toml( "a = 1\nb.c = \"d\"\n[t]\nx = [1, 2]\n[[items]]\nid = 1\n[[items]]\nid = 2\n[items.sub]\nk = 'v'\ns = \"\"\"\nline\n\"\"\"\nbig = 1_000\n" );
        $this->assertSame( 1, $data['a'] );
        $this->assertSame( 'd', $data['b']['c'] );
        $this->assertSame( array( 1, 2 ), $data['t']['x'] );
        $this->assertSame( 2, $data['items'][1]['id'] );
        $this->assertSame( array( 'k' => 'v', 's' => "line\n", 'big' => 1000 ), $data['items'][1]['sub'] );
    }

    public function testSource(): void
    {
        foreach ( array( 'teas.yaml', 'teas.toml' ) as $file )
        {
            $out = array();
            foreach ( ( new expImportYamlSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/' . $file, 'record_path' => 'teas', 'key' => 'id' ) ) )->records() as $r )
                $out[$r->ref] = $r->data;
            $this->assertSame( array( 'sencha', 'darjeeling', 'rooibos' ), array_keys( $out ), $file );
            $this->assertSame( 'Darjeeling: first flush', $out['darjeeling']['name'], $file );
            $this->assertSame( 11.0, $out['darjeeling']['price']['amount'], $file );
            $this->assertSame( array( 'black', 'spring' ), $out['darjeeling']['tags'], $file );
            $this->assertFalse( $out['rooibos']['caffeine'], $file );
        }
        $out = array();
        foreach ( ( new expImportYamlSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/teas.yaml', 'key' => '_key' ) ) )->records() as $r )
            $out[] = $r->ref;
        $this->assertSame( array( 'shop', 'teas' ), $out, 'a mapping at the top: its keys name the records' );
    }
}
