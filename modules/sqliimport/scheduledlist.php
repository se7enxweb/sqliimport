<?php
/**
 * sqliimport/scheduledlist (1.x): the 1.x scheduled imports are part of sqliimport/schedules now (section "1.x
 * scheduled imports"). This address stays and goes there.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$Module->redirectTo( expImportLegacy::redirectFor( 'scheduledlist', array(), (array)$Params['UserParameters'] ) );
