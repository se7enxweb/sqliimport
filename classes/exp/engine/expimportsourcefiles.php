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
                return self::download( $url, $dir . '/source' . self::extensionOf( parse_url( $url, PHP_URL_PATH ) ?: '' ) );
            case 'path':
                return self::allowedPath( isset( $config['path'] ) ? (string)$config['path'] : '' );
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
     * @return string|null the reason it is refused, null when it is fine
     */
    public static function refuseUrl( $url )
    {
        $parts = parse_url( (string)$url );
        if ( !$parts || !isset( $parts['scheme'], $parts['host'] ) || !in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) )
            return 'only http and https addresses can be read';
        if ( expImport::setting( 'SourceSettings', 'AllowPrivateHosts', 'disabled' ) === 'enabled' )
            return null;
        $host = trim( $parts['host'], '[]' );
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
     * @return string the file
     * @throws expImportSourceException
     */
    public static function download( $url, $target )
    {
        $refusal = self::refuseUrl( $url );
        if ( $refusal !== null )
            throw new expImportSourceException( $refusal );
        $timeout = max( 5, (int)expImport::setting( 'ImportSettings', 'StreamTimeout', 50 ) );
        $max = self::maxBytes();
        $context = stream_context_create( array(
            'http' => array( 'timeout' => $timeout, 'follow_location' => 1, 'max_redirects' => 3, 'user_agent' => '7x SQLI Import',
                             'ignore_errors' => false ),
        ) );
        $in = @fopen( $url, 'rb', false, $context );
        if ( !$in )
            throw new expImportSourceException( 'the address could not be read: ' . preg_replace( '#//[^/@]*@#', '//', $url ) );
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
