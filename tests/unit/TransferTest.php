<?php

use PHPUnit\Framework\TestCase;

/**
 * A site for the transfer tests: its nodes, sections, users, imports, classes, secrets and formats are arrays.
 */
class TransferTestSite extends expImportTransferSite
{
    /** @var array node ID => remote ID (the nodes of this site) */
    public $nodes = array();
    /** @var array section ID => identifier */
    public $sections = array();
    /** @var array user object ID => remote ID */
    public $users = array();
    /** @var array identifier => import ID */
    public $imports = array();
    /** @var array class identifier => attributes */
    public $classes = array();
    /** @var string[] */
    public $secrets = array();
    /** @var string[] */
    public $languages = array( 'eng-GB', 'ger-DE' );

    public function nodeRef( $nodeID )
    {
        return isset( $this->nodes[$nodeID] ) ? array( 'node_id' => (int)$nodeID, 'remote_id' => $this->nodes[$nodeID],
                                                       'object_remote_id' => 'obj-' . $this->nodes[$nodeID], 'path' => 'folder/' . $nodeID ) : null;
    }

    public function resolveNode( array $ref )
    {
        if ( !empty( $ref['remote_id'] ) && ( $id = array_search( $ref['remote_id'], $this->nodes, true ) ) !== false )
            return (int)$id;
        return !empty( $ref['node_id'] ) && isset( $this->nodes[$ref['node_id']] ) ? (int)$ref['node_id'] : 0;
    }

    public function sectionRef( $sectionID )
    {
        return isset( $this->sections[$sectionID] ) ? array( 'id' => (int)$sectionID, 'identifier' => $this->sections[$sectionID] ) : null;
    }

    public function resolveSection( array $ref )
    {
        $id = !empty( $ref['identifier'] ) ? array_search( $ref['identifier'], $this->sections, true ) : false;
        return $id !== false ? (int)$id : 0;
    }

    public function userRef( $userID )
    {
        return isset( $this->users[$userID] ) ? array( 'object_id' => (int)$userID, 'remote_id' => $this->users[$userID] ) : null;
    }

    public function resolveUser( array $ref )
    {
        $id = !empty( $ref['remote_id'] ) ? array_search( $ref['remote_id'], $this->users, true ) : false;
        return $id !== false ? (int)$id : 0;
    }

    public function importId( $identifier )
    {
        return isset( $this->imports[$identifier] ) ? $this->imports[$identifier] : 0;
    }

    public function classAttributes( $identifier )
    {
        return isset( $this->classes[$identifier] ) ? $this->classes[$identifier] : null;
    }

    public function hasSecret( $name )
    {
        return in_array( $name, $this->secrets, true );
    }

    public function hasConnection( $identifier )
    {
        return false;
    }

    public function hasLanguage( $locale )
    {
        return in_array( $locale, $this->languages, true );
    }

    public function sources()
    {
        return array(
            'csv' => array( 'label' => 'CSV', 'status' => 'enabled', 'origins' => array( 'upload', 'url', 'path' ), 'schema' => expImportCsvSource::schema() ),
            'sql' => array( 'label' => 'SQL', 'status' => 'enabled', 'origins' => array( 'remote' ), 'schema' => array(
                'host' => array( 'type' => 'string' ),
                'password' => array( 'type' => 'secret' ),
                'query' => array( 'type' => 'text', 'required' => true ),
                'limit' => array( 'type' => 'int', 'default' => 0 ),
                'feed' => array( 'type' => 'url' ),
            ) ),
        );
    }

    public function handlers()
    {
        return array( 'mapping' => array( 'label' => 'Mapping', 'status' => 'enabled', 'legacy' => false, 'schema' => array() ),
                      'oldhandler' => array( 'label' => 'A 1.x handler', 'status' => 'enabled', 'legacy' => true, 'schema' => array() ) );
    }

    public function urlProblem( $url )
    {
        return null;
    }

    public function pathProblem( $path )
    {
        return null;
    }

    public function defaultParent()
    {
        return 2;
    }
}

/**
 * Import definitions as JSON (expImportTransfer) and templates of imports (expImportTemplates), without a database:
 * the round trip from one site to another, secrets that never reach the file, placeholders, taken identifiers and
 * files that are refused.
 */
