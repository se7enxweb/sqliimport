<?php
/**
 * File containing the expImportMediaSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Media files: a ZIP of a folder (photos, documents, audio, video) or one file. Every file is a record with what its
 * embedded metadata says:
 *
 *   id, path, folder, file, extension, mime, size, sha1   where it is and what it is (sha1: the content's hash, so the
 *                 same file uploaded twice is the same record: a remote ID pattern media-{sha1} imports it once)
 *   name          the IPTC object name or headline, else the EXIF description, else the file name in words
 *   caption       the IPTC caption, else the EXIF description
 *   keywords      the IPTC keywords (a list); author, copyright, credit, source, city, country from IPTC (else EXIF)
 *   date_taken    when the photo was taken (EXIF DateTimeOriginal, else the IPTC date), ISO 8601
 *   width, height, orientation, camera (make and model), gps_lat, gps_lon (decimal degrees, '' when none)
 *   exif, iptc    the EXIF fields read and the IPTC fields by name
 *   media         a reference to the file in the source ("source:<path>"): the transforms image and file take it
 *                 like an address, so the file itself is imported, out of the ZIP of the run
 *
 * Folders starting with "." or "__MACOSX" are left out. The cursor is the position in the sorted list of files.
 */
class expImportMediaSource extends expImportAbstractSource
{
    /** IPTC datasets by name */
    const IPTC = array( '2#005' => 'object_name', '2#105' => 'headline', '2#120' => 'caption', '2#025' => 'keywords', '2#080' => 'byline',
                        '2#085' => 'byline_title', '2#116' => 'copyright', '2#110' => 'credit', '2#115' => 'source', '2#090' => 'city',
                        '2#095' => 'state', '2#101' => 'country', '2#055' => 'date_created', '2#060' => 'time_created', '2#015' => 'category',
                        '2#040' => 'instructions', '2#122' => 'writer' );

    /** @var string[]|null */
    protected $files = null;

    public static function identifier()
    {
        return 'media';
    }

    public static function label()
    {
        return 'Media files (folder as ZIP) with EXIF and IPTC';
    }

