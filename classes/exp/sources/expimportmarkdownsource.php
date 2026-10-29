<?php
/**
 * File containing the expImportMarkdownSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Markdown files with front matter, one file or a ZIP of a folder (a Jekyll or Hugo site, an Obsidian vault, a
 * documentation folder). Every .md / .markdown file is one record:
 *
 *   id            the front matter's id or slug, else the path without extension (posts/2026-03-20-opening)
 *   path, folder, file   where the file is in the ZIP
 *   slug          the front matter's slug, else the file name without a Jekyll date and extension
 *   title         the front matter's title, else the first heading, else the file name
 *   date          the front matter's date, else the date of a Jekyll file name (2026-03-20-...)
 *   draft         true for draft: true or published: false
 *   tags, categories   lists (a text "a, b" or "a b" is split)
 *   summary       the front matter's description, summary or excerpt, else the first paragraph as text
 *   markdown      the body as written; html: the body as HTML (expImportMarkdown); text: the body without markup
 *   links         Obsidian [[links]] of the body; images: the image paths of the body
 *   front         the whole front matter; every key of it is also a field of its own unless a field above has its name
 *
 * Files in folders starting with "_" or "." other than Jekyll's _posts and _drafts (layouts, includes, .obsidian) are
 * left out, so are README and index files when the option says so. The cursor is the position in the sorted list.
 */
class expImportMarkdownSource extends expImportAbstractSource
{
    /** @var string[]|null the Markdown files of the ZIP to read, sorted */
    protected $files = null;

    /** @var string the folder every Markdown file of the ZIP is in ("site/"), '' if none */
    protected $root = '';

    public static function identifier()
    {
        return 'markdown';
    }

    public static function label()
    {
        return 'Markdown files (folder as ZIP)';
    }

    public static function extensions()
    {
        return array( 'zip', 'md', 'markdown' );
    }

    public static function description()
    {
        return 'Markdown with front matter, one file or a folder as ZIP: a Jekyll or Hugo site, an Obsidian vault, a '
             . 'documentation folder. Every Markdown file is a record with its title, date, tags, summary, the body as '
             . 'HTML and every front matter key.';
    }

