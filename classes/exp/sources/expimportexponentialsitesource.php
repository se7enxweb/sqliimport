<?php
/**
 * File containing the expImportExponentialSiteSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * Content of another Exponential site through its REST API (v1, the API of the mobile applications, served by the
 * extension ezprestapi): the source reads the children of a node page by page
 * (GET <site>/api/ezp/v1/content/node/<node id>/list/offset/<n>/limit/<m>?ResponseGroups=Metadata,Fields), down to
 * the depth of the option, and writes them as NDJSON into the run's directory once per run.
 *
 * Authentication: none (a site whose REST API is public), basic (user and password), a token (an OAuth access token
 * or a personal API key, sent as "Authorization: Bearer"), or OAuth2 at the site's token end point
 * (<site>/api/oauth/token): the password grant (client ID, client secret, user, password) or a refresh token, which
 * is the grant an Exponential site gives (authorization_code once, refresh_token after). Passwords, secrets and
 * tokens are names of secrets (sqliimport.ini [SecretSettings] or env:VARIABLE), never the values.
 *
 * A node becomes a record: node_id, object_id, remote_id, node_remote_id, class_identifier, name, published, modified,
 * url (the full address), url_alias, parent_node_id, depth, and every attribute in fields.<identifier> (images as
 * addresses on the site), with its datatype in field_types.<identifier>.
 */
class expImportExponentialSiteSource extends expImportJsonSource
{
    public static function identifier()
    {
        return 'exponential_site';
    }

    public static function label()
    {
        return 'Another Exponential site (REST API)';
    }

    public static function extensions()
    {
        return array( 'ndjson' );
    }

    public static function group()
    {
        return 'platform';
    }

    public static function description()
    {
        return 'Content of another Exponential site, read through its REST API: the children of a node (or a subtree '
             . 'to a depth), of one class or every class, with every attribute. The mapping is made from the first '
             . 'record (its class, every attribute by identifier) and matches the remote ID, so the copy follows the '
             . 'original at every run.';
    }

    public static function examples()
    {
        return array( array( 'label' => 'Two articles of a site, as the source reads them (a snapshot)', 'file' => 'exponential-site.ndjson',
                             'options' => array( 'base_url' => 'https://www.example.org', 'parent_node' => 2 ) ) );
    }

    public static function variants()
    {
        return array();
    }

    public static function schema()
    {
        return array(
            'base_url' => array( 'type' => 'url', 'label' => 'Site address', 'required' => true,
                                 'help' => 'The address of the other site, e.g. https://www.example.org (REST API at /api/ezp/v1)' ),
            'parent_node' => array( 'type' => 'node', 'label' => 'Parent node ID', 'default' => 2, 'help' => 'The node of the other site whose children are read' ),
            'depth' => array( 'type' => 'int', 'label' => 'Depth', 'default' => 1, 'min' => 1, 'max' => 10, 'help' => '1: the children; 2: their children too, and so on' ),
            'class' => array( 'type' => 'class', 'label' => 'Class', 'help' => 'Only the objects of this class (empty: every class)' ),
            'translation' => array( 'type' => 'string', 'label' => 'Translation', 'pattern' => '/^([a-z]{3}-[A-Z]{2})?$/',
                                    'help' => 'The language to read, e.g. ger-DE (empty: the site\'s)' ),
            'page_size' => array( 'type' => 'int', 'label' => 'Page size', 'default' => 50, 'min' => 1, 'max' => 100 ),
            'max_items' => array( 'type' => 'int', 'label' => 'Most nodes', 'default' => 1000, 'min' => 1,
                                  'help' => 'At most this many nodes per run (and never more than [PlatformSettings] MaxItems)' ),
            'auth' => array( 'type' => 'enum', 'label' => 'Authentication', 'default' => 'none',
                             'values' => array( 'none' => 'None (a public REST API)', 'basic' => 'User and password', 'bearer' => 'Token or API key',
                                                'oauth_password' => 'OAuth2: password grant', 'oauth_refresh' => 'OAuth2: refresh token' ) ),
            'user' => array( 'type' => 'string', 'label' => 'User', 'help' => 'With User and password and the password grant: the user name' ),
            'password_secret' => array( 'type' => 'secret', 'label' => 'Password (secret)', 'help' => 'The name of the secret with the password' ),
            'token_secret' => array( 'type' => 'secret', 'label' => 'Token (secret)', 'help' => 'With Token: the name of the secret with the token or API key' ),
            'client_id' => array( 'type' => 'string', 'label' => 'OAuth client ID' ),
            'client_secret' => array( 'type' => 'secret', 'label' => 'OAuth client secret (secret)', 'help' => 'The name of the secret with the client secret (empty for a public client)' ),
            'refresh_token_secret' => array( 'type' => 'secret', 'label' => 'Refresh token (secret)', 'help' => 'With the refresh token: the name of the secret with it' ),
            'token_url' => array( 'type' => 'url', 'label' => 'Token end point', 'help' => 'Empty: <site address>/api/oauth/token' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'remote_id', 'help' => 'The field that names a record; remote_id is the object\'s remote ID.' ),
        );
    }

