<?php
/**
 * sqliimport/settings (policy sqliimport/settings): the settings of 7x SQLI Import as they stand, the export of the
 * import definitions as JSON (expImportTransfer: to move them from staging to live; no secret, file, key or run), and
 * the way to "Import definitions" (sqliimport/transfer), which reads such a file. Every change is a POST with the form
 * token.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
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
    // the definitions file of expImportTransfer (version 2): references instead of node IDs, the names of secrets,
    // never a stored file, key or salt; only imports the user may manage (the handler limitation)
    $ids = $http->hasPostVariable( 'ImportIDs' ) ? array_map( 'intval', (array)$http->postVariable( 'ImportIDs' ) ) : array();
    $list = array();
    foreach ( expImportDefinition::fetchList( 0, 1000 ) as $import )
        if ( ( !$ids || in_array( (int)$import->attribute( 'id' ), $ids, true ) ) && expImportAdmin::can( 'edit', (string)$import->attribute( 'handler' ) ) )
            $list[] = $import;
    if ( $list )
    {
        $document = expImportTransfer::exportImports( $list );
        expImport::audit( 'definitions.export', array( 'imports' => array_map( function ( $i ) { return (string)$i->attribute( 'identifier' ); }, $list ),
                                                       'secrets' => count( $document['secrets'] ) ) );
        expImportTransfer::send( $document );
        return;
    }
    $message = array( 'kind' => 'warn', 'message' => ezpI18n::tr( 'extension/sqliimport', 'There is no import to export.' ) );
}
// a file is read by "Import definitions" (sqliimport/transfer), which shows what it would create first
if ( $isPost && $http->hasPostVariable( 'ImportButton' ) )
{
    $Module->redirectTo( '/sqliimport/transfer' );
    return;
}
$ini = expImport::ini();
$settings = array();
foreach ( array( 'RunSettings' => array( 'ChunkSize', 'DryRunRows', 'LogLevel', 'KeepDays' ),
                 'SourceSettings' => array( 'MaxSourceMegabytes', 'AllowPrivateHosts', 'AllowedPaths' ),
                 // ServerKeySecret is the name of a secret, never its value
                 'TriggerSettings' => array( 'Trigger', 'SignatureWindowSeconds', 'RequestsPerMinute', 'StatusRequestsPerMinute', 'FailedRequestsPerMinute',
                                             'MaxBodyMegabytes', 'ServerKeySecret' ),
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