class TransferTest extends TestCase
{
    /** @return TransferTestSite the site an import is exported from */
    protected static function sourceSite()
    {
        $site = new TransferTestSite();
        $site->nodes = array( 2 => 'root-node', 61 => 'news-folder', 70 => 'events-folder' );
        $site->sections = array( 1 => 'standard', 3 => 'media' );
        $site->users = array( 14 => 'user-robot' );
        $site->classes = array( 'article' => array( 'title' => 'ezstring', 'intro' => 'ezxmltext' ), 'folder' => array( 'name' => 'ezstring' ) );
        return $site;
    }

    /** @return TransferTestSite another installation: other node IDs, the same remote IDs */
    protected static function targetSite()
    {
        $site = new TransferTestSite();
        $site->nodes = array( 2 => 'root-node', 99 => 'news-folder' );
        $site->sections = array( 7 => 'media' );
        $site->users = array( 40 => 'user-robot' );
        $site->classes = array( 'article' => array( 'title' => 'ezstring', 'intro' => 'ezxmltext' ), 'folder' => array( 'name' => 'ezstring' ) );
        $site->secrets = array( 'crm_db' );
        return $site;
    }

    /** @return array an import as raw() gives it */
    protected static function rawCsv()
    {
        return array(
            'identifier' => 'news',
            'name' => 'News',
            'description' => 'The news of the CRM',
            'enabled' => true,
            'def_version' => 7,
            'source_type' => 'csv',
            'source' => array( 'origin' => 'upload', 'file' => 'f0e1d2c3b4a5.csv', 'original_name' => 'news.csv', 'manifest_file' => 'x.json',
                               'handed_over_by' => 'xrowextract:12', 'delimiter' => ';', 'encoding' => 'UTF-8', 'header' => true, 'stale' => 'gone' ),
            'handler' => 'mapping',
            'options' => array(),
            'target' => array( 'parent_node_id' => 61, 'section_id' => 3, 'language' => 'eng-GB', 'owner_id' => 14 ),
            'behaviour' => array( 'chunk_size' => 100, 'update_policy' => 'update', 'conflict_policy' => 'merge', 'notify_emails' => array( 'ops@example.invalid' ) ),
            'mapping' => array(
                'class' => 'article', 'language' => 'eng-GB', 'match' => array( 'type' => 'remote_id', 'pattern' => 'news-{id}' ),
                'fields' => array(
                    array( 'source' => 'title', 'attribute' => 'title', 'transforms' => array(), 'default' => '', 'required' => true, 'language' => '' ),
                    array( 'source' => 'title_de', 'attribute' => 'title', 'transforms' => array(), 'default' => '', 'required' => false, 'language' => 'ger-DE' ),
                ),
                'location' => array( 'parent_node_id' => 70, 'parent_from' => '', 'parent_prefix' => '' ),
                'class_from' => 'type',
                'variants' => array( 'folder' => array( 'class' => 'folder', 'fields' => array( array( 'source' => 'title', 'attribute' => 'name' ) ) ) ),
            ),
            'schedules' => array( array( 'kind' => 'daily', 'run_at' => 1700000000, 'interval_minutes' => 0, 'cron_expr' => '', 'timezone' => 'Europe/Berlin',
                                         'catch_up' => 'skip', 'notify' => array( 'ops@example.invalid' ), 'is_active' => 1 ) ),
            'trigger' => array( 'is_enabled' => 1, 'allow_push' => 1, 'secret_ref' => '', 'run_user_id' => 14, 'sign_salt' => 'SALT-1234567890abcdef' ),
        );
    }