    public function records( array $cursor = array() )
    {
        $this->config['format'] = 'ndjson';
        return parent::records( $cursor );
    }

    public function count()
    {
        $this->config['format'] = 'ndjson';
        return parent::count();
    }

    // ---- reading the site ----------------------------------------------------------------------------------------

    /**
     * Reads the site and writes its nodes as NDJSON (the remote origin, once per run).
     *
     * @param array $options the validated options
     * @param string $target the snapshot file
     * @param int $limit at most so many records (0: all)
     */
    public static function fetch( array $options, $target, $limit )
    {
        $used = array();
        try
        {
            expImportPlatformHttp::writeNdjson( self::nodes( $options, (int)$limit, $used ), $target, (int)$limit );
        }
        catch ( expImportSourceException $e )
        {
            throw new expImportSourceException( expImportSecrets::mask( $e->getMessage(), $used ) );
        }
    }

    /**
     * The headers of the requests: the Authorization of the option auth (an OAuth grant asks the token end point once).
     *
     * @param array $options
     * @param string[] $used the secrets used
     * @return array
     */
    public static function headers( array $options, array &$used )
    {
        $auth = isset( $options['auth'] ) ? (string)$options['auth'] : 'none';
        if ( $auth !== 'oauth_password' && $auth !== 'oauth_refresh' )
            return expImportPlatformHttp::authHeaders( $options, $used );
        $base = rtrim( (string)$options['base_url'], '/' );
        $tokenUrl = !empty( $options['token_url'] ) ? (string)$options['token_url'] : $base . '/api/oauth/token';
        if ( !expImportPlatformHttp::sameSite( $tokenUrl, $base ) )
            throw new expImportSourceException( 'the token end point must be on the site itself' );
        $form = array( 'client_id' => isset( $options['client_id'] ) ? (string)$options['client_id'] : '' );
        if ( !empty( $options['client_secret'] ) )
        {
            $used[] = (string)$options['client_secret'];
            $form['client_secret'] = expImportSecrets::required( (string)$options['client_secret'], 'the OAuth client secret' );
        }
        if ( $auth === 'oauth_password' )
        {
            $used[] = isset( $options['password_secret'] ) ? (string)$options['password_secret'] : '';
            $form += array( 'grant_type' => 'password', 'username' => isset( $options['user'] ) ? (string)$options['user'] : '',
                            'password' => expImportSecrets::required( isset( $options['password_secret'] ) ? (string)$options['password_secret'] : '', 'the password' ) );
        }
        else
        {
            $used[] = isset( $options['refresh_token_secret'] ) ? (string)$options['refresh_token_secret'] : '';
            $form += array( 'grant_type' => 'refresh_token',
                            'refresh_token' => expImportSecrets::required( isset( $options['refresh_token_secret'] ) ? (string)$options['refresh_token_secret'] : '', 'the refresh token' ) );
        }
        $answer = expImportPlatformHttp::request( 'POST', $tokenUrl, array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json' ),
                                                  http_build_query( $form, '', '&', PHP_QUERY_RFC3986 ) );
        $data = json_decode( (string)$answer['body'], true );
        if ( $answer['status'] < 200 || $answer['status'] >= 300 || !is_array( $data ) || empty( $data['access_token'] ) || !is_string( $data['access_token'] ) )
        {
            $reason = is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ? ': ' . substr( $data['error'], 0, 80 ) : '';
            throw new expImportSourceException( 'the token end point ' . expImportPlatformHttp::shown( $tokenUrl ) . ' gave no access token (' . $answer['status'] . $reason . ')' );
        }
        return array( 'Authorization' => 'Bearer ' . $data['access_token'] );
    }

