<?php
/**
 * File containing the expImportCommand class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The commands ./console ext:sqliimport:<name> (extension/sqliimport/bin/php/<name>.php); each file only calls
 * expImportCommand::main( '<name>' ). Started as root, a command becomes the site user before it reads a setting or
 * opens the database (when expqueue offers that), so nothing it writes belongs to root.
 *
 * Exit codes: 0 success, 1 failure or a bad argument, 2 nothing to do.
 */
class expImportCommand
{
    /** @var eZCLI */
    protected $cli;

    /** @var eZScript */
    protected $script;

    /** @var array */
    protected $options = array();

    /** @var string[] */
    protected $args = array();

    /**
     * The option specs and help of each command: name => array( description, option spec, option help ).
     * @return array
     */
    public static function commands()
    {
        return array(
            'install-schema' => array( 'Creates or upgrades the tables of 7x SQLI Import (idempotent) and reports what is missing.',
                                       '[install][check][print:][write-files]',
                                       array( 'install' => 'create the missing tables, columns and indexes (the default)',
                                              'check' => 'only report what is missing',
                                              'print' => 'print the SQL of mysql, postgresql, sqlite or oracle',
                                              'write-files' => 'rewrite share/db_schema.dba and sql/*/schema.sql of the extension' ) ),
            'list' => array( 'Lists the imports with their last run.', '[json]', array( 'json' => 'print JSON' ) ),
            'run' => array( 'Starts a run of an import: run <identifier|id>.', '[dry-run][wait][max-rows:][json]',
                            array( 'dry-run' => 'publish nothing, record what would happen (the first [RunSettings] DryRunRows records)',
                                   'wait' => 'process the run here (a worker of the job queue limited to this import) and print its result',
                                   'max-rows' => 'stop after this many records', 'json' => 'print JSON' ) ),
            'status' => array( 'The recent runs, or one run with its log: status [<run id>].', '[limit:][json]',
                               array( 'limit' => 'runs or log lines to show (default 20)', 'json' => 'print JSON' ) ),
            'cancel' => array( 'Cancels a run: cancel <run id>.', '', array() ),
            'retry' => array( 'Starts a run of the failed rows of a run: retry <run id>.', '[wait]',
                              array( 'wait' => 'process the run here and print its result' ) ),
            'migrate-1x' => array( 'Migrates the handlers, scheduled imports and history of sqliimport 1.x (reports only without --apply).', '[apply][roles]',
                                   array( 'apply' => 'write the migration (without it, nothing is changed)',
                                          'roles' => 'with --apply: add the 2.x policies to the roles holding the 1.x ones' ) ),
            'tick' => array( 'One round of the cron part: due schedules, 1.x pending imports, ended jobs, purge.', '', array() ),
            'purge' => array( 'Removes ended runs older than [RunSettings] KeepDays with their rows, log and files.', '[days:][dry-run]',
                              array( 'days' => 'older than this many days', 'dry-run' => 'only count' ) ),
        );
    }

    /** @return expImportDefinition the import named by the first argument (identifier or ID) */
    protected function importArgument()
    {
        $ref = $this->args ? (string)$this->args[0] : '';
        if ( $ref === '' )
            throw new expImportException( 'name the import (its identifier or ID)' );
        $import = ctype_digit( $ref ) ? expImportDefinition::fetch( (int)$ref ) : expImportDefinition::fetchByIdentifier( $ref );
        if ( !$import )
            throw new expImportException( "no import $ref (./console ext:sqliimport:list)" );
        return $import;
    }

    /** @return expImportRun the run named by the first argument */
    protected function runArgument()
    {
        $id = $this->args ? (int)$this->args[0] : 0;
        $run = $id ? expImportRun::fetch( $id ) : null;
        if ( !$run )
            throw new expImportException( 'name a run by its ID (./console ext:sqliimport:status)' );
        return $run;
    }

    /** @return array a run as plain data */
    public static function runData( expImportRun $run )
    {
        $out = array();
        foreach ( array( 'id', 'import_id', 'run_kind', 'trigger_kind', 'status', 'total_count', 'read_count', 'created_count', 'updated_count',
                         'unchanged_count', 'skipped_count', 'failed_count', 'processed_count', 'user_id', 'queue_group' ) as $field )
            $out[$field] = $run->attribute( $field );
        $out['percent'] = $run->percent();
        $out['duration'] = $run->duration();
        $out['memory_peak_mb'] = round( (int)$run->attribute( 'memory_peak' ) / 1048576, 1 );
        return $out;
    }

    protected function printRun( expImportRun $run )
    {
        $d = self::runData( $run );
        $this->cli->output( sprintf( 'run #%d %s (%s, %s): %d%% of %d; created %d, updated %d, unchanged %d, skipped %d, failed %d%s; %ds, %.1f MB',
                                     $d['id'], $d['status'], $d['run_kind'], $d['trigger_kind'], $d['percent'], $d['total_count'], $d['created_count'],
                                     $d['updated_count'], $d['unchanged_count'], $d['skipped_count'], $d['failed_count'],
                                     $d['processed_count'] ? ', processed ' . $d['processed_count'] : '', $d['duration'], $d['memory_peak_mb'] ) );
    }

