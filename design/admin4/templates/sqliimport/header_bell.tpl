{* The bell of 7x SQLI Import in the administration's header (design.ini [AdminHeaderSettings] HeaderItems[], the
   kernel's parts/a4_header_item.tpl): the current user's unread notices of runs that ended. The header is cached per
   user, so the count and the entries come from the feed sqliimport::notices (expImportServerFunctions::notices),
   which admin4.js loads. Only for a user who may see the imports. *}
{if fetch( 'user', 'has_access_to', hash( 'module', 'sqliimport', 'function', 'view' ) )}
{include uri='design:parts/a4_header_item.tpl'
         item_id='sqliimport'
         item_label='Import notifications'|i18n( 'extension/sqliimport' )
         item_icon='bell'
         item_url='/sqliimport/list'
         item_all='All imports'|i18n( 'extension/sqliimport' )
         item_empty='No new notifications'|i18n( 'extension/sqliimport' )
         item_feed=concat( 'ezjscore/call/sqliimport::notices::8'|ezurl( 'no' ), '?ContentType=json' )}
{/if}
