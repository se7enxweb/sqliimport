<?php
/**
 * File containing the expImportDataTableFieldSourceBase class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The fields of an import's source for the kernel's "Module table" wizard (Exponential 6.0.15, the data table layer):
 * the card "Create a new table from this source" opens the wizard with array( 'import' => <id> ), and the wizard asks
 * this provider for the fields (expImportDataTableFields over the source's sample) and a label.
 *
 * The methods of expImportDataTableFieldSource, which implements the kernel's interface where the kernel has it.
 */
abstract class expImportDataTableFieldSourceBase
{
    /** How many records of the source are read for the guess */
    const SAMPLE = 50;

    /** @return string the id of this source in the wizard (the part of the service id after datatable.field_source.) */
    public function dataTableSourceName()
    {
        return 'sqliimport';
    }

    /**
     * The imports the wizard offers (ref => label): the 2.x imports of this site the user may see, newest first.
     *
     * @return array
     */
    public function dataTableReferences()
    {
        if ( !expImportAdmin::can( 'view' ) || !expImportSchema::installed() )
            return array();
        $out = array();
        foreach ( eZPersistentObject::fetchObjectList( expImportDefinition::definition(), null, null, array( 'id' => 'desc' ), array( 'offset' => 0, 'length' => 200 ) ) as $import )
        {
            if ( $import->isLegacy() )
                continue;
            $out[(string)(int)$import->attribute( 'id' )] = $this->label( $import );
        }
        return $out;
    }

    /**
     * @param array $options 'ref' => the import's id ('import' is read too)
     * @return array list of array( name, label, type, length, required, unique, sample )
     * @throws RuntimeException the reference names no import the user may see, or its source cannot be read
     */
    public function dataTableFields( array $options )
    {
        $import = $this->import( $options );
        if ( !$import )
            throw new RuntimeException( ezpI18n::tr( 'extension/sqliimport', 'There is no import %ref of 7x SQLI Import that you may see.', null,
                                                     array( '%ref' => self::ref( $options ) ) ) );
        if ( $import->isLegacy() )
            throw new RuntimeException( ezpI18n::tr( 'extension/sqliimport', 'A 1.x handler reads its records itself; its fields cannot be read.' ) );
        try
        {
            $sample = expImportPreview::sample( $import, self::SAMPLE );
        }
        catch ( Exception $e )
        {
            throw new RuntimeException( ezpI18n::tr( 'extension/sqliimport', 'The source of the import could not be read: %error', null,
                                                     array( '%error' => $e->getMessage() ) ) );
        }
        $flat = array();
        foreach ( $sample['records'] as $record )
            $flat[] = $record['flat'];
        return expImportDataTableFields::infer( $flat );
    }

    /**
     * @param array $options array( 'import' => id )
     * @return string e.g. 'Import "Products" (CSV)'
     */
    public function dataTableSourceLabel( array $options )
    {
        $import = $this->import( $options );
        if ( !$import )
            return '';
        return ezpI18n::tr( 'extension/sqliimport', 'the import %id of 7x SQLI Import, %label', null,
                            array( '%id' => (int)$import->attribute( 'id' ), '%label' => $this->label( $import ) ) );
    }

    /** @return string 'Products (CSV)' */
    protected function label( expImportDefinition $import )
    {
        $sources = expImportRegistry::instance()->sources();
        $type = (string)$import->attribute( 'source_type' );
        $format = isset( $sources[$type]['label'] ) ? $sources[$type]['label'] : $type;
        return $import->attribute( 'name' ) . ' (' . $format . ')';
    }

    /** @return string the reference of the options: ref, else import */
    protected static function ref( array $options )
    {
        foreach ( array( 'ref', 'import' ) as $key )
            if ( isset( $options[$key] ) && is_scalar( $options[$key] ) && trim( (string)$options[$key] ) !== '' )
                return trim( (string)$options[$key] );
        return '';
    }

    /**
     * The import, for a user who may see the imports
     *
     * @param array $options
     * @return expImportDefinition|null
     */
    protected function import( array $options )
    {
        $ref = self::ref( $options );
        if ( !ctype_digit( $ref ) || !expImportAdmin::can( 'view' ) )
            return null;
        $import = expImportDefinition::fetch( (int)$ref );
        return $import ?: null;
    }
}
