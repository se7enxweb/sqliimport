<?php
/**
 * A handler written the way sqliimport 1.x handlers are (after the upstream examples: SQLICSVDoc for the source,
 * SQLIContent and SQLIContentPublisher for the content), used by the integration checks to prove that a 1.x handler
 * runs untouched in the 2.x engine. Not part of the shipped extension's catalogue.
 *
 * It reads the CSV of the option "csv" (or of its handler setting CSVFile) with the columns id and name and publishes
 * one folder per line below its DefaultParentNodeID; a line whose name is FAIL throws, as a broken record would.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */
class SQLIFixtureCSVHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    protected $rowIndex = 0;

    protected $rowCount = 0;

    protected $rows = array();

    public function __construct( ?SQLIImportHandlerOptions $options = null )
    {
        parent::__construct( $options );
        $this->remoteIDPrefix = $this->getHandlerIdentifier() . '-';
    }

    public function initialize()
    {
        $file = $this->options && $this->options->hasAttribute( 'csv' ) ? $this->options->attribute( 'csv' ) : $this->handlerConfArray['CSVFile'];
        $doc = new SQLICSVDoc( new SQLICSVOptions( array( 'csv_path' => $file, 'delimiter' => ',' ) ) );
        $doc->parse();
        $this->rows = array();
        foreach ( $doc->rows as $row )
            $this->rows[] = $row;
        $this->rowCount = count( $this->rows );
    }

    public function getProcessLength()
    {
        return $this->rowCount;
    }

    public function getNextRow()
    {
        if ( $this->rowIndex < $this->rowCount )
            return $this->rows[$this->rowIndex++];
        return false;
    }

    public function process( $row )
    {
        if ( (string)$row->name === 'FAIL' )
            throw new SQLIImportRuntimeException( 'the fixture line ' . $row->id . ' fails on purpose' );
        $content = SQLIContent::create( new SQLIContentOptions( array(
            'class_identifier' => 'folder',
            'remote_id' => $this->remoteIDPrefix . $row->id,
        ) ) );
        $content->fields->name = (string)$row->name;
        $publisher = SQLIContentPublisher::getInstance();
        $publisher->setOptions( new SQLIContentPublishOptions( array( 'parent_node_id' => (int)$this->handlerConfArray['DefaultParentNodeID'] ) ) );
        $publisher->publish( $content );
        $this->progressionNotes = 'line ' . $row->id . ': ' . $row->name;
        $content->flush();
    }

    public function cleanup()
    {
        $this->rows = array();
    }

    public function getHandlerName()
    {
        return 'Fixture CSV handler (1.x style)';
    }

    public function getHandlerIdentifier()
    {
        return 'fixturecsv';
    }

    public function getProgressionNotes()
    {
        return $this->progressionNotes;
    }
}
