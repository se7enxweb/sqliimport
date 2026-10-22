<?php
/**
 * The admin module "sqliimport" (the Imports tab).
 *
 * 2.x views: list (the dashboard: figures, charts, imports, runs), statistics (the run history: trends, a table per
 * import, comparison of two periods, suggestions, CSV), run (one run: progress, counters, rows, log), edit (the
 * import editor), schedules, catalogue, settings (export and import of definitions) and action
 * (every change: run, dry run, cancel, retry failed rows; POST only, checked by ezformtoken).
 * The 1.x requests and scheduled imports are part of these pages (expImportLegacy): the dashboard lists the 1.x
 * import requests (request, cancel, interrupt, purge), sqliimport/schedules the 1.x scheduled imports (add, edit, run
 * now, enable, disable, delete). The addresses of the 1.x views stay (items, scheduledlist, addimport, addscheduled,
 * alterimport, removescheduled, purgelist): a GET goes to the 2.x page, a POST with the 1.x fields does what it did
 * (checked by ezformtoken and the handler limitation) and then goes there. They draw no page of their own.
 *
 * Policies (2.x, two as the owner chose): "Manage imports" (manage) and "Run imports" (run), both with the handler limitation
 * SQLIImport_Type. The 1.x functions listimports and manageimports stay and still give access (the view's
 * function expression accepts them, and expImportAdmin::can() checks the actions the same way).
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

$Module = array( 'name' => 'sqliimport', 'variable_params' => false );

$ViewList = array();

// who may look: a 2.x policy (Manage imports, Run imports), or a 1.x one
$canView = array( 'manage or run or listimports or manageimports' );
// who may change through the addresses of the 1.x pages (they check the handler limitation themselves, with
// expImportAdmin::can(): manageimports of 1.x as before, or Manage imports of 2.x)
$canManage = array( 'manage or manageimports' );

$ViewList['list'] = array(
    'script' => 'list.php',
    'params' => array(),
    'unordered_params' => array( 'status' => 'Status', 'import' => 'Import', 'offset' => 'Offset', 'legacyoffset' => 'LegacyOffset' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['run'] = array(
    'script' => 'run.php',
    'params' => array( 'RunID' ),
    'unordered_params' => array( 'outcome' => 'Outcome', 'offset' => 'Offset' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['report'] = array(
    'script' => 'report.php',
    'params' => array( 'RunID' ),
    'unordered_params' => array( 'outcome' => 'Outcome' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['statistics'] = array(
    'script' => 'statistics.php',
    'params' => array(),
    'unordered_params' => array( 'days' => 'Days', 'from' => 'From', 'to' => 'To', 'import' => 'Import', 'source' => 'Source', 'user' => 'User',
                                 'scope' => 'Scope', 'compare' => 'Compare', 'cfrom' => 'CompareFrom', 'cto' => 'CompareTo', 'format' => 'Format' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['action'] = array(
    'script' => 'action.php',
    'params' => array(),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['edit'] = array(
    'script' => 'edit.php',
    'params' => array( 'ImportID' ),
    'unordered_params' => array( 'step' => 'Step', 'source' => 'Source' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => array( 'manage or manageimports' ),
);

$ViewList['schedules'] = array(
    'script' => 'schedules.php',
    'params' => array(),
    'unordered_params' => array( 'legacy' => 'Legacy' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['catalogue'] = array(
    'script' => 'catalogue.php',
    'params' => array(),
    'unordered_params' => array( 'example' => 'Example', 'n' => 'N' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['settings'] = array(
    'script' => 'settings.php',
    'params' => array(),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => array( 'manage or manageimports' ),
);

// The addresses of the 1.x views: a GET goes to the 2.x page (expImportLegacy::redirectFor()), a POST does what the
// 1.x view did and then goes there
$ViewList['items'] = array(
    'script' => 'items.php',
    'params' => array(),
    'unordered_params' => array( 'offset' => 'Offset' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['scheduledlist'] = array(
    'script' => 'scheduledlist.php',
    'params' => array(),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

$ViewList['addimport'] = array(
    'script' => 'addimport.php',
    'params' => array(),
    'single_post_actions' => array( 'RequestImportButton' => 'RequestImport' ),
    'post_action_parameters' => array( 'RequestImport' => array( 'ImportHandler' => 'ImportHandler', 'ImportOptions' => 'ImportOptions' ) ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canManage,
);

$ViewList['addscheduled'] = array(
    'script' => 'addscheduled.php',
    'params' => array( 'ScheduledImportID' ),
    'single_post_actions' => array( 'RequestScheduledImportButton' => 'RequestScheduledImport' ),
    'post_action_parameters' => array( 'RequestScheduledImport' => array(
        'ImportHandler' => 'ImportHandler',
        'ImportOptions' => 'ImportOptions',
        'ScheduledDate' => 'ScheduledDate',
        'ScheduledHour' => 'ScheduledHour',
        'ScheduledMinute' => 'ScheduledMinute',
        'ScheduledFrequency' => 'ScheduledFrequency',
        'ManualScheduledFrequency' => 'ManualScheduledFrequency',
        'ScheduledLabel' => 'ScheduledLabel',
        'ScheduledActive' => 'ScheduledActive',
    ) ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canManage,
);

$ViewList['removescheduled'] = array(
    'script' => 'removescheduled.php',
    'params' => array( 'ImportID' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canManage,
);

$ViewList['alterimport'] = array(
    'script' => 'alterimport.php',
    'params' => array( 'Action', 'ImportID' ),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canManage,
);

$ViewList['purgelist'] = array(
    'script' => 'purgelist.php',
    'params' => array(),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canManage,
);

// The handler limitation of a policy
$Type = array(
    'name' => 'SQLIImport_Type',
    'values' => array(),
    'extension' => 'sqliimport',
    'path' => 'classes/',
    'file' => 'sqliimportutils.php',
    'class' => 'SQLIImportUtils',
    'function' => 'fetchHandlerLimitationList',
    'parameter' => array(),
);

$FunctionList = array();
// "Manage imports": create, edit, copy, delete, schedule and run imports, the settings and the export (with the
// handler limitation); "Run imports": run, dry run, cancel and retry the imports one may see (with the limitation)
$FunctionList['manage'] = array( 'SQLIImport_Type' => $Type );
$FunctionList['run'] = array( 'SQLIImport_Type' => $Type );
// 1.x, kept: roles made for 1.x keep working (manageimports counts as manage, listimports lets one look)
$FunctionList['listimports'] = array();
$FunctionList['manageimports'] = array( 'SQLIImport_Type' => $Type );
