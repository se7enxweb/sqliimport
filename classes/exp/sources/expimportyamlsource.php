<?php
/**
 * File containing the expImportYamlSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * YAML and TOML data files: a list of records at the top or at a record path (products, data.items), as static site
 * generators, configuration-driven catalogues and data repositories keep them. In TOML, an array of tables
 * ([[products]]) is the usual list. A mapping of mappings (id: {...}) is a list too: its keys become the field "_key".
 * Both are read by expImportYaml, the same on every server. A file is read whole: very large data is better as NDJSON.
 */
class expImportYamlSource extends expImportAbstractSource
{
    /** @var array|null */
    protected $list = null;

    public static function identifier()
    {
        return 'yaml';
    }

    public static function label()
    {
        return 'YAML data file';
    }

    public static function extensions()
    {
        return array( 'yaml', 'yml', 'toml' );
    }

    public static function description()
    {
        return 'A YAML file with a list of records at the top or at a path such as products: the data files of static site '
             . 'generators and of catalogues kept in a repository. Nested values are mapped by their path (price.amount).';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Three teas with nested prices and tags', 'file' => 'teas.yaml', 'options' => array( 'record_path' => 'teas' ) ) );
    }

    public static function variants()
    {
        return array(
            'toml' => array(
                'label' => 'TOML data file',
                'description' => 'A TOML file whose array of tables ([[teas]]) or array at a path is the list of records, as Hugo data '
                               . 'files and many tool configurations are written.',
                'extensions' => array( 'toml' ),
                'defaults' => array( 'format' => 'toml' ),
                'examples' => array( array( 'label' => 'Three teas as an array of tables', 'file' => 'teas.toml', 'options' => array( 'record_path' => 'teas' ) ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'format' => array( 'type' => 'enum', 'label' => 'Format', 'default' => 'auto',
                               'values' => array( 'auto' => 'Detect by the file name', 'yaml' => 'YAML', 'toml' => 'TOML' ) ),
            'record_path' => array( 'type' => 'string', 'label' => 'Record list',
                                    'help' => 'The path of the list of records, e.g. products or data.items; empty: the top' ),
        ) + parent::commonSchema();
    }

    /** @return array the list of records */
    protected function decodedList()
    {
        if ( $this->list !== null )
            return $this->list;
        $file = $this->file();
        $format = isset( $this->config['format'] ) ? (string)$this->config['format'] : 'auto';
        if ( $format !== 'yaml' && $format !== 'toml' )
            $format = preg_match( '/\.toml$/i', isset( $this->config['original_name'] ) ? (string)$this->config['original_name'] : $file ) ? 'toml' : 'yaml';
        $this->detected['format'] = $format;
        $text = self::toUtf8( (string)file_get_contents( $file ) );
        $data = $format === 'toml' ? expImportYaml::toml( $text ) : expImportYaml::parse( $text );
        if ( !is_array( $data ) )
            throw new expImportSourceException( 'the file holds no list or mapping' );
        $path = isset( $this->config['record_path'] ) ? trim( (string)$this->config['record_path'] ) : '';
        if ( $path !== '' )
        {
            $data = expImportRecord::pathValue( $data, $path, null );
            if ( !is_array( $data ) )
                throw new expImportSourceException( "the file has no list at '$path'" );
        }
        if ( array_values( $data ) !== $data )
        {
            // a mapping of mappings: its keys name the records
            $list = array();
            foreach ( $data as $key => $value )
                $list[] = is_array( $value ) ? array( '_key' => (string)$key ) + $value : array( '_key' => (string)$key, 'value' => $value );
            $data = $list;
        }
        return $this->list = $data;
    }

    public function records( array $cursor = array() )
    {
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        foreach ( $this->decodedList() as $index => $data )
        {
            if ( $index < $skip )
                continue;
            if ( !is_array( $data ) )
                $data = array( 'value' => $data );
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
        }
    }

    public function count()
    {
        return count( $this->decodedList() );
    }
}
