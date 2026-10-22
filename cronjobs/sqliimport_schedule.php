<?php
/**
 * The cron part "sqliimport" (php runcronjobs.php -q sqliimport, every minute): queues the runs of the schedules that
 * are due and the pending imports of 1.x, ends runs whose jobs ended without them, and purges old runs
 * (expImportScheduler::tick()). The runs are processed by the workers of the job queue (cron part expqueue).
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZCLI $cli */
$isQuiet = isset( $isQuiet ) ? $isQuiet : false;
$stats = expImportScheduler::tick( function ( $line ) use ( $cli, $isQuiet )
{
    if ( !$isQuiet )
        $cli->output( $line );
} );
if ( !$isQuiet )
    $cli->output( sprintf( 'sqliimport: %d runs queued, %d skipped, %d ended, %d 1.x imports, %d purged',
                           $stats['started'], $stats['skipped'], $stats['ended'], $stats['legacy'], $stats['purged'] ) );
