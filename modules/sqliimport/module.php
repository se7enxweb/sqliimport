<?php
/**
 * The admin module "sqliimport" (the Imports tab).
 *
 * 2.x views: list (the dashboard: figures, imports, runs), run (one run: progress, counters, rows, log) and action
 * (every change: run, dry run, cancel, retry failed rows; POST only, checked by ezformtoken).
 * 1.x views, kept: items (the 1.x import requests), scheduledlist, addimport, addscheduled, alterimport,
 * removescheduled, purgelist; their changes are POST only as well.
 *
 * Policies (2.x): view, edit, run, manage, rollback, ai, settings; edit and run take the handler limitation
 * SQLIImport_Type. The 1.x functions listimports and manageimports stay and still give access (the view's
 * function expression accepts them, and expImportAdmin::can() checks the actions the same way).
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

$Module = array( 'name' => 'sqliimport', 'variable_params' => false );

$ViewList = array();

// who may look: the 2.x function view, or a 1.x role
$canView = array( 'view or listimports or manageimports' );
// who may change through the 1.x pages (they check the 1.x function and its handler limitation themselves)
$canManage = array( 'manageimports' );

$ViewList['list'] = array(
    'script' => 'list.php',
    'params' => array(),
    'unordered_params' => array( 'status' => 'Status', 'import' => 'Import', 'offset' => 'Offset' ),
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

$ViewList['action'] = array(
    'script' => 'action.php',
    'params' => array(),
    'default_navigation_part' => 'sqliimportnavigationpart',
    'functions' => $canView,
);

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
// 2.x
$FunctionList['view'] = array();
$FunctionList['edit'] = array( 'SQLIImport_Type' => $Type );
$FunctionList['run'] = array( 'SQLIImport_Type' => $Type );
$FunctionList['manage'] = array();
$FunctionList['rollback'] = array();
$FunctionList['ai'] = array();
$FunctionList['settings'] = array();
// 1.x, kept: roles made for 1.x keep working
$FunctionList['listimports'] = array();
$FunctionList['manageimports'] = array( 'SQLIImport_Type' => $Type );
