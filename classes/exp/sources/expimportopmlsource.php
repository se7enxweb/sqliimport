<?php
/**
 * File containing the expImportOpmlSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * OPML files (outline lists; most often the list of feeds a feed reader exports). Every <outline> is one record:
 *
 *   id            the feed address (xmlUrl), else the web address (htmlUrl), else the outline's position (1.2.3)
 *   text, title   the outline's text and title (each falls back to the other)
 *   type          rss, link, include ... as written
 *   xml_url       the feed address, html_url the web address, description, language, version
 *   category      the category attribute, else the path of the enclosing outlines ("News/Tech")
 *   path, depth   the enclosing outlines' texts joined by "/", and how deep the outline is (1 at the top)
 *   parent_id     the id of the enclosing outline ('' at the top)
 *   attributes    every attribute of the outline, as written
 *
 * The file is read with XMLReader, never loading external entities; the cursor is the outline's position in the file.
 * An OPML can also be turned into one RSS import per feed: expImportOpmlSource::feedImports().
 */
class expImportOpmlSource extends expImportAbstractSource
{
    public static function identifier()
    {
        return 'opml';
    }

    public static function label()
    {
        return 'OPML outline / feed list';
    }

    public static function extensions()
    {
        return array( 'opml', 'xml' );
    }

    public static function description()
    {
        return 'The outline format feed readers and podcast apps export their subscriptions in. Every outline is a record, '
             . 'with its feed and web address, its category and its place in the outline. Import the outlines as link '
             . 'objects, or let the import create one RSS import per feed of the list.';
    }

    public static function group()
    {
        return 'content';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A feed list of three feeds in two categories', 'file' => 'feeds.opml' ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'link',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'opml-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'name' ),
                array( 'source' => 'description', 'attribute' => 'description', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'xml_url', 'attribute' => 'location' ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'outlines' => array( 'type' => 'enum', 'label' => 'Outlines', 'default' => 'feeds',
                                 'values' => array( 'feeds' => 'Only feeds (outlines with a feed address)', 'leaves' => 'Only outlines without children',
                                                    'all' => 'Every outline, the categories too' ) ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record. id: the feed address, else the web address, else the position.' ),
        );
    }

