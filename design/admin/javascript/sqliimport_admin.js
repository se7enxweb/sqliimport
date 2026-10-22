/*
 * 7x SQLI Import: the live run page. An element with data-exp-sqliimport-run='{"run": <id>, "log": <last log id>}'
 * (sqliimport/run.tpl) asks the server every two seconds (sqliimport::runstatus through Exp.io of expui) while the
 * run is queued or running, and updates the status, the progress bar, the counters and the log in place; when the
 * run ends, the page is loaded again so the records show. Without expui or without JavaScript the page works as it
 * is, with its Refresh button. Loaded through design.ini [JavaScriptSettings] BackendJavaScriptList; no inline
 * script, so the page works under a Content Security Policy without 'unsafe-inline'.
 */
(function ( window ) {
    'use strict';

    function start() {
        if ( !window.Exp || !window.Exp.register || !window.Exp.io )
            return;
        window.Exp.register( 'sqliimport-run', function ( $el, options ) {
            var run = parseInt( options.run, 10 ), last = parseInt( options.log || 0, 10 ), stopped = false, misses = 0;
            if ( !run )
                return;
            var $ = window.Exp.$;
            function field( name, value ) {
                $el.find( '[data-sq-field="' + name + '"]' ).text( value );
            }
            function update( s ) {
                var $bar = $el.find( '[data-sq-meter] > span' );
                $bar.attr( 'class', 'a4-pct-' + s.percent );
                $el.find( '[data-sq-meter]' ).attr( 'aria-valuenow', s.percent );
                $.each( [ 'created', 'updated', 'unchanged', 'skipped', 'failed', 'processed', 'done', 'percent', 'duration', 'status_label' ], function ( i, k ) {
                    if ( s[k] !== undefined )
                        field( k, s[k] );
                } );
                field( 'total', s.total > 0 ? s.total : '?' );
                var $log = $el.find( '[data-sq-log]' );
                $.each( s.log || [], function ( i, line ) {
                    last = Math.max( last, line.id );
                    var kind = line.severity === 'error' ? 'is-bad' : ( line.severity === 'warning' ? 'is-warn' : ( line.severity === 'notice' ? 'is-info' : 'is-ok' ) );
                    var $li = $( '<li>' ).addClass( kind );
                    $li.append( $( '<span class="a4-timeline-time">' ).text( line.time ) );
                    var $body = $( '<span class="a4-timeline-body">' );
                    $body.append( $( '<span class="a4-badge">' ).addClass( line.severity === 'error' ? 'is-bad' : ( line.severity === 'warning' ? 'is-warn' : '' ) ).text( line.severity ) );
                    if ( line.ref )
                        $body.append( ' ', $( '<span class="a4-meta">' ).text( line.ref ) );
                    $body.append( ' ', $( '<span>' ).text( line.message ) );
                    $li.append( $body );
                    $log.append( $li );
                    $log.prop( 'hidden', false );
                } );
                $el.find( '[data-sq-log-empty]' ).prop( 'hidden', $log.children().length > 0 );
            }
            function tick() {
                if ( stopped )
                    return;
                window.Exp.io.call( 'sqliimport::runstatus', [ run, last ] ).then( function ( s ) {
                    misses = 0;
                    update( s );
                    if ( s.is_final ) {
                        stopped = true;
                        window.location.reload();
                        return;
                    }
                    window.setTimeout( tick, 2000 );
                }, function () {
                    // a missed answer (network, a restart) is tried again a few times, then the page is left as it is
                    if ( ++misses < 5 )
                        window.setTimeout( tick, 5000 );
                } );
            }
            window.setTimeout( tick, 1500 );
        } );
    }

    if ( window.Exp && window.Exp.ready )
        window.Exp.ready( start );
    else if ( window.addEventListener )
        window.addEventListener( 'DOMContentLoaded', start );
})( window );

/*
 * The source step of the import editor: the form holds the option fields of every format
 * (fieldset[data-sq-source-options="<format>"], sqliimport/edit/source.tpl); choosing a format shows its options and
 * hides the others. Plain DOM, no library; without the script, saving the step shows the chosen format's options.
 */
(function ( document ) {
    'use strict';

    function show( form, format ) {
        Array.prototype.forEach.call( form.querySelectorAll( '[data-sq-source-options]' ), function ( fieldset ) {
            fieldset.hidden = fieldset.getAttribute( 'data-sq-source-options' ) !== format;
        } );
    }

    function start() {
        // the errors of a step that could not be saved: the summary takes the focus, its links lead to the fields
        var summary = document.getElementById( 'a4-error-summary' );
        if ( summary && summary.closest( '.a4-sqliimport' ) )
            summary.focus();
        Array.prototype.forEach.call( document.querySelectorAll( 'form' ), function ( form ) {
            if ( !form.querySelector( '[data-sq-source-options]' ) )
                return;
            form.addEventListener( 'change', function ( e ) {
                if ( e.target && e.target.name === 'SourceType' && e.target.checked )
                    show( form, e.target.value );
            } );
            var checked = form.querySelector( 'input[name="SourceType"]:checked' );
            if ( checked )
                show( form, checked.value );
        } );
    }

    if ( document.readyState === 'loading' )
        document.addEventListener( 'DOMContentLoaded', start );
    else
        start();
})( document );
