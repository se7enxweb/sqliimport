<?php
/**
 * File containing the expImportEditor class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The import editor of the administration (sqliimport/edit): six steps, each a form that is saved on its own, so an
 * import is never lost half-way and every step can be opened again.
 *
 *   source      name, source type, where the data comes from (upload, address, server file) and the source's options;
 *               a new import is created here, disabled until the review step saves it
 *   processing  the no-code mapping or a handler with its options (the form is made from the handler's schema; a 1.x
 *               handler keeps its free key=value options), the target and the behaviour
 *   mapping     the class, how a record finds its object, where new objects go, and one line per attribute (source
 *               field, transforms, default, required); Auto-match fills the lines from the sample, without AI
 *   preview     the sample of the source and a dry run of its first records, in the request
 *   schedule    none, once, every N minutes, hourly, daily, weekly, monthly or a cron expression, in a time zone
 *   review      the summary; Save enables the import, Save and run starts a run
 *
 * Every value is checked on the server (the source and handler options against their schemas, the mapping against
 * the class, the schedule by expImportCron). This class does the work; the view only reads the request and shows the
 * template.
 */
class expImportEditor
{
    const STEPS = array( 'source', 'processing', 'mapping', 'preview', 'schedule', 'review' );

    /** @var expImportDefinition|null */
    public $import;

    /** @var array name => message */
    public $errors = array();

    /** @var string a message for the page (saved, matched ...) */
    public $notice = '';

    /** @var eZHTTPTool */
    protected $http;

    public function __construct( ?expImportDefinition $import, ?eZHTTPTool $http = null )
    {
        $this->import = $import;
        $this->http = $http ?: eZHTTPTool::instance();
    }

    /**
     * The steps this import has: no mapping step for a handler, no preview for a 1.x handler.
     * @return string[]
     */
    public function steps()
    {
        $steps = self::STEPS;
        if ( $this->import && $this->import->attribute( 'handler' ) !== 'mapping' )
            $steps = array_values( array_diff( $steps, array( 'mapping' ) ) );
        if ( $this->import && $this->import->isLegacy() )
            $steps = array_values( array_diff( $steps, array( 'preview' ) ) );
        return $steps;
    }

    /** @return string the step after $step */
    public function next( $step )
    {
        $steps = $this->steps();
        $i = array_search( $step, $steps, true );
        return $i === false || !isset( $steps[$i + 1] ) ? 'review' : $steps[$i + 1];
    }

    /** @return string */
    protected function post( $name, $default = '' )
    {
        return $this->http->hasPostVariable( $name ) ? $this->http->postVariable( $name ) : $default;
    }

    /** @return array */
    protected function postArray( $name )
    {
        $value = $this->post( $name, array() );
        return is_array( $value ) ? $value : array();
    }

    // ---- source ------------------------------------------------------------------------------------------------

