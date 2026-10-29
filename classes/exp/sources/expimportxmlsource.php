<?php
/**
 * File containing the expImportXmlSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * XML files, read as a stream with XMLReader: every element at the record path is one record, turned into an array
 * (attributes as "@name", child elements by name, repeated children as a list, the text of an element with
 * attributes or children as "#text"). External entities are never loaded, and an XSD can check the file first.
 *
 * The record path is a list of element names separated by "/" ("catalog/book", "channel/item"); it matches the end
 * of an element's path, so "book" finds every book. Namespaced elements are named with their prefix ("dc:creator").
 */
class expImportXmlSource extends expImportAbstractSource
{
    public static function identifier()
    {
        return 'xml';
    }

    public static function label()
    {
        return 'XML file';
    }

    public static function extensions()
    {
        return array( 'xml' );
    }

    public static function description()
    {
        return 'Any XML file: name the element of one record (book, catalog/book), and its attributes (@id) and child '
             . 'elements become the fields. Read as a stream, external entities are never loaded, and an XSD can check it first.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A catalogue of two books', 'file' => 'books.xml', 'options' => array( 'record_path' => 'book', 'key' => '@id' ) ) );
    }

    public static function schema()
    {
        return array(
            'record_path' => array( 'type' => 'string', 'label' => 'Record element', 'required' => true,
                                    'help' => 'The element of one record, e.g. book or catalog/book' ),
            'xsd' => array( 'type' => 'path', 'label' => 'XSD schema', 'help' => 'A schema file the source must validate against (optional)' ),
        ) + parent::commonSchema();
    }

    /** @return XMLReader */
    protected function reader()
    {
        $file = $this->file();
        $reader = new XMLReader();
        if ( !@$reader->open( $file, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) )
            throw new expImportSourceException( 'the XML file cannot be opened' );
        $xsd = isset( $this->config['xsd'] ) ? (string)$this->config['xsd'] : '';
        if ( $xsd !== '' )
        {
            if ( !is_file( $xsd ) )
                throw new expImportSourceException( 'the XSD file cannot be read' );
            $reader->setSchema( $xsd );
        }
        return $reader;
    }

    /** @return string[] the record path, as element names */
    protected function recordPath()
    {
        $path = trim( isset( $this->config['record_path'] ) ? (string)$this->config['record_path'] : '', '/ ' );
        if ( $path === '' )
            throw new expImportSourceException( 'the record element is not set' );
        return explode( '/', $path );
    }

    public function records( array $cursor = array() )
    {
        $want = $this->recordPath();
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $reader = $this->reader();
        $path = array();
        $index = 0;
        $previous = libxml_use_internal_errors( true );
        try
        {
            // after next() the reader already stands on the following node: it is looked at without read()
            $ok = @$reader->read();
            while ( $ok )
            {
                if ( $reader->nodeType !== XMLReader::ELEMENT )
                {
                    $ok = @$reader->read();
                    continue;
                }
                $path = array_slice( $path, 0, $reader->depth );
                $path[] = $reader->name;
                if ( array_slice( $path, -count( $want ) ) !== $want )
                {
                    $ok = @$reader->read();
                    continue;
                }
                if ( $index < $skip )
                {
                    $index++;
                    $ok = @$reader->next();
                    continue;
                }
                $xml = $reader->readOuterXml();
                $data = self::elementToArray( $xml );
                yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
                $index++;
                $ok = @$reader->next();
            }
            $error = libxml_get_last_error();
            libxml_clear_errors();
            if ( $error && $error->level >= LIBXML_ERR_ERROR )
                throw new expImportSourceException( 'the XML is not valid at line ' . $error->line . ': ' . trim( $error->message ) );
        }
        finally
        {
            $reader->close();
            libxml_use_internal_errors( $previous );
        }
    }

    /**
     * An element as an array.
     *
     * @param string $xml the element's XML
     * @return array
     */
    public static function elementToArray( $xml )
    {
        $previous = libxml_use_internal_errors( true );
        $doc = new DOMDocument();
        $ok = $doc->loadXML( $xml, LIBXML_NONET | LIBXML_NOCDATA );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( !$ok || !$doc->documentElement )
            return array();
        $value = self::nodeToValue( $doc->documentElement );
        return is_array( $value ) ? $value : array( '#text' => $value );
    }

    /** @return array|string */
    protected static function nodeToValue( DOMElement $element )
    {
        $out = array();
        foreach ( $element->attributes as $attribute )
            $out['@' . $attribute->nodeName] = $attribute->nodeValue;
        $text = '';
        $hasChildren = false;
        foreach ( $element->childNodes as $child )
        {
            if ( $child instanceof DOMElement )
            {
                $hasChildren = true;
                $name = $child->nodeName;
                $value = self::nodeToValue( $child );
                if ( !array_key_exists( $name, $out ) )
                    $out[$name] = $value;
                else
                {
                    if ( !is_array( $out[$name] ) || !array_key_exists( 0, $out[$name] ) )
                        $out[$name] = array( $out[$name] );
                    $out[$name][] = $value;
                }
            }
            elseif ( $child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE )
                $text .= $child->nodeValue;
        }
        $text = trim( $text );
        if ( !$out )
            return $text;
        if ( $text !== '' && !$hasChildren )
            $out['#text'] = $text;
        elseif ( $text !== '' )
            $out['#text'] = $text;
        return $out;
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }
}
