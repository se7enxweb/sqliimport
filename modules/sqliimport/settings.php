<?php
/**
 * sqliimport/settings (policy sqliimport/settings): the settings of 7x SQLI Import as they stand, the export of the
 * import definitions as JSON (to move them from staging to live; no secret and no run), and the import of such a
 * file. Every change is a POST with the form token.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();
if ( !expImportSchema::installed() )
    return $Module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
$isPost = strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) === 'POST';
$message = null;
if ( $isPost && $http->hasPostVariable( 'ExportButton' ) )
{
    $ids = $http->hasPostVariable( 'ImportIDs' ) ? array_map( 'intval', (array)$http->postVariable( 'ImportIDs' ) ) : array();
    $list = array();
    foreach ( expImportDefinition::fetchList( 0, 1000 ) as $import )
        if ( !$ids || in_array( (int)$import->attribute( 'id' ), $ids, true ) )
            $list[] = $import->export();
    $json = json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 1, 'exported' => date( 'c', expImport::now() ), 'imports' => $list ),
                         JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    while ( @ob_end_clean() );
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="sqliimport-definitions-' . date( 'Ymd-His', expImport::now() ) . '.json"' );
    header( 'X-Content-Type-Options: nosniff' );
    echo $json;
    eZExecution::cleanExit();
}
if ( $isPost && $http->hasPostVariable( 'ImportButton' ) )
{
    $file = eZHTTPFile::canFetch( 'DefinitionsFile' ) ? eZHTTPFile::fetch( 'DefinitionsFile' ) : null;
    $data = $file instanceof eZHTTPFile && filesize( $file->attribute( 'filename' ) ) < 10485760
          ? json_decode( (string)file_get_contents( $file->attribute( 'filename' ) ), true ) : null;
    if ( !is_array( $data ) || !isset( $data['format'] ) || $data['format'] !== 'sqliimport-definitions' )
        $message = array( 'kind' => 'bad', 'message' => ezpI18n::tr( 'extension/sqliimport', 'The file is not an export of import definitions.' ) );
    else
    {
        list( $created, $updated, $errors ) = expImportEditor::importDefinitions( $data );
        $message = array( 'kind' => $errors ? 'warn' : 'ok',
                          'message' => ezpI18n::tr( 'extension/sqliimport', '%created imports created (disabled), %updated updated.', null,
                                                    array( '%created' => $created, '%updated' => $updated ) ) . ( $errors ? ' ' . implode( '; ', $errors ) : '' ) );
    }
}
$ini = expImport::ini();
$settings = array();
foreach ( array( 'RunSettings' => array( 'ChunkSize', 'DryRunRows', 'LogLevel', 'KeepDays' ),
                 'SourceSettings' => array( 'MaxSourceMegabytes', 'AllowPrivateHosts', 'AllowedPaths' ),
                 'SqlSourceSettings' => array( 'AllowedHosts', 'SiteDatabase', 'QueryTimeout', 'OracleDriver', 'AllowedTnsNames', 'SqlServerDriver', 'OdbcDriver', 'OdbcCharset', 'SqlServerEncrypt', 'SqlServerTrustServerCertificate' ),
                 'ImapSourceSettings' => array( 'AllowedHosts', 'Timeout', 'MaxMessageMegabytes' ),
                 'MongoDbSourceSettings' => array( 'AllowedHosts', 'Timeout', 'MaxBatchSize' ),
                 'RedisSourceSettings' => array( 'AllowedHosts', 'Client', 'Timeout', 'MaxValueMegabytes', 'MaxScannedKeys', 'TestScanRounds' ),
                 'LdapSourceSettings' => array( 'AllowedHosts', 'Timeout' ),
                 'CouchDbSourceSettings' => array( 'AllowedHosts', 'MaxPages' ),
                 'CouchbaseSourceSettings' => array( 'AllowedHosts', 'QueryTimeout', 'MaxPages' ),
                 'CosmosDbSourceSettings' => array( 'AllowedHosts', 'MaxPages' ),
                 'DynamoDbSourceSettings' => array( 'AllowedHosts', 'MaxPages' ),
                 'GoogleCloudSourceSettings' => array( 'MaxPages', 'MaximumBytesBilled', 'AllowedHosts' ),
                 'CassandraSourceSettings' => array( 'AllowedHosts', 'Client', 'Timeout', 'MaxFrameMegabytes', 'MaxPages' ),
                 'Neo4jSourceSettings' => array( 'AllowedHosts', 'Timeout', 'MaxPages' ),
                 'ArangoDbSourceSettings' => array( 'AllowedHosts', 'MaxPages' ),
                 'ClickHouseSourceSettings' => array( 'AllowedHosts', 'AllowedTableFunctions', 'MaxExecutionTime', 'MaxResultRows', 'MaxPages' ),
                 'SnowflakeSourceSettings' => array( 'AllowedHosts', 'StatementTimeout', 'MaxPages' ),
                 'InfluxDbSourceSettings' => array( 'AllowedHosts', 'MaxPages' ),
                 'SupabaseSourceSettings' => array( 'AllowedHosts', 'MaxPages' ),
                 'BaserowSourceSettings' => array( 'AllowedHosts', 'MaxPages' ),
                 'SalesforceSourceSettings' => array( 'AllowedDomains', 'AllowedHosts', 'MaxPages' ),
                 'HubSpotSourceSettings' => array( 'MaxPages' ),
                 'YouTubeSettings' => array( 'MaxPages', 'MaxSearchPages', 'AllowPageScraper' ),
                 'RestApiSourceSettings' => array( 'AllowedHosts', 'AllowedTokenHosts', 'MaxPages', 'MaxWaitSeconds' ),
                 'ImportSettings' => array( 'DefaultParentNodeID', 'ViewCaching', 'ObjectIndexing', 'StreamTimeout' ),
                 'AdminSettings' => array( 'ListLimit' ) ) as $section => $names )
    foreach ( $names as $name )
        $settings[] = array( 'name' => "[$section] $name", 'value' => $ini->hasVariable( $section, $name ) ? $ini->variable( $section, $name ) : '' );
$tpl = eZTemplate::factory();
$tpl->setVariable( 'settings', $settings );
// the names of the secrets only, never a value
$tpl->setVariable( 'secret_names', array_values( array_filter( array_map( 'strval', expImportSecrets::names() ), 'strlen' ) ) );
$tpl->setVariable( 'secrets_url', expImportSecrets::manageUrl() );
$tpl->setVariable( 'imports', expImportDefinition::fetchList( 0, 1000 ) );
$tpl->setVariable( 'flash', $message );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/settings.tpl' );
$Result['path'] = array( array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Settings' ) ) );
