<?php
/**
 * File containing the expImportTransferSite class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What expImportTransfer asks the site while it exports or reads import definitions: references for node, section
 * and user IDs (so a definition names its parent by remote ID and works on another installation), and the lookups
 * of an import plan (is the identifier taken, does the class, the node, the secret, the source type exist here).
 *
 * Every method is one question, so the unit tests replace the site with a subclass that answers from arrays; this
 * class answers from the installation.
 */
class expImportTransferSite
{
    /**
     * A node as a reference that survives a move to another installation.
     *
     * @param int $nodeID
     * @return array|null array( node_id, remote_id, object_remote_id, path ), null when there is no such node
     */
    public function nodeRef( $nodeID )
    {
        $nodeID = (int)$nodeID;
        if ( $nodeID < 1 || !class_exists( 'eZContentObjectTreeNode' ) )
            return null;
        $node = eZContentObjectTreeNode::fetch( $nodeID );
        if ( !$node instanceof eZContentObjectTreeNode )
            return null;
        $object = $node->attribute( 'object' );
        return array( 'node_id' => $nodeID, 'remote_id' => (string)$node->attribute( 'remote_id' ),
                      'object_remote_id' => $object instanceof eZContentObject ? (string)$object->attribute( 'remote_id' ) : '',
                      'path' => (string)$node->attribute( 'path_identification_string' ) );
    }

    /**
     * The node of a reference on this site: by the node's remote ID, then the main node of the object's remote ID,
     * then the node ID itself (the fallback, only when that node exists).
     *
     * @param array $ref
     * @return int 0 when nothing is found
     */
    public function resolveNode( array $ref )
    {
        if ( !class_exists( 'eZContentObjectTreeNode' ) )
            return 0;
        if ( !empty( $ref['remote_id'] ) )
        {
            $node = eZContentObjectTreeNode::fetchByRemoteID( (string)$ref['remote_id'] );
            if ( $node instanceof eZContentObjectTreeNode )
                return (int)$node->attribute( 'node_id' );
        }
        if ( !empty( $ref['object_remote_id'] ) )
        {
            $object = eZContentObject::fetchByRemoteID( (string)$ref['object_remote_id'] );
            if ( $object instanceof eZContentObject && (int)$object->attribute( 'main_node_id' ) )
                return (int)$object->attribute( 'main_node_id' );
        }
        if ( !empty( $ref['node_id'] ) && eZContentObjectTreeNode::fetch( (int)$ref['node_id'], false, false ) )
            return (int)$ref['node_id'];
        return 0;
    }

    /**
     * @param int $sectionID
     * @return array|null array( id, identifier )
     */
    public function sectionRef( $sectionID )
    {
        $section = (int)$sectionID > 0 && class_exists( 'eZSection' ) ? eZSection::fetch( (int)$sectionID ) : null;
        return $section ? array( 'id' => (int)$sectionID, 'identifier' => (string)$section->attribute( 'identifier' ) ) : null;
    }

    /** @return int the section of a reference here (identifier first, then the ID), 0 when none */
    public function resolveSection( array $ref )
    {
        if ( !class_exists( 'eZSection' ) )
            return 0;
        if ( !empty( $ref['identifier'] ) && method_exists( 'eZSection', 'fetchByIdentifier' ) )
        {
            $section = eZSection::fetchByIdentifier( (string)$ref['identifier'] );
            if ( $section )
                return (int)$section->attribute( 'id' );
        }
        return !empty( $ref['id'] ) && eZSection::fetch( (int)$ref['id'] ) ? (int)$ref['id'] : 0;
    }

    /**
     * A user as a reference (the remote ID of the user object and its ID); no name or address.
     *
     * @param int $userID
     * @return array|null
     */
    public function userRef( $userID )
    {
        $object = (int)$userID > 0 && class_exists( 'eZContentObject' ) ? eZContentObject::fetch( (int)$userID ) : null;
        return $object instanceof eZContentObject ? array( 'object_id' => (int)$userID, 'remote_id' => (string)$object->attribute( 'remote_id' ) ) : null;
    }

    /** @return int the user of a reference here, 0 when none */
    public function resolveUser( array $ref )
    {
        if ( !class_exists( 'eZUser' ) )
            return 0;
        if ( !empty( $ref['remote_id'] ) )
        {
            $object = eZContentObject::fetchByRemoteID( (string)$ref['remote_id'] );
            if ( $object instanceof eZContentObject && eZUser::fetch( (int)$object->attribute( 'id' ) ) )
                return (int)$object->attribute( 'id' );
        }
        return !empty( $ref['object_id'] ) && eZUser::fetch( (int)$ref['object_id'] ) ? (int)$ref['object_id'] : 0;
    }

    /** @return int the ID of the import with this identifier, 0 when there is none */
    public function importId( $identifier )
    {
        $import = expImportDefinition::fetchByIdentifier( (string)$identifier );
        return $import ? (int)$import->attribute( 'id' ) : 0;
    }

    /** @return array|null the attributes of a class (identifier => datatype), null when this site has no such class */
    public function classAttributes( $identifier )
    {
        return expImportMappingHandler::classAttributes( (string)$identifier );
    }

    /** @return bool whether a secret of this name is set here (never its value) */
    public function hasSecret( $name )
    {
        return expImportSecrets::get( (string)$name ) !== null;
    }

    /** @return bool whether a named connection of Setup > Data tables > Connections is there (Exponential 6.0.15) */
    public function hasConnection( $identifier )
    {
        if ( !class_exists( 'expDataConnections' ) )
            return false;
        try
        {
            return expDataConnections::get( (string)$identifier ) !== null;
        }
        catch ( Exception $e )
        {
            return false;
        }
    }

    /** @return bool whether the site has this language */
    public function hasLanguage( $locale )
    {
        return class_exists( 'eZContentLanguage' ) && eZContentLanguage::fetchByLocale( (string)$locale ) ? true : false;
    }

    /** @return array the source types (expImportRegistry::sources()) */
    public function sources()
    {
        return expImportRegistry::instance()->sources();
    }

    /** @return array the handlers (expImportRegistry::handlers()) */
    public function handlers()
    {
        return expImportRegistry::instance()->handlers();
    }

    /** @return string|null why the address may not be read here, null when it may */
    public function urlProblem( $url )
    {
        return expImportSourceFiles::refuseUrl( (string)$url );
    }

    /** @return string|null why the server path may not be read here, null when it may */
    public function pathProblem( $path )
    {
        try
        {
            expImportSourceFiles::allowedPath( (string)$path );
            return null;
        }
        catch ( expImportSourceException $e )
        {
            return $e->getMessage();
        }
    }

    /** @return string[] the schedule's errors (expImportCron::validate()) */
    public function scheduleErrors( array $calc )
    {
        return expImportCron::validate( $calc );
    }

    /** @return int the parent of a new import when its own is not found here */
    public function defaultParent()
    {
        return (int)expImport::setting( 'ImportSettings', 'DefaultParentNodeID', 2 );
    }
}
