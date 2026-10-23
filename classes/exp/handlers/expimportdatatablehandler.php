<?php
/**
 * File containing the expImportDataTableHandler class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The target "A data table": each record becomes a row of a persistent class (a module's own table, Exponential
 * 6.0.15, Setup > Data tables), written through the kernel's expDataTableWriter: checked against the class's
 * definition, on the class's connection, matched by a unique key, never raw SQL. Nothing of content is made.
 *
 * Options: the class, the mode (upsert, insert, update, replace), the keys a row is matched by (empty: the class's
 * keys), and the columns: one line "column = field | transforms" each (the one-line chain of the mapping grid, e.g.
 * "price = price | number"); without lines, every field of the record whose name is a column of the class is taken.
 * A relation column takes a reference to content: @object:<remote id>, @node:<remote id>, @path:<url alias>.
 *
 * A dry run checks and counts with the writer's dry_run, writes nothing. Undo is not offered for this target (the
 * rows are not content); a run's report lists the key of every row it wrote.
 */
class expImportDataTableHandler implements expImportHandlerInterface
{
    /** @var callable|null tests: function ( string $class, array $options ) returning an object with write( array $rows ) */
    public static $writerFactory = null;

    /** @var callable|null tests: function () returning class => entry (the kernel's catalogue) */
    public static $catalogue = null;

    /** @var object|null the writer of this chunk */
    protected $writer;

    /** @var array the column lines: column => array( 'source' => field, 'chain' => transforms ) */
    protected $columns = array();

    /** @var array the fields of the class: name => definition */
    protected $fields = array();

    public static function identifier()
    {
        return 'datatable';
    }

    public static function label()
    {
        return 'Rows of a data table (a persistent class)';
    }

    /** @return bool false: the rows are not content, a run of this target is not undone (expImportUndoHandler) */
    public static function undoable()
    {
        return false;
    }

    /** @return bool whether the kernel can write data tables (Exponential 6.0.15) */
    public static function available()
    {
        return self::$writerFactory !== null || class_exists( 'expDataTableWriter' );
    }

    /** @return array class => entry of the persistent classes with a table, as the kernel's catalogue gives them */
    public static function classes()
    {
        if ( self::$catalogue !== null )
            return (array)call_user_func( self::$catalogue );
        if ( !class_exists( 'expDataTableCatalogue' ) )
            return array();
        $out = array();
        foreach ( expDataTableCatalogue::instance()->classes() as $class => $entry )
            if ( !empty( $entry['table'] ) && empty( $entry['error'] ) )
                $out[$class] = $entry;
        return $out;
    }

    public static function schema()
    {
        $values = array();
        foreach ( self::classes() as $class => $entry )
            $values[$class] = $class . ' (' . $entry['table'] . ( !empty( $entry['origin'] ) ? ', ' . $entry['origin'] : '' ) . ')';
        ksort( $values );
        $class = $values
            ? array( 'type' => 'enum', 'label' => 'Data table', 'required' => true, 'values' => array( '' => '' ) + $values,
                     'help' => 'The persistent class whose table the rows go into (Setup > Data tables)' )
            : array( 'type' => 'string', 'label' => 'Data table', 'required' => true, 'max' => 100,
                     'help' => 'The persistent class whose table the rows go into (Setup > Data tables, Exponential 6.0.15)' );
        return array(
            'class' => $class,
            'mode' => array( 'type' => 'enum', 'label' => 'Rows', 'default' => 'upsert',
                             'values' => array( 'upsert' => 'Add new rows, update existing ones', 'insert' => 'Only add new rows',
                                                'update' => 'Only update existing rows', 'replace' => 'Replace existing rows (removed and written again)' ) ),
            'keys' => array( 'type' => 'list', 'label' => 'Match rows by',
                             'help' => 'One column per line that names a row (e.g. sku). Empty: the keys of the class' ),
            'columns' => array( 'type' => 'text', 'label' => 'Columns',
                                'help' => 'One line per column: column = field of the record | transforms (e.g. price = price | number). Empty: every field whose name is a column' ),
        );
    }

