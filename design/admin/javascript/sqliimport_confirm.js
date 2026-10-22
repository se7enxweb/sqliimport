/*
 * 7x SQLI Import: the question of an import action (sqliimport/parts/postbutton.tpl, a form with data-a4-confirm)
 * in an administration design without admin4's a4_actions.js. admin4 and admin4l ask it themselves (a4_actions.js
 * sets window.a4Actions); in the other designs this listener asks it, so an action that needs a confirmation is never
 * sent without one. Loaded through design.ini [JavaScriptSettings] BackendJavaScriptList, so the form needs no inline
 * onsubmit handler and the page works under a Content Security Policy without 'unsafe-inline'.
 */
(function ( window, document ) {
    'use strict';

    if ( !document.addEventListener )
        return;

    document.addEventListener( 'submit', function ( e ) {
        var form = e.target;
        // admin4's a4_actions.js handles data-a4-confirm on its own: asking here as well would ask twice
        if ( window.a4Actions || !form || !form.hasAttribute || !form.hasAttribute( 'data-a4-confirm' ) )
            return;
        if ( form.className.split( /\s+/ ).indexOf( 'a4-sqliimport-inline' ) === -1 )
            return;
        if ( !window.confirm( form.getAttribute( 'data-a4-confirm' ) ) )
            e.preventDefault();
    } );
})( window, document );
