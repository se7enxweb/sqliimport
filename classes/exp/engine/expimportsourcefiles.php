<?php
/**
 * File containing the expImportSourceFiles class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Where the data of a run comes from, made into a local file once per run (the snapshot every chunk reads):
 *
 *   upload  a file uploaded in the admin, kept in var/<site>/storage/sqliimport/uploads/
 *   url     an http or https address, downloaded with a time and size limit; addresses of private networks and of
 *           the server itself are refused unless [SourceSettings] AllowPrivateHosts=enabled
 *   path    a file on the server, inside one of [SourceSettings] AllowedPaths[] (nothing else is read)
 *
 * Snapshots and downloaded images live in var/<site>/storage/sqliimport/runs/<run id>/ and are removed with the run's
 * history after the retention time.
 */
class expImportSourceFiles
{
    /** @return string the directory of a run's files */
    public static function runDirectory( $runID )
    {
        return expImport::storageDirectory( 'runs/' . (int)$runID );
    }

    /** @return int the size limit of a download or an upload, in bytes */
    public static function maxBytes()
    {
        return max( 1, (int)expImport::setting( 'SourceSettings', 'MaxSourceMegabytes', 200 ) ) * 1048576;
    }

    /**
     * The local snapshot of an import's source for a run, or for the sample and preview of the import editor
     * (without a run: var/<site>/storage/sqliimport/preview/<import id>/).
     *
     * @param expImportDefinition $import
     * @param expImportRun|null $run
     * @return string the file
     * @throws expImportSourceException
     */
    public static function snapshot( expImportDefinition $import, ?expImportRun $run = null )
    {
        $config = $import->sourceConfig();
        $origin = isset( $config['origin'] ) ? (string)$config['origin'] : 'upload';
        $dir = $run ? self::runDirectory( $run->attribute( 'id' ) ) : expImport::storageDirectory( 'preview/' . (int)$import->attribute( 'id' ) );
        switch ( $origin )
        {
            case 'upload':
                $name = isset( $config['file'] ) ? basename( (string)$config['file'] ) : '';
                $file = expImport::storageDirectory( 'uploads' ) . '/' . $name;
                if ( $name === '' || !is_file( $file ) )
                    throw new expImportSourceException( 'the uploaded source file is missing; upload it again' );
                $target = $dir . '/source' . self::extensionOf( $name );
                if ( !copy( $file, $target ) )
                    throw new expImportSourceException( 'the uploaded file could not be copied for the run' );
                return $target;
            case 'url':
                $url = isset( $config['url'] ) ? (string)$config['url'] : '';
                // a format may turn the address it is given into the one to download (a sheet's link into its CSV)
                $sources = expImportRegistry::instance()->sources();
                $type = (string)$import->attribute( 'source_type' );
                if ( isset( $sources[$type] ) && method_exists( $sources[$type]['class'], 'downloadUrl' ) )
                {
                    $url = (string)call_user_func( array( $sources[$type]['class'], 'downloadUrl' ), $url );
                    $refusal = self::refuseUrl( $url );
                    if ( $refusal !== null )
                        throw new expImportSourceException( $refusal );
                }
                return self::download( $url, $dir . '/source' . self::extensionOf( parse_url( $url, PHP_URL_PATH ) ?: '' ) );
            case 'path':
                return self::allowedPath( isset( $config['path'] ) ? (string)$config['path'] : '' );
            case 'remote':
                // the source reads its data itself (a database, a mailbox, a site): it writes a snapshot of it once
                // per run, which every chunk of the run then reads like a file
                $sources = expImportRegistry::instance()->sources();
                $type = (string)$import->attribute( 'source_type' );
                if ( !isset( $sources[$type] ) || !method_exists( $sources[$type]['class'], 'fetch' ) )
                    throw new expImportSourceException( "the source type '$type' cannot read data itself" );
                list( $values, $errors ) = expImportOptionSchema::validate( $sources[$type]['schema'], $config );
                if ( $errors )
                    throw new expImportSourceException( 'the source is not configured correctly: ' . implode( '; ', $errors ) );
                $target = $dir . '/source.ndjson';
                $limit = $run ? 0 : max( 20, (int)expImport::setting( 'RunSettings', 'DryRunRows', 20 ) );
                call_user_func( array( $sources[$type]['class'], 'fetch' ), $values, $target, $limit );
                if ( !is_file( $target ) )
                    throw new expImportSourceException( 'the source wrote no data' );
                return $target;
        }
        throw new expImportSourceException( "unknown source origin '$origin'" );
    }