    /**
     * The nodes of the site as records, children first, level by level down to the depth.
     *
     * @param array $options
     * @param int $limit
     * @param string[] $used the secrets used
     * @return Generator array
     */
    public static function nodes( array $options, $limit, array &$used )
    {
        $base = rtrim( (string)$options['base_url'], '/' );
        $headers = self::headers( $options, $used );
        $pageSize = max( 1, min( 100, isset( $options['page_size'] ) ? (int)$options['page_size'] : 50 ) );
        $depth = max( 1, min( 10, isset( $options['depth'] ) ? (int)$options['depth'] : 1 ) );
        $class = isset( $options['class'] ) ? (string)$options['class'] : '';
        $max = min( max( 1, isset( $options['max_items'] ) ? (int)$options['max_items'] : 1000 ),
                    max( 1, (int)expImportPlatformHttp::setting( 'PlatformSettings', 'MaxItems', 5000 ) ) );
        if ( $limit > 0 )
            $max = min( $max, $limit );
        $requests = max( 1, (int)expImportPlatformHttp::setting( 'PlatformSettings', 'MaxPages', 200 ) );
        $query = '?ResponseGroups=Metadata,Fields' . ( !empty( $options['translation'] ) ? '&Translation=' . rawurlencode( (string)$options['translation'] ) : '' );
        $queue = array( array( max( 1, (int)( isset( $options['parent_node'] ) ? $options['parent_node'] : 2 ) ), 1 ) );
        $seen = array();
        $n = 0;
        $made = 0;
        while ( $queue )
        {
            list( $parent, $level ) = array_shift( $queue );
            for ( $offset = 0; ; $offset += $pageSize )
            {
                if ( ++$made > $requests )
                    throw new expImportSourceException( 'more requests than [PlatformSettings] MaxPages allows; lower the depth or the number of nodes' );
                $url = $base . '/api/ezp/v1/content/node/' . $parent . '/list/offset/' . $offset . '/limit/' . $pageSize . $query;
                $answer = expImportPlatformHttp::json( 'GET', $url, $headers );
                $children = isset( $answer['childrenNodes'] ) && is_array( $answer['childrenNodes'] ) ? $answer['childrenNodes'] : null;
                if ( $children === null )
                    throw new expImportSourceException( 'the answer of ' . expImportPlatformHttp::shown( $url ) . ' is not a list of nodes' );
                foreach ( $children as $child )
                {
                    if ( !is_array( $child ) || empty( $child['nodeId'] ) || isset( $seen[(int)$child['nodeId']] ) )
                        continue;
                    $seen[(int)$child['nodeId']] = true;
                    $record = self::nodeRecord( $child, $base, $parent, $level );
                    if ( $level < $depth )
                        $queue[] = array( (int)$child['nodeId'], $level + 1 );
                    if ( $class !== '' && $record['class_identifier'] !== $class )
                        continue;
                    yield $record;
                    if ( ++$n >= $max )
                        return;
                }
                $total = isset( $answer['metadata']['childrenCount'] ) ? (int)$answer['metadata']['childrenCount'] : -1;
                if ( count( $children ) < $pageSize || ( $total >= 0 && $offset + $pageSize >= $total ) )
                    break;
            }
        }
    }

