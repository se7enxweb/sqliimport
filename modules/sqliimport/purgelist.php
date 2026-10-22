<?php
/**
 * SQLi Import import purge history import view
 * @copyright Copyright (C) 2010 - SQLi Agency. All rights reserved
 * @licence http://www.gnu.org/licenses/gpl-2.0.txt GNU GPLv2
 * @author Jerome Vieilledent
 * @version @@@VERSION@@@
 * @package sqliimport
 */

$Module = $Params['Module'];
// A change is made only by a POST (the form token of ezformtoken checks it), never by a link a page could embed
if ( strtoupper( (string)eZSys::serverVariable( 'REQUEST_METHOD', true ) ) !== 'POST' )
    return $Module->redirectToView( 'items' );
$Result = array();
$tpl = SQLIImportUtils::templateInit();

try
{
    $user = eZUser::currentUser();
    $userID = $user->attribute( 'contentobject_id' );
    $userLogin = $user->attribute( 'login' );
    SQLIImportLogger::logNotice(
        'User "'.$userLogin.'" (#'.$userID.') requested import history purge on '.date( 'Y-m-d H:i' ),
        false
    );
    SQLIImportItem::purgeImportHistory();

    $Module->redirectToView( 'items' );
}
catch( Exception $e )
{
    $errMsg = $e->getMessage();
    SQLIImportLogger::writeError( $errMsg );
    $tpl->setVariable( 'error_message', $errMsg );
    
    $Result['path'] = array(
        array(
            'url'       => false,
            'text'      => SQLIImportUtils::translate( 'extension/sqliimport/error', 'Error' )
        )
    );
    $Result['content'] = $tpl->fetch( 'design:sqliimport/altererror.tpl' );
}

