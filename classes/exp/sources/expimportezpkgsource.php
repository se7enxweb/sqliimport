<?php
/**
 * File containing the expImportEzpkgSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * A package of Exponential's package system (.ezpkg: a tar.gz with package.xml, ezcontentobject/contentobjects.xml and
 * one XML file per content object, and the images and files in simplefiles/) as a source: every content object is a
 * record with its remote ID, class, name, language, dates, the remote ID of its parent node and its attributes by
 * identifier (fields.<identifier>, the datatype in field_types.<identifier>).
 *
 * The archive is read by a read-only tar reader of its own (through zlib, never extracted, no file is written): the
 * kernel's bootstrap removes the phar stream wrapper on purpose, so an uploaded file can never be opened as a phar,
 * and PharData cannot be used. An archive with an entry outside it (.., an absolute path), a broken header, a file
 * over 64 MB or more than 20000 files is refused, and the XML is read with LIBXML_NONET. An image or file of an
 * object is named "source:<path in the package>", which the transforms image and file take out of the run's own
 * snapshot.
 *
 * The values are in the fromString() form of their datatype where it has one: rich text (ezxmltext) as its XML,
 * dates as timestamps, a link as "address|text", relations as lists of remote IDs.
 */
class expImportEzpkgSource extends expImportAbstractSource
{
    /** @var array|null the files of the archive: name => size */
    protected $entries = null;

    /** @var string the directory of package.xml inside the archive ('' or 'name/') */
    protected $root = '';

    /** The largest file of a package that is read, in bytes */
    const MAX_MEMBER = 67108864;

    /** The most files a package may have */
    const MAX_ENTRIES = 20000;

    /** @var array name => content of the files of the archive that are kept */
    protected $contents = array();

    /** @var string|null the one file to keep (member()), null: the XML files */
    protected $only = null;

    /** @var array simple-file key => path in the archive */
    protected $simpleFiles = array();

    public static function identifier()
    {
        return 'ezpkg';
    }

    public static function label()
    {
        return 'Exponential package (.ezpkg)';
    }

    public static function extensions()
    {
        return array( 'ezpkg', 'tgz' );
    }

    public static function group()
    {
        return 'platform';
    }

