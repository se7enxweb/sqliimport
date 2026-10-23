<?php
/**
 * File containing the expImportAi class.
 *
 * @copyright Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The AI assist of the import editor and the run page, through the extension expai (expImportAiExpaiTransport):
 *
 *   mapping   "Suggest with AI" in the mapping step: per attribute a source field, transforms, a confidence and a reason
 *   class     the content class (or the data table) that fits the source's fields: the best three with reasons
 *   explain   for a failed row of a run: what went wrong and a suggested fix
 *   summary   for a finished run: a short summary of its counts, durations, top errors and changes
 *
 * Assistive only. Nothing is applied without a person: suggestions are shown and accepted line by line or all at
 * once into the form, which the person then saves; once accepted lines were saved, a dry run must finish before the
 * next real run (dryRunRefusal(), checked by expImportRunner).
 *
 * Data minimisation: by default only names and detected types of the source's fields go to the model, never a value.
 * Sample values only when the import opts in (the option "Send sample values to the AI", behaviour ai_samples, off by
 * default), and then masked here first: e-mail addresses, IBAN-like and phone-like values (mask()). Run summaries
 * use the run's own statistics, and messages are masked and stripped of quoted values.
 *
 * Shown only when [AiSettings] Enabled=enabled in sqliimport.ini, expai is installed and the transport accepts the
 * user (refusal()). Every answer is checked here: malformed answers are rejected (parseLines()), and suggestions that
 * name an attribute, a field, a class or a transform that does not exist are dropped. Answers are kept for the
 * session per import or run, so a page that is opened again does not ask again.
 *
 * The functions without "for" in their name are pure (no database, no session) and unit tested with a transport
 * double (expImportAi::$transport).
 */
class expImportAi
{
    const PURPOSES = array( 'mapping', 'class', 'explain', 'summary' );

    /** The session variable of the cached answers */
    const SESSION = 'SQLIImportAi';

    /** @var expImportAiTransport|null tests: the transport instead of expai */
    public static $transport = null;

    /** @var array|null the cache when there is no session (tests, the command line) */
    protected static $memory = null;

    // ---- availability --------------------------------------------------------------------------------------------

    /** @return bool [AiSettings] Enabled=enabled */
    public static function enabled()
    {
        return (string)expImport::setting( 'AiSettings', 'Enabled', 'disabled' ) === 'enabled';
    }

    /** @return expImportAiTransport|null the transport, null when expai is not installed */
    public static function transport()
    {
        if ( self::$transport !== null )
            return self::$transport;
        return expImportAiExpaiTransport::installed() ? new expImportAiExpaiTransport() : null;
    }

    /**
     * Why the assist is not offered here ('' when it is).
     * @param array $context purpose, import_id, ...
     * @return string
     */
    public static function refusal( array $context = array() )
    {
        if ( !self::enabled() )
            return 'The AI assist is switched off ([AiSettings] Enabled).';
        $transport = self::transport();
        if ( !$transport )
            return 'Exponential Intelligence (expai) is not installed.';
        return (string)$transport->refusal( $context );
    }

    /** @return bool the assist is offered (buttons shown) */
    public static function available( array $context = array() )
    {
        return self::refusal( $context ) === '';
    }

    /** @return bool the import sends masked sample values (behaviour ai_samples, off by default) */
    public static function sendsSamples( array $behaviour )
    {
        return !empty( $behaviour['ai_samples'] );
    }

    // ---- data minimisation (pure) ----------------------------------------------------------------------------------