    /**
     * The column lines of the option columns.
     *
     * @param string $text
     * @return array column => array( 'source' => field, 'chain' => transforms )
     * @throws expImportValidationException a line cannot be read
     */
    public static function parseColumns( $text )
    {
        $out = array();
        $errors = array();
        foreach ( preg_split( '/\R/', (string)$text ) as $n => $line )
        {
            $line = trim( $line );
            if ( $line === '' || $line[0] === '#' )
                continue;
            if ( !preg_match( '/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*([^|]*?)\s*(?:\|\s*(.*))?$/', $line, $m ) )
            {
                $errors['columns_' . $n] = 'line ' . ( $n + 1 ) . ': write column = field (| transforms)';
                continue;
            }
            try
            {
                $chain = isset( $m[3] ) && trim( $m[3] ) !== '' ? expImportTransforms::parseChain( $m[3] ) : array();
            }
            catch ( expImportValidationException $e )
            {
                $errors['columns_' . $n] = 'line ' . ( $n + 1 ) . ': ' . $e->getMessage();
                continue;
            }
            $out[$m[1]] = array( 'source' => trim( $m[2] ), 'chain' => $chain );
        }
        if ( $errors )
            throw new expImportValidationException( $errors, implode( '; ', $errors ) );
        return $out;
    }

    public function open( expImportContext $context )
    {
        if ( !self::available() )
            throw new expImportException( 'the target "A data table" needs Exponential 6.0.15 or later (Setup > Data tables)' );
        $class = isset( $context->options['class'] ) ? trim( (string)$context->options['class'] ) : '';
        $classes = self::classes();
        if ( $class === '' || !isset( $classes[$class] ) )
            throw new expImportException( "the data table '$class' is not a persistent class with a table of this site" );
        $this->fields = isset( $classes[$class]['fields'] ) ? (array)$classes[$class]['fields'] : array();
        $this->columns = self::parseColumns( isset( $context->options['columns'] ) ? (string)$context->options['columns'] : '' );
        foreach ( array_keys( $this->columns ) as $column )
            if ( $this->fields && !isset( $this->fields[$column] ) )
                throw new expImportException( "the data table $class has no column '$column'" );
        $keys = isset( $context->options['keys'] ) ? array_values( array_filter( array_map( 'trim', (array)$context->options['keys'] ), 'strlen' ) ) : array();
        $options = array( 'mode' => isset( $context->options['mode'] ) ? (string)$context->options['mode'] : 'upsert', 'dry_run' => (bool)$context->dryRun,
                          'chunk' => 1, 'unknown' => 'refuse' );
        if ( $keys )
            $options['keys'] = $keys;
        try
        {
            $this->writer = self::$writerFactory !== null ? call_user_func( self::$writerFactory, $class, $options ) : new expDataTableWriter( $class, $options );
        }
        catch ( InvalidArgumentException $e )
        {
            throw new expImportException( 'data table: ' . $e->getMessage() );
        }
    }

    /**
     * The row of a record: the column lines, or every field whose name is a column of the class.
     *
     * @param expImportRecord $record
     * @return array column => value
     */
    public function row( expImportRecord $record )
    {
        $row = array();
        if ( $this->columns )
        {
            foreach ( $this->columns as $column => $line )
            {
                $value = $line['source'] !== '' ? $record->get( $line['source'], '' ) : '';
                if ( $line['chain'] )
                    $value = expImportTransforms::applyChain( $value, $line['chain'], $record );
                $row[$column] = is_array( $value ) ? json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $value;
            }
            return $row;
        }
        foreach ( (array)$record->data as $field => $value )
        {
            if ( !is_string( $field ) || !isset( $this->fields[$field] ) )
                continue;
            $row[$field] = is_array( $value ) ? json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $value;
        }
        return $row;
    }

    public function process( expImportRecord $record, expImportContext $context )
    {
        $row = $this->row( $record );
        if ( !$row )
            return expImportRowResult::skipped( 'no field of the record is a column of the data table' );
        $result = $this->writer->write( array( $row ) );
        $key = isset( $result['ids'] ) && $result['ids'] ? (string)( is_array( reset( $result['ids'] ) ) ? implode( '/', reset( $result['ids'] ) ) : reset( $result['ids'] ) ) : '';
        $note = ( $context->dryRun ? 'dry run: ' : '' ) . ( $key !== '' ? 'row ' . $key : '' );
        if ( !empty( $result['failed'] ) )
            return expImportRowResult::failed( 'data table: ' . implode( '; ', (array)$result['errors'] ) );
        if ( !empty( $result['inserted'] ) )
            $r = new expImportRowResult( expImportRowResult::CREATED, trim( $note ) );
        elseif ( !empty( $result['updated'] ) )
            $r = new expImportRowResult( expImportRowResult::UPDATED, trim( $note ) );
        elseif ( !empty( $result['unchanged'] ) )
            $r = new expImportRowResult( expImportRowResult::UNCHANGED, trim( $note ) );
        else
            $r = expImportRowResult::skipped( trim( ( $result['errors'] ? implode( '; ', (array)$result['errors'] ) . ' ' : '' ) . $note ) ?: 'not written (the mode leaves it out)' );
        $r->remoteID = $key;
        return $r;
    }

    public function close( expImportContext $context )
    {
        $this->writer = null;
    }
}
