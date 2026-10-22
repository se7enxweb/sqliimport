<?php
/**
 * File containing the expImportTransforms class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The transforms of a mapping: the built-in ones and those of kernel services tagged sqliimport.transform.
 *
 * A mapping line names a chain: array( array( 'name' => 'trim' ), array( 'name' => 'date', 'args' => array( 'format' =>
 * 'd.m.Y' ) ) ). apply() runs it on a value. Every built-in transform below is a pure function of its value, its
 * arguments and the record, except richtext (the kernel's XML input parser), lookup (a database read) and
 * image/file (a download into the run's directory), which need a running installation.
 */
class expImportTransforms
{
    /** @var expImportTransformInterface[] name => transform of a site (kernel services), filled by register() */
    protected static $extra = array();

    /** @var callable|null fn( string $remoteID ): int object ID or 0; set by the engine, replaced in tests */
    public static $lookup = null;

    /** @var callable|null fn( string $url, string $kind ): string local file path; set by the engine */
    public static $download = null;

    /** @var callable|null fn( string $url, array $args ): int the ID of the media object of a file; set by the engine */
    public static $mediaObject = null;

    /** @var string[] what a record lost without failing (a download that could not be had); read and emptied by the handler */
    public static $notices = array();

    /**
     * The built-in transforms: name => array( label, argument schema ).
     * @return array
     */
    public static function builtins()
    {
        return array(
            'trim' => array( 'Removes spaces at both ends', array() ),
            'lower' => array( 'Lower case', array() ),
            'upper' => array( 'Upper case', array() ),
            'title' => array( 'Upper case first letter of each word', array() ),
            'strip_tags' => array( 'Removes HTML tags and decodes entities', array() ),
            'prefix' => array( 'Puts a text before the value', array( 'text' => array( 'type' => 'string', 'required' => true, 'raw' => true ) ) ),
            'suffix' => array( 'Puts a text after the value', array( 'text' => array( 'type' => 'string', 'required' => true, 'raw' => true ) ) ),
            'replace' => array( 'Replaces a text', array( 'search' => array( 'type' => 'string', 'required' => true, 'raw' => true ),
                                                           'replace' => array( 'type' => 'string', 'raw' => true ) ) ),
            'regex_replace' => array( 'Replaces what a regular expression matches', array( 'pattern' => array( 'type' => 'string', 'required' => true, 'raw' => true ),
                                                                                           'replace' => array( 'type' => 'string', 'raw' => true ) ) ),
            'truncate' => array( 'Cuts the value to a length', array( 'length' => array( 'type' => 'int', 'required' => true, 'min' => 1 ) ) ),
            'default' => array( 'A value when the source is empty', array( 'value' => array( 'type' => 'string' ) ) ),
            'map' => array( 'Replaces a value by another from a table', array( 'table' => array( 'type' => 'json', 'required' => true ),
                                                                                'otherwise' => array( 'type' => 'string', 'help' => 'empty: keep the value' ) ) ),
            'split' => array( 'Splits a text into a list', array( 'separator' => array( 'type' => 'string', 'default' => ',', 'raw' => true ) ) ),
            'join' => array( 'Joins a list into a text', array( 'separator' => array( 'type' => 'string', 'default' => ', ', 'raw' => true ) ) ),
            'first' => array( 'The first item of a list', array() ),
            'concat' => array( 'Joins the value with other fields of the record', array( 'paths' => array( 'type' => 'list', 'required' => true ),
                                                                                          'separator' => array( 'type' => 'string', 'default' => ' ', 'raw' => true ) ) ),
            'template' => array( 'Builds a text from fields of the record: "{title} ({year})"', array( 'text' => array( 'type' => 'text', 'required' => true ) ) ),
            'number' => array( 'A number (decimal comma or point)', array( 'decimals' => array( 'type' => 'int', 'default' => -1 ) ) ),
            'int' => array( 'A whole number', array() ),
            'bool' => array( 'Yes or no as 1 or 0', array() ),
            'date' => array( 'A date or time as a timestamp (ezdate, ezdatetime)', array( 'format' => array( 'type' => 'string', 'help' => 'PHP date format of the source; excel: a spreadsheet date (days since 1899); empty: any form strtotime() reads' ),
                                                                                         'timezone' => array( 'type' => 'string', 'default' => 'UTC' ) ) ),
            'date_format' => array( 'A date as a text in a format', array( 'format' => array( 'type' => 'string', 'required' => true ),
                                                                           'timezone' => array( 'type' => 'string', 'default' => 'UTC' ) ) ),
            'slug' => array( 'A URL-friendly identifier', array() ),
            'richtext' => array( 'HTML to the rich text (ezxmltext) of the attribute', array(
                'images' => array( 'type' => 'string', 'default' => 'embed', 'help' => 'embed: every <img> of a file of the run (run:, source:) becomes an image object embedded in the text; drop: images are left out' ),
                'image_parent' => array( 'type' => 'int', 'help' => 'the node the image objects are created below (default: the import\'s parent node)' ),
                'image_size' => array( 'type' => 'string', 'default' => 'medium', 'help' => 'the image alias of the embed (small, medium, large, original)' ) ) ),
            'lookup' => array( 'The ID of the object with this remote ID (object relations)', array( 'prefix' => array( 'type' => 'string', 'help' => 'put before the value to make the remote ID' ) ) ),
            'image' => array( 'Downloads an image from a URL (ezimage)', array( 'required' => array( 'type' => 'bool', 'help' => 'the record fails when the image cannot be had (default: it is imported without it, with a note)' ) ) ),
            'file' => array( 'Downloads a file from a URL (ezbinaryfile, ezmedia)', array( 'required' => array( 'type' => 'bool', 'help' => 'the record fails when the file cannot be had (default: it is imported without it, with a note)' ) ) ),
            'media_object' => array( 'An image or file object for a URL, made once per file (object relations)', array(
                'parent' => array( 'type' => 'int', 'help' => 'the node the media objects are created below (default: the import\'s parent node)' ),
                'class' => array( 'type' => 'string', 'help' => 'the class of a media object (default image)' ),
                'attribute' => array( 'type' => 'string', 'help' => 'its file attribute (default image)' ),
                'name' => array( 'type' => 'string', 'help' => 'a field of the record for its name (default: the file name)' ),
                'required' => array( 'type' => 'bool', 'help' => 'the record fails when the file cannot be had (default: it is imported without it, with a note)' ) ) ),
        );
    }