    /**
     * A value with e-mail addresses, IBAN-like and phone-like values masked: [email], [iban], [phone]. Long digit runs
     * (seven digits or more, with spaces, dots, dashes, slashes or brackets between them) count as phone-like, except
     * a date or a time; a card or account number is masked with them.
     *
     * @param mixed $value
     * @return string
     */
    public static function mask( $value )
    {
        $text = is_scalar( $value ) ? (string)$value : (string)json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $text = (string)preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email]', $text );
        $text = (string)preg_replace( '/\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]){11,30}\b/u', '[iban]', $text );
        $text = (string)preg_replace_callback( '/(?<![\w\[])\+?\(?\d[\d ()\/.\-]{5,}\d(?![\w\]])/u', function ( $m )
        {
            $digits = preg_replace( '/\D/', '', $m[0] );
            if ( strlen( $digits ) < 7 )
                return $m[0];
            // a date or a time (2024-01-15, 15.01.2024, 2024-01-15 10:30) is no phone number
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}(?::\d{2}){0,2})?$/', trim( $m[0] ) ) || preg_match( '/^\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4}$/', trim( $m[0] ) ) )
                return $m[0];
            return '[phone]';
        }, $text );
        return $text;
    }

    /** @return string a message with masked values and its quoted parts left out (for summaries: no record values) */
    public static function maskMessage( $message )
    {
        $text = self::mask( $message );
        $text = (string)preg_replace( '/"[^"]{0,200}"|\'[^\']{0,200}\'|«[^»]{0,200}»|„[^“"]{0,200}[“"]/u', '"…"', $text );
        return self::cut( self::oneLine( $text ), 240 );
    }

    /**
     * The type of a field from its sample values: empty, integer, number, boolean, date, datetime, email, url, html,
     * list, text.
     *
     * @param array $values
     * @return string
     */
    public static function detectType( array $values )
    {
        $kinds = array();
        foreach ( $values as $value )
        {
            if ( is_array( $value ) )
            {
                $kinds['list'] = true;
                continue;
            }
            $v = trim( (string)$value );
            if ( $v === '' )
                continue;
            if ( preg_match( '/^-?\d+$/', $v ) )
                $kinds['integer'] = true;
            elseif ( is_numeric( str_replace( ',', '.', $v ) ) )
                $kinds['number'] = true;
            elseif ( in_array( strtolower( $v ), array( 'true', 'false', 'yes', 'no', 'ja', 'nein', 'y', 'n' ), true ) )
                $kinds['boolean'] = true;
            elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$|^\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4}$/', $v ) )
                $kinds['date'] = true;
            elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $v ) )
                $kinds['datetime'] = true;
            elseif ( preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $v ) )
                $kinds['email'] = true;
            elseif ( preg_match( '#^https?://#i', $v ) )
                $kinds['url'] = true;
            elseif ( preg_match( '/<[a-z][^>]*>/i', $v ) )
                $kinds['html'] = true;
            else
                $kinds['text'] = true;
        }
        if ( !$kinds )
            return 'empty';
        if ( count( $kinds ) === 1 )
            return (string)key( $kinds );
        if ( count( $kinds ) === 2 && isset( $kinds['integer'], $kinds['number'] ) )
            return 'number';
        if ( count( $kinds ) === 2 && isset( $kinds['date'], $kinds['datetime'] ) )
            return 'datetime';
        return 'text';
    }

    /**
     * What the model learns of the source's fields: name and detected type, and only with $withSamples up to
     * [AiSettings] SampleValues masked, shortened sample values each.
     *
     * @param array $sample fields (paths), records (list of array( flat => path => value ))
     * @param bool $withSamples
     * @return array list of array( name, type[, samples] )
     */
    public static function fieldProfiles( array $sample, $withSamples )
    {
        $max = max( 1, min( 5, (int)expImport::setting( 'AiSettings', 'SampleValues', 3 ) ) );
        $fields = array();
        foreach ( isset( $sample['fields'] ) ? (array)$sample['fields'] : array() as $f )
            if ( is_scalar( $f ) && (string)$f !== '' )
                $fields[(string)$f] = true;
        foreach ( isset( $sample['records'] ) ? (array)$sample['records'] : array() as $record )
            foreach ( isset( $record['flat'] ) && is_array( $record['flat'] ) ? array_keys( $record['flat'] ) : array() as $f )
                $fields[(string)$f] = true;
        $out = array();
        foreach ( array_slice( array_keys( $fields ), 0, max( 1, (int)expImport::setting( 'AiSettings', 'MaxFields', 150 ) ) ) as $name )
        {
            $values = array();
            foreach ( isset( $sample['records'] ) ? (array)$sample['records'] : array() as $record )
                if ( isset( $record['flat'][$name] ) )
                    $values[] = $record['flat'][$name];
            $profile = array( 'name' => self::cut( (string)$name, 120 ), 'type' => self::detectType( $values ) );
            if ( $withSamples )
            {
                $samples = array();
                foreach ( $values as $value )
                {
                    $value = self::cut( self::oneLine( self::mask( $value ) ), 80 );
                    if ( $value !== '' && !in_array( $value, $samples, true ) )
                        $samples[] = $value;
                    if ( count( $samples ) >= $max )
                        break;
                }
                $profile['samples'] = $samples;
            }
            $out[] = $profile;
        }
        return $out;
    }

    // ---- requests (pure) -----------------------------------------------------------------------------------------

    /** @return string the fields as the model reads them, one per line */
    protected static function fieldLines( array $profiles )
    {
        $lines = array();
        foreach ( $profiles as $p )
            $lines[] = json_encode( $p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return implode( "\n", $lines );
    }

    /**
     * The request of the mapping suggestions.
     *
     * @param array $attributes list of array( identifier, name, datatype )
     * @param array $profiles fieldProfiles()
     * @param array $transforms the names of the transforms there are
     * @return array system, user, max_tokens
     */
    public static function mappingRequest( array $attributes, array $profiles, array $transforms )
    {
        $attr = array();
        foreach ( $attributes as $a )
            $attr[] = json_encode( array( 'identifier' => (string)$a['identifier'], 'name' => (string)$a['name'], 'datatype' => (string)$a['datatype'] ),
                                   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return array(
            'system' => 'You suggest how the fields of a data source map to the attributes of a content class of a content '
                      . 'management system. Answer with JSON Lines only: one JSON object per line, no other text, no code fence. '
                      . 'Each object: {"attribute": <attribute identifier>, "source": <source field name exactly as given>, '
                      . '"transforms": <a transform chain in one line, steps separated by " | ", arguments in parentheses as '
                      . 'name=value, e.g. "trim | date(format=d.m.Y)", or "">, "confidence": <number 0 to 1>, "reason": <at most 15 words>}. '
                      . 'Only attributes and fields from the lists, only these transforms: ' . implode( ', ', $transforms ) . '. '
                      . 'At most one line per attribute; leave out attributes no field fits.',
            'user' => "Attributes of the class:\n" . implode( "\n", $attr ) . "\n\nFields of the source:\n" . self::fieldLines( $profiles ),
            'max_tokens' => 2000,
        );
    }

    /**
     * The request of the class suggestions.
     *
     * @param array $candidates identifier => name (content classes, or the data tables)
     * @param array $profiles fieldProfiles()
     * @param string $kind content | datatable
     * @return array
     */
    public static function classRequest( array $candidates, array $profiles, $kind = 'content' )
    {
        $list = array();
        foreach ( array_slice( $candidates, 0, 300, true ) as $id => $name )
            $list[] = json_encode( array( 'identifier' => (string)$id, 'name' => (string)$name ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $what = $kind === 'datatable' ? 'data table (a persistent class with a database table)' : 'content class';
        return array(
            'system' => "You choose the $what that the records of a data source are best imported into, from the field names "
                      . 'and types of the source. Answer with JSON Lines only: one JSON object per line, no other text, at most '
                      . 'three lines, the best first. Each object: {"class": <identifier from the list>, "confidence": <number 0 to 1>, '
                      . '"reason": <at most 20 words>}.',
            'user' => 'Candidates (' . $what . "):\n" . implode( "\n", $list ) . "\n\nFields of the source:\n" . self::fieldLines( $profiles ),
            'max_tokens' => 600,
        );
    }

    /**
     * The request of the explanation of a failed row.
     *
     * @param string $message the row's message
     * @param array $lines the mapping lines concerned: list of array( attribute, source, transforms, required )
     * @param array|null $values field => value of the record (only when the import opts in; masked here)
     * @return array
     */
    public static function explainRequest( $message, array $lines, ?array $values = null )
    {
        $text = 'Error message: ' . self::cut( self::oneLine( self::mask( $message ) ), 600 ) . "\n\nMapping lines (attribute <- source | transforms):\n";
        foreach ( array_slice( $lines, 0, 60 ) as $l )
            $text .= '- ' . $l['attribute'] . ' <- ' . ( $l['source'] !== '' ? $l['source'] : '(no source)' ) . ( $l['transforms'] !== '' ? ' | ' . $l['transforms'] : '' )
                   . ( !empty( $l['required'] ) ? ' (required)' : '' ) . "\n";
        if ( !$lines )
            $text .= "(none)\n";
        if ( $values !== null )
        {
            $text .= "\nValues of the record (masked):\n";
            foreach ( array_slice( $values, 0, 40, true ) as $field => $value )
                $text .= '- ' . self::cut( (string)$field, 120 ) . ': ' . self::cut( self::oneLine( self::mask( $value ) ), 120 ) . "\n";
        }
        return array(
            'system' => 'A record of a content import failed. Explain in two sentences at most what went wrong, for an editor, and '
                      . 'suggest one concrete fix of the mapping (a transform of the one-line chain, a default, another source '
                      . 'field) or of the source. Answer with one JSON object on one line and nothing else: '
                      . '{"explanation": <text>, "fix": <text>}.',
            'user' => $text,
            'max_tokens' => 500,
        );
    }

    /**
     * The request of the summary of a run.
     *
     * @param array $stats summaryStats()
     * @return array
     */
    public static function summaryRequest( array $stats )
    {
        return array(
            'system' => 'You summarise a finished run of a content import for an editor in three sentences at most: what it did, '
                      . 'what went wrong and what to look at next. Only use the figures given. Answer with one JSON object on '
                      . 'one line and nothing else: {"summary": <text>}.',
            'user' => "Statistics of the run:\n" . json_encode( $stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
            'max_tokens' => 500,
        );
    }

    /**
     * The statistics of a run a summary is made from: no record values, messages masked.
     *
     * @param array $run expImportAdmin::runRow()
     * @param string[] $failedMessages the messages of failed rows
     * @param array $changedFields field => count of the updated rows' changed fields
     * @return array
     */
    public static function summaryStats( array $run, array $failedMessages, array $changedFields = array() )
    {
        $messages = array();
        foreach ( $failedMessages as $m )
            $messages[] = self::maskMessage( $m );
        $top = array();
        foreach ( expImportStatistics::topMessages( $messages, 5 ) as $t )
            $top[] = array( 'message' => $t['message'], 'count' => $t['count'] );
        arsort( $changedFields );
        $changed = array();
        foreach ( array_slice( $changedFields, 0, 10, true ) as $field => $n )
            $changed[self::cut( (string)$field, 80 )] = (int)$n;
        $duration = isset( $run['started_at'], $run['finished_at'] ) && $run['finished_at'] > $run['started_at'] ? (int)$run['finished_at'] - (int)$run['started_at'] : 0;
        $done = 0;
        foreach ( array( 'created', 'updated', 'unchanged', 'skipped', 'failed', 'processed' ) as $k )
            $done += isset( $run[$k] ) ? (int)$run[$k] : 0;
        $stats = array(
            'status' => isset( $run['status'] ) ? (string)$run['status'] : '',
            'kind' => isset( $run['kind'] ) ? (string)$run['kind'] : '',
            'trigger' => isset( $run['trigger'] ) ? (string)$run['trigger'] : '',
            'total' => isset( $run['total'] ) ? (int)$run['total'] : 0,
            'duration_seconds' => $duration,
            'wait_seconds' => isset( $run['queued_at'], $run['started_at'] ) && $run['started_at'] > $run['queued_at'] ? (int)$run['started_at'] - (int)$run['queued_at'] : 0,
            'ms_per_record' => $done > 0 && $duration > 0 ? (int)round( $duration * 1000 / $done ) : 0,
            'memory_peak_mb' => isset( $run['memory'] ) ? (float)$run['memory'] : 0,
            'top_errors' => $top,
            'changed_fields' => $changed,
        );
        foreach ( array( 'created', 'updated', 'unchanged', 'skipped', 'failed', 'processed', 'undone' ) as $k )
            $stats['counts'][$k] = isset( $run[$k] ) ? (int)$run[$k] : 0;
        return $stats;
    }

    // ---- answers (pure) ------------------------------------------------------------------------------------------

    /**
     * Reads an answer of JSON Lines: every line that is not empty is one JSON object. A code fence around the lines is
     * allowed; anything else (prose, a line that is not an object, an answer too long) rejects the whole answer.
     *
     * @param string $text
     * @param int $max the most lines
     * @return array list of arrays
     * @throws expImportAiException
     */
    public static function parseLines( $text, $max = 400 )
    {
        $text = trim( (string)$text );
        if ( $text === '' )
            throw new expImportAiException( 'The model gave no answer.' );
        if ( strlen( $text ) > 200000 )
            throw new expImportAiException( 'The answer of the model is too long.' );
        $text = (string)preg_replace( '/^```[a-z]*\s*\n|\n?```\s*$/i', '', $text );
        $out = array();
        foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line )
        {
            $line = trim( $line );
            if ( $line === '' )
                continue;
            $data = json_decode( $line, true );
            if ( !is_array( $data ) || $data === array() || array_keys( $data ) === range( 0, count( $data ) - 1 ) )
                throw new expImportAiException( 'The answer of the model could not be read.' );
            $out[] = $data;
            if ( count( $out ) > $max )
                throw new expImportAiException( 'The answer of the model is too long.' );
        }
        if ( !$out )
            throw new expImportAiException( 'The model gave no answer.' );
        return $out;
    }

    /** @return float|null a confidence 0 to 1, null when it is none */
    protected static function confidence( $value )
    {
        if ( !is_int( $value ) && !is_float( $value ) && !( is_string( $value ) && is_numeric( $value ) ) )
            return null;
        $c = (float)$value;
        return $c < 0 || $c > 1 ? null : round( $c, 2 );
    }

    /**
     * The mapping suggestions an answer makes that hold: the attribute is one of the class, the source one of the
     * fields, the transforms a chain of known transforms with valid arguments. Every other line is dropped; per
     * attribute the line with the highest confidence counts.
     *
     * @param array $lines parseLines()
     * @param array $attributes identifier => datatype
     * @param string[] $fields the source fields
     * @return array lines (attribute => array( attribute, source, transforms (text), confidence, reason )), dropped (int)
     */
    public static function validateMapping( array $lines, array $attributes, array $fields )
    {
        $fieldSet = array_flip( array_map( 'strval', $fields ) );
        $out = array();
        $dropped = 0;
        foreach ( $lines as $line )
        {
            $attribute = isset( $line['attribute'] ) && is_string( $line['attribute'] ) ? $line['attribute'] : '';
            $source = isset( $line['source'] ) && is_string( $line['source'] ) ? $line['source'] : '';
            $confidence = self::confidence( isset( $line['confidence'] ) ? $line['confidence'] : null );
            $text = isset( $line['transforms'] ) && is_string( $line['transforms'] ) ? trim( $line['transforms'] ) : ( !isset( $line['transforms'] ) ? '' : null );
            if ( !isset( $attributes[$attribute] ) || !isset( $fieldSet[$source] ) || $confidence === null || $text === null || strlen( $text ) > 500 )
            {
                $dropped++;
                continue;
            }
            try
            {
                $chain = expImportTransforms::parseChain( $text );
            }
            catch ( expImportValidationException $e )
            {
                $dropped++;
                continue;
            }
            if ( expImportTransforms::validateChain( $chain ) )
            {
                $dropped++;
                continue;
            }
            if ( isset( $out[$attribute] ) )
            {
                $dropped++;
                if ( $out[$attribute]['confidence'] >= $confidence )
                    continue;
            }
            $out[$attribute] = array( 'attribute' => $attribute, 'source' => $source, 'transforms' => expImportTransforms::formatChain( $chain ),
                                      'confidence' => $confidence, 'reason' => self::reason( isset( $line['reason'] ) ? $line['reason'] : '' ) );
        }
        return array( 'lines' => $out, 'dropped' => $dropped );
    }

    /**
     * The class suggestions that hold: a candidate, with a confidence; the best three.
     *
     * @param array $lines parseLines()
     * @param array $candidates identifier => name
     * @return array list of array( identifier, name, confidence, reason ), dropped
     */
    public static function validateClasses( array $lines, array $candidates )
    {
        $out = array();
        $dropped = 0;
        foreach ( $lines as $line )
        {
            $id = isset( $line['class'] ) && is_string( $line['class'] ) ? $line['class'] : '';
            $confidence = self::confidence( isset( $line['confidence'] ) ? $line['confidence'] : null );
            if ( $id === '' || !isset( $candidates[$id] ) || $confidence === null || isset( $out[$id] ) )
            {
                $dropped++;
                continue;
            }
            $out[$id] = array( 'identifier' => $id, 'name' => (string)$candidates[$id], 'confidence' => $confidence,
                               'reason' => self::reason( isset( $line['reason'] ) ? $line['reason'] : '' ) );
        }
        uasort( $out, function ( $a, $b ) { return $b['confidence'] <=> $a['confidence']; } );
        return array( 'classes' => array_slice( array_values( $out ), 0, 3 ), 'dropped' => $dropped );
    }

    /**
     * The one object of an answer with the given text keys.
     *
     * @param string $text
     * @param string[] $keys required keys (strings)
     * @return array key => text (one line, shortened)
     * @throws expImportAiException
     */
    public static function parseObject( $text, array $keys )
    {
        $lines = self::parseLines( $text, 1 );
        if ( count( $lines ) !== 1 )
            throw new expImportAiException( 'The answer of the model could not be read.' );
        $out = array();
        foreach ( $keys as $key )
        {
            if ( !isset( $lines[0][$key] ) || !is_string( $lines[0][$key] ) || trim( $lines[0][$key] ) === '' )
                throw new expImportAiException( 'The answer of the model could not be read.' );
            $out[$key] = self::cut( self::oneLine( strip_tags( $lines[0][$key] ) ), 700 );
        }
        return $out;
    }

    /**
     * Accepts suggestions into the lines of the form (the mapping as posted): the source and the transforms of each
     * accepted attribute are replaced, nothing else; nothing is stored.
     *
     * @param array $mapping the mapping in the form's shape (expImportEditor::mappingFromPost())
     * @param array $suggestions attribute => suggestion (validateMapping()['lines'])
     * @param string[] $attributes the attributes to accept (empty: all)
     * @return array the mapping and the accepted attributes (string[])
     */
    public static function accept( array $mapping, array $suggestions, array $attributes = array() )
    {
        $want = $attributes ? array_flip( array_map( 'strval', $attributes ) ) : null;
        $accepted = array();
        $fields = isset( $mapping['fields'] ) ? (array)$mapping['fields'] : array();
        foreach ( $suggestions as $attribute => $s )
        {
            if ( $want !== null && !isset( $want[$attribute] ) )
                continue;
            $done = false;
            foreach ( $fields as $i => $field )
                if ( $field['attribute'] === $attribute && (string)( isset( $field['language'] ) ? $field['language'] : '' ) === '' )
                {
                    $fields[$i]['source'] = $s['source'];
                    $fields[$i]['transforms_text'] = $s['transforms'];
                    $fields[$i]['transforms'] = array();
                    $done = true;
                }
            if ( !$done )
                $fields[] = array( 'source' => $s['source'], 'attribute' => (string)$attribute, 'transforms_text' => $s['transforms'], 'transforms' => array(),
                                   'default' => '', 'required' => false, 'language' => '' );
            $accepted[] = (string)$attribute;
        }
        $mapping['fields'] = $fields;
        return array( $mapping, $accepted );
    }

    /**
     * Whether a real run must wait for a dry run: accepted suggestions were saved in version $from of the import
     * (behaviour ai_dry_run_from), and no dry run of that version or a later one has finished.
     *
     * @param array $behaviour
     * @param int[] $dryRunVersions the import versions of the finished dry runs
     * @return bool
     */
    public static function needsDryRun( array $behaviour, array $dryRunVersions )
    {
        $from = isset( $behaviour['ai_dry_run_from'] ) ? (int)$behaviour['ai_dry_run_from'] : 0;
        if ( $from <= 0 )
            return false;
        foreach ( $dryRunVersions as $v )
            if ( (int)$v >= $from )
                return false;
        return true;
    }

    /** @return string a reason of the model: one line, no markup, at most 200 characters */
    protected static function reason( $value )
    {
        return is_string( $value ) ? self::cut( self::oneLine( strip_tags( $value ) ), 200 ) : '';
    }

    /** @return string one line */
    protected static function oneLine( $text )
    {
        return trim( (string)preg_replace( '/\s+/u', ' ', (string)$text ) );
    }

    /** @return string at most $max characters */
    protected static function cut( $text, $max )
    {
        $text = (string)$text;
        return mb_strlen( $text, 'UTF-8' ) > $max ? rtrim( mb_substr( $text, 0, $max - 1, 'UTF-8' ) ) . '…' : $text;
    }

    // ---- the features (pure but for the transport) ----------------------------------------------------------------

    /**
     * Asks for mapping suggestions.
     *
     * @param array $attributes list of array( identifier, name, datatype )
     * @param array $sample fields, records (flat)
     * @param bool $withSamples
     * @param array $context
     * @return array lines, dropped, model, samples (bool: values were sent)
     * @throws expImportAiException
     */
    public static function suggestMapping( array $attributes, array $sample, $withSamples, array $context )
    {
        $profiles = self::fieldProfiles( $sample, $withSamples );
        if ( !$attributes || !$profiles )
            throw new expImportAiException( 'There are no fields or no attributes to match.' );
        $answer = self::send( self::mappingRequest( $attributes, $profiles, array_keys( expImportTransforms::catalogue() ) ), array( 'purpose' => 'mapping' ) + $context );
        $types = array();
        foreach ( $attributes as $a )
            $types[(string)$a['identifier']] = (string)$a['datatype'];
        $fields = array();
        foreach ( $profiles as $p )
            $fields[] = $p['name'];
        return self::validateMapping( self::parseLines( $answer['text'] ), $types, $fields ) + array( 'model' => $answer['model'], 'samples' => (bool)$withSamples );
    }

    /**
     * Asks for the class.
     * @return array classes, dropped, model, samples
     * @throws expImportAiException
     */
    public static function suggestClass( array $candidates, array $sample, $withSamples, $kind, array $context )
    {
        $profiles = self::fieldProfiles( $sample, $withSamples );
        if ( !$candidates || !$profiles )
            throw new expImportAiException( 'There are no fields or no classes to choose from.' );
        $answer = self::send( self::classRequest( $candidates, $profiles, $kind ), array( 'purpose' => 'class' ) + $context );
        return self::validateClasses( self::parseLines( $answer['text'], 20 ), $candidates ) + array( 'model' => $answer['model'], 'samples' => (bool)$withSamples );
    }

    /**
     * Asks for the explanation of a failed row.
     * @return array explanation, fix, model, samples
     * @throws expImportAiException
     */
    public static function explain( $message, array $lines, ?array $values, array $context )
    {
        $answer = self::send( self::explainRequest( $message, $lines, $values ), array( 'purpose' => 'explain' ) + $context );
        return self::parseObject( $answer['text'], array( 'explanation', 'fix' ) ) + array( 'model' => $answer['model'], 'samples' => $values !== null );
    }

    /**
     * Asks for the summary of a run.
     * @return array summary, model
     * @throws expImportAiException
     */
    public static function summarize( array $stats, array $context )
    {
        $answer = self::send( self::summaryRequest( $stats ), array( 'purpose' => 'summary' ) + $context );
        return self::parseObject( $answer['text'], array( 'summary' ) ) + array( 'model' => $answer['model'] );
    }

    /** @return array the answer of the transport */
    protected static function send( array $request, array $context )
    {
        if ( !self::enabled() )
            throw new expImportAiException( 'The AI assist is switched off ([AiSettings] Enabled).' );
        $transport = self::transport();
        if ( !$transport )
            throw new expImportAiException( 'Exponential Intelligence (expai) is not installed.' );
        $answer = $transport->send( $request, $context );
        return array( 'text' => isset( $answer['text'] ) ? (string)$answer['text'] : '', 'model' => isset( $answer['model'] ) ? (string)$answer['model'] : '' );
    }

    // ---- the session cache -----------------------------------------------------------------------------------------

    /** @return mixed the answer kept under $key, null when there is none */
    public static function cached( $key )
    {
        $all = self::cacheAll();
        return isset( $all[$key] ) ? $all[$key] : null;
    }

    /** Keeps an answer for the session (the last 40). */
    public static function remember( $key, $value )
    {
        $all = self::cacheAll();
        unset( $all[$key] );
        $all[$key] = $value;
        $all = array_slice( $all, -40, null, true );
        if ( self::hasSession() )
            eZHTTPTool::instance()->setSessionVariable( self::SESSION, $all );
        else
            self::$memory = $all;
    }

    /** @var bool|null tests: false keeps the answers in memory although a kernel is loaded */
    public static $useSession = null;

    /** @return bool the answers are kept in the user's session (in an installation) */
    protected static function hasSession()
    {
        if ( self::$useSession !== null )
            return self::$useSession;
        return class_exists( 'eZHTTPTool' ) && class_exists( 'eZSession' );
    }

    /** Forgets every kept answer (tests). */
    public static function forget()
    {
        self::$memory = null;
    }

    /** @return array */
    protected static function cacheAll()
    {
        if ( self::hasSession() )
        {
            $http = eZHTTPTool::instance();
            $all = $http->hasSessionVariable( self::SESSION ) ? $http->sessionVariable( self::SESSION ) : array();
            return is_array( $all ) ? $all : array();
        }
        return self::$memory !== null ? self::$memory : array();
    }

    /** @return string the cache key of a feature for an import or run and what was asked */
    public static function key( $purpose, $id, array $what = array() )
    {
        return $purpose . ':' . (int)$id . ':' . substr( sha1( json_encode( $what ) ), 0, 16 );
    }

    // ---- the installation (database, session) ----------------------------------------------------------------------

    /**
     * The context of an import: its target's parent node and section (the section of the parent when the target
     * names none), the class.
     *
     * @return array
     */
    public static function contextFor( expImportDefinition $import, $classIdentifier = '' )
    {
        $target = $import->target();
        $section = (int)$target['section_id'];
        $node = (int)$target['parent_node_id'];
        if ( !$section && $node && class_exists( 'eZContentObjectTreeNode' ) )
        {
            $parent = eZContentObjectTreeNode::fetch( $node );
            if ( $parent instanceof eZContentObjectTreeNode && $parent->object() )
                $section = (int)$parent->object()->attribute( 'section_id' );
        }
        return array( 'import_id' => (int)$import->attribute( 'id' ), 'node_id' => $node, 'section_id' => $section, 'class_identifier' => (string)$classIdentifier );
    }

    /** @return array list of array( identifier, name, datatype ) of a content class */
    public static function attributesFor( $classIdentifier )
    {
        $class = $classIdentifier !== '' && class_exists( 'eZContentClass' ) ? eZContentClass::fetchByIdentifier( $classIdentifier ) : null;
        $out = array();
        if ( $class instanceof eZContentClass )
            foreach ( $class->fetchAttributes() as $a )
                $out[] = array( 'identifier' => (string)$a->attribute( 'identifier' ), 'name' => (string)$a->attribute( 'name' ), 'datatype' => (string)$a->attribute( 'data_type_string' ) );
        return $out;
    }

    /**
     * The refusal of a real run of an import whose accepted suggestions have not had a dry run yet ('' when it may run).
     * @return string
     */
    public static function dryRunRefusal( expImportDefinition $import )
    {
        $behaviour = $import->behaviour();
        if ( empty( $behaviour['ai_dry_run_from'] ) )
            return '';
        $versions = array();
        foreach ( expImportRun::fetchList( array( 'import_id' => (int)$import->attribute( 'id' ), 'run_kind' => 'dry_run',
                                                  'status' => array( array( expImportRun::STATUS_COMPLETED, expImportRun::STATUS_COMPLETED_WITH_ERRORS ) ),
                                                  'def_version' => array( '>=', (int)$behaviour['ai_dry_run_from'] ) ), 0, 1 ) as $run )
            $versions[] = (int)$run->attribute( 'def_version' );
        return self::needsDryRun( $behaviour, $versions )
            ? 'the mapping has lines suggested by the AI that were accepted: run a dry run of this version first'
            : '';
    }

    /**
     * The mapping lines a failed row's message concerns (all lines when it names none): no default values.
     *
     * @param array|null $mapping
     * @param string $message
     * @return array list of array( attribute, source, transforms, required )
     */
    public static function linesFor( $mapping, $message )
    {
        $all = array();
        $named = array();
        foreach ( is_array( $mapping ) && isset( $mapping['fields'] ) ? (array)$mapping['fields'] : array() as $f )
        {
            $line = array( 'attribute' => (string)$f['attribute'], 'source' => (string)$f['source'],
                           'transforms' => isset( $f['transforms'] ) ? expImportTransforms::formatChain( (array)$f['transforms'] ) : '', 'required' => !empty( $f['required'] ) );
            $all[] = $line;
            foreach ( array( $line['attribute'], $line['source'] ) as $word )
                if ( $word !== '' && preg_match( '/(?<![\w])' . preg_quote( $word, '/' ) . '(?![\w])/u', (string)$message ) )
                {
                    $named[] = $line;
                    break;
                }
        }
        return $named ? $named : $all;
    }
}