    /** Runs the jobs of an import's resource here until none is ready, then prints the run. */
    protected function wait( expImportRun $run )
    {
        $import = $run->import();
        $worker = new expQueueWorker( array( 'resources' => array( expImportRunner::resource( $import ) ), 'until_empty' => true, 'origin' => 'cli' ) );
        $worker->run();
        $run->reload();
        $this->printRun( $run );
        return in_array( $run->attribute( 'status' ), array( expImportRun::STATUS_COMPLETED, expImportRun::STATUS_COMPLETED_WITH_ERRORS ), true ) ? 0 : 1;
    }

    protected function cmdList()
    {
        $rows = array();
        foreach ( expImportDefinition::fetchList( 0, 1000 ) as $import )
        {
            $last = $import->lastRun();
            $rows[] = array( 'id' => (int)$import->attribute( 'id' ), 'identifier' => $import->attribute( 'identifier' ), 'name' => $import->attribute( 'name' ),
                             'source' => $import->attribute( 'source_type' ), 'handler' => $import->attribute( 'handler' ),
                             'enabled' => (bool)$import->attribute( 'is_enabled' ),
                             'last_run' => $last ? array( 'id' => (int)$last->attribute( 'id' ), 'status' => $last->attribute( 'status' ) ) : null );
        }
        if ( !empty( $this->options['json'] ) )
        {
            $this->cli->output( json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
            return 0;
        }
        if ( !$rows )
            $this->cli->output( 'no imports yet' );
        foreach ( $rows as $r )
            $this->cli->output( sprintf( '%4d  %-30s %-6s %-20s %s%s', $r['id'], $r['identifier'], $r['source'], $r['handler'],
                                         $r['last_run'] ? 'last run #' . $r['last_run']['id'] . ' ' . $r['last_run']['status'] : 'never run',
                                         $r['enabled'] ? '' : ' (disabled)' ) );
        return 0;
    }

    protected function cmdRun()
    {
        $import = $this->importArgument();
        $options = array( 'trigger' => 'cli', 'run_kind' => !empty( $this->options['dry-run'] ) ? 'dry_run' : 'import' );
        if ( !empty( $this->options['max-rows'] ) )
            $options['max_rows'] = (int)$this->options['max-rows'];
        $run = expImportRunner::start( $import, $options );
        if ( !empty( $this->options['json'] ) && empty( $this->options['wait'] ) )
        {
            $this->cli->output( json_encode( self::runData( $run ) ) );
            return 0;
        }
        $this->cli->output( 'PASS run #' . $run->attribute( 'id' ) . ' of ' . $import->attribute( 'identifier' ) . ' queued (job group ' . $run->attribute( 'queue_group' ) . ')' );
        return !empty( $this->options['wait'] ) ? $this->wait( $run ) : 0;
    }

    protected function cmdStatus()
    {
        $limit = !empty( $this->options['limit'] ) ? (int)$this->options['limit'] : 20;
        if ( $this->args )
        {
            $run = $this->runArgument();
            $lines = expImportRunLog::fetchByRun( $run->attribute( 'id' ), 0, array(), 0, $limit );
            if ( !empty( $this->options['json'] ) )
            {
                $log = array();
                foreach ( $lines as $line )
                    $log[] = array( 'severity' => $line->attribute( 'severity' ), 'ref' => $line->attribute( 'row_ref' ), 'message' => $line->attribute( 'message' ) );
                $this->cli->output( json_encode( self::runData( $run ) + array( 'log' => $log ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
                return 0;
            }
            $this->printRun( $run );
            foreach ( $lines as $line )
                $this->cli->output( '  ' . date( 'H:i:s', (int)$line->attribute( 'created' ) ) . ' ' . str_pad( $line->attribute( 'severity' ), 7 )
                                    . ( $line->attribute( 'row_ref' ) !== '' ? ' [' . $line->attribute( 'row_ref' ) . ']' : '' ) . ' ' . $line->attribute( 'message' ) );
            return 0;
        }
        $runs = expImportRun::fetchList( array(), 0, $limit );
        if ( !empty( $this->options['json'] ) )
        {
            $this->cli->output( json_encode( array_map( array( __CLASS__, 'runData' ), $runs ), JSON_PRETTY_PRINT ) );
            return 0;
        }
        if ( !$runs )
            $this->cli->output( 'no runs yet' );
        foreach ( $runs as $run )
            $this->printRun( $run );
        return 0;
    }

    protected function cmdCancel()
    {
        $run = $this->runArgument();
        if ( !expImportRunner::cancel( $run, 'cancelled from the command line' ) )
        {
            $this->cli->output( 'the run has ended already (' . $run->attribute( 'status' ) . ')' );
            return 2;
        }
        $this->cli->output( 'PASS run #' . $run->attribute( 'id' ) . ' cancelled' );
        return 0;
    }

    protected function cmdRetry()
    {
        $run = expImportRunner::retryFailed( $this->runArgument() );
        $this->cli->output( 'PASS run #' . $run->attribute( 'id' ) . ' queued for the failed rows' );
        return !empty( $this->options['wait'] ) ? $this->wait( $run ) : 0;
    }

    protected function cmdMigrate1x()
    {
        $apply = !empty( $this->options['apply'] );
        $report = expImportMigration::run( $apply, !empty( $this->options['roles'] ) );
        foreach ( $report as $line )
            $this->cli->output( str_pad( $line['what'], 10 ) . ( $line['done'] ? 'DONE  ' : ( $apply ? '      ' : 'WOULD ' ) ) . $line['text'] );
        $this->cli->output( $apply ? 'PASS migrated' : 'PASS dry run: nothing was changed (--apply to migrate)' );
        return 0;
    }

    protected function cmdTick()
    {
        $cli = $this->cli;
        $stats = expImportScheduler::tick( function ( $line ) use ( $cli ) { $cli->output( $line ); } );
        $this->cli->output( 'PASS ' . json_encode( $stats ) );
        return 0;
    }

    protected function cmdPurge()
    {
        $days = isset( $this->options['days'] ) && $this->options['days'] !== null ? (int)$this->options['days'] : null;
        $dry = !empty( $this->options['dry-run'] );
        $n = expImportScheduler::purge( $days, $dry );
        $this->cli->output( 'PASS ' . $n . ' runs ' . ( $dry ? 'would be removed' : 'removed' ) );
        return 0;
    }

    /**
     * The entry point of bin/php/<name>.php.
     *
     * @param string $name
     */
    public static function main( $name )
    {
        $root = dirname( __DIR__, 4 );
        if ( class_exists( 'expQueueLock' ) && method_exists( 'expQueueLock', 'dropPrivileges' ) )
        {
            $error = expQueueLock::dropPrivileges( $root );
            if ( $error !== null )
            {
                fwrite( STDERR, "FAIL $error\n" );
                exit( 1 );
            }
        }
        $command = new self();
        exit( $command->start( $name ) );
    }

    /** @return int exit code */
    public function start( $name )
    {
        $commands = static::commands();
        if ( !isset( $commands[$name] ) )
        {
            fwrite( STDERR, "FAIL unknown command $name\n" );
            return 1;
        }
        list( $description, $spec, $help ) = $commands[$name];
        $this->cli = eZCLI::instance();
        $this->script = eZScript::instance( array( 'description' => "7x SQLI Import: $description\n", 'use-session' => false,
                                                   'use-modules' => true, 'use-extensions' => true ) );
        $this->script->startup();
        $this->options = $this->script->getOptions( $spec, '', $help );
        $this->script->initialize();
        $this->args = isset( $this->options['arguments'] ) ? array_values( $this->options['arguments'] ) : array();
        if ( $name !== 'install-schema' && !expImportSchema::installed() )
        {
            $this->cli->error( 'FAIL the tables of 7x SQLI Import are missing in this database: run ./console ext:sqliimport:install-schema' );
            $this->script->shutdown( 1 );
            return 1;
        }
        $method = 'cmd' . str_replace( ' ', '', ucwords( str_replace( '-', ' ', $name ) ) );
        try
        {
            $code = (int)$this->$method();
        }
        catch ( Exception $e )
        {
            $this->cli->error( 'FAIL ' . $e->getMessage() );
            $code = 1;
        }
        $this->script->shutdown( $code );
        return $code;
    }

    protected function cmdInstallSchema()
    {
        $o = $this->options;
        if ( !empty( $o['print'] ) )
        {
            $statements = expImportSchema::sqlStatements( (string)$o['print'] );
            if ( !$statements )
            {
                $this->cli->error( 'FAIL no schema handler for ' . $o['print'] );
                return 1;
            }
            foreach ( $statements as $statement )
                $this->cli->output( $statement . ( substr( $statement, -1 ) === ';' ? '' : ';' ) );
            return 0;
        }
        if ( !empty( $o['write-files'] ) )
        {
            list( $written, $errors ) = expImportSchema::writeFiles( dirname( __DIR__, 2 ) );
            foreach ( $written as $file )
                $this->cli->output( "PASS wrote $file" );
            foreach ( $errors as $error )
                $this->cli->error( "FAIL $error" );
            return $errors ? 1 : 0;
        }
        $db = eZDB::instance();
        $this->cli->output( 'database engine: ' . expImportSchema::engineOf( $db ) );
        $failed = false;
        if ( empty( $o['check'] ) )
        {
            list( $created, $errors ) = expImportSchema::ensure( $db );
            foreach ( $created as $what )
                $this->cli->output( "created $what" );
            foreach ( $errors as $error )
                $this->cli->error( "error: $error" );
            $failed = (bool)$errors;
        }
        $missing = array_merge( expImportSchema::missingTables( $db ), expImportSchema::missingColumns( $db ) );
        if ( $missing )
        {
            $this->cli->output( 'FAIL missing: ' . implode( ', ', $missing ) );
            if ( !empty( $o['check'] ) )
                $this->cli->output( 'run without --check to add them' );
            return 1;
        }
        $this->cli->output( 'PASS the tables ' . implode( ', ', expImportSchema::tables() ) . ' exist with every column and index' );
        return $failed ? 1 : 0;
    }
}