    public static function description()
    {
        return 'A content package of an Exponential site (Setup, Packages, Export): every content object of the package is '
             . 'a record with its class, remote ID, name, language and its attributes by identifier. The mapping is made '
             . 'from the package (the class of its objects, every attribute by identifier, matched by the remote ID), so '
             . 'importing the package again updates the objects.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A folder and three articles with an image', 'file' => 'exponential-package.ezpkg',
                             'options' => array( 'class' => 'article' ) ) );
    }

    public static function schema()
    {
        return array(
            'class' => array( 'type' => 'class', 'label' => 'Class', 'help' => 'Only the objects of this class (empty: every object of the package)' ),
            'language' => array( 'type' => 'string', 'label' => 'Language', 'pattern' => '/^([a-z]{3}-[A-Z]{2})?$/',
                                 'help' => 'The translation to read, e.g. ger-DE (empty: the initial language of each object)' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'remote_id',
                            'help' => 'The field that names a record; remote_id is the object\'s remote ID.' ),
        );
    }

    /**
     * The files of the archive, checked: no entry may point outside it.
     *
     * @return array name => size
     * @throws expImportSourceException
     */
    protected function entries()
    {
        if ( $this->entries !== null )
            return $this->entries;
        $file = $this->file();
        if ( filesize( $file ) > 0 && class_exists( 'expImportSourceFiles' ) && class_exists( 'eZINI' ) && filesize( $file ) > expImportSourceFiles::maxBytes() )
            throw new expImportSourceException( 'the package is larger than [SourceSettings] MaxSourceMegabytes' );
        $entries = array();
        $this->contents = array();
        $gz = @gzopen( $file, 'rb' );
        if ( !$gz )
            throw new expImportSourceException( 'the file is not a package (a tar.gz archive): ' . basename( $file ) );
        try
        {
            $longName = null;
            while ( true )
            {
                $header = (string)gzread( $gz, 512 );
                if ( strlen( $header ) < 512 || trim( $header, "\0" ) === '' )
                    break;
                if ( !self::validHeader( $header ) )
                    throw new expImportSourceException( 'the file is not a package (a tar.gz archive): ' . basename( $file ) );
                $size = (int)octdec( trim( substr( $header, 124, 12 ), " \0" ) );
                $type = $header[156];
                $name = rtrim( substr( $header, 0, 100 ), "\0" );
                if ( substr( $header, 257, 5 ) === 'ustar' && trim( substr( $header, 345, 155 ), "\0" ) !== '' )
                    $name = rtrim( substr( $header, 345, 155 ), "\0" ) . '/' . $name;
                $isFile = in_array( $type, array( '0', "\0" ), true );
                if ( $type !== 'L' && $longName !== null )
                {
                    $name = $longName;
                    $longName = null;
                }
                $name = (string)preg_replace( '#^\./#', '', $name );
                if ( $isFile && !self::safeName( $name ) )
                    throw new expImportSourceException( 'the package has an entry outside it, which is refused: ' . substr( $name, 0, 80 ) );
                if ( ( $isFile || $type === 'L' ) && $size > self::MAX_MEMBER )
                    throw new expImportSourceException( 'the package has a file larger than ' . ( self::MAX_MEMBER >> 20 ) . ' MB: ' . substr( $name, 0, 80 ) );
                // only what is needed is kept: the XML for the records, one file for member()
                $keep = $type === 'L' || ( $isFile && ( $this->only !== null ? $name === $this->only : (bool)preg_match( '/\.xml$/i', $name ) ) );
                $data = '';
                if ( $keep && $size > 0 )
                {
                    $data = (string)gzread( $gz, $size );
                    if ( strlen( $data ) !== $size )
                        throw new expImportSourceException( 'the package is cut short' );
                }
                elseif ( $size > 0 )
                    gzseek( $gz, $size, SEEK_CUR );
                $pad = ( 512 - $size % 512 ) % 512;
                if ( $pad )
                    gzread( $gz, $pad );
                if ( $type === 'L' )
                {
                    // a GNU long name: it names the next entry
                    $longName = rtrim( $data, "\0" );
                    continue;
                }
                if ( !$isFile )
                    continue;
                if ( count( $entries ) >= self::MAX_ENTRIES )
                    throw new expImportSourceException( 'the package has more than ' . self::MAX_ENTRIES . ' files' );
                $entries[$name] = $size;
                if ( $keep )
                    $this->contents[$name] = $data;
            }
        }
        finally
        {
            gzclose( $gz );
        }
        if ( !$entries )
            throw new expImportSourceException( 'the file is not a package (a tar.gz archive): ' . basename( $file ) );
        $this->root = '';
        if ( !isset( $entries['package.xml'] ) )
        {
            $found = null;
            foreach ( array_keys( $entries ) as $name )
                if ( preg_match( '#^([^/]+/)package\.xml$#', $name, $m ) )
                    $found = $m[1];
            if ( $found === null )
                throw new expImportSourceException( isset( $entries['package.json'] ) ? 'the package is in the JSON format; export it as XML'
                                                                                      : 'the archive has no package.xml: it is not a package' );
            $this->root = $found;
        }
        $this->detected['format'] = 'ezpkg';
        return $this->entries = $entries;
    }

    /** @return bool whether a name of an archive entry stays inside the archive */
    public static function safeName( $name )
    {
        $name = (string)$name;
        if ( $name === '' || $name[0] === '/' || $name[0] === '\\' || strpos( $name, "\0" ) !== false || preg_match( '#^[a-z]:#i', $name ) )
            return false;
        foreach ( preg_split( '#[/\\\\]#', $name ) as $part )
            if ( $part === '..' )
                return false;
        return true;
    }

    /**
     * The content of an XML file of the package (the other files are read with member()).
     *
     * @param string $name its path inside the package (relative to package.xml)
     * @return string|null null when the package has no such file
     */
    public function read( $name )
    {
        $entries = $this->entries();
        $name = $this->root . ltrim( (string)$name, '/' );
        if ( !self::safeName( $name ) || !isset( $entries[$name] ) )
            return null;
        return isset( $this->contents[$name] ) ? $this->contents[$name] : null;
    }

    /** @return bool whether a block is a tar header: its checksum fits */
    protected static function validHeader( $header )
    {
        $stored = trim( substr( $header, 148, 8 ), " \0" );
        if ( $stored === '' || !preg_match( '/^[0-7]+$/', $stored ) )
            return false;
        $sum = 0;
        for ( $i = 0; $i < 512; $i++ )
            $sum += ( $i >= 148 && $i < 156 ) ? 32 : ord( $header[$i] );
        return $sum === octdec( $stored );
    }

    /**
     * A file of a package archive, for the transforms image and file ("source:<path>").
     *
     * @param string $archive the package
     * @param string $member the path inside the archive, as the records name it
     * @return string|null
     */
    public static function member( $archive, $member )
    {
        $member = ltrim( (string)$member, '/' );
        if ( !self::safeName( $member ) )
            return null;
        $source = new self( array( 'file' => $archive ) );
        $source->only = $member;
        $entries = $source->entries();
        return isset( $entries[$member], $source->contents[$member] ) ? $source->contents[$member] : null;
    }

    /**
     * An XML document of the package, parsed without the network.
     *
     * @param string $name
     * @return DOMDocument
     */
    protected function document( $name )
    {
        $text = $this->read( $name );
        if ( $text === null )
            throw new expImportSourceException( "the package has no $name" );
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors( true );
        $ok = $doc->loadXML( $text, LIBXML_NONET | LIBXML_COMPACT );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );
        if ( !$ok || !$doc->documentElement )
            throw new expImportSourceException( "$name of the package is not well-formed XML" );
        return $doc;
    }

    /** @return array the package's definition: name, summary, simple files */
    public function package()
    {
        $doc = $this->document( 'package.xml' );
        $root = $doc->documentElement;
        $text = function ( $tag ) use ( $root )
        {
            foreach ( $root->childNodes as $child )
                if ( $child instanceof DOMElement && $child->localName === $tag )
                    return trim( $child->textContent );
            return '';
        };
        $this->simpleFiles = array();
        foreach ( $doc->getElementsByTagName( 'simple-file' ) as $node )
        {
            $path = (string)$node->getAttribute( 'package-path' );
            if ( $node->getAttribute( 'key' ) !== '' && self::safeName( $path ) )
                $this->simpleFiles[(string)$node->getAttribute( 'key' )] = $path;
        }
        $items = array();
        foreach ( $doc->getElementsByTagName( 'install' ) as $install )
        {
            foreach ( $install->getElementsByTagName( 'item' ) as $item )
                if ( $item->getAttribute( 'type' ) === 'ezcontentobject' )
                    $items[] = trim( (string)$item->getAttribute( 'sub-directory' ), '/' ) . '/' . $item->getAttribute( 'filename' ) . '.xml';
            break;
        }
        return array( 'name' => $text( 'name' ), 'summary' => $text( 'summary' ), 'items' => $items );
    }

    /**
     * The object elements of the package, in its install order.
     *
     * @return Generator DOMElement
     */
    protected function objects()
    {
        $package = $this->package();
        if ( !$package['items'] )
            throw new expImportSourceException( 'the package has no content objects' );
        foreach ( $package['items'] as $item )
        {
            $doc = $this->document( $item );
            $dir = strpos( $item, '/' ) !== false ? substr( $item, 0, strrpos( $item, '/' ) + 1 ) : '';
            // the objects inline (older packages), or one file per object
            $inline = $doc->getElementsByTagNameNS( 'http://ez.no/ezobject', 'object' );
            if ( $inline->length )
            {
                foreach ( $inline as $object )
                    yield $object;
                continue;
            }
            foreach ( $doc->getElementsByTagName( 'object-file' ) as $file )
            {
                $name = basename( (string)$file->getAttribute( 'filename' ) );
                if ( $name === '' )
                    continue;
                $object = $this->document( $dir . $name )->documentElement;
                if ( $object->localName === 'object' )
                    yield $object;
            }
        }
    }

    public function records( array $cursor = array() )
    {
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $class = isset( $this->config['class'] ) ? trim( (string)$this->config['class'] ) : '';
        $language = isset( $this->config['language'] ) ? trim( (string)$this->config['language'] ) : '';
        $index = 0;
        foreach ( $this->objects() as $object )
        {
            $data = $this->objectRecord( $object, $language );
            if ( $data === null || ( $class !== '' && $data['class_identifier'] !== $class ) )
                continue;
            if ( $index >= $skip )
                yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'index' => $index + 1 ) );
            $index++;
        }
    }

    public function count()
    {
        $n = 0;
        foreach ( $this->records() as $record )
            $n++;
        return $n;
    }

    /**
     * One object as a record.
     *
     * @param DOMElement $object
     * @param string $language the translation to read ('' for the initial language)
     * @return array|null null for an object without a version
     */
    public function objectRecord( DOMElement $object, $language = '' )
    {
        $ns = 'http://ez.no/ezobject';
        $versions = $object->getElementsByTagNameNS( 'http://ez.no/object/', 'version' );
        if ( !$versions->length )
            return null;
        $version = $versions->item( $versions->length - 1 );
        $initial = (string)$object->getAttribute( 'initial_language' );
        $translations = array();
        foreach ( $version->getElementsByTagNameNS( 'http://ez.no/object/', 'object-translation' ) as $translation )
            $translations[(string)$translation->getAttribute( 'language' )] = $translation;
        if ( !$translations )
            return null;
        $chosen = $language !== '' && isset( $translations[$language] ) ? $language : ( isset( $translations[$initial] ) ? $initial : (string)key( $translations ) );
        if ( $language !== '' && !isset( $translations[$language] ) )
            return null;
        $translation = $translations[$chosen];
        $date = function ( $value )
        {
            $ts = $value !== '' ? strtotime( $value ) : false;
            return $ts === false ? 0 : (int)$ts;
        };
        $parent = '';
        $node = '';
        $parents = array();
        foreach ( $version->getElementsByTagName( 'node-assignment' ) as $assignment )
        {
            $parents[] = (string)$assignment->getAttribute( 'parent-node-remote-id' );
            if ( $assignment->getAttribute( 'is-main-node' ) === '1' || $parent === '' )
            {
                $parent = (string)$assignment->getAttribute( 'parent-node-remote-id' );
                $node = (string)$assignment->getAttribute( 'remote-id' );
            }
        }
        $name = (string)$translation->getAttribute( 'object_name' );
        $data = array(
            'remote_id' => (string)$object->getAttribute( 'remote_id' ),
            'object_id' => (int)$object->getAttributeNS( $ns, 'id' ),
            'name' => $name !== '' ? $name : (string)$object->getAttribute( 'name' ),
            'class_identifier' => (string)$object->getAttributeNS( $ns, 'class_identifier' ),
            'class_remote_id' => (string)$object->getAttribute( 'class_remote_id' ),
            'language' => $chosen,
            'languages' => array_keys( $translations ),
            'published' => $date( (string)$object->getAttributeNS( $ns, 'published' ) ),
            'modified' => $date( (string)$object->getAttributeNS( $ns, 'modified' ) ),
            'section_id' => (int)$object->getAttributeNS( $ns, 'section_id' ),
            'node_remote_id' => $node,
            'parent_node_remote_id' => $parent,
            'parent_node_remote_ids' => $parents,
            'fields' => array(),
            'field_types' => array(),
        );
        foreach ( $translation->childNodes as $attribute )
        {
            if ( !$attribute instanceof DOMElement || $attribute->localName !== 'attribute' )
                continue;
            $identifier = (string)$attribute->getAttributeNS( $ns, 'identifier' );
            if ( $identifier === '' )
                continue;
            $type = (string)$attribute->getAttribute( 'type' );
            $data['fields'][$identifier] = $this->attributeValue( $attribute, $type );
            $data['field_types'][$identifier] = $type;
            if ( $type === 'ezimage' && $attribute->getAttribute( 'alternative-text' ) !== '' )
                $data['fields'][$identifier . '_alt'] = (string)$attribute->getAttribute( 'alternative-text' );
        }
        return $data;
    }

    /**
     * The value of an attribute element in the fromString() form of its datatype.
     *
     * @param DOMElement $attribute
     * @param string $type
     * @return mixed
     */
    protected function attributeValue( DOMElement $attribute, $type )
    {
        $child = function ( $tag ) use ( $attribute )
        {
            foreach ( $attribute->getElementsByTagName( $tag ) as $node )
                return $node;
            return null;
        };
        $text = function ( $tag ) use ( $child )
        {
            $node = $child( $tag );
            return $node ? (string)$node->textContent : '';
        };
        switch ( $type )
        {
            case 'ezstring':
            case 'eztext':
                return $text( 'text' );
            case 'ezinteger':
            case 'ezfloat':
            case 'ezboolean':
                return trim( $text( 'value' ) );
            case 'ezemail':
                return trim( $text( 'email' ) );
            case 'ezkeyword':
                return trim( $text( 'keyword-string' ) );
            case 'ezdatetime':
            case 'ezdate':
            case 'eztime':
                $value = trim( $text( $type === 'ezdatetime' ? 'date_time' : ( $type === 'ezdate' ? 'date' : 'time' ) ) );
                if ( $value === '' )
                    return '';
                $ts = strtotime( $value );
                return $ts === false ? $value : (string)$ts;
            case 'ezurl':
                $url = urldecode( trim( $text( 'url' ) ) );
                $label = trim( $text( 'text' ) );
                return $url === '' ? '' : ( $label !== '' ? $url . '|' . $label : $url );
            case 'ezxmltext':
                $section = $child( 'section' );
                if ( !$section )
                    return '';
                $doc = new DOMDocument( '1.0', 'utf-8' );
                $doc->appendChild( $doc->importNode( $section, true ) );
                return (string)$doc->saveXML();
            case 'ezimage':
                $key = (string)$attribute->getAttribute( 'image-file-key' );
                return $key !== '' && isset( $this->simpleFiles[$key] ) ? 'source:' . $this->root . $this->simpleFiles[$key] : '';
            case 'ezbinaryfile':
            case 'ezmedia':
                $node = $child( $type === 'ezmedia' ? 'media-file' : 'binary-file' );
                $key = $node ? (string)$node->getAttribute( 'filekey' ) : '';
                return $key !== '' && isset( $this->simpleFiles[$key] ) ? 'source:' . $this->root . $this->simpleFiles[$key] : '';
            case 'ezobjectrelation':
                return trim( $text( 'related-object-remote-id' ) );
            case 'ezobjectrelationlist':
                $ids = array();
                foreach ( $attribute->getElementsByTagName( 'relation-item' ) as $item )
                    if ( $item->getAttribute( 'contentobject-remote-id' ) !== '' )
                        $ids[] = (string)$item->getAttribute( 'contentobject-remote-id' );
                return $ids;
            case 'eztags':
                $keywords = trim( $text( 'keyword-string' ) );
                return $keywords === '' ? array() : array_values( array_filter( array_map( 'trim', explode( '|#', $keywords ) ), 'strlen' ) );
        }
        // any other datatype: its text, or its XML when it has elements
        foreach ( $attribute->childNodes as $node )
        {
            if ( $node instanceof DOMElement )
                return (string)$attribute->ownerDocument->saveXML( $node );
        }
        return trim( (string)$attribute->textContent );
    }

    /**
     * The mapping the package itself gives: the class of its objects (or of the option class), every attribute by
     * identifier, and the remote ID to match, so importing the package again updates its objects.
     *
     * @param array $config the source configuration with 'file' (a local path)
     * @return array a preset, or array() when the objects have no common class
     */
    public static function presetFor( array $config )
    {
        $source = new self( $config );
        $class = isset( $config['class'] ) ? trim( (string)$config['class'] ) : '';
        $types = array();
        foreach ( $source->records() as $record )
        {
            if ( $class === '' )
                $class = $record->data['class_identifier'];
            elseif ( $record->data['class_identifier'] !== $class )
                return array();
            $types += $record->data['field_types'];
        }
        if ( $class === '' || !$types )
            return array();
        return self::presetFromTypes( $class, $types );
    }

    /**
     * A preset for a class from the datatypes of its attributes: the datatypes whose values the package gives in
     * their fromString() form are mapped, images and files through the run's snapshot. Relations name objects of
     * the source site and are left to the mapping.
     *
     * @param string $class
     * @param array $types identifier => datatype
     * @return array
     */
    public static function presetFromTypes( $class, array $types )
    {
        $fields = array();
        foreach ( $types as $identifier => $type )
        {
            $line = array( 'source' => 'fields.' . $identifier, 'attribute' => (string)$identifier );
            switch ( $type )
            {
                case 'ezimage':
                    $line['transforms'] = array( array( 'name' => 'image' ) );
                    break;
                case 'ezbinaryfile':
                case 'ezmedia':
                    $line['transforms'] = array( array( 'name' => 'file' ) );
                    break;
                case 'ezstring':
                case 'eztext':
                case 'ezxmltext':
                case 'ezinteger':
                case 'ezfloat':
                case 'ezboolean':
                case 'ezemail':
                case 'ezkeyword':
                case 'ezdatetime':
                case 'ezdate':
                case 'eztime':
                case 'ezurl':
                case 'ezisbn':
                case 'ezcountry':
                    break;
                default:
                    continue 2;
            }
            $line['datatypes'] = array( $type );
            $fields[] = $line;
            // a site whose class has an object relation where the package has an image gets an image object
            if ( $type === 'ezimage' )
                $fields[] = array( 'source' => 'fields.' . $identifier, 'attribute' => (string)$identifier,
                                   'transforms' => array( array( 'name' => 'media_object' ) ), 'datatypes' => array( 'ezobjectrelation' ) );
        }
        return array( 'class' => (string)$class, 'match' => array( 'type' => 'remote_id', 'pattern' => '{remote_id}' ), 'fields' => $fields );
    }
}