    public static function group()
    {
        return 'content';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A small site of three Markdown files (Jekyll, Hugo and Obsidian style) as ZIP', 'file' => 'markdown-site.zip' ),
                      array( 'label' => 'One Markdown file with YAML front matter', 'file' => 'post.md' ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'article',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'md-{id}' ),
            'fields' => array(
                array( 'source' => 'title', 'attribute' => 'title' ),
                array( 'source' => 'summary', 'attribute' => 'intro', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'html', 'attribute' => 'body', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'date', 'attribute' => 'publish_date', 'transforms' => array( array( 'name' => 'date' ) ), 'datatypes' => array( 'ezdate', 'ezdatetime' ) ),
                array( 'source' => 'tags', 'attribute' => 'tags', 'transforms' => array( array( 'name' => 'join', 'args' => array( 'separator' => ', ' ) ) ), 'datatypes' => array( 'ezkeyword', 'eztags' ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'drafts' => array( 'type' => 'bool', 'label' => 'Read drafts too', 'default' => false,
                               'help' => 'Files with draft: true or published: false, and the _drafts folder' ),
            'skip_index' => array( 'type' => 'bool', 'label' => 'Leave out README and index files', 'default' => false ),
            'folder' => array( 'type' => 'string', 'label' => 'Only this folder', 'help' => 'A folder in the ZIP, e.g. content/posts; empty: all' ),
            'wiki_base' => array( 'type' => 'string', 'label' => 'Address of [[links]]',
                                  'help' => 'Obsidian [[Page]] links become links to this address plus the page slug; empty: their text only' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id', 'help' => 'The field that names a record; id is the path or the front matter id' ),
        );
    }

    /** @return bool whether the source file is a ZIP */
    protected function isZip()
    {
        $fp = fopen( $this->file(), 'rb' );
        $magic = (string)fread( $fp, 4 );
        fclose( $fp );
        return $magic === "PK\x03\x04";
    }

    /** @return ZipArchive */
    protected function zip()
    {
        if ( !class_exists( 'ZipArchive' ) )
            throw new expImportSourceException( 'reading a ZIP needs the PHP extension zip' );
        $zip = new ZipArchive();
        if ( $zip->open( $this->file(), ZipArchive::RDONLY ) !== true )
            throw new expImportSourceException( 'the ZIP file cannot be opened' );
        return $zip;
    }

    /** @return string[] the Markdown files of the ZIP to read, sorted */
    protected function fileList( ZipArchive $zip )
    {
        if ( $this->files !== null )
            return $this->files;
        $only = trim( isset( $this->config['folder'] ) ? (string)$this->config['folder'] : '', '/ ' );
        $drafts = !empty( $this->config['drafts'] );
        $skipIndex = !empty( $this->config['skip_index'] );
        $all = array();
        for ( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $name = (string)$zip->getNameIndex( $i );
            if ( substr( $name, -1 ) === '/' || !preg_match( '/\.(md|markdown)$/i', $name ) || strpos( $name, '..' ) !== false || $name[0] === '/' )
                continue;
            $all[] = $name;
        }
        // a ZIP of one folder: its name is no part of the paths
        $this->root = self::commonRoot( $all );
        $out = array();
        foreach ( $all as $name )
        {
            if ( $only !== '' && strpos( (string)substr( $name, strlen( $this->root ) ), $only . '/' ) !== 0 )
                continue;
            $hidden = false;
            foreach ( array_slice( explode( '/', $name ), 0, -1 ) as $part )
                if ( ( $part[0] === '.' || $part[0] === '_' ) && $part !== '_posts' && !( $drafts && $part === '_drafts' ) )
                    $hidden = true;
            if ( $hidden || ( $skipIndex && preg_match( '/^(readme|index|_index)\.(md|markdown)$/i', basename( $name ) ) ) )
                continue;
            $out[] = $name;
        }
        sort( $out, SORT_STRING );
        return $this->files = $out;
    }

    public function records( array $cursor = array() )
    {
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $drafts = !empty( $this->config['drafts'] );
        if ( !$this->isZip() )
        {
            $data = self::record( basename( $this->file() ), (string)file_get_contents( $this->file() ), $this->config );
            if ( $skip === 0 && ( $drafts || !$data['draft'] ) )
                yield new expImportRecord( $this->refOf( $data, 0 ), 0, $data, array( 'index' => 1 ) );
            return;
        }
        $zip = $this->zip();
        try
        {
            $files = $this->fileList( $zip );
            $root = $this->root;
            $index = 0;
            foreach ( $files as $position => $name )
            {
                if ( $position < $skip )
                {
                    continue;
                }
                $text = $zip->getFromName( $name );
                if ( $text === false )
                    throw new expImportSourceException( "the file $name in the ZIP cannot be read" );
                $data = self::record( (string)substr( $name, strlen( $root ) ), $text, $this->config );
                if ( !$drafts && $data['draft'] )
                    continue;
                yield new expImportRecord( $this->refOf( $data, $position ), $position, $data, array( 'index' => $position + 1 ) );
                $index++;
            }
        }
        finally
        {
            $zip->close();
        }
    }

    /** @return string the folder all files share ("site/" of a ZIP made from the folder site), '' if none */
    public static function commonRoot( array $files )
    {
        if ( !$files )
            return '';
        $first = explode( '/', $files[0] );
        if ( count( $first ) < 2 )
            return '';
        foreach ( $files as $file )
            if ( strpos( $file, $first[0] . '/' ) !== 0 )
                return '';
        return $first[0] . '/';
    }

    /**
     * The record of one Markdown file.
     *
     * @param string $path its path (in the ZIP, without a common root)
     * @param string $text its content
     * @param array $config the source's configuration (wiki_base)
     * @return array
     */
    public static function record( $path, $text, array $config = array() )
    {
        list( $front, $body ) = expImportFrontMatter::split( expImportAbstractSource::toUtf8( $text ) );
        $file = basename( $path );
        $folder = trim( dirname( $path ), './' );
        $stem = (string)preg_replace( '/\.(md|markdown)$/i', '', $file );
        $jekyllDate = '';
        if ( preg_match( '/^(\d{4}-\d{2}-\d{2})-(.+)$/', $stem, $m ) )
        {
            $jekyllDate = $m[1];
            $stem = $m[2];
        }
        $get = function ( array $keys ) use ( $front )
        {
            foreach ( $keys as $key )
                if ( isset( $front[$key] ) && $front[$key] !== '' && $front[$key] !== array() )
                    return $front[$key];
            return null;
        };
        $list = function ( $value )
        {
            if ( $value === null )
                return array();
            if ( is_array( $value ) )
                return array_values( array_filter( array_map( function ( $v ) { return trim( (string)$v ); }, $value ), 'strlen' ) );
            return array_values( array_filter( array_map( 'trim', preg_split( strpos( (string)$value, ',' ) !== false ? '/,/' : '/\s+/', (string)$value ) ), 'strlen' ) );
        };
        $converter = new expImportMarkdown( isset( $config['wiki_base'] ) ? (string)$config['wiki_base'] : '' );
        $html = $converter->convert( $body );
        $title = $get( array( 'title' ) );
        if ( $title === null && preg_match( '/^\s{0,3}#\s+(.+?)\s*#*\s*$/m', $body, $h ) )
            $title = $h[1];
        if ( $title === null )
            $title = ucfirst( str_replace( array( '-', '_' ), ' ', $stem ) );
        $text = trim( html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        $summary = $get( array( 'description', 'summary', 'excerpt' ) );
        if ( $summary === null && preg_match( '/<p>(.*?)<\/p>/s', $html, $p ) )
            $summary = trim( html_entity_decode( strip_tags( $p[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        $draft = $get( array( 'draft' ) );
        $published = isset( $front['published'] ) ? $front['published'] : null;
        $id = $get( array( 'id', 'uid' ) );
        $slug = $get( array( 'slug' ) );
        $data = array(
            'id' => $id !== null ? (string)$id : ( $folder !== '' ? $folder . '/' : '' ) . (string)preg_replace( '/\.(md|markdown)$/i', '', $file ),
            'path' => $path,
            'folder' => $folder,
            'file' => $file,
            'slug' => $slug !== null ? (string)$slug : $stem,
            'title' => trim( (string)$title ),
            'date' => (string)( $get( array( 'date', 'created', 'publishDate', 'published_at' ) ) ?? $jekyllDate ),
            'draft' => $draft === true || $draft === 'true' || $published === false || strpos( $path, '_drafts/' ) !== false,
            'tags' => $list( $get( array( 'tags', 'tag', 'keywords' ) ) ),
            'categories' => $list( $get( array( 'categories', 'category' ) ) ),
            'summary' => $summary !== null ? (string)$summary : '',
            'markdown' => $body,
            'html' => $html,
            'text' => $text,
            'links' => array_values( array_unique( $converter->wikiLinks ) ),
            'images' => array_values( array_unique( $converter->images ) ),
            'front' => $front,
        );
        foreach ( $front as $key => $value )
            if ( is_string( $key ) && !array_key_exists( $key, $data ) && preg_match( '/^[A-Za-z0-9_\-]+$/', $key ) )
                $data[$key] = $value;
        return $data;
    }

    public function count()
    {
        if ( !$this->isZip() )
            return 1;
        $zip = $this->zip();
        $n = count( $this->fileList( $zip ) );
        $zip->close();
        return $n;
    }
}
