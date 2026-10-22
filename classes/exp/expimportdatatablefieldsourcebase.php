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

    /**
     * @param array $options array( 'import' => id )
     * @return array list of array( name, label, type, length, required, unique, sample )
     */
    public function dataTableFields( array $options )
    {
        $import = $this->import( $options );
        if ( !$import )
            return array();
        $sample = expImportPreview::sample( $import, self::SAMPLE );
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
        $sources = expImportRegistry::instance()->sources();
        $type = (string)$import->attribute( 'source_type' );
        $format = isset( $sources[$type]['label'] ) ? $sources[$type]['label'] : $type;
        return ezpI18n::tr( 'extension/sqliimport', 'Import "%name" (%format)', null,
                            array( '%name' => $import->attribute( 'name' ), '%format' => $format ) );
    }

    /**
     * The import, for a user who may see the imports
     *
     * @param array $options
     * @return expImportDefinition|null
     */
    protected function import( array $options )
    {
        if ( !isset( $options['import'] ) || !expImportAdmin::can( 'view' ) )
            return null;
        $import = expImportDefinition::fetch( (int)$options['import'] );
        return $import ?: null;
    }
}
