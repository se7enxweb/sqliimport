<?php
/**
 * File containing the expImportCieSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The CSV export of CIE (bccie): the form submissions of an information collector, one row each. The first row names
 * the collector's attributes, the columns Created and ID are always there. The file is UTF-8 (with or without a byte
 * order mark), UTF-16 (as CIE writes it for Excel) or Windows-1252, separated by ";" or ","; all are detected. The
 * preset maps the columns to a class the user picks (testimonials, applications ...) by attribute identifier or name,
 * matched by the submission's ID (remote ID cie-<ID>).
 */
class expImportCieSource extends expImportCsvSource
{
    public static function identifier()
    {
        return 'cie';
    }

    public static function label()
    {
        return 'CIE form submissions (bccie CSV)';
    }

    public static function extensions()
    {
        return array( 'csv', 'txt' );
    }

    public static function description()
    {
        return 'The form submissions CIE (bccie) exports from an information collector: the first row names the form\'s '
             . 'fields, every further row is one submission with its Created date and ID. Choose the class the submissions '
             . 'become (testimonials, applications ...); its attributes are matched to the columns by identifier or name.';
    }

    public static function group()
    {
        return 'platform';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Three testimonials submitted through a form', 'file' => 'cie-testimonials.csv', 'options' => array( 'key' => 'ID' ) ) );
    }

    public static function schema()
    {
        $schema = parent::schema();
        $schema['class'] = array( 'type' => 'class', 'label' => 'Class', 'help' => 'The class a submission becomes; its attributes are matched to the columns' );
        $schema['key']['default'] = 'ID';
        return $schema;
    }

    /** @return resource UTF-16 files as UTF-8 (a stream of the converted text), others as they are */
    protected function openFile()
    {
        $fp = @fopen( $this->file(), 'rb' );
        if ( !$fp )
            throw new expImportSourceException( 'the CSV file cannot be opened' );
        $bom = (string)fread( $fp, 3 );
        $utf16 = strncmp( $bom, "\xFF\xFE", 2 ) === 0 ? 'UTF-16LE' : ( strncmp( $bom, "\xFE\xFF", 2 ) === 0 ? 'UTF-16BE' : '' );
        if ( $utf16 === '' )
        {
            if ( $bom !== "\xEF\xBB\xBF" )
                rewind( $fp );
            return $fp;
        }
        fclose( $fp );
        $text = (string)substr( (string)file_get_contents( $this->file() ), 2 );
        $temp = fopen( 'php://temp', 'w+b' );
        fwrite( $temp, (string)mb_convert_encoding( $text, 'UTF-8', $utf16 ) );
        rewind( $temp );
        $this->detected['encoding'] = $utf16;
        return $temp;
    }

    /** The delimiter of a CIE export is ";" or "," (read from the converted text too). */
    protected function delimiter( $fp )
    {
        $delimiter = isset( $this->config['delimiter'] ) ? (string)$this->config['delimiter'] : 'auto';
        if ( $delimiter !== '' && $delimiter !== 'auto' )
            return parent::delimiter( $fp );
        $pos = ftell( $fp );
        $line = (string)fgets( $fp, 65536 );
        fseek( $fp, $pos );
        $line = preg_replace( '/"[^"]*"/', '', $line );
        $best = substr_count( $line, ';' ) >= substr_count( $line, ',' ) && substr_count( $line, ';' ) > 0 ? ';' : ',';
        $this->detected['delimiter'] = $best;
        return $best;
    }

    /**
     * The preset for a file and the chosen class: the columns matched to the class's attributes by identifier or name.
     *
     * @param array $config with 'file' and 'class'
     * @return array
     */
    public static function presetFor( array $config )
    {
        $class = isset( $config['class'] ) ? trim( (string)$config['class'] ) : '';
        $contentClass = $class !== '' && class_exists( 'eZContentClass' ) ? eZContentClass::fetchByIdentifier( $class ) : null;
        if ( !$contentClass )
            return array();
        $attributes = array();
        foreach ( $contentClass->dataMap() as $identifier => $attribute )
            $attributes[$identifier] = (string)$attribute->attribute( 'name' );
        $source = new self( $config );
        $columns = array();
        foreach ( $source->records() as $record )
        {
            $columns = array_keys( $record->data );
            break;
        }
        return array( 'class' => $class, 'match' => array( 'type' => 'remote_id', 'pattern' => 'cie-{ID}' ),
                      'fields' => self::matchColumns( $columns, $attributes ) );
    }

    /**
     * Columns to attributes: by identifier, else by name (case and spaces do not matter); Created and ID are left out.
     *
     * @param string[] $columns
     * @param array $attributes identifier => name
     * @return array preset lines
     */
    public static function matchColumns( array $columns, array $attributes )
    {
        $norm = function ( $s )
        {
            return preg_replace( '/[^a-z0-9]+/', '', strtolower( expImportTransforms::ascii( (string)$s ) ) );
        };
        $byName = array();
        foreach ( $attributes as $identifier => $name )
        {
            $byName[$norm( $identifier )] = $identifier;
            if ( !isset( $byName[$norm( $name )] ) )
                $byName[$norm( $name )] = $identifier;
        }
        $fields = array();
        $used = array();
        foreach ( $columns as $column )
        {
            if ( in_array( $column, array( 'Created', 'ID' ), true ) )
                continue;
            $key = $norm( $column );
            if ( isset( $byName[$key] ) && !isset( $used[$byName[$key]] ) )
            {
                $fields[] = array( 'source' => (string)$column, 'attribute' => $byName[$key] );
                $used[$byName[$key]] = true;
            }
        }
        return $fields;
    }
}
