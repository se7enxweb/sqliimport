{* The links of the Imports tab's left menu: menu.ini [Leftmenu_sqliimport] drawn by the administration's shared menu
   template (parts/ini_menu.tpl): the same markup, groups, access checks and current-page mark as every module's menu.
   The names are translated here, in the context extension/sqliimport/menu. *}
{include uri='design:parts/ini_menu.tpl' ini_section='Leftmenu_sqliimport'
    i18n_hash=hash(
    'Imports',   'Imports'|i18n( 'extension/sqliimport/menu' ),
    'dashboard', 'Overview'|i18n( 'extension/sqliimport/menu' ),
    'statistics', 'Statistics'|i18n( 'extension/sqliimport/menu' ),
    'new',       'New import'|i18n( 'extension/sqliimport/menu' ),
    'schedules', 'Schedules'|i18n( 'extension/sqliimport/menu' ),
    'catalogue', 'Catalogue'|i18n( 'extension/sqliimport/menu' ),
    'templates', 'Templates'|i18n( 'extension/sqliimport/menu' ),
    'transfer',  'Import definitions'|i18n( 'extension/sqliimport/menu' ),
    'settings',  'Settings'|i18n( 'extension/sqliimport/menu' ),
    'active',    'Running runs'|i18n( 'extension/sqliimport/menu' ),
    'failed',    'Failed runs'|i18n( 'extension/sqliimport/menu' ),
    'queue',     'Job queue'|i18n( 'extension/sqliimport/menu' ),
)}
{* the other import and export tools of the transfer registry (Exponential 6.0.15 and later; nothing on an older kernel) *}
{if ezini_hasvariable( 'Service_transfer.registry', 'Class', 'kernelservices.ini' )}{include uri='design:transfer/left_menu.tpl' current='transfer.sqliimport'}{/if}