    public function testRoundTripToAnotherSite(): void
    {
        $item = expImportTransfer::exportItem( self::rawCsv(), self::sourceSite() );
        $this->assertSame( array( 'origin', 'original_name', 'delimiter', 'encoding', 'header' ), array_keys( $item['source'] ) );
        $this->assertSame( 'news-folder', $item['target']['parent']['remote_id'] );
        $this->assertSame( 61, $item['target']['parent_node_id'], 'the node ID stays as the fallback' );
        $this->assertSame( 'media', $item['target']['section']['identifier'] );
        $this->assertSame( 'user-robot', $item['target']['owner']['remote_id'] );
        $this->assertSame( 'events-folder', $item['mapping']['location']['parent']['remote_id'] );
        $this->assertSame( 'type', $item['mapping']['class_from'] );
        $this->assertSame( 'ger-DE', $item['mapping']['fields'][1]['language'] );
        $this->assertSame( array( 'enabled' => true, 'allow_push' => true, 'secret' => '', 'run_user' => array( 'object_id' => 14, 'remote_id' => 'user-robot' ) ), $item['trigger'] );
        $this->assertTrue( $item['schedules'][0]['active'] );

        $document = expImportTransfer::document( array( $item ), 1700000000 );
        $this->assertSame( 'sqliimport-definitions', $document['format'] );
        $this->assertSame( 2, $document['version'] );
        $this->assertSame( 'sqliimport-import-news.json', expImportTransfer::filename( $document['imports'], 1700000000 ) );

        $items = expImportTransfer::parse( expImportTransfer::encode( $document ) );
        $plan = expImportTransfer::plan( $items, self::targetSite() );
        $this->assertCount( 1, $plan );
        $entry = $plan[0];
        $this->assertSame( array(), $entry['errors'] );
        $this->assertSame( 0, $entry['existing_id'] );
        $this->assertSame( 'new', $entry['choice'] );
        $this->assertSame( array( 'new', 'skip' ), $entry['choices'] );
        $resolved = $entry['resolved'];
        // the references resolve to this site's IDs
        $this->assertSame( 99, $resolved['import']['target']['parent_node_id'] );
        $this->assertSame( 7, $resolved['import']['target']['section_id'] );
        $this->assertSame( 40, $resolved['import']['target']['owner_id'] );
        $this->assertSame( 'eng-GB', $resolved['import']['target']['language'] );
        // the mapping's own parent is not on this site: the import's parent is used, with a warning
        $this->assertSame( 99, $resolved['mapping']['location']['parent_node_id'] );
        $this->assertArrayNotHasKey( 'parent', $resolved['mapping']['location'] );
        $this->assertSame( 'type', $resolved['mapping']['class_from'] );
        $this->assertCount( 2, $resolved['mapping']['fields'] );
        $this->assertSame( array( 'origin' => 'upload', 'delimiter' => ';', 'enclosure' => '"', 'encoding' => 'UTF-8', 'header' => true ),
                           array_intersect_key( $resolved['import']['source_config'], array_flip( array( 'origin', 'delimiter', 'enclosure', 'encoding', 'header' ) ) ) );
        $this->assertArrayNotHasKey( 'file', $resolved['import']['source_config'] );
        $this->assertSame( 'merge', $resolved['import']['behaviour']['conflict_policy'] );
        $this->assertSame( 100, $resolved['import']['behaviour']['chunk_size'] );
        // schedules come paused (store()), the trigger switched off, its run user resolved
        $this->assertCount( 1, $resolved['schedules'] );
        $this->assertSame( 'skip', $resolved['schedules'][0]['catch_up'] );
        $this->assertSame( array( 'allow_push' => true, 'secret_ref' => '', 'run_user_id' => 40 ), $resolved['trigger'] );
        $warnings = implode( "\n", $entry['warnings'] );
        $this->assertStringContainsString( 'upload the file news.csv again', $warnings );
        $this->assertStringContainsString( 'the parent node of the mapping folder/70', $warnings );
        $this->assertSame( 'article', $entry['class'] );
        $this->assertTrue( $entry['class_found'] );
    }

    public function testVersionOneFilesAreRead(): void
    {
        $v1 = array( 'format' => 'sqliimport-definitions', 'version' => 1, 'exported' => '2026-01-01T00:00:00+00:00', 'imports' => array(
            array( 'identifier' => 'old', 'name' => 'Old', 'source_type' => 'csv', 'source' => array( 'origin' => 'url', 'url' => 'https://example.invalid/a.csv' ),
                   'handler' => 'mapping', 'mapping' => null, 'target' => array( 'parent_node_id' => 2, 'section_id' => 0, 'language' => '', 'owner_id' => 0 ),
                   'behaviour' => array(), 'options' => array() ) ) );
        $plan = expImportTransfer::plan( expImportTransfer::parse( (string)json_encode( $v1 ) ), self::targetSite() );
        $this->assertSame( array(), $plan[0]['errors'] );
        $this->assertSame( 2, $plan[0]['resolved']['import']['target']['parent_node_id'] );
        $this->assertSame( 'https://example.invalid/a.csv', $plan[0]['resolved']['import']['source_config']['url'] );
        $this->assertStringContainsString( 'no mapping yet', implode( ' ', $plan[0]['warnings'] ) );
    }

