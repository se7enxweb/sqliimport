<?php
/**
 * File containing the expImportAbstractSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The common part of the file sources: the configuration, the record reference from a key field, the sample and the
 * text encoding.
 */
abstract class expImportAbstractSource implements expImportSourceInterface
{
    /** @var array */
    protected $config;

    /** @var array what the source detected while reading (delimiter, encoding, record path ...) */
    protected $detected = array();

    public function __construct( array $config )
    {
        $this->config = $config;
    }

    /**
     * The options every file source has besides its own.
     * @return array
     */
    public static function commonSchema()
    {
        return array(
            'key' => array( 'type' => 'string', 'label' => 'Key field',
                            'help' => 'The field that names a record (an ID column, a GUID). Empty: the position in the source.' ),
        );
    }

    // ---- the catalogue entry (expImportSourceEntry reads these; a source overrides what it has to say) -------------

    /** @return string what the format is and what one record of it is */
    public static function description()
    {
        return '';
    }

    /** @return string data, content, platform, commerce, media or other */
    public static function group()
    {
        return 'data';
    }

    /** @return array list of array( 'label', 'file' (in the extension's share/examples) or 'url' ) */
    public static function examples()
    {
        return array();
    }

    /**
     * A mapping to start from: array( 'class' => identifier, 'match' => array( 'type', 'pattern' ),
     * 'fields' => list of array( 'source', 'attribute', 'transforms' ) ), or array() when none fits.
     * @return array
     */
    public static function preset()
    {
        return array();
    }

    /** @return array id => array( 'label', 'description', 'extensions', 'defaults', 'examples', 'preset' ) */
    public static function variants()
    {
        return array();
    }

    /** @return string the local file, checked */
    protected function file()
    {
        $file = isset( $this->config['file'] ) ? (string)$this->config['file'] : '';
        if ( $file === '' || !is_file( $file ) || !is_readable( $file ) )
            throw new expImportSourceException( 'the source file cannot be read' . ( $file !== '' ? ': ' . basename( $file ) : '' ) );
        return $file;
    }

    /** @return string the reference of a record: its key field, else its position */
    protected function refOf( array $data, $index )
    {
        $key = isset( $this->config['key'] ) ? (string)$this->config['key'] : '';
        if ( $key !== '' )
        {
            $value = expImportRecord::pathValue( $data, $key, '' );
            if ( is_scalar( $value ) && trim( (string)$value ) !== '' )
                return substr( trim( (string)$value ), 0, 255 );
        }
        return '#' . ( $index + 1 );
    }

    public function count()
    {
        return null;
    }

    public function sample( $limit = 20 )
    {
        $records = array();
        $fields = array();
        foreach ( $this->records() as $record )
        {
            $records[] = $record;
            foreach ( expImportRecord::paths( $record->data ) as $path )
                $fields[$path] = true;
            if ( count( $records ) >= $limit )
                break;
        }
        return array( 'records' => $records, 'fields' => array_keys( $fields ), 'detected' => $this->detected );
    }

    /**
     * Text in UTF-8: a UTF-8 text stays as it is, a text in the configured encoding (or Windows-1252 when it is not
     * valid UTF-8) is converted.
     *
     * @param string $text
     * @param string $encoding the configured encoding, or 'auto'
     * @return string
     */
    public static function toUtf8( $text, $encoding = 'auto' )
    {
        $text = (string)$text;
        $encoding = strtoupper( (string)$encoding );
        if ( $encoding === '' || $encoding === 'AUTO' )
        {
            if ( $text === '' || preg_match( '//u', $text ) )
                return $text;
            $encoding = 'WINDOWS-1252';
        }
        if ( $encoding === 'UTF-8' )
            return $text;
        if ( function_exists( 'mb_convert_encoding' ) )
            return (string)mb_convert_encoding( $text, 'UTF-8', $encoding );
        $out = @iconv( $encoding, 'UTF-8//IGNORE', $text );
        return $out === false ? $text : $out;
    }
}
