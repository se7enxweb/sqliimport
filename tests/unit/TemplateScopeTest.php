<?php

use PHPUnit\Framework\TestCase;

/**
 * An included template shares the namespace of the template that includes it: a {def} of a name the caller uses
 * warns "already defined", and its {undef} removes the caller's variable. So every variable an included template
 * of sqliimport defines (parts/, the editor's steps in edit/, the menus) starts with sq_.
 */
class TemplateScopeTest extends TestCase
{
    /** @return string[] the included templates */
    public static function includedTemplates(): array
    {
        $root = dirname( __DIR__, 2 ) . '/design';
        $files = array_merge(
            glob( $root . '/admin/templates/sqliimport/parts/*.tpl' ) ?: array(),
            glob( $root . '/admin/templates/sqliimport/edit/*.tpl' ) ?: array(),
            glob( $root . '/admin/templates/parts/sqliimport/*.tpl' ) ?: array(),
            glob( $root . '/admin4/templates/parts/sqliimport/*.tpl' ) ?: array(),
            glob( $root . '/admin/templates/dashboard/*.tpl' ) ?: array()
        );
        sort( $files );
        return $files;
    }

    public function testDefinedNamesArePrefixed(): void
    {
        $files = self::includedTemplates();
        $this->assertNotEmpty( $files );
        $bad = array();
        foreach ( $files as $file )
        {
            $text = (string)file_get_contents( $file );
            preg_match_all( '/\{def\s(.*?)\}/s', $text, $blocks );
            foreach ( $blocks[1] as $block )
            {
                preg_match_all( '/\$([A-Za-z_][A-Za-z0-9_]*)\s*=/', $block, $names );
                foreach ( $names[1] as $name )
                    if ( strpos( $name, 'sq_' ) !== 0 )
                        $bad[] = basename( dirname( $file ) ) . '/' . basename( $file ) . ': $' . $name;
            }
        }
        $this->assertSame( array(), $bad, 'variables defined in included templates must start with sq_' );
    }
}
