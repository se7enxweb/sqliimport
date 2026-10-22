<?php
/**
 * sqliimport/catalogue: the sources, handlers and transforms of this site (expImportRegistry), with their status and
 * their options; the 1.x handlers are marked legacy.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/** @var eZModule $Module */
$Module = $Params['Module'];
$registry = expImportRegistry::instance();
$registry->registerTransforms();
$tpl = eZTemplate::factory();
$tpl->setVariable( 'sources', $registry->sources() );
$tpl->setVariable( 'handlers', $registry->handlers() );
$tpl->setVariable( 'transforms', expImportTransforms::catalogue() );
$Result = array();
$Result['content'] = $tpl->fetch( 'design:sqliimport/catalogue.tpl' );
$Result['path'] = array( array( 'url' => 'sqliimport/list', 'text' => ezpI18n::tr( 'extension/sqliimport', 'Imports' ) ),
                         array( 'url' => false, 'text' => ezpI18n::tr( 'extension/sqliimport', 'Catalogue' ) ) );
