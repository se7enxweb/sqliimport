<?php
/**
 * sqliimport/items (1.x): the list of the 1.x import requests is part of the dashboard now (sqliimport/list, section
 * "1.x import requests"). This address stays and goes there, with its (offset).
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$Module->redirectTo( expImportLegacy::redirectFor( 'items', array(), (array)$Params['UserParameters'] ) );
