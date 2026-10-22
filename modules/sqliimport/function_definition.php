<?php
/**
 * Template fetch functions of 7x SQLI Import:
 *
 *   fetch( 'sqliimport', 'recent_runs', hash( 'limit', 5 ) )   the latest runs (expImportAdmin::runRow()), for users
 *                                                              who may see the imports
 *   fetch( 'sqliimport', 'failed_runs_24h' )                   how many runs failed or ended with errors in a day
 *   fetch( 'sqliimport', 'notices', hash( 'limit', 10 ) )        the current user's unread notices of runs that ended:
 *                                                              hash( count, items ) (a bell in the header can show them)
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

$FunctionList = array();
$FunctionList['recent_runs'] = array( 'name' => 'recent_runs',
                                      'operation_types' => array( 'read' ),
                                      'call_method' => array( 'class' => 'expImportFunctionCollection', 'method' => 'fetchRecentRuns' ),
                                      'parameter_type' => 'standard',
                                      'parameters' => array( array( 'name' => 'limit', 'type' => 'integer', 'required' => false, 'default' => 5 ) ) );
$FunctionList['failed_runs_24h'] = array( 'name' => 'failed_runs_24h',
                                          'operation_types' => array( 'read' ),
                                          'call_method' => array( 'class' => 'expImportFunctionCollection', 'method' => 'fetchFailedRuns24h' ),
                                          'parameter_type' => 'standard',
                                          'parameters' => array() );
$FunctionList['notices'] = array( 'name' => 'notices',
                                  'operation_types' => array( 'read' ),
                                  'call_method' => array( 'class' => 'expImportFunctionCollection', 'method' => 'fetchNotices' ),
                                  'parameter_type' => 'standard',
                                  'parameters' => array( array( 'name' => 'limit', 'type' => 'integer', 'required' => false, 'default' => 10 ) ) );