    /**
     * Saves the source step.
     * @return bool
     */
    public function saveSource()
    {
        $registry = expImportRegistry::instance();
        $sources = $registry->sources();
        $handlers = $registry->handlers();
        $name = trim( (string)$this->post( 'Name' ) );
        $type = (string)$this->post( 'SourceType', 'csv' );
        $origin = (string)$this->post( 'Origin', 'upload' );
        if ( $name === '' )
            $this->errors['Name'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Give the import a name.' );
        // a 1.x handler is its own source
        $legacy = $type === 'legacy';
        if ( !$legacy && ( !isset( $sources[$type] ) || $sources[$type]['status'] !== 'enabled' ) )
            $this->errors['SourceType'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Choose a source type.' );
        $config = $this->import ? $this->import->sourceConfig() : array();
        if ( !$legacy && isset( $sources[$type] ) )
        {
            list( $options, $optionErrors ) = expImportOptionSchema::validate( $sources[$type]['schema'], $this->postArray( 'SourceOptions' ) );
            foreach ( $optionErrors as $key => $message )
                $this->errors['SourceOptions_' . $key] = $message;
            $config = array_intersect_key( $config, array_flip( array( 'file', 'original_name', 'manifest_file', 'handed_over_by' ) ) ) + $options;
            $config['origin'] = in_array( $origin, array( 'upload', 'url', 'path' ), true ) ? $origin : 'upload';
            if ( $config['origin'] === 'upload' )
            {
                $file = eZHTTPFile::canFetch( 'SourceFile' ) ? eZHTTPFile::fetch( 'SourceFile' ) : null;
                if ( $file instanceof eZHTTPFile )
                {
                    try
                    {
                        $config['file'] = expImportSourceFiles::storeUpload( $file->attribute( 'filename' ), $file->attribute( 'original_filename' ),
                                                                             $sources[$type]['extensions'] );
                        $config['original_name'] = basename( (string)$file->attribute( 'original_filename' ) );
                    }
                    catch ( expImportSourceException $e )
                    {
                        $this->errors['SourceFile'] = $e->getMessage();
                    }
                }
                elseif ( ( $handover = expImportExternalFile::pending() ) && $this->post( 'UseHandover' ) === '1' )
                {
                    // a file another tool handed over (sqliimport/edit/new/(from)/<tool>/(job)/<id>), stored in the uploads
                    $config['file'] = $handover['file'];
                    $config['original_name'] = $handover['original_name'];
                    if ( $handover['manifest'] !== '' )
                        $config['manifest_file'] = $handover['manifest'];
                    $config['handed_over_by'] = $handover['tool'] . ':' . $handover['job'];
                    expImportExternalFile::forget();
                }
                elseif ( empty( $config['file'] ) )
                    $this->errors['SourceFile'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Choose the file to upload.' );
            }
            elseif ( $config['origin'] === 'url' )
            {
                $config['url'] = trim( (string)$this->post( 'SourceURL' ) );
                $refusal = expImportSourceFiles::refuseUrl( $config['url'] );
                if ( $refusal !== null )
                    $this->errors['SourceURL'] = $refusal;
            }
            else
            {
                $config['path'] = trim( (string)$this->post( 'SourcePath' ) );
                try
                {
                    expImportSourceFiles::allowedPath( $config['path'] );
                }
                catch ( expImportSourceException $e )
                {
                    $this->errors['SourcePath'] = $e->getMessage();
                }
            }
        }
        if ( $legacy )
        {
            $handler = (string)$this->post( 'LegacyHandler' );
            if ( !isset( $handlers[$handler] ) || !$handlers[$handler]['legacy'] )
                $this->errors['LegacyHandler'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Choose the 1.x handler.' );
            $config = array();
        }
        $identifier = trim( (string)$this->post( 'Identifier' ) );
        if ( $identifier !== '' && !preg_match( '/^[a-z0-9_-]{1,100}$/', $identifier ) )
            $this->errors['Identifier'] = ezpI18n::tr( 'extension/sqliimport/edit', 'The identifier takes lower case letters, digits, _ and -.' );
        elseif ( $identifier !== '' )
        {
            $other = expImportDefinition::fetchByIdentifier( $identifier );
            if ( $other && ( !$this->import || (int)$other->attribute( 'id' ) !== (int)$this->import->attribute( 'id' ) ) )
                $this->errors['Identifier'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Another import has this identifier.' );
        }
        if ( $this->errors )
            return false;
        $new = !$this->import;
        if ( $new )
            $this->import = expImportDefinition::create( array( 'name' => $name, 'identifier' => $identifier, 'is_enabled' => 0 ) );
        $this->import->setAttribute( 'name', $name );
        if ( $identifier !== '' )
            $this->import->setAttribute( 'identifier', $identifier );
        $this->import->setAttribute( 'description', (string)$this->post( 'Description' ) );
        $this->import->setAttribute( 'source_type', $legacy ? 'legacy' : $type );
        $this->import->setAttribute( 'source_config', expImport::json( $config ) );
        if ( $legacy )
            $this->import->setAttribute( 'handler', (string)$this->post( 'LegacyHandler' ) );
        elseif ( $this->import->isLegacy() || $this->import->attribute( 'handler' ) === '' )
            $this->import->setAttribute( 'handler', 'mapping' );
        $this->save( $new );
        if ( $new )
            $this->applyEntryParent( (int)$this->post( 'EntryParent', 0 ) );
        if ( !$legacy && !(int)$this->import->attribute( 'mapping_id' ) )
        {
            $preset = self::presetFor( $sources[$type], $config );
            $class = $new ? trim( (string)$this->post( 'EntryClass' ) ) : '';
            if ( $class !== '' && eZContentClass::fetchByIdentifier( $class ) && ( !$preset || $preset['class'] !== $class ) )
                $this->applyPreset( array( 'class' => $class, 'fields' => array() ) );
            elseif ( $preset )
                $this->applyPreset( $preset );
        }
        // "Load a mapping": a mapping file of another import or installation, uploaded with the source
        if ( !$legacy && $this->loadMappingUpload() === false )
            return false;
        return true;
    }

    /**
     * The preset of a format for a source: the format's own, or one made from the file itself when the format can
     * (presetFor(), e.g. an export's column manifest).
     *
     * @param array $source the registry entry
     * @param array $config the source configuration (file is the stored upload name)
     * @return array
     */
    public static function presetFor( array $source, array $config )
    {
        $class = $source['class'];
        if ( method_exists( $class, 'presetFor' ) )
        {
            if ( !empty( $config['file'] ) )
                $config['file'] = expImport::storageDirectory( 'uploads' ) . '/' . basename( (string)$config['file'] );
            try
            {
                $preset = $class::presetFor( $config );
                if ( $preset )
                    return $preset;
            }
            catch ( expImportException $e )
            {
                eZDebug::writeNotice( 'no preset from the file: ' . $e->getMessage(), __METHOD__ );
            }
        }
        return !empty( $source['preset'] ) ? $source['preset'] : array();
    }

    /**
     * An import's mapping as the content of a mapping file (sqliimport/mapping/<id>): the class, language, match
     * rule and lines; the location is left out, it belongs to the installation the mapping is loaded in.
     *
     * @param expImportDefinition $import
     * @return array
     */
    public static function mappingFile( expImportDefinition $import )
    {
        $mapping = (array)$import->mappingArray();
        unset( $mapping['location'], $mapping['id'] );
        return array( 'sqliimport_mapping' => 1, 'source_type' => (string)$import->attribute( 'source_type' ),
                      'import' => (string)$import->attribute( 'identifier' ), 'exported' => gmdate( 'Y-m-d\TH:i:s\Z', expImport::now() ),
                      'mapping' => $mapping );
    }

    /**
     * Reads a mapping file and checks it against this site (the class and its attributes must exist here).
     *
     * @param string $json
     * @return array the mapping (class, language, match, fields)
     * @throws expImportValidationException
     */
    public static function readMappingFile( $json )
    {
        $data = json_decode( (string)$json, true );
        if ( !is_array( $data ) || empty( $data['sqliimport_mapping'] ) || !isset( $data['mapping']['class'] ) )
            throw new expImportValidationException( array( 'MappingFile' => 'not a mapping file of 7x SQLI Import' ), 'not a mapping file' );
        $mapping = array_intersect_key( $data['mapping'], array_flip( array( 'class', 'language', 'match', 'fields' ) ) );
        $attributes = expImportMappingHandler::classAttributes( (string)$mapping['class'] );
        if ( $attributes === null )
            throw new expImportValidationException( array( 'MappingFile' => 'this site has no class ' . $mapping['class'] ), 'unknown class' );
        $missing = array();
        foreach ( isset( $mapping['fields'] ) ? (array)$mapping['fields'] : array() as $field )
            if ( isset( $field['attribute'] ) && !isset( $attributes[$field['attribute']] ) )
                $missing[] = $field['attribute'];
        if ( $missing )
            throw new expImportValidationException( array( 'MappingFile' => 'the class ' . $mapping['class'] . ' of this site has no ' . implode( ', ', $missing ) ),
                                                    'unknown attributes' );
        return $mapping + array( 'language' => '', 'match' => array( 'type' => 'remote_id', 'pattern' => '{id}' ), 'fields' => array() );
    }

    /**
     * Loads an uploaded mapping file (form field MappingFile) into the import, keeping its location.
     *
     * @return bool|null null when no file was uploaded
     */
    public function loadMappingUpload()
    {
        $file = eZHTTPFile::canFetch( 'MappingFile' ) ? eZHTTPFile::fetch( 'MappingFile' ) : null;
        if ( !$file instanceof eZHTTPFile )
            return null;
        try
        {
            $mapping = self::readMappingFile( (string)file_get_contents( $file->attribute( 'filename' ) ) );
        }
        catch ( expImportValidationException $e )
        {
            $this->errors['MappingFile'] = implode( '; ', $e->errors );
            return false;
        }
        $current = $this->import->mappingArray();
        $target = $this->import->target();
        $mapping['location'] = $current && isset( $current['location'] ) ? $current['location']
                             : array( 'parent_node_id' => (int)$target['parent_node_id'] ?: (int)expImport::setting( 'ImportSettings', 'DefaultParentNodeID', 2 ) );
        $existing = (int)$this->import->attribute( 'mapping_id' ) ? expImportMapping::fetch( $this->import->attribute( 'mapping_id' ) ) : null;
        $row = expImportMapping::saveArray( $mapping, $this->import->attribute( 'id' ), $existing ?: null );
        $this->import->setAttribute( 'mapping_id', $row->attribute( 'id' ) );
        $this->import->store();
        expImport::audit( 'import.mapping_loaded', array( 'id' => (int)$this->import->attribute( 'id' ), 'class' => $mapping['class'] ) );
        $this->notice = ezpI18n::tr( 'extension/sqliimport/edit', 'The mapping file was loaded: %n lines for the class %class.', null,
                                     array( '%n' => count( $mapping['fields'] ), '%class' => $mapping['class'] ) );
        return true;
    }

    /**
     * The target location of a new import opened from a folder ("Import into this folder…", (parent)/<node id>):
     * used only when the node exists and the user may create content below it, else ignored.
     *
     * @param int $nodeID
     * @return bool
     */
    public function applyEntryParent( $nodeID )
    {
        $node = $nodeID > 0 ? eZContentObjectTreeNode::fetch( (int)$nodeID ) : null;
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canCreate() )
            return false;
        $target = $this->import->target();
        $target['parent_node_id'] = (int)$nodeID;
        $this->import->setAttribute( 'target_json', expImport::json( $target ) );
        $this->import->store();
        return true;
    }

    /**
     * The lines of a preset that fit a class: its attribute exists and, when the line names 'datatypes', has one of
     * them (an image download fits ezimage, not an object relation).
     *
     * @param array $preset
     * @param array $datatypes attribute identifier => datatype string of the class
     * @return array
     */
    public static function presetFields( array $preset, array $datatypes )
    {
        $fields = array();
        foreach ( isset( $preset['fields'] ) ? (array)$preset['fields'] : array() as $field )
        {
            $attribute = isset( $field['attribute'] ) ? (string)$field['attribute'] : '';
            if ( !isset( $datatypes[$attribute] ) )
                continue;
            if ( !empty( $field['datatypes'] ) && !in_array( $datatypes[$attribute], (array)$field['datatypes'], true ) )
                continue;
            unset( $field['datatypes'] );
            $fields[] = $field;
        }
        return $fields;
    }

    /**
     * Gives an import without a mapping the preset mapping of its format, when the site has the preset's class.
     *
     * @param array $preset
     * @return bool whether it was applied
     */
    public function applyPreset( array $preset )
    {
        $identifier = isset( $preset['class'] ) ? (string)$preset['class'] : '';
        $class = $identifier !== '' ? eZContentClass::fetchByIdentifier( $identifier ) : null;
        if ( !$class )
        {
            $this->notice = ezpI18n::tr( 'extension/sqliimport/edit', 'The format comes with a mapping to the class %class, which this site does not have. Map the fields yourself.',
                                         null, array( '%class' => $identifier ) );
            return false;
        }
        $attributes = $class->dataMap();
        $fields = array();
        $fields = self::presetFields( $preset, array_map( function ( $attribute ) { return (string)$attribute->attribute( 'data_type_string' ); }, $attributes ) );
        $target = $this->import->target();
        $mapping = array(
            'class' => $identifier,
            'language' => '',
            'match' => isset( $preset['match'] ) ? $preset['match'] : array( 'type' => 'remote_id', 'pattern' => (string)$this->import->attribute( 'identifier' ) . '-{id}' ),
            'fields' => $fields,
            'location' => array( 'parent_node_id' => !empty( $target['parent_node_id'] ) ? (int)$target['parent_node_id']
                                                                                      : (int)expImport::setting( 'ImportSettings', 'DefaultParentNodeID', 2 ) ),
        );
        $row = expImportMapping::saveArray( $mapping, $this->import->attribute( 'id' ) );
        $this->import->setAttribute( 'mapping_id', $row->attribute( 'id' ) );
        $this->import->store();
        $this->notice = $fields
            ? ezpI18n::tr( 'extension/sqliimport/edit', 'The mapping of the format to %class was filled in (%n fields). Check it in the step Mapping.',
                           null, array( '%class' => $identifier, '%n' => count( $fields ) ) )
            : ezpI18n::tr( 'extension/sqliimport/edit', 'The mapping starts with the class %class: match its fields in the step Mapping (Auto-match helps).',
                           null, array( '%class' => $identifier ) );
        return true;
    }

    // ---- processing ----------------------------------------------------------------------------------------------

    /** @return bool */
    public function saveProcessing()
    {
        $handlers = expImportRegistry::instance()->handlers();
        $handler = $this->import->isLegacy() ? (string)$this->import->attribute( 'handler' ) : (string)$this->post( 'Handler', 'mapping' );
        if ( !isset( $handlers[$handler] ) || ( !$this->import->isLegacy() && $handlers[$handler]['legacy'] ) || $handlers[$handler]['status'] === 'disabled' )
            $this->errors['Handler'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Choose how the records are processed.' );
        $options = array();
        if ( !$this->errors )
        {
            if ( $handlers[$handler]['legacy'] )
                $options = expImportOptionSchema::fromLegacyText( (string)$this->post( 'LegacyOptions' ) );
            elseif ( $handlers[$handler]['schema'] )
            {
                list( $options, $optionErrors ) = expImportOptionSchema::validate( $handlers[$handler]['schema'], $this->postArray( 'HandlerOptions' ) );
                foreach ( $optionErrors as $key => $message )
                    $this->errors['HandlerOptions_' . $key] = $message;
            }
        }
        $target = $this->import->target();
        $parent = (int)$this->post( 'ParentNodeID', $target['parent_node_id'] );
        if ( $parent && !eZContentObjectTreeNode::fetch( $parent, false, false ) )
            $this->errors['ParentNodeID'] = ezpI18n::tr( 'extension/sqliimport/edit', 'There is no node with this ID.' );
        $section = (int)$this->post( 'SectionID', $target['section_id'] );
        if ( $section && !eZSection::fetch( $section ) )
            $this->errors['SectionID'] = ezpI18n::tr( 'extension/sqliimport/edit', 'There is no section with this ID.' );
        $language = trim( (string)$this->post( 'Language', $target['language'] ) );
        if ( $language !== '' && !eZContentLanguage::fetchByLocale( $language ) )
            $this->errors['Language'] = ezpI18n::tr( 'extension/sqliimport/edit', 'The site has no language %locale.', null, array( '%locale' => $language ) );
        $owner = (int)$target['owner_id'];
        if ( expImportAdmin::can( 'settings' ) )
        {
            $owner = (int)$this->post( 'OwnerID', $owner );
            if ( $owner && !eZUser::fetch( $owner ) )
                $this->errors['OwnerID'] = ezpI18n::tr( 'extension/sqliimport/edit', 'There is no user with this ID.' );
        }
        $behaviour = $this->import->behaviour();
        $chunk = (int)$this->post( 'ChunkSize', $behaviour['chunk_size'] );
        if ( $chunk < 1 || $chunk > 5000 )
            $this->errors['ChunkSize'] = ezpI18n::tr( 'extension/sqliimport/edit', 'A chunk has between 1 and 5000 records.' );
        $policy = (string)$this->post( 'UpdatePolicy', $behaviour['update_policy'] );
        if ( !in_array( $policy, array( 'update', 'create_only', 'update_only' ), true ) )
            $policy = 'update';
        $conflict = (string)$this->post( 'ConflictPolicy', $behaviour['conflict_policy'] );
        if ( !in_array( $conflict, array( 'overwrite', 'skip', 'fill_empty', 'merge' ), true ) )
            $conflict = 'overwrite';
        $undoDays = trim( (string)$this->post( 'UndoDays', $behaviour['undo_days'] ) );
        if ( $undoDays !== '' && ( !ctype_digit( $undoDays ) || (int)$undoDays > 3650 ) )
            $this->errors['UndoDays'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Undo days: a number of days, or empty for the setting.' );
        if ( $this->errors )
            return false;
        if ( !expImportAdmin::can( 'edit', $handler ) )
        {
            $this->errors['Handler'] = ezpI18n::tr( 'extension/sqliimport/edit', 'You may not edit imports of this handler.' );
            return false;
        }
        $this->import->setAttribute( 'handler', $handler );
        $this->import->setAttribute( 'options_json', expImport::json( $options ) );
        $this->import->setAttribute( 'target_json', expImport::json( array( 'parent_node_id' => $parent, 'section_id' => $section, 'language' => $language, 'owner_id' => $owner ) ) );
        $this->import->setAttribute( 'behaviour_json', expImport::json( array( 'chunk_size' => $chunk, 'update_policy' => $policy,
                                                                                'trash_policy' => 'skip', 'max_rows' => max( 0, (int)$this->post( 'MaxRows', $behaviour['max_rows'] ) ),
                                                                                'conflict_policy' => $conflict, 'undo_days' => $undoDays ) ) );
        $this->save( false );
        return true;
    }

    // ---- mapping -----------------------------------------------------------------------------------------------

    /**
     * The mapping as the form shows it: the stored one, or a new one for the class chosen.
     * @return array
     */
    public function mappingForForm()
    {
        $mapping = $this->import ? $this->import->mappingArray() : null;
        $target = $this->import ? $this->import->target() : array( 'parent_node_id' => 0, 'language' => '' );
        if ( !$mapping )
            $mapping = array( 'class' => '', 'language' => '', 'match' => array( 'type' => 'remote_id', 'pattern' => '' ), 'fields' => array(),
                              'location' => array( 'parent_node_id' => (int)$target['parent_node_id'], 'parent_from' => '', 'parent_prefix' => '' ) );
        return $mapping;
    }

    /**
     * The lines of the mapping grid: one per attribute of the class, with what is mapped to it.
     *
     * @param array $mapping
     * @return array list of array( attribute, name, datatype, source, transforms (text), default, required )
     */
    public static function gridLines( array $mapping )
    {
        $class = $mapping['class'] !== '' ? eZContentClass::fetchByIdentifier( $mapping['class'] ) : null;
        $mapped = array();
        foreach ( $mapping['fields'] as $field )
            if ( (string)( isset( $field['language'] ) ? $field['language'] : '' ) === '' )
                $mapped[$field['attribute']] = $field;
        $lines = array();
        if ( $class instanceof eZContentClass )
        {
            foreach ( $class->fetchAttributes() as $attribute )
            {
                $id = $attribute->attribute( 'identifier' );
                $field = isset( $mapped[$id] ) ? $mapped[$id] : array();
                $lines[] = array(
                    'attribute' => $id,
                    'name' => $attribute->attribute( 'name' ),
                    'datatype' => $attribute->attribute( 'data_type_string' ),
                    'is_required' => (bool)$attribute->attribute( 'is_required' ),
                    'source' => isset( $field['source'] ) ? $field['source'] : '',
                    'transforms' => isset( $field['transforms'] ) ? expImportTransforms::formatChain( (array)$field['transforms'] ) : '',
                    'default' => isset( $field['default'] ) ? $field['default'] : '',
                    'required' => !empty( $field['required'] ),
                );
            }
        }
        return $lines;
    }

    /**
     * Saves the mapping step; with $autoMatch, the lines without a source are filled from the sample first.
     *
     * @param bool $autoMatch
     * @param string[] $sampleFields
     * @return bool
     */
    public function saveMapping( $autoMatch = false, array $sampleFields = array() )
    {
        $class = trim( (string)$this->post( 'ClassIdentifier' ) );
        $attributes = $class !== '' ? expImportMappingHandler::classAttributes( $class ) : null;
        if ( $attributes === null )
        {
            $this->errors['ClassIdentifier'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Choose the content class.' );
            return false;
        }
        $previous = $this->mappingForForm();
        $fields = array();
        $lines = $this->postArray( 'Fields' );
        // a new class: the old lines do not apply
        if ( $previous['class'] !== $class )
            $lines = array();
        foreach ( $lines as $attribute => $line )
        {
            if ( !isset( $attributes[$attribute] ) || !is_array( $line ) )
                continue;
            $source = trim( isset( $line['source'] ) ? (string)$line['source'] : '' );
            $default = isset( $line['default'] ) ? (string)$line['default'] : '';
            $text = isset( $line['transforms'] ) ? (string)$line['transforms'] : '';
            if ( $source === '' && $default === '' && trim( $text ) === '' )
                continue;
            try
            {
                $chain = expImportTransforms::parseChain( $text );
            }
            catch ( expImportValidationException $e )
            {
                $this->errors['Fields_' . $attribute] = $attribute . ': ' . $e->getMessage();
                $chain = array();
            }
            $fields[] = array( 'source' => $source, 'attribute' => (string)$attribute, 'transforms' => $chain, 'default' => $default,
                               'required' => !empty( $line['required'] ), 'language' => '' );
        }
        // the lines in other languages are kept as they were (the grid edits the main language)
        foreach ( $previous['fields'] as $field )
            if ( isset( $field['language'] ) && $field['language'] !== '' && $previous['class'] === $class )
                $fields[] = $field;
        if ( $autoMatch )
        {
            $have = array();
            foreach ( $fields as $field )
                $have[$field['attribute']] = true;
            $info = array();
            $class_ = eZContentClass::fetchByIdentifier( $class );
            foreach ( $class_->fetchAttributes() as $attribute )
                if ( !isset( $have[$attribute->attribute( 'identifier' )] ) )
                    $info[$attribute->attribute( 'identifier' )] = array( 'name' => $attribute->attribute( 'name' ), 'datatype' => $attribute->attribute( 'data_type_string' ) );
            $used = array();
            foreach ( $fields as $field )
                $used[] = $field['source'];
            $n = 0;
            foreach ( expImportMapper::autoMatch( array_values( array_diff( $sampleFields, $used ) ), $info ) as $line )
            {
                unset( $line['confidence'] );
                $fields[] = $line + array( 'default' => '', 'required' => false, 'language' => '' );
                $n++;
            }
            $this->notice = ezpI18n::tr( 'extension/sqliimport/edit', 'Auto-match filled %n lines. Check them, then save.', null, array( '%n' => $n ) );
        }
        $matchType = (string)$this->post( 'MatchType', 'remote_id' );
        $match = $matchType === 'attribute'
            ? array( 'type' => 'attribute', 'attribute' => (string)$this->post( 'MatchAttribute' ), 'source' => trim( (string)$this->post( 'MatchSource' ) ) )
            : array( 'type' => 'remote_id', 'pattern' => trim( (string)$this->post( 'MatchPattern' ) ) );
        $mapping = array(
            'class' => $class,
            'language' => trim( (string)$this->post( 'MappingLanguage' ) ),
            'match' => $match,
            'fields' => $fields,
            'location' => array(
                'parent_node_id' => (int)$this->post( 'LocationParentNodeID' ),
                'parent_from' => trim( (string)$this->post( 'LocationParentFrom' ) ),
                'parent_prefix' => trim( (string)$this->post( 'LocationParentPrefix' ) ),
                'extra_parents_from' => trim( (string)$this->post( 'LocationExtraParentsFrom' ) ),
            ),
        );
        // stored even when not complete yet (the grid keeps what was entered); the errors are shown
        $existing = (int)$this->import->attribute( 'mapping_id' ) ? expImportMapping::fetch( $this->import->attribute( 'mapping_id' ) ) : null;
        $row = expImportMapping::saveArray( $mapping, $this->import->attribute( 'id' ), $existing );
        $this->import->setAttribute( 'mapping_id', (int)$row->attribute( 'id' ) );
        $this->save( false );
        if ( !$autoMatch )
        {
            foreach ( expImportMapper::validate( $mapping, $attributes ) as $i => $message )
                $this->errors['Mapping_' . $i] = $message;
        }
        return !$this->errors;
    }

    // ---- schedule ----------------------------------------------------------------------------------------------

    /** @return array the schedule as the form shows it */
    public function scheduleForForm()
    {
        $schedules = $this->import ? $this->import->schedules() : array();
        $s = $schedules ? $schedules[0] : null;
        $zone = $s ? $s->attribute( 'timezone' ) : ( date_default_timezone_get() ?: 'UTC' );
        $runAt = $s ? (int)$s->attribute( 'run_at' ) : expImport::now() + 3600;
        $local = new DateTime( '@' . $runAt );
        try
        {
            $local->setTimezone( new DateTimeZone( $zone ) );
        }
        catch ( Exception $e )
        {
        }
        return array(
            'kind' => $s ? $s->attribute( 'kind' ) : 'none',
            'run_at_local' => $local->format( 'Y-m-d\TH:i' ),
            'interval_minutes' => $s ? (int)$s->attribute( 'interval_minutes' ) : 60,
            'cron_expr' => $s ? $s->attribute( 'cron_expr' ) : '0 2 * * *',
            'timezone' => $zone,
            'catch_up' => $s ? $s->attribute( 'catch_up' ) : 'one',
            'notify' => $s ? implode( "\n", $s->notify() ) : '',
            'is_active' => $s ? (bool)$s->attribute( 'is_active' ) : true,
            'next' => $s && (int)$s->attribute( 'is_active' ) ? self::nextTimes( $s->calcArray(), 5 ) : array(),
        );
    }

    /** @return int[] the next $n run times of a schedule */
    public static function nextTimes( array $calc, $n )
    {
        $out = array();
        $after = expImport::now();
        for ( $i = 0; $i < $n; $i++ )
        {
            $next = expImportCron::next( $calc, $after );
            if ( !$next )
                break;
            $out[] = $next;
            $after = $next;
        }
        return $out;
    }

    /** @return bool */
    public function saveSchedule()
    {
        $kind = (string)$this->post( 'ScheduleKind', 'none' );
        $id = (int)$this->import->attribute( 'id' );
        $schedules = $this->import->schedules();
        if ( $kind === 'none' )
        {
            foreach ( $schedules as $s )
                $s->remove();
            $this->save( false, 'schedule.change' );
            return true;
        }
        $zone = (string)$this->post( 'Timezone', 'UTC' );
        try
        {
            $tz = new DateTimeZone( $zone );
        }
        catch ( Exception $e )
        {
            $this->errors['Timezone'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Unknown time zone.' );
            return false;
        }
        $runAt = 0;
        $local = (string)$this->post( 'RunAt' );
        if ( $local !== '' )
        {
            $date = DateTime::createFromFormat( 'Y-m-d\TH:i', $local, $tz );
            if ( !$date )
                $this->errors['RunAt'] = ezpI18n::tr( 'extension/sqliimport/edit', 'Choose a date and time.' );
            else
                $runAt = $date->getTimestamp();
        }
        $notify = array();
        foreach ( preg_split( '/[\s,;]+/', (string)$this->post( 'Notify' ) ) as $address )
        {
            $address = trim( $address );
            if ( $address === '' )
                continue;
            if ( !filter_var( $address, FILTER_VALIDATE_EMAIL ) )
                $this->errors['Notify'] = ezpI18n::tr( 'extension/sqliimport/edit', '%address is not an e-mail address.', null, array( '%address' => $address ) );
            $notify[] = $address;
        }
        $calc = array( 'kind' => $kind, 'run_at' => $runAt, 'interval_minutes' => (int)$this->post( 'IntervalMinutes' ),
                       'cron_expr' => trim( (string)$this->post( 'CronExpr' ) ), 'timezone' => $zone );
        foreach ( expImportCron::validate( $calc ) as $i => $message )
            $this->errors['Schedule_' . $i] = $message;
        if ( $this->errors )
            return false;
        $now = expImport::now();
        $s = $schedules ? $schedules[0] : new expImportSchedule( array( 'import_id' => $id, 'created' => $now, 'creator_id' => expImport::currentUserID(),
                                                                           'last_run' => 0 ) );
        foreach ( $calc as $key => $value )
            $s->setAttribute( $key, $value );
        $s->setAttribute( 'catch_up', $this->post( 'CatchUp' ) === 'skip' ? 'skip' : 'one' );
        $s->setAttribute( 'notify_json', expImport::json( $notify ) );
        $s->setAttribute( 'is_active', $this->post( 'ScheduleActive' ) ? 1 : 0 );
        $s->setAttribute( 'modified', $now );
        $s->computeNext( $now );
        if ( !$this->post( 'ScheduleActive' ) )
            $s->setAttribute( 'is_active', 0 );
        $s->store();
        // one schedule per import in the editor
        foreach ( array_slice( $schedules, 1 ) as $extra )
            $extra->remove();
        $this->save( false, 'schedule.change' );
        return true;
    }

    // ---- review --------------------------------------------------------------------------------------------------

    /**
     * What is still missing before the import can run.
     * @return string[]
     */
    public function problems()
    {
        $out = array();
        if ( !$this->import )
            return array( ezpI18n::tr( 'extension/sqliimport/edit', 'The import has no source yet.' ) );
        if ( $this->import->attribute( 'handler' ) === 'mapping' )
        {
            $mapping = $this->import->mappingArray();
            if ( !$mapping )
                $out[] = ezpI18n::tr( 'extension/sqliimport/edit', 'The mapping is missing.' );
            else
                $out = array_merge( $out, expImportMapper::validate( $mapping, expImportMappingHandler::classAttributes( $mapping['class'] ) ) );
        }
        return $out;
    }

    /** @return bool */
    public function saveReview()
    {
        $problems = $this->problems();
        $enable = (bool)$this->post( 'Enabled' );
        if ( $enable && $problems )
        {
            foreach ( $problems as $i => $message )
                $this->errors['Review_' . $i] = $message;
            return false;
        }
        $this->import->setAttribute( 'is_enabled', $enable ? 1 : 0 );
        $this->save( false );
        return true;
    }

    // ---- shared --------------------------------------------------------------------------------------------------

    /** Stores the import (a new version), with its audit event. */
    protected function save( $new, $event = '' )
    {
        if ( $new )
        {
            $this->import->store();
            expImport::audit( 'import.create', array( 'id' => (int)$this->import->attribute( 'id' ), 'identifier' => $this->import->attribute( 'identifier' ) ) );
        }
        else
        {
            $this->import->storeChange();
            expImport::audit( $event !== '' ? $event : 'import.change', array( 'id' => (int)$this->import->attribute( 'id' ), 'identifier' => $this->import->attribute( 'identifier' ),
                                                                              'version' => (int)$this->import->attribute( 'def_version' ) ) );
        }
    }

    /**
     * A copy of an import (its source, processing, mapping; not its schedules or runs), disabled.
     *
     * @param expImportDefinition $import
     * @return expImportDefinition
     */
    public static function duplicate( expImportDefinition $import )
    {
        $name = ezpI18n::tr( 'extension/sqliimport', 'Copy of %name', null, array( '%name' => $import->attribute( 'name' ) ) );
        $copy = expImportDefinition::create( array(
            'name' => $name,
            'description' => $import->attribute( 'description' ),
            'source_type' => $import->attribute( 'source_type' ),
            'source_config' => $import->attribute( 'source_config' ),
            'handler' => $import->attribute( 'handler' ),
            'target_json' => $import->attribute( 'target_json' ),
            'behaviour_json' => $import->attribute( 'behaviour_json' ),
            'options_json' => $import->attribute( 'options_json' ),
            'is_enabled' => 0,
        ) );
        $copy->store();
        $mapping = $import->mappingArray();
        if ( $mapping )
        {
            $row = expImportMapping::saveArray( $mapping, $copy->attribute( 'id' ) );
            $copy->setAttribute( 'mapping_id', (int)$row->attribute( 'id' ) );
            $copy->store();
        }
        expImport::audit( 'import.create', array( 'id' => (int)$copy->attribute( 'id' ), 'copy_of' => (int)$import->attribute( 'id' ) ) );
        return $copy;
    }

    /**
     * Imports exported import definitions (Settings > Import definitions). An import with the same identifier is
     * updated, others are created disabled.
     *
     * @param array $list the decoded export
     * @return array ( int created, int updated, string[] errors )
     */
    public static function importDefinitions( array $list )
    {
        $created = $updated = 0;
        $errors = array();
        if ( isset( $list['imports'] ) && is_array( $list['imports'] ) )
            $list = $list['imports'];
        foreach ( $list as $i => $item )
        {
            if ( !is_array( $item ) || empty( $item['identifier'] ) || !preg_match( '/^[a-z0-9_-]{1,100}$/', (string)$item['identifier'] ) || empty( $item['name'] ) )
            {
                $errors[] = 'entry ' . ( $i + 1 ) . ': identifier and name are needed';
                continue;
            }
            $import = expImportDefinition::fetchByIdentifier( $item['identifier'] );
            $new = !$import;
            if ( $new )
                $import = expImportDefinition::create( array( 'identifier' => (string)$item['identifier'], 'name' => (string)$item['name'], 'is_enabled' => 0 ) );
            $import->setAttribute( 'name', (string)$item['name'] );
            $import->setAttribute( 'description', isset( $item['description'] ) ? (string)$item['description'] : '' );
            $import->setAttribute( 'source_type', isset( $item['source_type'] ) ? (string)$item['source_type'] : 'csv' );
            $import->setAttribute( 'source_config', expImport::json( isset( $item['source'] ) && is_array( $item['source'] ) ? $item['source'] : array() ) );
            $import->setAttribute( 'handler', isset( $item['handler'] ) ? (string)$item['handler'] : 'mapping' );
            $import->setAttribute( 'target_json', expImport::json( isset( $item['target'] ) && is_array( $item['target'] ) ? $item['target'] : array() ) );
            $import->setAttribute( 'behaviour_json', expImport::json( isset( $item['behaviour'] ) && is_array( $item['behaviour'] ) ? $item['behaviour'] : array() ) );
            $import->setAttribute( 'options_json', expImport::json( isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : array() ) );
            if ( $new )
                $import->store();
            else
                $import->storeChange();
            if ( isset( $item['mapping'] ) && is_array( $item['mapping'] ) )
            {
                $existing = (int)$import->attribute( 'mapping_id' ) ? expImportMapping::fetch( $import->attribute( 'mapping_id' ) ) : null;
                $row = expImportMapping::saveArray( $item['mapping'], $import->attribute( 'id' ), $existing );
                $import->setAttribute( 'mapping_id', (int)$row->attribute( 'id' ) );
                $import->store();
            }
            $new ? $created++ : $updated++;
        }
        expImport::audit( 'import.change', array( 'imported' => $created + $updated ) );
        return array( $created, $updated, $errors );
    }
}
