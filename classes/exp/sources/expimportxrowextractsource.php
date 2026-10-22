<?php
/**
 * File containing the expImportXrowextractSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The export files of Export (xrowextract): a class export as CSV, JSON or XML, with or without its typed column
 * manifest. The manifest is read from the sidecar file that came with it (an upload of "<file>.manifest.json", or a
 * file handed over by "Re-import this file"), else from inside the file (the JSON envelope {"manifest", "rows"}, the
 * XML <manifest> element). With a manifest, the preset is exact: the class, every attribute column mapped to its
 * attribute, and the remote ID column (written by the "identity" column set) to match existing objects, so a
 * re-import updates instead of creating. Without one, the class is the one of the option "class" and columns are
 * matched to attributes by identifier.
 *
 * Every record is keyed by the file's column keys (the CSV header, the JSON keys, the XML field names).
 */
class expImportXrowextractSource extends expImportAbstractSource
{
    /** @var array|null the manifest, once read (array() for none) */
    protected $manifest = null;

    /** @var string|null csv, json or xml */
    protected $format = null;

    public static function identifier()
    {
        return 'xrowextract';
    }

    public static function label()
    {
        return 'Export (xrowextract) file';
    }

    public static function extensions()
    {
        return array( 'csv', 'json', 'xml' );
    }

    public static function description()
    {
        return 'A class export of Export (xrowextract) as CSV, JSON or XML. Its column manifest names the class and the '
             . 'attribute of every column, so the mapping is made exactly; with the remote ID column, objects are updated '
             . 'instead of created. Use "Re-import this file" on the Jobs page of Export, or upload the file with its '
             . '.manifest.json.';
    }