    public function testSecretsNeverReachTheFile(): void
    {
        $raw = array(
            'identifier' => 'crm', 'name' => 'CRM', 'source_type' => 'sql', 'handler' => 'mapping', 'mapping' => null,
            'source' => array( 'origin' => 'remote', 'host' => 'db.example.invalid', 'password' => 'crm_db', 'query' => 'SELECT 1',
                               'feed' => 'https://reader:hunter2-feed@feeds.example.invalid/x' ),
            'options' => array(), 'target' => array(), 'behaviour' => array(),
            'trigger' => array( 'is_enabled' => 1, 'allow_push' => 0, 'secret_ref' => 'trigger_signing', 'run_user_id' => 0, 'sign_salt' => 'SALT-abcdef0123456789' ),
        );
        $item = expImportTransfer::exportItem( $raw, self::sourceSite() );
        $this->assertSame( array( 'crm_db', 'trigger_signing' ), $item['secrets'] );
        $this->assertSame( 'https://feeds.example.invalid/x', $item['source']['feed'] );

        // a secret option with a value that is not a name is never written; nor the free options of a 1.x handler
        // that look like a password
        $raw['source']['password'] = 'p@ss w0rd!';
        $raw['source']['url'] = 'https://u:topsecret-url@example.invalid/data.csv';
        $legacy = array( 'identifier' => 'legacy-rss', 'name' => 'RSS 1.x', 'source_type' => 'legacy', 'handler' => 'oldhandler', 'source' => array(),
                         'options' => array( 'feed' => 'https://example.invalid/rss', 'password' => 'legacy-pass-1', 'api_key' => 'legacy-key-2' ) );
        $document = expImportTransfer::document( array( expImportTransfer::exportItem( $raw, self::sourceSite() ),
                                                        expImportTransfer::exportItem( $legacy, self::sourceSite() ) ), 1700000000 );
        $json = expImportTransfer::encode( $document );
        foreach ( array( 'p@ss w0rd!', 'SALT-abcdef0123456789', 'topsecret-url', 'hunter2-feed', 'legacy-pass-1', 'legacy-key-2', 'sign_salt', 'key_hash' ) as $secret )
            $this->assertStringNotContainsString( $secret, $json );
        $this->assertSame( array( 'trigger_signing' ), $document['secrets'] );
        $this->assertSame( array( 'feed' => 'https://example.invalid/rss' ), $document['imports'][1]['options'] );

        // on the other site: the secret that is set is found, the other one is a warning
        $plan = expImportTransfer::plan( expImportTransfer::parse( expImportTransfer::encode(
            expImportTransfer::document( array( $item ), 1700000000 ) ) ), self::targetSite() );
        $this->assertSame( array( 'crm_db' => true, 'trigger_signing' => false ), $plan[0]['secrets'] );
        $this->assertStringContainsString( 'the secret trigger_signing is not set on this site', implode( ' ', $plan[0]['warnings'] ) );
        $this->assertSame( 'remote', $plan[0]['resolved']['import']['source_config']['origin'] );
    }