    /** @param expImportTransformInterface $transform a transform of a site */
    public static function register( expImportTransformInterface $transform )
    {
        self::$extra[$transform->name()] = $transform;
    }

    /** Forgets the transforms of a site (tests, and a new request on a long-lived worker). */
    public static function reset()
    {
        self::$extra = array();
    }

    /** @return array name => array( 'label' => ..., 'schema' => ..., 'builtin' => bool ) */
    public static function catalogue()
    {
        $out = array();
        foreach ( self::builtins() as $name => $def )
            $out[$name] = array( 'label' => $def[0], 'schema' => $def[1], 'builtin' => true );
        foreach ( self::$extra as $name => $transform )
            $out[$name] = array( 'label' => $transform->label(), 'schema' => $transform->schema(), 'builtin' => false );
        ksort( $out );
        return $out;
    }

    /** @return bool */
    public static function exists( $name )
    {
        return isset( self::$extra[$name] ) || array_key_exists( $name, self::builtins() );
    }

    /**
     * Checks a chain (names known, arguments valid).
     *
     * @param array $chain
     * @return string[] errors
     */
    public static function validateChain( array $chain )
    {
        $errors = array();
        $catalogue = self::catalogue();
        foreach ( $chain as $i => $step )
        {
            $name = is_array( $step ) && isset( $step['name'] ) ? (string)$step['name'] : ( is_string( $step ) ? $step : '' );
            if ( !isset( $catalogue[$name] ) )
            {
                $errors[] = 'unknown transform ' . ( $name === '' ? '#' . ( $i + 1 ) : "'$name'" );
                continue;
            }
            $args = is_array( $step ) && isset( $step['args'] ) && is_array( $step['args'] ) ? $step['args'] : array();
            list( , $argErrors ) = expImportOptionSchema::validate( $catalogue[$name]['schema'], $args );
            foreach ( $argErrors as $error )
                $errors[] = "$name: $error";
            if ( $name === 'regex_replace' && isset( $args['pattern'] ) && @preg_match( (string)$args['pattern'], '' ) === false )
                $errors[] = 'regex_replace: the pattern is not a valid regular expression (delimiters included, e.g. /a+/)';
        }
        return $errors;
    }