    public static function group()
    {
        return 'platform';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A folder export as JSON with its manifest inside', 'file' => 'xrowextract-folders.json' ),
                      array( 'label' => 'The same export as CSV (with no manifest the class option names the class)', 'file' => 'xrowextract-folders.csv',
                             'options' => array( 'class' => 'folder' ) ) );
    }

    public static function schema()
    {
        return array(
            'class' => array( 'type' => 'class', 'label' => 'Class', 'help' => 'Used when the file has no manifest: the class whose attributes the columns are named after' ),
            'manifest_file' => array( 'type' => 'string', 'hidden' => true, 'label' => 'Manifest' ),
        ) + parent::commonSchema();
    }

    /** @return string csv, json or xml, from the file's first bytes */
    protected function format()
    {
        if ( $this->format !== null )
            return $this->format;
        $fp = fopen( $this->file(), 'rb' );
        $head = ltrim( (string)fread( $fp, 512 ), "\xEF\xBB\xBF \t\r\n" );
        fclose( $fp );
        $this->format = $head !== '' && $head[0] === '<' ? 'xml' : ( $head !== '' && ( $head[0] === '{' || $head[0] === '[' ) ? 'json' : 'csv' );
        $this->detected['format'] = $this->format;
        return $this->format;
    }

    /** @return array the manifest of the file, array() when it has none */
    public function manifest()
    {
        if ( $this->manifest !== null )
            return $this->manifest;
        $this->manifest = array();
        $name = isset( $this->config['manifest_file'] ) ? basename( (string)$this->config['manifest_file'] ) : '';
        $candidates = array();
        if ( $name !== '' && class_exists( 'eZSys' ) )
            $candidates[] = expImport::storageDirectory( 'uploads' ) . '/' . $name;
        $candidates[] = $this->file() . '.manifest.json';
        foreach ( $candidates as $path )
        {
            if ( is_file( $path ) )
            {
                $data = json_decode( (string)file_get_contents( $path ), true );
                if ( self::isManifest( $data ) )
                    return $this->manifest = $data;
            }
        }
        if ( $this->format() === 'json' )
        {
            $data = json_decode( (string)file_get_contents( $this->file() ), true );
            if ( isset( $data['manifest'] ) && self::isManifest( $data['manifest'] ) )
                $this->manifest = $data['manifest'];
        }
        elseif ( $this->format() === 'xml' )
        {
            $reader = new XMLReader();
            if ( @$reader->open( $this->file(), null, LIBXML_NONET ) )
            {
                while ( @$reader->read() )
                {
                    if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'manifest' )
                    {
                        $data = json_decode( (string)$reader->readString(), true );
                        if ( self::isManifest( $data ) )
                            $this->manifest = $data;
                        break;
                    }
                    if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'object' )
                        break;
                }
                $reader->close();
            }
        }
        if ( $this->manifest )
            $this->detected['manifest'] = 'yes';
        return $this->manifest;
    }

    /** @return bool whether data is an export manifest of xrowextract */
    public static function isManifest( $data )
    {
        return is_array( $data ) && isset( $data['columns'] ) && is_array( $data['columns'] );
    }

    public function records( array $cursor = array() )
    {
        switch ( $this->format() )
        {
            case 'csv':
                $csv = new expImportCsvSource( array( 'file' => $this->file(), 'delimiter' => 'auto', 'enclosure' => '"', 'encoding' => 'auto',
                                                      'header' => true, 'key' => isset( $this->config['key'] ) ? $this->config['key'] : '' ) );
                foreach ( $csv->records( $cursor ) as $record )
                    yield $record;
                return;
            case 'json':
                $data = json_decode( (string)file_get_contents( $this->file() ), true );
                if ( !is_array( $data ) )
                    throw new expImportSourceException( 'the export is not valid JSON: ' . json_last_error_msg() );
                $rows = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : ( array_values( $data ) === $data ? $data : array() );
                $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
                foreach ( $rows as $index => $row )
                {
                    if ( $index < $skip || !is_array( $row ) )
                        continue;
                    yield new expImportRecord( $this->refOf( $row, $index ), $index, $row, array( 'index' => $index + 1 ) );
                }
                return;
        }
        // XML: <export><columns/><manifest/><object><field name="...">value</field>...</object>...</export>
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $reader = new XMLReader();
        if ( !@$reader->open( $this->file(), null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) )
            throw new expImportSourceException( 'the export XML cannot be opened' );
        $previous = libxml_use_internal_errors( true );
        $index = 0;
        try
        {
            $ok = @$reader->read();
            while ( $ok )
            {
                if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'object' )
                {
                    $ok = @$reader->read();
                    continue;
                }
                if ( $index >= $skip )
                {
                    $doc = new DOMDocument();
                    $row = array();
                    if ( @$doc->loadXML( $reader->readOuterXml(), LIBXML_NONET ) )
                        foreach ( $doc->documentElement->getElementsByTagName( 'field' ) as $field )
                            $row[$field->getAttribute( 'name' )] = $field->textContent;
                    yield new expImportRecord( $this->refOf( $row, $index ), $index, $row, array( 'index' => $index + 1 ) );
                }
                $index++;
                $ok = @$reader->next();
            }
            $error = libxml_get_last_error();
            if ( $error && $error->level >= LIBXML_ERR_ERROR )
                throw new expImportSourceException( 'the export XML is not well-formed at line ' . $error->line . ': ' . trim( $error->message ) );
        }
        finally
        {
            libxml_clear_errors();
            libxml_use_internal_errors( $previous );
            $reader->close();
        }
    }

    /**
     * The mapping the file itself gives: from its manifest, else from the class option and the column names.
     *
     * @param array $config the source configuration with 'file' (a local path)
     * @return array a preset, or array() when the file gives none
     */
    public static function presetFor( array $config )
    {
        $source = new self( $config );
        $manifest = $source->manifest();
        if ( $manifest )
            return self::presetFromManifest( $manifest );
        $class = isset( $config['class'] ) ? trim( (string)$config['class'] ) : '';
        if ( $class === '' )
            return array();
        $first = null;
        foreach ( $source->records() as $record )
        {
            $first = $record;
            break;
        }
        if ( !$first )
            return array();
        return self::presetFromColumns( $class, array_keys( $first->data ) );
    }

    /**
     * A preset from an export manifest: the class, attribute columns to their attribute, the remote ID column to match.
     *
     * @param array $manifest
     * @return array
     */
    public static function presetFromManifest( array $manifest )
    {
        $class = !empty( $manifest['class']['identifier'] ) ? (string)$manifest['class']['identifier']
               : ( !empty( $manifest['import']['class'] ) ? (string)$manifest['import']['class'] : '' );
        if ( $class === '' )
            return array();
        $fields = array();
        $match = null;
        $nameKey = '';
        foreach ( $manifest['columns'] as $column )
        {
            $key = isset( $column['key'] ) ? (string)$column['key'] : ( isset( $column['id'] ) ? (string)$column['id'] : '' );
            $id = isset( $column['id'] ) ? (string)$column['id'] : '';
            $kind = isset( $column['kind'] ) ? (string)$column['kind'] : 'attribute';
            if ( $key === '' )
                continue;
            if ( $id === 'ezcontentobject.remote_id' )
                $match = array( 'type' => 'remote_id', 'pattern' => '{' . $key . '}' );
            elseif ( $id === 'ezcontentobject.name' )
                $nameKey = $key;
            elseif ( $kind === 'attribute' && !empty( $column['attribute'] ) )
                $fields[] = array( 'source' => $key, 'attribute' => (string)$column['attribute'] );
        }
        return array(
            'class' => $class,
            'match' => $match ?: array( 'type' => 'remote_id', 'pattern' => 'xrowextract-' . $class . '-{' . ( $nameKey !== '' ? $nameKey : ( $fields ? $fields[0]['source'] : 'id' ) ) . '}' ),
            'fields' => $fields,
        );
    }

    /**
     * A preset without a manifest: the columns named after attribute identifiers of the class.
     *
     * @param string $class
     * @param string[] $columns
     * @return array
     */
    public static function presetFromColumns( $class, array $columns )
    {
        $fields = array();
        $match = null;
        foreach ( $columns as $column )
        {
            $column = (string)$column;
            if ( in_array( strtolower( $column ), array( 'remote_id', 'ezcontentobject.remote_id' ), true ) )
                $match = array( 'type' => 'remote_id', 'pattern' => '{' . $column . '}' );
            elseif ( preg_match( '/^[a-z0-9_]+$/', $column ) )
                $fields[] = array( 'source' => $column, 'attribute' => $column );
        }
        return array( 'class' => $class, 'match' => $match ?: array( 'type' => 'remote_id', 'pattern' => 'xrowextract-' . $class . '-{' . ( $fields ? $fields[0]['source'] : 'id' ) . '}' ),
                      'fields' => $fields );
    }
}