    public function testOptionsAreValidated(): void
    {
        $base = array( 'identifier' => 'q', 'name' => 'Q', 'source_type' => 'sql', 'handler' => 'mapping', 'mapping' => null );
        // a required option without a value: a warning, the import can be created (disabled) and finished in the editor
        $plan = expImportTransfer::plan( array( $base + array( 'source' => array( 'origin' => 'remote' ) ) ), self::targetSite() );
        $this->assertSame( array(), $plan[0]['errors'] );
        $this->assertStringContainsString( 'query is required', implode( ' ', $plan[0]['warnings'] ) );
        // a value of the wrong kind, an unknown source type or handler: it cannot be created
        $plan = expImportTransfer::plan( array( $base + array( 'source' => array( 'query' => 'SELECT 1', 'limit' => 'many' ) ),
                                                array( 'identifier' => 'x', 'name' => 'X', 'source_type' => 'nosuch' ),
                                                array( 'identifier' => 'y', 'name' => 'Y', 'source_type' => 'csv', 'handler' => 'nosuch' ),
                                                array( 'identifier' => 'z', 'name' => 'Z', 'source_type' => 'csv', 'handler' => 'oldhandler' ) ), self::targetSite() );
        $this->assertStringContainsString( 'a whole number is expected', implode( ' ', $plan[0]['errors'] ) );
        $this->assertSame( array( 'this site has no source type nosuch' ), $plan[1]['errors'] );
        $this->assertSame( array( 'this site has no handler nosuch' ), $plan[2]['errors'] );
        $this->assertStringContainsString( 'is a 1.x handler', implode( ' ', $plan[3]['errors'] ) );
        foreach ( $plan as $entry )
            $this->assertSame( array( 'skip' ), $entry['choices'] );
        // the mapping is checked against the class of this site
        $plan = expImportTransfer::plan( array( array( 'identifier' => 'm', 'name' => 'M', 'source_type' => 'csv', 'source' => array( 'origin' => 'upload' ),
                                                       'mapping' => array( 'class' => 'product', 'match' => array( 'type' => 'remote_id', 'pattern' => 'p-{id}' ),
                                                                           'fields' => array( array( 'source' => 'a', 'attribute' => 'price' ) ),
                                                                           'location' => array( 'parent_node_id' => 2 ) ) ),
                                                array( 'identifier' => 'n', 'name' => 'N', 'source_type' => 'csv', 'source' => array( 'origin' => 'upload' ),
                                                       'mapping' => array( 'class' => 'article', 'match' => array( 'type' => 'remote_id', 'pattern' => 'p-{id}' ),
                                                                           'fields' => array( array( 'source' => 'a', 'attribute' => 'nosuch' ) ),
                                                                           'location' => array( 'parent_node_id' => 2 ) ) ) ), self::targetSite() );
        $this->assertStringContainsString( 'this site has no class product', implode( ' ', $plan[0]['warnings'] ) );
        $this->assertFalse( $plan[0]['class_found'] );
        $this->assertStringContainsString( "the class has no attribute 'nosuch'", implode( ' ', $plan[1]['warnings'] ) );
    }

    public function testTakenIdentifiers(): void
    {
        $site = self::targetSite();
        $site->imports = array( 'news' => 12, 'news_2' => 13 );
        $plan = expImportTransfer::plan( array( array( 'identifier' => 'news', 'name' => 'News', 'source_type' => 'csv', 'source' => array( 'origin' => 'upload' ) ) ), $site );
        $this->assertSame( 12, $plan[0]['existing_id'] );
        $this->assertSame( 'news_3', $plan[0]['new_identifier'] );
        $this->assertSame( array( 'new', 'replace', 'skip' ), $plan[0]['choices'] );
        $this->assertSame( 'skip', $plan[0]['choice'], 'an identifier that is taken is skipped unless the person chooses' );
        $this->assertSame( 'free', expImportTransfer::freeIdentifier( 'free', function ( $i ) { return false; } ) );
        $long = str_repeat( 'a', 100 );
        $this->assertSame( 1, preg_match( expImportTransfer::IDENTIFIER, expImportTransfer::freeIdentifier( $long, function ( $i ) use ( $long ) { return $i === $long; } ) ) );
    }