    /**
     * Runs a chain on a value.
     *
     * @param mixed $value
     * @param array $chain
     * @param expImportRecord|null $record
     * @return mixed
     * @throws expImportTransformException
     */
    public static function applyChain( $value, array $chain, ?expImportRecord $record = null )
    {
        $catalogue = self::catalogue();
        foreach ( $chain as $step )
        {
            $name = is_array( $step ) && isset( $step['name'] ) ? (string)$step['name'] : (string)$step;
            if ( !isset( $catalogue[$name] ) )
                throw new expImportTransformException( "unknown transform '$name'" );
            $args = is_array( $step ) && isset( $step['args'] ) && is_array( $step['args'] ) ? $step['args'] : array();
            list( $args, $errors ) = expImportOptionSchema::validate( $catalogue[$name]['schema'], $args );
            if ( $errors )
                throw new expImportTransformException( "$name: " . implode( '; ', $errors ) );
            $value = self::apply( $name, $value, $args, $record );
        }
        return $value;
    }

    /**
     * One transform.
     *
     * @param string $name
     * @param mixed $value
     * @param array $args validated arguments
     * @param expImportRecord|null $record
     * @return mixed
     * @throws expImportTransformException
     */
    public static function apply( $name, $value, array $args = array(), ?expImportRecord $record = null )
    {
        if ( isset( self::$extra[$name] ) )
            return self::$extra[$name]->apply( $value, $args, $record );
        $text = function ( $v )
        {
            if ( is_array( $v ) )
                return implode( ', ', array_map( 'strval', array_filter( $v, 'is_scalar' ) ) );
            return $v === null ? '' : (string)$v;
        };
        $each = function ( $v, callable $fn )
        {
            return is_array( $v ) ? array_map( $fn, $v ) : $fn( $v );
        };
        switch ( $name )
        {
            case 'trim':
                return $each( $value, function ( $v ) { return trim( (string)$v ); } );
            case 'lower':
                return $each( $value, function ( $v ) { return mb_strtolower( (string)$v, 'UTF-8' ); } );
            case 'upper':
                return $each( $value, function ( $v ) { return mb_strtoupper( (string)$v, 'UTF-8' ); } );
            case 'title':
                return $each( $value, function ( $v ) { return mb_convert_case( (string)$v, MB_CASE_TITLE, 'UTF-8' ); } );
            case 'strip_tags':
                return $each( $value, function ( $v ) { return trim( html_entity_decode( strip_tags( (string)$v ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); } );
            case 'prefix':
                return $each( $value, function ( $v ) use ( $args ) { return $args['text'] . $v; } );
            case 'suffix':
                return $each( $value, function ( $v ) use ( $args ) { return $v . $args['text']; } );
            case 'replace':
                return $each( $value, function ( $v ) use ( $args ) { return str_replace( $args['search'], $args['replace'], (string)$v ); } );
            case 'regex_replace':
                return $each( $value, function ( $v ) use ( $args )
                {
                    $out = @preg_replace( $args['pattern'], $args['replace'], (string)$v );
                    if ( $out === null )
                        throw new expImportTransformException( 'regex_replace: the pattern is not a valid regular expression' );
                    return $out;
                } );
            case 'truncate':
                return $each( $value, function ( $v ) use ( $args ) { return mb_substr( (string)$v, 0, (int)$args['length'], 'UTF-8' ); } );
            case 'default':
                return ( $value === null || $value === '' || $value === array() ) ? $args['value'] : $value;
            case 'map':
                return $each( $value, function ( $v ) use ( $args )
                {
                    $key = (string)$v;
                    if ( array_key_exists( $key, $args['table'] ) )
                        return $args['table'][$key];
                    return $args['otherwise'] !== '' ? $args['otherwise'] : $v;
                } );
            case 'split':
                if ( is_array( $value ) )
                    return $value;
                $separator = $args['separator'] === '' ? ',' : $args['separator'];
                return array_values( array_filter( array_map( 'trim', explode( $separator, (string)$value ) ), 'strlen' ) );
            case 'join':
                return is_array( $value ) ? implode( $args['separator'], array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) : $text( $value );
            case 'first':
                if ( is_array( $value ) )
                    return $value ? reset( $value ) : '';
                return $value;
            case 'concat':
                $parts = array( $text( $value ) );
                foreach ( $args['paths'] as $path )
                    $parts[] = $record ? $text( $record->get( $path, '' ) ) : '';
                return implode( $args['separator'], array_filter( $parts, 'strlen' ) );
            case 'template':
                return preg_replace_callback( '/\{([A-Za-z0-9_.@:-]+)\}/', function ( $m ) use ( $record, $value, $text )
                {
                    if ( $m[1] === 'value' )
                        return $text( $value );
                    return $record ? $text( $record->get( $m[1], '' ) ) : '';
                }, $args['text'] );
            case 'number':
                return $each( $value, function ( $v ) use ( $args )
                {
                    $s = trim( (string)$v );
                    if ( $s === '' )
                        return '';
                    // 1.234,56 and 1,234.56 both read as 1234.56
                    if ( preg_match( '/^-?[\d.]+,\d+$/', $s ) )
                        $s = str_replace( array( '.', ',' ), array( '', '.' ), $s );
                    else
                        $s = str_replace( array( ',', ' ' ), '', $s );
                    if ( !is_numeric( $s ) )
                        throw new expImportTransformException( "number: '$v' is not a number" );
                    $n = (float)$s;
                    return (int)$args['decimals'] >= 0 ? number_format( $n, (int)$args['decimals'], '.', '' ) : (string)( $n + 0 );
                } );
            case 'int':
                return $each( $value, function ( $v )
                {
                    $s = trim( (string)$v );
                    if ( $s === '' )
                        return '';
                    if ( !is_numeric( str_replace( ',', '.', $s ) ) )
                        throw new expImportTransformException( "int: '$v' is not a number" );
                    return (string)(int)round( (float)str_replace( ',', '.', $s ) );
                } );
            case 'bool':
                return $each( $value, function ( $v )
                {
                    $s = strtolower( trim( (string)$v ) );
                    return in_array( $s, array( '1', 'true', 'yes', 'y', 'on', 'ja', 'oui', 'x' ), true ) ? '1' : '0';
                } );
            case 'date':
                return $each( $value, function ( $v ) use ( $args ) { return self::timestamp( $v, $args ); } );
            case 'date_format':
                return $each( $value, function ( $v ) use ( $args )
                {
                    $ts = self::timestamp( $v, array( 'format' => '', 'timezone' => $args['timezone'] ) );
                    if ( $ts === '' )
                        return '';
                    $date = new DateTime( '@' . $ts );
                    $date->setTimezone( self::zone( $args['timezone'] ) );
                    return $date->format( $args['format'] );
                } );
            case 'slug':
                return $each( $value, function ( $v )
                {
                    $s = strtolower( preg_replace( '/[^A-Za-z0-9]+/', '-', self::ascii( (string)$v ) ) );
                    return trim( $s, '-' );
                } );
            case 'richtext':
                if ( !class_exists( 'SQLIContentUtils' ) || !class_exists( 'eZXMLTextType' ) )
                    throw new expImportTransformException( 'richtext needs a running installation' );
                return SQLIContentUtils::getRichContent( self::richtextImages( $text( $value ), $args ) );
            case 'lookup':
                $fn = self::$lookup;
                if ( !$fn )
                    throw new expImportTransformException( 'lookup needs a running installation' );
                return $each( $value, function ( $v ) use ( $fn, $args )
                {
                    $s = trim( (string)$v );
                    if ( $s === '' )
                        return '';
                    $id = (int)$fn( $args['prefix'] . $s );
                    if ( !$id )
                        throw new expImportTransformException( "lookup: no object with the remote ID '{$args['prefix']}$s'" );
                    return (string)$id;
                } );
            case 'image':
            case 'file':
                $fn = self::$download;
                if ( !$fn )
                    throw new expImportTransformException( "$name needs a running installation" );
                $url = trim( $text( is_array( $value ) ? reset( $value ) : $value ) );
                return $url === '' ? '' : self::optionalDownload( function () use ( $fn, $url, $name ) { return (string)$fn( $url, $name ); }, $name, $url, $args );
            case 'media_object':
                $fn = self::$mediaObject;
                if ( !$fn )
                    throw new expImportTransformException( 'media_object needs a running installation' );
                $url = trim( $text( is_array( $value ) ? reset( $value ) : $value ) );
                if ( $url === '' )
                    return '';
                if ( isset( $args['name'] ) && $args['name'] !== '' && $record )
                    $args['name_value'] = (string)$record->get( (string)$args['name'], '' );
                return self::optionalDownload( function () use ( $fn, $url, $args ) { return (string)(int)$fn( $url, $args ); }, $name, $url, $args );
        }
        throw new expImportTransformException( "unknown transform '$name'" );
    }

    /**
     * The images of an HTML text for the rich text: every <img> whose src is a file of the run (run:<path>, as ezodf's
     * document formats write the pictures of a converted document) or of the source (source:<path in the ZIP>)
     * becomes an image object, made once per file like media_object (below image_parent, default the import's parent;
     * the alt text as its name), embedded where the picture was (an embed of size image_size in the XML). With
     * images=drop, or when a file cannot be had (a note for the row), the picture is left out; an <img> of another
     * address is left to the XML parser, which drops it as before. A dry run makes no object and drops the picture.
     *
     * @param string $html
     * @param array $args images, image_parent, image_size
     * @return string
     */
    public static function richtextImages( $html, array $args )
    {
        $mode = isset( $args['images'] ) && $args['images'] === 'drop' ? 'drop' : 'embed';
        $size = isset( $args['image_size'] ) && preg_match( '/^[a-z0-9_]+$/i', (string)$args['image_size'] ) ? (string)$args['image_size'] : 'medium';
        $fn = self::$mediaObject;
        return (string)preg_replace_callback( '/<img\b[^>]*>/i', function ( $m ) use ( $mode, $size, $fn, $args )
        {
            $src = self::htmlAttribute( $m[0], 'src' );
            if ( !preg_match( '/^(run|source):/', $src ) )
                return $m[0];
            if ( $mode === 'drop' || !$fn )
                return '';
            $alt = trim( self::htmlAttribute( $m[0], 'alt' ) );
            $objectArgs = array( 'parent' => isset( $args['image_parent'] ) ? (int)$args['image_parent'] : 0, 'class' => '', 'attribute' => '',
                                 'name_value' => $alt );
            $id = (int)self::optionalDownload( function () use ( $fn, $src, $objectArgs ) { return (string)(int)$fn( $src, $objectArgs ); },
                                               'richtext image', $src, array() );
            // the form the XML input parser reads as an embed (as the online editor writes it): id eZObject_<id>, alt = the size
            return $id > 0 ? '<img id="eZObject_' . $id . '" src="" alt="' . $size . '" />' : '';
        }, (string)$html );
    }

    /** @return string the decoded value of an attribute of one HTML tag, '' when it has none */
    protected static function htmlAttribute( $tag, $name )
    {
        if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $m ) )
            return html_entity_decode( $m[1] !== '' ? $m[1] : ( isset( $m[2] ) && $m[2] !== '' ? $m[2] : ( isset( $m[3] ) ? $m[3] : '' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return '';
    }

    /**
     * A download of the transforms image, file and media_object: when it cannot be had, the record goes on without it
     * (an empty value, and a note in $notices for the record's row), unless the transform says required.
     *
     * @param callable $download
     * @param string $name the transform
     * @param string $url
     * @param array $args
     * @return string
     * @throws expImportTransformException when the file is required
     */
    protected static function optionalDownload( callable $download, $name, $url, array $args )
    {
        try
        {
            return $download();
        }
        catch ( expImportTransformException $e )
        {
            if ( !empty( $args['required'] ) )
                throw $e;
            self::$notices[] = "$name: " . preg_replace( '/^' . preg_quote( $name, '/' ) . ':\s*/', '', $e->getMessage() ) . ' (' . mb_substr( $url, 0, 120 ) . ')';
            return '';
        }
    }

    /**
     * A text in plain ASCII letters where it can be: accents taken off (é > e), the German and Nordic letters spelled
     * out (ä > ae, ß > ss, ø > o). The same on every server: it does not depend on the locale iconv() would use.
     *
     * @param string $s
     * @return string
     */
    public static function ascii( $s )
    {
        $s = strtr( (string)$s, array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
                                       'æ' => 'ae', 'Æ' => 'Ae', 'ø' => 'o', 'Ø' => 'O', 'œ' => 'oe', 'Œ' => 'Oe', 'ð' => 'd', 'þ' => 'th',
                                       'ł' => 'l', 'Ł' => 'L', 'đ' => 'd', 'Đ' => 'D', 'ı' => 'i' ) );
        if ( class_exists( 'Normalizer' ) )
        {
            $n = Normalizer::normalize( $s, Normalizer::FORM_D );
            if ( is_string( $n ) )
                return (string)preg_replace( '/\p{Mn}+/u', '', $n );
        }
        $from = 'ÀÁÂÃÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÙÚÛÝàáâãåçèéêëìíîïñòóôõùúûýÿčćšžřňťďěůČĆŠŽŘŇŤĎĚŮ';
        $to = 'AAAAACEEEEIIIINOOOOUUUYaaaaaceeeeiiiinoooouuuyyccszrntdeuCCSZRNTDEU';
        return strtr( $s, array_combine( preg_split( '//u', $from, -1, PREG_SPLIT_NO_EMPTY ), str_split( $to ) ) );
    }

    /**
     * A chain as the one-line text of the mapping editor: "trim | date(format=d.m.Y, timezone=Europe/Berlin) |
     * map(table={"D":"Germany"})". An argument whose value has a comma, a parenthesis or a "|" is written as JSON.
     *
     * @param array $chain
     * @return string
     */
    public static function formatChain( array $chain )
    {
        $parts = array();
        foreach ( $chain as $step )
        {
            $name = is_array( $step ) && isset( $step['name'] ) ? (string)$step['name'] : (string)$step;
            $args = is_array( $step ) && isset( $step['args'] ) && is_array( $step['args'] ) ? $step['args'] : array();
            if ( !$args )
            {
                $parts[] = $name;
                continue;
            }
            $list = array();
            foreach ( $args as $key => $value )
            {
                if ( is_array( $value ) || ( is_string( $value ) && ( preg_match( '/[,()|"{}\[\]]/', $value ) || trim( $value ) !== $value || $value === '' ) ) )
                    $value = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                elseif ( is_bool( $value ) )
                    $value = $value ? 'true' : 'false';
                $list[] = $key . '=' . $value;
            }
            $parts[] = $name . '(' . implode( ', ', $list ) . ')';
        }
        return implode( ' | ', $parts );
    }

    /**
     * Reads the one-line text of a chain (formatChain()).
     *
     * @param string $text
     * @return array the chain
     * @throws expImportValidationException the text cannot be read
     */
    public static function parseChain( $text )
    {
        $text = trim( (string)$text );
        if ( $text === '' )
            return array();
        $chain = array();
        foreach ( self::splitTop( $text, '|' ) as $part )
        {
            $part = trim( $part );
            if ( $part === '' )
                continue;
            if ( !preg_match( '/^([a-z][a-z0-9_]*)\s*(?:\((.*)\))?$/s', $part, $m ) )
                throw new expImportValidationException( array( 'transforms' => "cannot read the transform '$part'" ) );
            $step = array( 'name' => $m[1] );
            if ( isset( $m[2] ) && trim( $m[2] ) !== '' )
            {
                $args = array();
                foreach ( self::splitTop( $m[2], ',' ) as $pair )
                {
                    $pair = trim( $pair );
                    if ( $pair === '' )
                        continue;
                    if ( !preg_match( '/^([a-z][a-z0-9_]*)\s*=\s*(.*)$/s', $pair, $p ) )
                        throw new expImportValidationException( array( 'transforms' => "cannot read the argument '$pair' of {$m[1]}" ) );
                    $value = trim( $p[2] );
                    if ( $value !== '' && ( $value[0] === '{' || $value[0] === '[' || $value[0] === '"' ) )
                    {
                        $decoded = json_decode( $value, true );
                        if ( $decoded === null && $value !== 'null' )
                            throw new expImportValidationException( array( 'transforms' => "the value of {$p[1]} in {$m[1]} is not valid JSON" ) );
                        $value = $decoded;
                    }
                    $args[$p[1]] = $value;
                }
                if ( $args )
                    $step['args'] = $args;
            }
            $chain[] = $step;
        }
        return $chain;
    }

    /** @return string[] the text split at $separator outside parentheses, braces, brackets and quotes */
    protected static function splitTop( $text, $separator )
    {
        $out = array();
        $depth = 0;
        $quote = false;
        $current = '';
        $length = strlen( $text );
        for ( $i = 0; $i < $length; $i++ )
        {
            $c = $text[$i];
            if ( $quote )
            {
                $current .= $c;
                if ( $c === '\\' && $i + 1 < $length )
                    $current .= $text[++$i];
                elseif ( $c === '"' )
                    $quote = false;
                continue;
            }
            if ( $c === '"' )
                $quote = true;
            elseif ( $c === '(' || $c === '{' || $c === '[' )
                $depth++;
            elseif ( $c === ')' || $c === '}' || $c === ']' )
                $depth--;
            elseif ( $c === $separator && $depth === 0 )
            {
                $out[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $out[] = $current;
        return $out;
    }

    /** @return DateTimeZone */
    protected static function zone( $name )
    {
        try
        {
            return new DateTimeZone( $name !== '' ? (string)$name : 'UTC' );
        }
        catch ( Exception $e )
        {
            throw new expImportTransformException( "unknown time zone '$name'" );
        }
    }

    /**
     * @param mixed $v
     * @param array $args format, timezone
     * @return string a timestamp, or '' for an empty value
     */
    protected static function timestamp( $v, array $args )
    {
        $s = trim( (string)$v );
        if ( $s === '' )
            return '';
        if ( preg_match( '/^\d{9,11}$/', $s ) )
            return $s;
        $zone = self::zone( isset( $args['timezone'] ) ? $args['timezone'] : 'UTC' );
        $format = isset( $args['format'] ) ? (string)$args['format'] : '';
        // a date of a spreadsheet: days since 1899-12-30, the fraction is the time of day (in the given zone)
        if ( $format === 'excel' )
        {
            if ( !is_numeric( $s ) )
                throw new expImportTransformException( "date: '$s' is not a spreadsheet date" );
            $days = (float)$s;
            $base = new DateTime( '1899-12-30 00:00:00', $zone );
            $base->modify( '+' . (int)floor( $days ) . ' days' );
            return (string)( $base->getTimestamp() + (int)round( ( $days - floor( $days ) ) * 86400 ) );
        }
        if ( $format !== '' )
        {
            $date = DateTime::createFromFormat( '!' . $format, $s, $zone );
            $errors = DateTime::getLastErrors();
            if ( !$date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) )
                throw new expImportTransformException( "date: '$s' does not match the format $format" );
        }
        else
        {
            try
            {
                $date = new DateTime( $s, $zone );
            }
            catch ( Exception $e )
            {
                throw new expImportTransformException( "date: '$s' is not a date" );
            }
        }
        return (string)$date->getTimestamp();
    }
}