    public static function extensions()
    {
        return array( 'zip', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff', 'pdf', 'mp3', 'mp4' );
    }

    public static function description()
    {
        return 'Photos, documents, audio and video, one file or a folder as ZIP. Every file is a record with its folder, '
             . 'size, content hash and what its EXIF and IPTC metadata say: title, caption, keywords, photographer, '
             . 'copyright, date taken, camera and GPS position. The file itself is imported into an image or file attribute.';
    }

    public static function group()
    {
        return 'media';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Three photos with EXIF and IPTC and a text file, in folders, as ZIP', 'file' => 'media.zip' ),
                      array( 'label' => 'One photo with EXIF and IPTC', 'file' => 'photo.jpg' ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'image',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'media-{sha1}' ),
            'fields' => array(
                array( 'source' => 'name', 'attribute' => 'name' ),
                array( 'source' => 'caption', 'attribute' => 'caption', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'media', 'attribute' => 'image', 'transforms' => array( array( 'name' => 'image' ) ), 'datatypes' => array( 'ezimage' ) ),
                array( 'source' => 'keywords', 'attribute' => 'tags', 'transforms' => array( array( 'name' => 'join', 'args' => array( 'separator' => ', ' ) ) ), 'datatypes' => array( 'ezkeyword', 'eztags' ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'types' => array( 'type' => 'enum', 'label' => 'Files', 'default' => 'images',
                              'values' => array( 'images' => 'Images only (JPEG, PNG, GIF, WebP, TIFF)', 'all' => 'Every file' ) ),
            'folder' => array( 'type' => 'string', 'label' => 'Only this folder', 'help' => 'A folder in the ZIP; empty: all' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'path',
                            'help' => 'The field that names a record: path, or sha1 to import the same file once wherever it is' ),
        );
    }

    /** @return bool */
    protected function isZip()
    {
        $fp = fopen( $this->file(), 'rb' );
        $magic = (string)fread( $fp, 4 );
        fclose( $fp );
        return $magic === "PK\x03\x04";
    }

    /** @return bool whether a file name is an image this source reads metadata of */
    public static function isImage( $name )
    {
        return (bool)preg_match( '/\.(jpe?g|png|gif|webp|tiff?)$/i', $name );
    }

    /** @return string[] the files of a ZIP to read, sorted (paths as in the ZIP) */
    protected function fileList( ZipArchive $zip )
    {
        if ( $this->files !== null )
            return $this->files;
        $only = trim( isset( $this->config['folder'] ) ? (string)$this->config['folder'] : '', '/ ' );
        $images = !isset( $this->config['types'] ) || $this->config['types'] !== 'all';
        $out = array();
        for ( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $name = (string)$zip->getNameIndex( $i );
            if ( substr( $name, -1 ) === '/' || strpos( $name, '..' ) !== false || $name[0] === '/' || preg_match( '#(^|/)(\.|__MACOSX)#', $name ) )
                continue;
            if ( $images && !self::isImage( $name ) )
                continue;
            if ( $only !== '' && strpos( $name, $only . '/' ) !== 0 && strpos( $name, '/' . $only . '/' ) === false )
                continue;
            $out[] = $name;
        }
        sort( $out, SORT_STRING );
        return $this->files = $out;
    }

    public function records( array $cursor = array() )
    {
        $skip = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        if ( !$this->isZip() )
        {
            if ( $skip === 0 )
            {
                $name = isset( $this->config['original_name'] ) && $this->config['original_name'] !== '' ? basename( (string)$this->config['original_name'] ) : basename( $this->file() );
                $data = self::describe( (string)file_get_contents( $this->file() ), $name, '' );
                yield new expImportRecord( $this->refOf( $data, 0 ), 0, $data, array( 'index' => 1 ) );
            }
            return;
        }
        if ( !class_exists( 'ZipArchive' ) )
            throw new expImportSourceException( 'reading a ZIP needs the PHP extension zip' );
        $zip = new ZipArchive();
        if ( $zip->open( $this->file(), ZipArchive::RDONLY ) !== true )
            throw new expImportSourceException( 'the ZIP file cannot be opened' );
        try
        {
            foreach ( $this->fileList( $zip ) as $position => $name )
            {
                if ( $position < $skip )
                    continue;
                $stat = $zip->statName( $name );
                if ( $stat && (int)$stat['size'] > 256 * 1048576 )
                    throw new expImportSourceException( "the file $name in the ZIP is larger than 256 MB" );
                $content = $zip->getFromName( $name );
                if ( $content === false )
                    throw new expImportSourceException( "the file $name in the ZIP cannot be read" );
                $data = self::describe( $content, basename( $name ), $name );
                unset( $content );
                yield new expImportRecord( $this->refOf( $data, $position ), $position, $data, array( 'index' => $position + 1 ) );
            }
        }
        finally
        {
            $zip->close();
        }
    }

    /**
     * The record of one file, read from memory (no file is written to read the metadata).
     *
     * @param string $content the file's content
     * @param string $name its file name
     * @param string $path its path in the ZIP ('' for a single file)
     * @return array
     */
    public static function describe( $content, $name, $path )
    {
        $content = (string)$content;
        $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        $folder = $path !== '' ? trim( dirname( $path ), './' ) : '';
        $mime = class_exists( 'finfo' ) ? (string)( new finfo( FILEINFO_MIME_TYPE ) )->buffer( $content ) : '';
        $data = array(
            'id' => $path !== '' ? $path : $name,
            'path' => $path !== '' ? $path : $name,
            'folder' => $folder,
            'file' => $name,
            'extension' => $extension,
            'mime' => $mime,
            'size' => strlen( $content ),
            'sha1' => sha1( $content ),
            'name' => '',
            'caption' => '',
            'keywords' => array(),
            'author' => '',
            'copyright' => '',
            'credit' => '',
            'source' => '',
            'city' => '',
            'country' => '',
            'date_taken' => '',
            'width' => 0,
            'height' => 0,
            'orientation' => 0,
            'camera' => '',
            'gps_lat' => '',
            'gps_lon' => '',
            'exif' => array(),
            'iptc' => array(),
            'media' => 'source:' . ( $path !== '' ? $path : '' ),
        );
        if ( self::isImage( $name ) )
        {
            $info = array();
            $size = @getimagesizefromstring( $content, $info );
            if ( $size )
            {
                $data['width'] = (int)$size[0];
                $data['height'] = (int)$size[1];
                if ( $data['mime'] === '' || $data['mime'] === 'application/octet-stream' )
                    $data['mime'] = (string)$size['mime'];
            }
            if ( isset( $info['APP13'] ) )
                $data['iptc'] = self::iptc( (string)$info['APP13'] );
            if ( function_exists( 'exif_read_data' ) && preg_match( '/^(jpe?g|tiff?)$/', $extension ) )
            {
                $stream = fopen( "php://memory", "w+b" );
                fwrite( $stream, $content );
                rewind( $stream );
                $exif = @exif_read_data( $stream, null, true );
                fclose( $stream );
                if ( is_array( $exif ) )
                    $data = self::withExif( $data, $exif );
            }
            $iptc = $data['iptc'];
            $pick = function ( $keys, $fallback = '' ) use ( $iptc )
            {
                foreach ( $keys as $key )
                    if ( isset( $iptc[$key] ) && $iptc[$key] !== '' && $iptc[$key] !== array() )
                        return is_array( $iptc[$key] ) ? implode( ', ', $iptc[$key] ) : $iptc[$key];
                return $fallback;
            };
            $description = isset( $data['exif']['ImageDescription'] ) ? $data['exif']['ImageDescription'] : '';
            $data['name'] = $pick( array( 'object_name', 'headline' ), $description );
            $data['caption'] = $pick( array( 'caption' ), $description );
            $data['keywords'] = isset( $iptc['keywords'] ) ? (array)$iptc['keywords'] : array();
            $data['author'] = $pick( array( 'byline' ), isset( $data['exif']['Artist'] ) ? $data['exif']['Artist'] : '' );
            $data['copyright'] = $pick( array( 'copyright' ), isset( $data['exif']['Copyright'] ) ? $data['exif']['Copyright'] : '' );
            $data['credit'] = $pick( array( 'credit' ) );
            $data['source'] = $pick( array( 'source' ) );
            $data['city'] = $pick( array( 'city' ) );
            $data['country'] = $pick( array( 'country' ) );
            if ( $data['date_taken'] === '' && isset( $iptc['date_created'] ) && preg_match( '/^(\d{4})(\d{2})(\d{2})$/', (string)$iptc['date_created'], $d ) )
                $data['date_taken'] = "$d[1]-$d[2]-$d[3]";
        }
        if ( $data['name'] === '' )
            $data['name'] = ucfirst( trim( (string)preg_replace( '/[-_ ]+/', ' ', pathinfo( $name, PATHINFO_FILENAME ) ) ) );
        return $data;
    }

    /** @return array the IPTC block by name, as UTF-8 (lists for repeatable datasets such as keywords) */
    public static function iptc( $app13 )
    {
        $raw = @iptcparse( $app13 );
        if ( !is_array( $raw ) )
            return array();
        $utf8 = isset( $raw['1#090'][0] ) && $raw['1#090'][0] === "\x1B%G";
        $out = array();
        foreach ( self::IPTC as $code => $key )
        {
            if ( !isset( $raw[$code] ) )
                continue;
            $values = array_map( function ( $v ) use ( $utf8 )
            {
                return trim( $utf8 ? (string)$v : expImportAbstractSource::toUtf8( (string)$v ) );
            }, (array)$raw[$code] );
            $out[$key] = in_array( $key, array( 'keywords', 'byline' ), true ) && count( $values ) > 1 ? $values
                       : ( $key === 'keywords' ? $values : $values[0] );
        }
        return $out;
    }

    /** @return array the record with what the EXIF of a photo says */
    public static function withExif( array $data, array $exif )
    {
        $flat = array();
        foreach ( array( 'IFD0', 'EXIF', 'GPS' ) as $section )
            if ( isset( $exif[$section] ) && is_array( $exif[$section] ) )
                foreach ( $exif[$section] as $key => $value )
                    if ( is_scalar( $value ) || ( is_array( $value ) && $section === 'GPS' ) )
                        $flat[$key] = is_string( $value ) ? trim( expImportAbstractSource::toUtf8( rtrim( $value, "\0" ) ) ) : $value;
        $keep = array( 'Make', 'Model', 'Orientation', 'ImageDescription', 'Artist', 'Copyright', 'DateTimeOriginal', 'DateTimeDigitized',
                       'DateTime', 'ExposureTime', 'FNumber', 'ISOSpeedRatings', 'FocalLength', 'LensModel', 'Software' );
        $data['exif'] = array_intersect_key( $flat, array_flip( $keep ) );
        $date = isset( $flat['DateTimeOriginal'] ) ? $flat['DateTimeOriginal'] : ( isset( $flat['DateTimeDigitized'] ) ? $flat['DateTimeDigitized'] : '' );
        if ( preg_match( '/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', (string)$date, $m ) )
            $data['date_taken'] = "$m[1]-$m[2]-$m[3]T$m[4]:$m[5]:$m[6]";
        $data['orientation'] = isset( $flat['Orientation'] ) ? (int)$flat['Orientation'] : 0;
        $data['camera'] = trim( ( isset( $flat['Make'] ) ? $flat['Make'] : '' ) . ' ' . ( isset( $flat['Model'] ) ? $flat['Model'] : '' ) );
        if ( isset( $flat['GPSLatitude'], $flat['GPSLongitude'] ) && is_array( $flat['GPSLatitude'] ) && is_array( $flat['GPSLongitude'] ) )
        {
            $lat = self::degrees( $flat['GPSLatitude'] );
            $lon = self::degrees( $flat['GPSLongitude'] );
            if ( $lat !== null && $lon !== null )
            {
                $data['gps_lat'] = round( ( isset( $flat['GPSLatitudeRef'] ) && strtoupper( $flat['GPSLatitudeRef'] ) === 'S' ? -1 : 1 ) * $lat, 6 );
                $data['gps_lon'] = round( ( isset( $flat['GPSLongitudeRef'] ) && strtoupper( $flat['GPSLongitudeRef'] ) === 'W' ? -1 : 1 ) * $lon, 6 );
            }
        }
        return $data;
    }

    /** @return float|null degrees from EXIF's three rationals ("53/1", "33/1", "404/100") */
    public static function degrees( array $parts )
    {
        $v = array();
        foreach ( array_slice( array_values( $parts ), 0, 3 ) as $part )
        {
            $pair = explode( '/', (string)$part );
            $den = isset( $pair[1] ) ? (float)$pair[1] : 1.0;
            if ( $den == 0 )
                return null;
            $v[] = (float)$pair[0] / $den;
        }
        if ( count( $v ) < 3 )
            return null;
        return $v[0] + $v[1] / 60 + $v[2] / 3600;
    }

    public function count()
    {
        if ( !$this->isZip() )
            return 1;
        $zip = new ZipArchive();
        if ( $zip->open( $this->file(), ZipArchive::RDONLY ) !== true )
            return null;
        $n = count( $this->fileList( $zip ) );
        $zip->close();
        return $n;
    }
}