    /** @return string ".csv" etc., or "" */
    protected static function extensionOf( $name )
    {
        $ext = strtolower( pathinfo( (string)$name, PATHINFO_EXTENSION ) );
        return preg_match( '/^[a-z0-9]{1,8}$/', $ext ) ? '.' . $ext : '';
    }

    /**
     * A server path inside [SourceSettings] AllowedPaths[] (relative paths are relative to the installation).
     *
     * @param string $path
     * @return string the real path
     * @throws expImportSourceException
     */
    public static function allowedPath( $path )
    {
        $real = realpath( (string)$path );
        if ( $real === false || !is_file( $real ) )
            throw new expImportSourceException( 'the source file does not exist on the server' );
        foreach ( (array)expImport::setting( 'SourceSettings', 'AllowedPaths', array() ) as $allowed )
        {
            $base = realpath( (string)$allowed );
            if ( $base !== false && strpos( $real, rtrim( $base, '/' ) . '/' ) === 0 )
                return $real;
        }
        throw new expImportSourceException( 'the path is outside the directories sqliimport.ini [SourceSettings] AllowedPaths[] allows' );
    }

    /**
     * Whether an address may be fetched: http or https, and (unless allowed) not a private, loopback or link-local
     * address.
     *
     * @param string $url
     * @param string[] $trusted hosts an administrator allowed explicitly (see refuseHost())
     * @return string|null the reason it is refused, null when it is fine
     */
    public static function refuseUrl( $url, array $trusted = array() )
    {
        $parts = parse_url( (string)$url );
        if ( !$parts || !isset( $parts['scheme'], $parts['host'] ) || !in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) )
            return 'only http and https addresses can be read';
        return self::refuseHost( $parts['host'], $trusted );
    }

    /**
     * Whether a host may be connected to (an address, a database server, a mailbox server): unless
     * [SourceSettings] AllowPrivateHosts=enabled or the host is one of the trusted hosts given, not one in a private,
     * loopback, link-local or reserved network, and not a name that resolves into one.
     *
     * @param string $host a name or an IP address (IPv6 with or without brackets)
     * @param string[] $trusted host names or addresses an administrator allowed explicitly (compared case-insensitively)
     * @return string|null the reason it is refused, null when it is fine
     */
    public static function refuseHost( $host, array $trusted = array() )
    {
        $host = trim( (string)$host, " []" );
        if ( $host === '' || !preg_match( '/^[A-Za-z0-9.:_-]+$/', $host ) )
            return 'a host name or an IP address is expected';
        foreach ( $trusted as $allowed )
            if ( strcasecmp( trim( (string)$allowed, " []" ), $host ) === 0 )
                return null;
        if ( expImport::setting( 'SourceSettings', 'AllowPrivateHosts', 'disabled' ) === 'enabled' )
            return null;
        $ips = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array)@gethostbynamel( $host );
        if ( !$ips )
            return "the host $host cannot be resolved";
        foreach ( $ips as $ip )
            if ( !filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) )
                return "the host $host is in a private or reserved network (sqliimport.ini [SourceSettings] AllowPrivateHosts)";
        return null;
    }

    /**
     * Downloads an address into a file, with the timeout of [ImportSettings] StreamTimeout and the size limit.
     *
     * @param string $url
     * @param string $target
     * @param array $http options of the http stream context that replace the defaults (user_agent, header,
     *                    follow_location, ignore_errors: the crawl sources set their own); timeout and size stay
     * @param array|null $responseHeaders set to the lines of the answer's headers (status lines included)
     * @return string the file
     * @throws expImportSourceException
     */
    public static function download( $url, $target, array $http = array(), &$responseHeaders = null )
    {
        $refusal = self::refuseUrl( $url );
        if ( $refusal !== null )
            throw new expImportSourceException( $refusal );
        $timeout = max( 5, (int)expImport::setting( 'ImportSettings', 'StreamTimeout', 50 ) );
        $max = self::maxBytes();
        unset( $http['timeout'] );
        $context = stream_context_create( array(
            'http' => array_merge( array( 'timeout' => $timeout, 'follow_location' => 1, 'max_redirects' => 3, 'user_agent' => '7x SQLI Import',
                                          'ignore_errors' => false ), $http ),
        ) );
        $in = @fopen( $url, 'rb', false, $context );
        if ( !$in )
            throw new expImportSourceException( 'the address could not be read: ' . preg_replace( '#//[^/@]*@#', '//', $url ) );
        $meta = stream_get_meta_data( $in );
        $responseHeaders = isset( $meta['wrapper_data'] ) ? (array)$meta['wrapper_data'] : array();
        $out = fopen( $target, 'wb' );
        $bytes = 0;
        while ( !feof( $in ) )
        {
            $chunk = fread( $in, 65536 );
            if ( $chunk === false )
                break;
            $bytes += strlen( $chunk );
            if ( $bytes > $max )
            {
                fclose( $in );
                fclose( $out );
                @unlink( $target );
                throw new expImportSourceException( 'the download is larger than [SourceSettings] MaxSourceMegabytes' );
            }
            fwrite( $out, $chunk );
        }
        fclose( $in );
        fclose( $out );
        return $target;
    }

    /**
     * Downloads an image or a file a record names (the transforms image and file) into the run's directory; the same
     * address is fetched once per run.
     *
     * @param string $url
     * @param string $kind image or file
     * @param expImportRun $run
     * @return string the local file
     */
    public static function downloadAsset( $url, $kind, expImportRun $run )
    {
        $dir = expImport::storageDirectory( 'runs/' . (int)$run->attribute( 'id' ) . '/assets' );
        if ( strpos( $url, 'run:' ) === 0 )
            return self::runAsset( (string)substr( $url, 4 ), $kind, $run );
        if ( strpos( $url, 'source:' ) === 0 )
            return self::sourceAsset( (string)substr( $url, 7 ), $kind, $run, $dir );
        $name = substr( sha1( $url ), 0, 16 ) . self::extensionOf( parse_url( $url, PHP_URL_PATH ) ?: '' );
        $target = $dir . '/' . $name;
        if ( is_file( $target ) )
            return $target;
        try
        {
            return self::download( $url, $target );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportTransformException( "$kind: " . $e->getMessage() );
        }
    }

    /**
     * A file a source wrote next to its snapshot ("run:<path below the run's directory>"): a document a remote
     * source downloaded, an image taken out of a document (ezodf's document formats). Resolved against the run's
     * directory, or for the editor's sample and preview (a run without an id) against the import's preview
     * directory; never a path outside it, never a directory, never larger than [SourceSettings] MaxSourceMegabytes.
     *
     * @param string $rel
     * @param string $kind
     * @param expImportRun $run
     * @return string the local file
     * @throws expImportTransformException
     */
    public static function runAsset( $rel, $kind, expImportRun $run )
    {
        $base = (int)$run->attribute( 'id' ) > 0 ? self::runDirectory( $run->attribute( 'id' ) )
                                                  : expImport::storageDirectory( 'preview/' . (int)$run->attribute( 'import_id' ) );
        return self::runFile( $base, $rel, $kind, self::maxBytes() );
    }

    /**
     * A file below a directory, by a relative path from a record: no "." or ".." part, no NUL, no link leading out of
     * the directory, a file (not a directory), at most $maxBytes.
     *
     * @param string $base
     * @param string $rel
     * @param string $kind for the message
     * @param int $maxBytes
     * @return string the real path of the file
     * @throws expImportTransformException
     */
    public static function runFile( $base, $rel, $kind, $maxBytes )
    {
        $rel = ltrim( str_replace( '\\', '/', (string)$rel ), '/' );
        if ( $rel === '' || strpos( $rel, "\0" ) !== false || preg_match( '#(^|/)\.\.?(/|$)#', $rel ) )
            throw new expImportTransformException( "$kind: run:$rel is not a file of the run" );
        $root = realpath( $base );
        $file = realpath( $base . '/' . $rel );
        if ( $root === false || $file === false || strpos( $file, $root . DIRECTORY_SEPARATOR ) !== 0 || !is_file( $file ) )
            throw new expImportTransformException( "$kind: the run has no file $rel" );
        if ( filesize( $file ) > (int)$maxBytes )
            throw new expImportTransformException( "$kind: $rel is larger than [SourceSettings] MaxSourceMegabytes" );
        return $file;
    }

    /**
     * A file of the run's own source ("source:<path in the ZIP>", or "source:" for a source that is the file itself),
     * as the media source names them: taken out of the snapshot of the run, never from anywhere else.
     *
     * @param string $member
     * @param string $kind
     * @param expImportRun $run
     * @param string $dir the run's asset directory
     * @return string the local file
     */
    public static function sourceAsset( $member, $kind, expImportRun $run, $dir )
    {
        $member = ltrim( $member, '/' );
        if ( strpos( $member, '..' ) !== false || strpos( $member, "\0" ) !== false )
            throw new expImportTransformException( "$kind: the path $member is not a file of the source" );
        $snapshot = glob( self::runDirectory( $run->attribute( 'id' ) ) . '/source*' ) ?: array();
        $snapshot = $snapshot ? $snapshot[0] : '';
        if ( $snapshot === '' || !is_file( $snapshot ) )
            throw new expImportTransformException( "$kind: the run has no source file to take $member from" );
        if ( $member === '' )
            return $snapshot;
        $target = $dir . '/' . substr( sha1( $member ), 0, 16 ) . self::extensionOf( $member );
        if ( is_file( $target ) )
            return $target;
        $zip = new ZipArchive();
        if ( $zip->open( $snapshot, ZipArchive::RDONLY ) === true )
        {
            $content = $zip->getFromName( $member );
            $zip->close();
        }
        else
        {
            // a package (.ezpkg, a tar.gz): its images and files are read from the archive, which is never extracted
            try
            {
                $content = expImportEzpkgSource::member( $snapshot, $member );
            }
            catch ( expImportSourceException $e )
            {
                throw new expImportTransformException( "$kind: the source is no ZIP or package to take $member from" );
            }
            if ( $content === null )
                $content = false;
        }
        if ( $content === false )
            throw new expImportTransformException( "$kind: the source has no file $member" );
        if ( strlen( $content ) > self::maxBytes() )
            throw new expImportTransformException( "$kind: $member is larger than [SourceSettings] MaxSourceMegabytes" );
        file_put_contents( $target, $content );
        return $target;
    }

    /**
     * Stores an uploaded source file (uploads directory, a random name keeping the extension).
     *
     * @param string $tmpFile the uploaded file
     * @param string $originalName
     * @param string[] $extensions the allowed extensions
     * @return string the stored name
     * @throws expImportSourceException
     */
    public static function storeUpload( $tmpFile, $originalName, array $extensions )
    {
        $ext = ltrim( self::extensionOf( $originalName ), '.' );
        if ( $ext === '' || !in_array( $ext, $extensions, true ) )
            throw new expImportSourceException( 'the file must be one of: ' . implode( ', ', $extensions ) );
        if ( !is_file( $tmpFile ) || filesize( $tmpFile ) > self::maxBytes() )
            throw new expImportSourceException( 'the file is missing or larger than [SourceSettings] MaxSourceMegabytes' );
        $name = date( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 6 ) ) . '.' . $ext;
        $target = expImport::storageDirectory( 'uploads' ) . '/' . $name;
        if ( !@copy( $tmpFile, $target ) )
            throw new expImportSourceException( 'the file could not be stored' );
        return $name;
    }

    /** Removes a run's directory (snapshots and assets). */
    public static function removeRunFiles( $runID )
    {
        $dir = eZSys::storageDirectory() . '/sqliimport/runs/' . (int)$runID;
        if ( is_dir( $dir ) )
            eZDir::recursiveDelete( $dir );
    }
}