    public function testMalformedFilesAreRefused(): void
    {
        $ok = array( 'identifier' => 'a', 'name' => 'A' );
        $cases = array(
            'not JSON' => '{nope',
            'other format' => json_encode( array( 'format' => 'something', 'version' => 1, 'imports' => array( $ok ) ) ),
            'newer version' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 3, 'imports' => array( $ok ) ) ),
            'version as text' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => '2', 'imports' => array( $ok ) ) ),
            'no list' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( 'a' => $ok ) ) ),
            'empty' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array() ) ),
            'no identifier' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( array( 'name' => 'A' ) ) ) ),
            'bad identifier' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( array( 'identifier' => '../etc', 'name' => 'A' ) ) ) ),
            'no name' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( array( 'identifier' => 'a', 'name' => ' ' ) ) ) ),
            'twice' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( $ok, $ok ) ) ),
            'mapping text' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( $ok + array( 'mapping' => 'x' ) ) ) ),
            'schedules object' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( $ok + array( 'schedules' => array( 'a' => 1 ) ) ) ) ),
            'source text' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( $ok + array( 'source' => 'x' ) ) ) ),
            'bad source type' => json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( $ok + array( 'source_type' => 'CSV;DROP' ) ) ) ),
            'too large' => str_repeat( ' ', expImportTransfer::MAX_BYTES + 1 ),
        );
        foreach ( $cases as $case => $json )
        {
            try
            {
                expImportTransfer::parse( (string)$json );
                $this->fail( "$case: the file is accepted" );
            }
            catch ( expImportValidationException $e )
            {
                $this->assertNotSame( array(), $e->errors, $case );
            }
        }
        $this->assertCount( 1, expImportTransfer::parse( (string)json_encode( array( 'format' => 'sqliimport-definitions', 'version' => 2, 'imports' => array( $ok ) ) ) ) );
    }

    public function testTemplatesHavePlaceholdersAndNoSourceData(): void
    {
        $item = expImportTransfer::exportItem( self::rawCsv(), self::sourceSite() );
        $template = expImportTemplates::fromItem( $item, 'News of the CRM', 'Articles from a CSV' );
        $this->assertSame( array(), expImportTemplates::check( $template ) );
        $this->assertSame( '{{name}}', $template['import']['name'] );
        $this->assertSame( '{{identifier}}', $template['import']['identifier'] );
        $this->assertSame( '{{identifier}}-{id}', $template['import']['mapping']['match']['pattern'] );
        $this->assertSame( array( 'origin' => 'upload', 'delimiter' => ';', 'encoding' => 'UTF-8', 'header' => true ), $template['import']['source'] );
        foreach ( array( 'schedules', 'trigger', 'enabled' ) as $key )
            $this->assertArrayNotHasKey( $key, $template['import'] );
        $this->assertArrayNotHasKey( 'owner', $template['import']['target'] );
        $this->assertSame( array(), $template['import']['behaviour']['notify_emails'] );
        $this->assertStringNotContainsString( 'example.invalid', expImportTransfer::encode( $template ) );

        $filled = expImportTemplates::fill( $template, array( 'name' => 'Press releases', 'identifier' => '' ) );
        $this->assertSame( 'Press releases', $filled['name'] );
        $this->assertSame( 'press_releases', $filled['identifier'] );
        $this->assertSame( 'press_releases-{id}', $filled['mapping']['match']['pattern'] );
        $plan = expImportTransfer::plan( array( $filled ), self::targetSite() );
        $this->assertSame( array(), $plan[0]['errors'] );

        foreach ( array( array( 'name' => '' ), array( 'name' => 'A {{identifier}}' ), array( 'name' => 'A', 'identifier' => 'Not valid' ) ) as $values )
        {
            try
            {
                expImportTemplates::fill( $template, $values );
                $this->fail( 'filled with ' . json_encode( $values ) );
            }
            catch ( expImportValidationException $e )
            {
                $this->assertNotSame( array(), $e->errors );
            }
        }
        // a placeholder the template uses must be declared
        $broken = $template;
        $broken['import']['description'] = 'from {{feed}}';
        $this->assertStringContainsString( '{{feed}} is used but not declared', implode( ' ', expImportTemplates::check( $broken ) ) );
        $this->assertNotSame( array(), expImportTemplates::check( array( 'format' => 'sqliimport-template', 'version' => 9, 'title' => 'x', 'import' => array() ) ) );
    }

    public function testShippedTemplates(): void
    {
        $files = glob( dirname( __DIR__, 2 ) . '/share/templates/*.json' ) ?: array();
        $this->assertNotEmpty( $files );
        $all = expImportTemplates::scan( array( array( 'origin' => 'sqliimport', 'files' => $files, 'deletable' => false ) ) );
        $this->assertCount( count( $files ), $all, 'every shipped template is read' );
        foreach ( $all as $id => $entry )
        {
            $this->assertMatchesRegularExpression( '/^ext-sqliimport-[a-z0-9-]+$/', $id );
            $this->assertFalse( $entry['deletable'] );
            $values = array();
            foreach ( $entry['placeholders'] as $name => $p )
                $values[$name] = $name === 'feed_url' ? 'https://example.invalid/feed.rss' : ( $p['default'] !== '' ? $p['default'] : 'x' );
            $item = expImportTemplates::fill( $entry['template'], $values );
            $this->assertSame( array(), expImportTransfer::checkItem( $item ), $id );
            $this->assertSame( array(), expImportTemplates::usedPlaceholders( $item ), $id );
        }
        $news = $all['ext-sqliimport-news-feed-articles'];
        $item = expImportTemplates::fill( $news['template'], array( 'feed_url' => 'https://example.invalid/feed.rss' ) );
        $this->assertSame( 'news_feed', $item['identifier'] );
        $this->assertSame( 'https://example.invalid/feed.rss', $item['source']['url'] );
    }
}