    public function records( array $cursor = array() )
    {
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $mode = isset( $this->config['outlines'] ) ? (string)$this->config['outlines'] : 'feeds';
        $reader = new XMLReader();
        if ( !@$reader->open( $this->file(), null, LIBXML_NONET | LIBXML_COMPACT ) )
            throw new expImportSourceException( 'the OPML file cannot be opened' );
        $previous = libxml_use_internal_errors( true );
        $stack = array();      // the open outlines: array( 'text', 'id', 'position' )
        $counters = array( 0 ); // the outlines seen per depth
        $index = 0;
        $seenOpml = false;
        try
        {
            while ( @$reader->read() )
            {
                if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'opml' )
                    $seenOpml = true;
                if ( $reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'outline' )
                {
                    array_pop( $stack );
                    array_pop( $counters );
                    continue;
                }
                if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'outline' )
                    continue;
                $attributes = array();
                if ( $reader->hasAttributes )
                {
                    while ( $reader->moveToNextAttribute() )
                        $attributes[$reader->name] = $reader->value;
                    $reader->moveToElement();
                }
                $empty = $reader->isEmptyElement;
                $depth = count( $stack ) + 1;
                $counters[count( $counters ) - 1]++;
                $position = implode( '.', $counters );
                $data = self::outline( $attributes, $stack, $position, $depth );
                $hasChildren = !$empty && self::hasOutlineChildren( $reader );
                if ( !$empty )
                {
                    $stack[] = array( 'text' => $data['text'], 'id' => $data['id'] );
                    $counters[] = 0;
                }
                $wanted = $mode === 'all' || ( $mode === 'feeds' && $data['xml_url'] !== '' ) || ( $mode === 'leaves' && !$hasChildren );
                if ( !$wanted )
                    continue;
                if ( $index >= $skip )
                    yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
                $index++;
            }
            if ( !$seenOpml )
                throw new expImportSourceException( 'the file is not OPML (no <opml> element)' );
            $error = libxml_get_last_error();
            if ( $error && $error->level >= LIBXML_ERR_ERROR )
                throw new expImportSourceException( 'the OPML is not well-formed: ' . trim( $error->message ) . ' (line ' . $error->line . ')' );
        }
        finally
        {
            libxml_clear_errors();
            libxml_use_internal_errors( $previous );
            $reader->close();
        }
    }

    /**
     * Whether the element the reader stands on has <outline> children. It is told from the file itself (a second
     * reader would cost a pass), so it looks at the raw inner XML only for outlines that are not empty.
     */
    protected static function hasOutlineChildren( XMLReader $reader )
    {
        return strpos( (string)$reader->readInnerXml(), '<outline' ) !== false;
    }

    /** @return array the record of one outline */
    public static function outline( array $attributes, array $stack, $position, $depth )
    {
        $get = function ( $name ) use ( $attributes )
        {
            return isset( $attributes[$name] ) ? trim( (string)$attributes[$name] ) : '';
        };
        $text = $get( 'text' ) !== '' ? $get( 'text' ) : $get( 'title' );
        $title = $get( 'title' ) !== '' ? $get( 'title' ) : $text;
        $path = implode( '/', array_map( function ( $o ) { return $o['text']; }, $stack ) );
        $xmlUrl = $get( 'xmlUrl' );
        $htmlUrl = $get( 'htmlUrl' ) !== '' ? $get( 'htmlUrl' ) : $get( 'url' );
        $parent = $stack ? $stack[count( $stack ) - 1]['id'] : '';
        return array(
            'id' => $xmlUrl !== '' ? $xmlUrl : ( $htmlUrl !== '' ? $htmlUrl : $position ),
            'text' => $text,
            'title' => $title,
            'type' => $get( 'type' ),
            'xml_url' => $xmlUrl,
            'html_url' => $htmlUrl,
            'description' => $get( 'description' ),
            'language' => $get( 'language' ),
            'version' => $get( 'version' ),
            'category' => $get( 'category' ) !== '' ? $get( 'category' ) : $path,
            'path' => $path,
            'depth' => $depth,
            'position' => $position,
            'parent_id' => $parent,
            'attributes' => $attributes,
        );
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }

    /**
     * One RSS import per feed of an OPML file. Each is created disabled, with the feed address as a URL source and
     * the mapping preset of the RSS format below the given parent node, so it is looked at before it runs.
     *
     * @param string $file the OPML file
     * @param int $parentNodeID where the objects of every feed import go
     * @param string $prefix the start of the identifiers ("opml" gives opml-<slug>)
     * @return array list of array( 'identifier', 'name', 'url', 'created' (bool), 'reason' )
     */
    public static function feedImports( $file, $parentNodeID, $prefix = 'opml' )
    {
        $source = new self( array( 'file' => $file, 'outlines' => 'feeds', 'key' => 'id' ) );
        $out = array();
        foreach ( $source->records() as $record )
        {
            $url = (string)$record->data['xml_url'];
            $name = $record->data['title'] !== '' ? (string)$record->data['title'] : $url;
            $identifier = substr( $prefix . '-' . trim( (string)preg_replace( '/[^a-z0-9]+/', '-', strtolower( expImportTransforms::ascii( $name ) ) ), '-' ), 0, 80 );
            $line = array( 'identifier' => $identifier, 'name' => $name, 'url' => $url, 'created' => false, 'reason' => '' );
            $refusal = expImportSourceFiles::refuseUrl( $url );
            if ( $refusal !== null )
                $line['reason'] = $refusal;
            elseif ( expImportDefinition::fetchByIdentifier( $identifier ) )
                $line['reason'] = 'an import with this identifier exists';
            else
            {
                $import = expImportDefinition::create( array( 'identifier' => $identifier, 'name' => $name, 'source_type' => 'rss', 'is_enabled' => 0,
                    'description' => 'Created from the OPML feed list' . ( $record->data['category'] !== '' ? ' (' . $record->data['category'] . ')' : '' ),
                    'source_config' => expImport::json( array( 'origin' => 'url', 'url' => $url, 'key' => 'id' ) ),
                    'target_json' => expImport::json( array( 'parent_node_id' => (int)$parentNodeID ) ) ) );
                $import->store();
                $preset = expImportRssSource::preset();
                if ( $preset )
                {
                    $preset['location'] = array( 'parent_node_id' => (int)$parentNodeID );
                    $preset['language'] = '';
                    $mapping = expImportMapping::saveArray( $preset, $import->attribute( 'id' ) );
                    $import->setAttribute( 'mapping_id', $mapping->attribute( 'id' ) );
                    $import->store();
                }
                $line['created'] = true;
            }
            $out[] = $line;
        }
        return $out;
    }
}
