<?php
/**
 * File containing the expImportFrontMatter class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Front matter of Markdown files (Jekyll, Hugo, Obsidian, Eleventy ...): YAML between "---" lines or TOML between
 * "+++" lines at the start of the file, read by expImportYaml: the same on every server, with or without the PHP
 * extension yaml.
 */
class expImportFrontMatter
{
    /**
     * Splits a text into its front matter and its body.
     *
     * @param string $text
     * @return array ( array front matter, string body, string format: yaml, toml or '' )
     */
    public static function split( $text )
    {
        $text = (string)$text;
        if ( strncmp( $text, "\xEF\xBB\xBF", 3 ) === 0 )
            $text = substr( $text, 3 );
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
        if ( preg_match( '/\A---[ \t]*\n(.*?)\n(?:---|\.\.\.)[ \t]*(?:\n|\z)/s', $text, $m ) )
            return array( self::yaml( $m[1] ), (string)substr( $text, strlen( $m[0] ) ), 'yaml' );
        if ( preg_match( '/\A\+\+\+[ \t]*\n(.*?)\n\+\+\+[ \t]*(?:\n|\z)/s', $text, $m ) )
            return array( self::toml( $m[1] ), (string)substr( $text, strlen( $m[0] ) ), 'toml' );
        return array( array(), $text, '' );
    }

    /**
     * The YAML of front matter as an array (expImportYaml; a front matter that cannot be read counts as none).
     *
     * @param string $yaml
     * @return array
     */
    public static function yaml( $yaml )
    {
        try
        {
            $data = expImportYaml::parse( $yaml );
        }
        catch ( expImportSourceException $e )
        {
            return array();
        }
        return is_array( $data ) ? self::plain( $data ) : array();
    }

    /**
     * The TOML of front matter as an array.
     *
     * @param string $toml
     * @return array
     */
    public static function toml( $toml )
    {
        try
        {
            return self::plain( expImportYaml::toml( $toml ) );
        }
        catch ( expImportSourceException $e )
        {
            return array();
        }
    }

    /** @return array the values with null as '' (an empty front matter key is an empty field) */
    protected static function plain( array $data )
    {
        foreach ( $data as $key => $value )
        {
            if ( is_array( $value ) )
                $data[$key] = self::plain( $value );
            elseif ( $value === null )
                $data[$key] = '';
        }
        return $data;
    }
}
