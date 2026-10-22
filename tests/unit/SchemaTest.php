<?php

use PHPUnit\Framework\TestCase;

/**
 * The schema of the extension is defined once (expImportSchema::definition()): share/db_schema.dba is generated from
 * it, every name fits Oracle's 30 characters, no new column is a reserved word, and the 1.x tables are unchanged.
 */
class SchemaTest extends TestCase
{
    /** Words reserved by at least one of MySQL, PostgreSQL, SQLite or Oracle that a column could plausibly be called */
    const RESERVED = array( 'level', 'mode', 'size', 'file', 'row', 'rows', 'number', 'comment', 'option', 'trigger', 'action',
                            'order', 'group', 'user', 'key', 'index', 'table', 'session', 'date', 'start', 'uid', 'access', 'resource' );

    public function testTheDbaFileIsGeneratedFromTheDefinition(): void
    {
        $file = dirname( __DIR__, 2 ) . '/share/db_schema.dba';
        $this->assertSame( expImportSchema::dbaText(), (string)file_get_contents( $file ),
                           'share/db_schema.dba differs from expImportSchema::definition(): run ./console ext:sqliimport:install-schema --write-files' );
    }

    public function testNamesFitOracleAndAvoidReservedWords(): void
    {
        $legacy = array_keys( expImportSchema::legacyDefinition() );
        foreach ( expImportSchema::definition() as $table => $def )
        {
            $this->assertLessThanOrEqual( 30, strlen( $table ), $table );
            foreach ( array_keys( $def['indexes'] ) as $index )
                $this->assertLessThanOrEqual( 30, strlen( $index ), "$table index $index" );
            if ( in_array( $table, $legacy, true ) )
                continue;
            $this->assertArrayHasKey( 'PRIMARY', $def['indexes'], $table );
            foreach ( array_keys( $def['fields'] ) as $field )
            {
                $this->assertLessThanOrEqual( 30, strlen( $field ), "$table.$field" );
                $this->assertNotContains( $field, self::RESERVED, "$table.$field is a reserved word" );
            }
        }
    }

    public function testTheTablesOf1xStayAsTheyWere(): void
    {
        $legacy = expImportSchema::legacyDefinition();
        $this->assertSame( array( 'sqliimport_item', 'sqliimport_scheduled' ), array_keys( $legacy ) );
        $this->assertSame( array( 'handler', 'id', 'options_serialized', 'percentage_int', 'process_time', 'progression_notes', 'requested_time',
                                  'scheduled_id', 'status', 'type', 'user_id' ), array_keys( $legacy['sqliimport_item']['fields'] ) );
        $this->assertSame( array( 'frequency', 'handler', 'id', 'is_active', 'label', 'manual_frequency', 'next', 'options_serialized',
                                  'requested_time', 'user_id' ), array_keys( $legacy['sqliimport_scheduled']['fields'] ) );
    }

    public function testEveryRunCounterHasItsColumn(): void
    {
        $fields = expImportSchema::definition()['sqliimport_run']['fields'];
        foreach ( expImportRun::COUNTERS as $column )
            $this->assertArrayHasKey( $column, $fields );
    }
}
