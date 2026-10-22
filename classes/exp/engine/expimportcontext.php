<?php
/**
 * File containing the expImportContext class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * What a handler gets besides the record: the run and its import, the validated options, the target, the behaviour,
 * the log, whether this is a dry run, and the cancel check of the job queue.
 */
class expImportContext
{
    /** @var expImportRun */
    public $run;

    /** @var expImportDefinition */
    public $import;

    /** @var array validated options of the handler */
    public $options;

    /** @var array parent_node_id, section_id, language, owner_id */
    public $target;

    /** @var array chunk_size, update_policy, trash_policy, max_rows */
    public $behaviour;

    /** @var expImportLogger */
    public $log;

    /** @var bool nothing is published, the rows say what would happen */
    public $dryRun = false;

    /** @var expQueueContext|null the job that runs the chunk */
    public $queue;

    public function __construct( expImportRun $run, expImportDefinition $import, array $options, expImportLogger $log, ?expQueueContext $queue = null )
    {
        $this->run = $run;
        $this->import = $import;
        $this->options = $options;
        $this->target = $import->target();
        $this->behaviour = $import->behaviour();
        $this->log = $log;
        $this->dryRun = $run->attribute( 'run_kind' ) === 'dry_run';
        $this->queue = $queue;
    }

    /** Extends the job's lease; throws when the run was cancelled meanwhile. */
    public function throwIfCancelled()
    {
        if ( $this->queue )
            $this->queue->throwIfCancelled();
    }

    /** @return bool */
    public function isCancelled()
    {
        return $this->queue ? $this->queue->isCancelled() : false;
    }
}
