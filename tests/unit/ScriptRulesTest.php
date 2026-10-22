<?php

use PHPUnit\Framework\TestCase;

/**
 * The extension's pages run under a Content Security Policy without 'unsafe-inline' (Exponential's
 * doc/features/6.0/content-security-policy.md): no template carries an inline script or an on...= event handler
 * attribute. A control that asks before it is sent declares its question in data-a4-confirm; admin4's a4_actions.js
 * asks it, and javascript/sqliimport_confirm.js asks it in the other administration designs.
 */
class ScriptRulesTest extends TestCase
{
    private static function root(): string
    {
        return dirname( __DIR__, 2 );
    }

    /** @return list<string> */
    private static function templates(): array
    {
        $files = array();
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::root() . '/design', FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $file )
        {
            if ( substr( $file->getFilename(), -4 ) === '.tpl' )
                $files[] = $file->getPathname();
        }
        sort( $files );
        return $files;
    }

    public function testTemplatesHaveNoInlineScriptsOrEventHandlers(): void
    {
        $found = array();
        foreach ( self::templates() as $path )
        {
            // template comments are not output
            $source = preg_replace( '/\{\*.*?\*\}/s', '', (string)file_get_contents( $path ) );
            $count = preg_match_all( '/\son[a-z]+\s*=/i', $source );
            // a <script> element is allowed only when it loads a file
            $count += preg_match_all( '/<script\b(?![^>]*\ssrc\s*=)/i', $source );
            if ( $count )
                $found[] = basename( $path ) . " ($count)";
        }
        $this->assertSame( array(), $found, 'inline script or on...= attribute in templates' );
    }

    public function testConfirmationIsDeclaredAndAskedOutsideAdmin4(): void
    {
        $button = (string)file_get_contents( self::root() . '/design/admin/templates/sqliimport/parts/button.tpl' );
        $this->assertStringContainsString( 'data-a4-confirm="{$confirm|wash}"', $button );

        $design = (string)file_get_contents( self::root() . '/settings/design.ini.append.php' );
        $this->assertMatchesRegularExpression( '/\[JavaScriptSettings\][^\[]*BackendJavaScriptList\[\]=sqliimport_confirm\.js/', $design );

        $script = (string)file_get_contents( self::root() . '/design/admin/javascript/sqliimport_confirm.js' );
        $this->assertStringContainsString( "hasAttribute( 'data-a4-confirm' )", $script );
        // admin4's a4_actions.js asks itself; the fallback must not ask a second time
        $this->assertStringContainsString( 'window.a4Actions', $script );
    }
}
