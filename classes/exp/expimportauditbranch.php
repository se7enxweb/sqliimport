<?php
/**
 * File containing the expImportAuditBranch class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The audit taxonomy branch of 7x SQLI Import (registered in settings/audit.ini.append.php): system.import.<action>,
 * one event per run that starts, ends, fails or is cancelled, and per import, mapping or schedule that is created,
 * changed or deleted. The rows a run processed are kept in sqliimport_run_row, not in the audit trail.
 */
class expImportAuditBranch implements expAuditTaxonomyBranch
{
    const ACTIONS = array(
        'run.start' => array( 'Import: run started', 'info' ),
        'run.finish' => array( 'Import: run finished', 'info' ),
        'run.fail' => array( 'Import: run failed', 'warning' ),
        'run.cancel' => array( 'Import: run cancelled', 'notice' ),
        'import.create' => array( 'Import: import created', 'notice' ),
        'import.change' => array( 'Import: import changed', 'notice' ),
        'import.delete' => array( 'Import: import deleted', 'notice' ),
        'schedule.change' => array( 'Import: schedule changed', 'notice' ),
        'migrate' => array( 'Import: 1.x imports migrated', 'notice' ),
        'ai.suggest' => array( 'Import: AI suggestions asked for', 'info' ),
        'ai.accept' => array( 'Import: AI suggestions accepted and saved', 'notice' ),
        'ai.explain' => array( 'Import: AI explanation of a failed row asked for', 'info' ),
        'ai.summary' => array( 'Import: AI summary of a run asked for', 'info' ),
        'definitions.export' => array( 'Import: definitions exported', 'info' ),
        'definitions.import' => array( 'Import: definitions imported', 'notice' ),
        'template.create' => array( 'Import: template saved', 'notice' ),
        'template.delete' => array( 'Import: template deleted', 'notice' ),
    );

    public function events()
    {
        $events = array();
        foreach ( self::ACTIONS as $action => $info )
            $events['system.import.' . $action] = array( 'label' => $info[0], 'severity' => $info[1], 'channel' => 'system', 'default' => 'on' );
        return $events;
    }
}