    /**
     * One node of a list answer as a record.
     *
     * @param array $node
     * @param string $base the site's address
     * @param int $parent
     * @param int $depth
     * @return array
     */
    public static function nodeRecord( array $node, $base = '', $parent = 0, $depth = 1 )
    {
        $url = isset( $node['fullUrl'] ) ? (string)$node['fullUrl'] : '';
        $record = array(
            'node_id' => isset( $node['nodeId'] ) ? (int)$node['nodeId'] : 0,
            'object_id' => isset( $node['objectId'] ) ? (int)$node['objectId'] : 0,
            'remote_id' => isset( $node['objectRemoteId'] ) ? (string)$node['objectRemoteId'] : '',
            'node_remote_id' => isset( $node['nodeRemoteId'] ) ? (string)$node['nodeRemoteId'] : '',
            'class_identifier' => isset( $node['classIdentifier'] ) ? (string)$node['classIdentifier'] : '',
            'name' => isset( $node['objectName'] ) ? (string)$node['objectName'] : '',
            'published' => isset( $node['datePublished'] ) ? (int)$node['datePublished'] : 0,
            'modified' => isset( $node['dateModified'] ) ? (int)$node['dateModified'] : 0,
            'url' => $url,
            'url_alias' => $url !== '' ? ltrim( (string)parse_url( $url, PHP_URL_PATH ), '/' ) : '',
            'parent_node_id' => (int)$parent,
            'depth' => (int)$depth,
            'fields' => array(),
            'field_types' => array(),
        );
        foreach ( isset( $node['fields'] ) && is_array( $node['fields'] ) ? $node['fields'] : array() as $identifier => $field )
        {
            if ( !is_array( $field ) )
                continue;
            $identifier = isset( $field['identifier'] ) ? (string)$field['identifier'] : (string)$identifier;
            $type = isset( $field['type'] ) ? (string)$field['type'] : '';
            $value = array_key_exists( 'value', $field ) ? $field['value'] : '';
            if ( $value === false || $value === null )
                $value = '';
            // an image or a file is a path on the site: its address
            if ( in_array( $type, array( 'ezimage', 'ezbinaryfile', 'ezmedia' ), true ) && is_string( $value ) && $value !== '' )
            {
                $path = explode( '|', $value )[0];
                $value = preg_match( '#^https?://#i', $path ) || $base === '' ? $path : rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
            }
            $record['fields'][$identifier] = $value;
            $record['field_types'][$identifier] = $type;
        }
        return $record;
    }

    /**
     * The mapping of the first record: its class, every attribute by identifier, matched by the remote ID.
     *
     * @param array $config the source configuration (the options; 'file' for a snapshot)
     * @return array
     */
    public static function presetFor( array $config )
    {
        $first = null;
        if ( !empty( $config['file'] ) && is_file( (string)$config['file'] ) )
        {
            foreach ( ( new self( $config ) )->records() as $record )
            {
                $first = $record->data;
                break;
            }
        }
        elseif ( !empty( $config['base_url'] ) )
        {
            list( $options ) = expImportOptionSchema::validate( self::schema(), $config );
            $used = array();
            try
            {
                foreach ( self::nodes( $options, 1, $used ) as $record )
                {
                    $first = $record;
                    break;
                }
            }
            catch ( expImportSourceException $e )
            {
                throw new expImportSourceException( expImportSecrets::mask( $e->getMessage(), $used ) );
            }
        }
        if ( !$first || empty( $first['class_identifier'] ) || empty( $first['field_types'] ) )
            return array();
        $fields = array();
        foreach ( $first['field_types'] as $identifier => $type )
        {
            $line = array( 'source' => 'fields.' . $identifier, 'attribute' => (string)$identifier );
            switch ( $type )
            {
                case 'ezxmltext':
                    // the REST API gives the text of rich text
                    $line['transforms'] = array( array( 'name' => 'richtext' ) );
                    break;
                case 'ezimage':
                    $line['transforms'] = array( array( 'name' => 'image' ) );
                    break;
                case 'ezstring':
                case 'eztext':
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
                case 'eztags':
                    break;
                default:
                    continue 2;
            }
            $line['datatypes'] = array( $type );
            $fields[] = $line;
        }
        return array( 'class' => (string)$first['class_identifier'], 'match' => array( 'type' => 'remote_id', 'pattern' => '{remote_id}' ), 'fields' => $fields );
    }
}
