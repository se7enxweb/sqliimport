<?php
/**
 * File containing the expImportStatistics class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * The statistics of the run history: the charts of the dashboard (sqliimport/list) and the Statistics page
 * (sqliimport/statistics) with its periods, filters, trends, per-import table, comparison of two periods, CSV export
 * and suggestions.
 *
 * Two layers:
 *  - pure functions that take plain arrays (runs as rows of sqliimport_run, failed-row messages as strings) and
 *    return plain arrays: periods and buckets in the site's time zone, percentiles, rates, deltas, the change point
 *    of a duration series, the most common error messages, the field a failing import stumbles on and the texts of
 *    the suggestions. They need no database and are covered by tests/unit/StatisticsTest.php.
 *  - thin loaders that read the run history with generic SQL (MySQL, PostgreSQL, SQLite and Oracle): every number
 *    is cast to int, every text is escaped, and a long text column (message) is never grouped on, because Oracle
 *    cannot group a CLOB. The runs of a period are read once (one narrow row per run, the run table is small next
 *    to the row table) and bucketed in PHP, so days follow the site's time zone and its daylight saving time.
 *
 * Production figures leave out dry runs and undo runs (kinds import and retry); a page can show them on request.
 */
class expImportStatistics
{
    /** The periods the page offers, in days */
    const PERIODS = array( 7, 30, 90, 365 );

    /** The longest custom period, in days */
    const MAX_DAYS = 3660;

    /** The run kinds of each scope */
    const SCOPES = array(
        'production' => array( 'import', 'retry', 'export' ),
        'dry_run' => array( 'dry_run' ),
        'all' => array( 'import', 'retry', 'dry_run', 'undo', 'export' ),
    );

    /** The row outcomes stacked in the record charts, bottom to top */
    const OUTCOMES = array( 'created', 'updated', 'unchanged', 'skipped', 'failed' );

    /** The counters of sqliimport_run that make the records processed of a run */
    const COUNTERS = array( 'created', 'updated', 'unchanged', 'skipped', 'failed', 'processed', 'undone' );

    /** The statuses a run ends with */
    const FINAL = array( 'completed', 'completed_with_errors', 'failed', 'cancelled' );

    /** The most runs and failed-row messages a page reads */
    const MAX_RUNS = 50000;
    const MAX_MESSAGES = 20000;

    // ---- periods and buckets (pure) ------------------------------------------------------------------------------

    /** @return DateTimeZone the zone of a name or object, UTC for anything unknown */
    public static function zone( $tz = null )
    {
        if ( $tz instanceof DateTimeZone )
            return $tz;
        try
        {
            return new DateTimeZone( $tz ? (string)$tz : date_default_timezone_get() );
        }
        catch ( Exception $e )
        {
            return new DateTimeZone( 'UTC' );
        }
    }

    /** @return int the start of the day of a date (Y-m-d) in a zone, 0 for a date that is not one */
    public static function dayStart( $date, $tz = null )
    {
        if ( !is_string( $date ) || !preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) || !checkdate( (int)$m[2], (int)$m[3], (int)$m[1] ) )
            return 0;
        $day = new DateTimeImmutable( $date . ' 00:00:00', self::zone( $tz ) );
        return $day->getTimestamp();
    }

    /** @return string the day (Y-m-d) of a time in a zone */
    public static function dayKey( $ts, $tz = null )
    {
        return ( new DateTimeImmutable( '@' . (int)$ts ) )->setTimezone( self::zone( $tz ) )->format( 'Y-m-d' );
    }

    /** @return int a day plus or minus whole calendar days (daylight saving time safe) */
    public static function addDays( $dayStart, $days, $tz = null )
    {
        $day = ( new DateTimeImmutable( '@' . (int)$dayStart ) )->setTimezone( self::zone( $tz ) )->setTime( 0, 0 );
        return $day->modify( ( $days >= 0 ? '+' : '' ) . (int)$days . ' days' )->getTimestamp();
    }

    /**
     * A period from the parameters of the page: the last N days (7, 30, 90, 365) up to today, or a custom from/to
     * (Y-m-d, both days included).
     *
     * @param array $params days, from, to
     * @param int $now
     * @param mixed $tz
     * @return array from (start, included), to (end, excluded), days, custom (bool), from_date, to_date
     */
    public static function period( array $params, $now, $tz = null )
    {
        $zone = self::zone( $tz );
        $today = self::dayStart( self::dayKey( $now, $zone ), $zone );
        $from = isset( $params['from'] ) ? self::dayStart( $params['from'], $zone ) : 0;
        $to = isset( $params['to'] ) ? self::dayStart( $params['to'], $zone ) : 0;
        if ( $from && $to && $from <= $to && self::dayCount( $from, self::addDays( $to, 1, $zone ), $zone ) <= self::MAX_DAYS )
        {
            $end = self::addDays( $to, 1, $zone );
            return self::periodArray( $from, $end, true, $zone );
        }
        $days = isset( $params['days'] ) && in_array( (int)$params['days'], self::PERIODS, true ) ? (int)$params['days'] : 30;
        $end = self::addDays( $today, 1, $zone );
        return self::periodArray( self::addDays( $end, -$days, $zone ), $end, false, $zone );
    }

    /** @return array the period just before a period, of the same number of days */
    public static function previousPeriod( array $period, $tz = null )
    {
        $zone = self::zone( $tz );
        return self::periodArray( self::addDays( $period['from'], -$period['days'], $zone ), $period['from'], $period['custom'], $zone );
    }

    /** @return int the calendar days between two day starts */
    public static function dayCount( $from, $to, $tz = null )
    {
        $zone = self::zone( $tz );
        $a = ( new DateTimeImmutable( '@' . (int)$from ) )->setTimezone( $zone )->setTime( 0, 0 );
        $b = ( new DateTimeImmutable( '@' . (int)$to ) )->setTimezone( $zone )->setTime( 0, 0 );
        return (int)$a->diff( $b )->format( '%r%a' );
    }

    protected static function periodArray( $from, $to, $custom, DateTimeZone $zone )
    {
        return array(
            'from' => (int)$from,
            'to' => (int)$to,
            'days' => self::dayCount( $from, $to, $zone ),
            'custom' => (bool)$custom,
            'from_date' => self::dayKey( $from, $zone ),
            'to_date' => self::dayKey( $to - 1, $zone ),
        );
    }

    /** @return string the bucket a chart of a period uses: day up to 62 days, week up to 200, then month */
    public static function unitFor( $days )
    {
        return $days <= 62 ? 'day' : ( $days <= 200 ? 'week' : 'month' );
    }

    /** @return string the key of the bucket of a time: its first day (Y-m-d), weeks start on Monday */
    public static function bucketKey( $ts, $unit, $tz = null )
    {
        $day = ( new DateTimeImmutable( '@' . (int)$ts ) )->setTimezone( self::zone( $tz ) )->setTime( 0, 0 );
        if ( $unit === 'week' )
            $day = $day->modify( '-' . ( (int)$day->format( 'N' ) - 1 ) . ' days' );
        elseif ( $unit === 'month' )
            $day = $day->modify( 'first day of this month' );
        return $day->format( 'Y-m-d' );
    }

    /**
     * The buckets of a period, oldest first.
     * @return array key => array( key, start (int), label (short, for an axis) )
     */
    public static function buckets( $from, $to, $unit, $tz = null )
    {
        $zone = self::zone( $tz );
        $out = array();
        $key = self::bucketKey( $from, $unit, $zone );
        $step = $unit === 'week' ? '+7 days' : ( $unit === 'month' ? 'first day of next month' : '+1 day' );
        for ( $day = new DateTimeImmutable( $key . ' 00:00:00', $zone ); $day->getTimestamp() < $to; $day = $day->modify( $step ) )
        {
            $k = $day->format( 'Y-m-d' );
            $out[$k] = array( 'key' => $k, 'start' => $day->getTimestamp(), 'label' => $unit === 'month' ? $day->format( 'n/Y' ) : $day->format( 'n/j' ) );
            if ( count( $out ) > 400 )
                break;
        }
        return $out;
    }

    // ---- numbers (pure) ------------------------------------------------------------------------------------------

    /**
     * A percentile with linear interpolation between the two nearest values (as spreadsheets do).
     *
     * @param array $values numbers
     * @param float $p 0..100
     * @return float|null null without values
     */
    public static function percentile( array $values, $p )
    {
        $values = array_values( array_map( 'floatval', $values ) );
        $n = count( $values );
        if ( !$n )
            return null;
        sort( $values );
        $pos = ( max( 0, min( 100, (float)$p ) ) / 100 ) * ( $n - 1 );
        $low = (int)floor( $pos );
        $high = (int)ceil( $pos );
        return $values[$low] + ( $values[$high] - $values[$low] ) * ( $pos - $low );
    }

    /** @return float|null the mean, null without values */
    public static function mean( array $values )
    {
        return $values ? array_sum( $values ) / count( $values ) : null;
    }

    /** @return float a share in percent with one decimal, 0 for an empty whole */
    public static function rate( $part, $whole )
    {
        return $whole > 0 ? round( 100 * $part / $whole, 1 ) : 0.0;
    }

    /**
     * The change from a previous value to the current one.
     *
     * @param float|int|null $current
     * @param float|int|null $previous
     * @param bool $higherIsBetter whether a rise is good (rows processed) or bad (failure rate, duration)
     * @return array current, previous, diff, pct (null when the previous value is 0 or unknown), direction (up, down,
     *               same), kind (good, bad, '')
     */
    public static function delta( $current, $previous, $higherIsBetter = true )
    {
        if ( $current === null || $previous === null )
            return array( 'current' => $current, 'previous' => $previous, 'diff' => null, 'pct' => null, 'direction' => 'same', 'kind' => '' );
        $diff = $current - $previous;
        $pct = $previous != 0 ? round( 100 * $diff / abs( $previous ), 1 ) : null;
        $direction = abs( $diff ) < 1e-9 ? 'same' : ( $diff > 0 ? 'up' : 'down' );
        $kind = $direction === 'same' ? '' : ( ( $direction === 'up' ) === (bool)$higherIsBetter ? 'good' : 'bad' );
        return array( 'current' => $current, 'previous' => $previous, 'diff' => round( $diff, 2 ), 'pct' => $pct, 'direction' => $direction, 'kind' => $kind );
    }

    /** @return int the class number of a value in a chart whose highest value is $max: 0..100, at least 1 when not 0 */
    public static function pct( $value, $max )
    {
        if ( $max <= 0 || $value <= 0 )
            return 0;
        return (int)max( 1, min( 100, round( 100 * $value / $max ) ) );
    }

    /** @return string a duration for a person: 0.4 s, 12 s, 3 min 12 s, 2 h 5 min */
    public static function formatDuration( $seconds )
    {
        if ( $seconds === null )
            return '–';
        $seconds = (float)$seconds;
        if ( $seconds < 10 )
            return number_format( $seconds, 1 ) . ' s';
        $seconds = (int)round( $seconds );
        if ( $seconds < 60 )
            return $seconds . ' s';
        if ( $seconds < 3600 )
            return intdiv( $seconds, 60 ) . ' min ' . ( $seconds % 60 ) . ' s';
        return intdiv( $seconds, 3600 ) . ' h ' . intdiv( $seconds % 3600, 60 ) . ' min';
    }

    // ---- runs (pure) ---------------------------------------------------------------------------------------------

    /** @return array a row of sqliimport_run with every value the statistics read, as numbers */
    public static function normalizeRun( array $row )
    {
        $row = array_change_key_case( $row, CASE_LOWER );
        $run = array(
            'id' => isset( $row['id'] ) ? (int)$row['id'] : 0,
            'import_id' => isset( $row['import_id'] ) ? (int)$row['import_id'] : 0,
            'user_id' => isset( $row['user_id'] ) ? (int)$row['user_id'] : 0,
            'run_kind' => isset( $row['run_kind'] ) ? (string)$row['run_kind'] : 'import',
            'status' => isset( $row['status'] ) ? (string)$row['status'] : '',
            'started_at' => isset( $row['started_at'] ) ? (int)$row['started_at'] : 0,
            'finished_at' => isset( $row['finished_at'] ) ? (int)$row['finished_at'] : 0,
            // the tool of the run: Imports, or another tool of the transfer history (its title and link with it)
            'tool' => isset( $row['tool'] ) && $row['tool'] !== '' ? (string)$row['tool'] : 'transfer.sqliimport',
            'title' => isset( $row['title'] ) ? (string)$row['title'] : '',
            'detail_url' => isset( $row['detail_url'] ) ? (string)$row['detail_url'] : '',
        );
        $rows = 0;
        foreach ( self::COUNTERS as $name )
        {
            $run[$name] = isset( $row[$name . '_count'] ) ? (int)$row[$name . '_count'] : ( isset( $row[$name] ) ? (int)$row[$name] : 0 );
            $rows += $run[$name];
        }
        $run['rows'] = $rows;
        $run['final'] = in_array( $run['status'], self::FINAL, true ) && $run['finished_at'] >= $run['started_at'] && $run['started_at'] > 0;
        $run['duration'] = $run['final'] ? $run['finished_at'] - $run['started_at'] : null;
        return $run;
    }

    /**
     * The figures of a set of runs.
     *
     * @param array $runs normalized runs (normalizeRun)
     * @return array runs, completed, with_errors, failed_runs, cancelled, running, success_rate, failed_run_rate,
     *               rows, the outcomes, failure_rate (failed rows of all rows), avg/p95/max duration (finished runs),
     *               rows_per_second, slowest (the run), duration_per_row
     */
    public static function summarize( array $runs )
    {
        $s = array( 'runs' => count( $runs ), 'completed' => 0, 'with_errors' => 0, 'failed_runs' => 0, 'cancelled' => 0, 'running' => 0, 'rows' => 0 );
        foreach ( self::COUNTERS as $name )
            $s[$name] = 0;
        $durations = array();
        $timedRows = 0;
        $slowest = null;
        foreach ( $runs as $run )
        {
            foreach ( self::COUNTERS as $name )
                $s[$name] += $run[$name];
            $s['rows'] += $run['rows'];
            switch ( $run['status'] )
            {
                case 'completed': $s['completed']++; break;
                case 'completed_with_errors': $s['with_errors']++; break;
                case 'failed': $s['failed_runs']++; break;
                case 'cancelled': $s['cancelled']++; break;
                default: $s['running']++;
            }
            if ( $run['duration'] !== null && $run['status'] !== 'cancelled' )
            {
                $durations[] = $run['duration'];
                $timedRows += $run['rows'];
                if ( !$slowest || $run['duration'] > $slowest['duration'] )
                    $slowest = $run;
            }
        }
        $ended = $s['completed'] + $s['with_errors'] + $s['failed_runs'];
        $s['ended'] = $ended;
        $s['success_rate'] = $ended ? self::rate( $s['completed'], $ended ) : null;
        $s['failed_run_rate'] = $ended ? self::rate( $s['with_errors'] + $s['failed_runs'], $ended ) : null;
        $s['failure_rate'] = $s['rows'] ? self::rate( $s['failed'], $s['rows'] ) : null;
        $s['avg_duration'] = self::mean( $durations );
        $s['p95_duration'] = self::percentile( $durations, 95 );
        $s['max_duration'] = $durations ? max( $durations ) : null;
        $total = array_sum( $durations );
        $s['rows_per_second'] = $total > 0 ? round( $timedRows / $total, 1 ) : null;
        $s['duration_per_row'] = $timedRows > 0 ? $total / $timedRows : null;
        $s['slowest'] = $slowest;
        return $s;
    }

    /**
     * Runs grouped into the buckets of a period.
     *
     * @param array $runs normalized runs
     * @param array $buckets buckets()
     * @param string $unit day, week, month
     * @return array key => runs
     */
    public static function group( array $runs, array $buckets, $unit, $tz = null )
    {
        $zone = self::zone( $tz );
        $out = array_fill_keys( array_keys( $buckets ), array() );
        foreach ( $runs as $run )
        {
            $key = self::bucketKey( $run['started_at'], $unit, $zone );
            if ( isset( $out[$key] ) )
                $out[$key][] = $run;
        }
        return $out;
    }

    /**
     * The data of a stacked bar chart: per bucket its segments with the class number of each height, scaled to the
     * highest total.
     *
     * @param array $buckets buckets()
     * @param array $values key => array( series => number )
     * @param string[] $series bottom to top
     * @return array bars (key, label, start, total, segments: list of array( name, value, pct )), max, sum (per series)
     */
    public static function stack( array $buckets, array $values, array $series )
    {
        $max = 0;
        $sum = array_fill_keys( $series, 0 );
        foreach ( $buckets as $key => $bucket )
        {
            $total = 0;
            foreach ( $series as $name )
            {
                $v = isset( $values[$key][$name] ) ? $values[$key][$name] : 0;
                $total += $v;
                $sum[$name] += $v;
            }
            $max = max( $max, $total );
        }
        $bars = array();
        foreach ( $buckets as $key => $bucket )
        {
            $segments = array();
            $total = 0;
            foreach ( $series as $name )
            {
                $v = isset( $values[$key][$name] ) ? $values[$key][$name] : 0;
                $total += $v;
                $segments[] = array( 'name' => $name, 'value' => $v, 'pct' => self::pct( $v, $max ) );
            }
            $bars[] = $bucket + array( 'total' => $total, 'segments' => $segments, 'pct' => self::pct( $total, $max ) );
        }
        return array( 'bars' => self::marks( $bars ), 'max' => $max, 'sum' => $sum, 'series' => $series );
    }

    /** @return array the bars with 'mark' set on about eight of them, the ones whose label the axis shows */
    public static function marks( array $bars, $labels = 8 )
    {
        $step = max( 1, (int)ceil( count( $bars ) / $labels ) );
        foreach ( array_keys( $bars ) as $i )
            $bars[$i]['mark'] = $i % $step === 0;
        return $bars;
    }

    /** @return array the class numbers of a sparkline (0..100) for a list of values */
    public static function sparkline( array $values )
    {
        $max = $values ? max( $values ) : 0;
        $out = array();
        foreach ( $values as $v )
            $out[] = self::pct( $v, $max );
        return $out;
    }

    /**
     * The trends of a period: per bucket the runs (ok, with errors, failed), the records by outcome, the average
     * duration and the failure rate of records.
     *
     * @param array $runs normalized runs
     * @return array runs, rows (stack()), duration, failure (bars with value and pct), unit
     */
    public static function trends( array $runs, array $buckets, $unit, $tz = null )
    {
        $grouped = self::group( $runs, $buckets, $unit, $tz );
        $runValues = $rowValues = array();
        $duration = $failure = array();
        foreach ( $grouped as $key => $list )
        {
            $s = self::summarize( $list );
            $runValues[$key] = array( 'ok' => $s['completed'], 'with_errors' => $s['with_errors'], 'failed' => $s['failed_runs'], 'other' => $s['cancelled'] + $s['running'] );
            $rowValues[$key] = array();
            foreach ( self::OUTCOMES as $name )
                $rowValues[$key][$name] = $s[$name] + ( $name === 'updated' ? $s['processed'] : 0 );
            $duration[$key] = $s['avg_duration'];
            $failure[$key] = $s['failure_rate'];
        }
        return array(
            'unit' => $unit,
            'runs' => self::stack( $buckets, $runValues, array( 'ok', 'with_errors', 'failed', 'other' ) ),
            'rows' => self::stack( $buckets, $rowValues, self::OUTCOMES ),
            'duration' => self::series( $buckets, $duration, 'duration' ),
            'failure' => self::series( $buckets, $failure, 'rate' ),
        );
    }

    /** @return array a single-series bar chart: bars (with value, text, pct), max */
    public static function series( array $buckets, array $values, $format = '' )
    {
        $numbers = array_filter( $values, function ( $v ) { return $v !== null; } );
        $max = $numbers ? max( $numbers ) : 0;
        $bars = array();
        foreach ( $buckets as $key => $bucket )
        {
            $v = isset( $values[$key] ) ? $values[$key] : null;
            $text = $v === null ? '–' : ( $format === 'duration' ? self::formatDuration( $v ) : ( $format === 'rate' ? number_format( $v, 1 ) . ' %' : (string)$v ) );
            $bars[] = $bucket + array( 'value' => $v, 'text' => $text, 'pct' => $v === null ? 0 : self::pct( $v, $max ) );
        }
        return array( 'bars' => self::marks( $bars ), 'max' => $max, 'max_text' => $format === 'duration' ? self::formatDuration( $max ) : ( $format === 'rate' ? number_format( $max, 1 ) . ' %' : (string)$max ) );
    }

    // ---- change point (pure) -------------------------------------------------------------------------------------

    /**
     * Where a series of measurements (time per record of the runs of an import, oldest first) stepped up for good:
     * the split into a before and an after with the least squared error, kept when the after is at least $factor
     * times the before, both sides have $minimum runs, and the step is clear of the noise (more than three standard
     * errors).
     *
     * @param array $points list of array( time, value ), oldest first
     * @return array|null since (the time of the first run after the step), before, after (means), factor, index
     */
    public static function changePoint( array $points, $factor = 1.5, $minimum = 4 )
    {
        $points = array_values( $points );
        $n = count( $points );
        if ( $n < 2 * $minimum )
            return null;
        $values = array();
        foreach ( $points as $p )
            $values[] = (float)$p[1];
        $best = null;
        for ( $k = $minimum; $k <= $n - $minimum; $k++ )
        {
            $a = array_slice( $values, 0, $k );
            $b = array_slice( $values, $k );
            $cost = self::sse( $a ) + self::sse( $b );
            if ( $best === null || $cost < $best['cost'] )
                $best = array( 'k' => $k, 'cost' => $cost );
        }
        $a = array_slice( $values, 0, $best['k'] );
        $b = array_slice( $values, $best['k'] );
        $before = self::mean( $a );
        $after = self::mean( $b );
        if ( $before <= 0 || $after < $before * $factor )
            return null;
        $se = sqrt( self::variance( $a ) / count( $a ) + self::variance( $b ) / count( $b ) );
        if ( $se > 0 && ( $after - $before ) < 3 * $se )
            return null;
        return array( 'since' => (int)$points[$best['k']][0], 'before' => $before, 'after' => $after, 'factor' => round( $after / $before, 1 ), 'index' => $best['k'] );
    }

    protected static function sse( array $values )
    {
        $m = self::mean( $values );
        $sum = 0.0;
        foreach ( $values as $v )
            $sum += ( $v - $m ) * ( $v - $m );
        return $sum;
    }

    protected static function variance( array $values )
    {
        $n = count( $values );
        return $n > 1 ? self::sse( $values ) / ( $n - 1 ) : 0.0;
    }

    /** @return array the time per record of the finished runs of a list, as change-point input */
    public static function durationPerRow( array $runs )
    {
        $points = array();
        foreach ( $runs as $run )
            if ( $run['duration'] !== null && $run['rows'] > 0 && $run['status'] !== 'cancelled' )
                $points[] = array( $run['started_at'], $run['duration'] / $run['rows'] );
        usort( $points, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
        return $points;
    }

    // ---- messages (pure) -----------------------------------------------------------------------------------------

    /** @return string an error message with its numbers and identifiers folded, so the same error counts once */
    public static function normalizeMessage( $message )
    {
        $text = trim( preg_replace( '/\s+/u', ' ', (string)$message ) );
        // record numbers, IDs, sizes and times differ from row to row
        $text = preg_replace( '/(?<![\w\'"])-?\d+(?:[.,:]\d+)*(?![\w\'"])/u', '#', $text );
        if ( mb_strlen( $text ) > 200 )
            $text = mb_substr( $text, 0, 199 ) . '…';
        return $text;
    }

    /**
     * The most common messages.
     *
     * @param string[] $messages
     * @return array list of array( message, count, share (percent of the messages) ), most common first
     */
    public static function topMessages( array $messages, $limit = 3 )
    {
        $counts = array();
        foreach ( $messages as $message )
        {
            $key = self::normalizeMessage( $message );
            if ( $key === '' )
                continue;
            $counts[$key] = ( isset( $counts[$key] ) ? $counts[$key] : 0 ) + 1;
        }
        $total = array_sum( $counts );
        uksort( $counts, function ( $a, $b ) use ( $counts ) { return $counts[$b] <=> $counts[$a] ?: strcmp( $a, $b ); } );
        $out = array();
        foreach ( array_slice( $counts, 0, $limit, true ) as $message => $count )
            $out[] = array( 'message' => (string)$message, 'count' => $count, 'share' => self::rate( $count, $total ) );
        return $out;
    }

    /** @return string the field, attribute or column a message names ('' when none) */
    public static function fieldOf( $message )
    {
        if ( preg_match( '/\b(?:attribute|field|column)\s+[\'"‘“]([^\'"’”]{1,80})[\'"’”]/iu', (string)$message, $m ) )
            return $m[1];
        return '';
    }

    /**
     * The field failed records of an import name most often.
     *
     * @param string[] $messages the messages of the failed records
     * @return array|null field, count
     */
    public static function failingField( array $messages )
    {
        $counts = array();
        foreach ( $messages as $message )
        {
            $field = self::fieldOf( $message );
            if ( $field !== '' )
                $counts[$field] = ( isset( $counts[$field] ) ? $counts[$field] : 0 ) + 1;
        }
        if ( !$counts )
            return null;
        uksort( $counts, function ( $a, $b ) use ( $counts ) { return $counts[$b] <=> $counts[$a] ?: strcmp( $a, $b ); } );
        $field = (string)array_key_first( $counts );
        return array( 'field' => $field, 'count' => $counts[$field] );
    }

    // ---- per import and suggestions (pure) -----------------------------------------------------------------------

    /**
     * The table of the imports of a period: one line per import with its figures, a sparkline, the most common
     * errors, the field it fails on and the step in its duration.
     *
     * @param array $runs normalized runs
     * @param array $messages array( 'rows' => run id => messages of failed records, 'logs' => run id => errors of the
     *                        log of runs that failed as a whole ) as loadMessages() returns it, or only the first part
     * @param array $imports import id => array( name, identifier, source_type )
     * @param array $buckets buckets()
     * @return array list, busiest first
     */
    public static function perImport( array $runs, array $messages, array $imports, array $buckets, $unit, $tz = null )
    {
        $rowMessages = isset( $messages['rows'] ) ? $messages['rows'] : $messages;
        $logMessages = isset( $messages['logs'] ) ? $messages['logs'] : array();
        $byImport = array();
        foreach ( $runs as $run )
            $byImport[$run['import_id']][] = $run;
        $out = array();
        foreach ( $byImport as $id => $list )
        {
            $s = self::summarize( $list );
            $failedMessages = $runErrors = array();
            foreach ( $list as $run )
            {
                if ( isset( $rowMessages[$run['id']] ) )
                    foreach ( $rowMessages[$run['id']] as $m )
                        $failedMessages[] = $m;
                if ( isset( $logMessages[$run['id']] ) )
                    foreach ( $logMessages[$run['id']] as $m )
                        $runErrors[] = $m;
            }
            $spark = array();
            foreach ( self::group( $list, $buckets, $unit, $tz ) as $key => $bucketRuns )
            {
                $rows = 0;
                foreach ( $bucketRuns as $r )
                    $rows += $r['rows'];
                $spark[] = $rows;
            }
            $field = self::failingField( $failedMessages );
            if ( $field && count( $failedMessages ) < $s['failed'] )
            {
                // only the newest messages were read: the count of the field scales with the failed records
                $field['count'] = (int)round( $field['count'] * $s['failed'] / count( $failedMessages ) );
            }
            $info = isset( $imports[$id] ) ? $imports[$id] : array();
            $out[] = array(
                'import_id' => (int)$id,
                'name' => isset( $info['name'] ) && $info['name'] !== '' ? (string)$info['name'] : '#' . $id,
                'identifier' => isset( $info['identifier'] ) ? (string)$info['identifier'] : '',
                'source_type' => isset( $info['source_type'] ) ? (string)$info['source_type'] : '',
                'stats' => $s,
                'spark' => self::sparkline( $spark ),
                'spark_values' => $spark,
                'errors' => self::topMessages( array_merge( $failedMessages, $runErrors ), 3 ),
                'run_errors' => self::topMessages( $runErrors, 1 ),
                'field' => $field ? $field + array( 'share' => self::rate( $field['count'], $s['rows'] ) ) : null,
                'slow' => self::changePoint( self::durationPerRow( $list ) ),
            );
        }
        usort( $out, function ( $a, $b ) { return $b['stats']['runs'] <=> $a['stats']['runs'] ?: $a['import_id'] <=> $b['import_id']; } );
        return $out;
    }

    /**
     * What the figures of the imports suggest, most useful first.
     *
     * @param array $perImport perImport()
     * @return array list of array( code, import_id, name, params ): field_fails (an import fails on one field in a
     *               share of its records), slow_since (time per record stepped up), runs_fail (a share of its runs
     *               ended with errors), mostly_unchanged (nearly every record unchanged: it may run less often)
     */
    public static function suggestions( array $perImport, $limit = 8 )
    {
        $out = array();
        foreach ( $perImport as $i )
        {
            $s = $i['stats'];
            $base = array( 'import_id' => $i['import_id'], 'name' => $i['name'] );
            if ( $i['field'] && $i['field']['count'] >= 5 && $i['field']['share'] >= 1 )
                $out[] = $base + array( 'code' => 'field_fails', 'weight' => 300 + $i['field']['share'],
                    'params' => array( 'field' => $i['field']['field'], 'share' => $i['field']['share'], 'count' => $i['field']['count'] ) );
            if ( $i['slow'] )
                $out[] = $base + array( 'code' => 'slow_since', 'weight' => 200 + $i['slow']['factor'],
                    'params' => array( 'since' => $i['slow']['since'], 'factor' => $i['slow']['factor'], 'before' => $i['slow']['before'], 'after' => $i['slow']['after'] ) );
            if ( $s['ended'] >= 4 && $s['failed_run_rate'] >= 20 )
                $out[] = $base + array( 'code' => 'runs_fail', 'weight' => 100 + $s['failed_run_rate'],
                    'params' => array( 'share' => $s['failed_run_rate'], 'runs' => $s['ended'], 'error' => !empty( $i['run_errors'] ) ? $i['run_errors'][0]['message'] : ( $i['errors'] ? $i['errors'][0]['message'] : '' ) ) );
            if ( $s['runs'] >= 10 && $s['rows'] > 0 && self::rate( $s['unchanged'], $s['rows'] ) >= 95 )
                $out[] = $base + array( 'code' => 'mostly_unchanged', 'weight' => 50,
                    'params' => array( 'share' => self::rate( $s['unchanged'], $s['rows'] ), 'runs' => $s['runs'] ) );
        }
        usort( $out, function ( $a, $b ) { return $b['weight'] <=> $a['weight'] ?: $a['import_id'] <=> $b['import_id']; } );
        return array_slice( $out, 0, $limit );
    }

    /**
     * The sentence of a suggestion, translated when the kernel is there.
     *
     * @param array $s a suggestion
     * @param mixed $tz for the date of slow_since
     * @return string
     */
    public static function suggestionText( array $s, $tz = null )
    {
        $p = $s['params'];
        switch ( $s['code'] )
        {
            case 'field_fails':
                return self::tr( '%name fails on the field %field in %share % of its records (%count records). Check the source values or the transform of this field.',
                    array( '%name' => $s['name'], '%field' => $p['field'], '%share' => number_format( $p['share'], 1 ), '%count' => $p['count'] ) );
            case 'slow_since':
                return self::tr( '%name is slow since %date: %factor times the time per record of before (%now instead of %then per 100 records). Look at what changed then: the source, the mapping or the server.',
                    array( '%name' => $s['name'], '%date' => self::dayKey( $p['since'], $tz ), '%factor' => number_format( $p['factor'], 1 ),
                           '%now' => self::formatDuration( $p['after'] * 100 ), '%then' => self::formatDuration( $p['before'] * 100 ) ) );
            case 'runs_fail':
                return self::tr( '%share % of the runs of %name ended with errors (%runs runs).', array( '%name' => $s['name'], '%share' => number_format( $p['share'], 1 ), '%runs' => $p['runs'] ) )
                    . ( $p['error'] !== '' ? ' ' . self::tr( 'The most common error: %error', array( '%error' => $p['error'] ) ) : '' );
            case 'mostly_unchanged':
                return self::tr( '%share % of the records of %name were unchanged over %runs runs: a less frequent schedule may do.',
                    array( '%name' => $s['name'], '%share' => number_format( $p['share'], 1 ), '%runs' => $p['runs'] ) );
        }
        return '';
    }

    protected static function tr( $text, array $args )
    {
        if ( class_exists( 'ezpI18n' ) )
            return ezpI18n::tr( 'extension/sqliimport/statistics', $text, null, $args );
        return strtr( $text, $args );
    }

    /**
     * The comparison of two periods.
     *
     * @param array $current summarize() of the period
     * @param array $previous summarize() of the period compared with
     * @return array list of array( name, label, current, previous, delta (delta()), current_text, previous_text, diff_text )
     */
    public static function compare( array $current, array $previous )
    {
        $metrics = array(
            'runs' => array( 'Runs', true, 'int' ),
            'rows' => array( 'Records processed', true, 'int' ),
            'failed' => array( 'Failed records', false, 'int' ),
            'failure_rate' => array( 'Failure rate of records', false, 'rate' ),
            'success_rate' => array( 'Successful runs', true, 'rate' ),
            'avg_duration' => array( 'Average duration', false, 'duration' ),
            'p95_duration' => array( 'Duration, 95th percentile', false, 'duration' ),
            'rows_per_second' => array( 'Records per second', true, 'number' ),
        );
        $out = array();
        foreach ( $metrics as $name => $def )
        {
            $d = self::delta( $current[$name], $previous[$name], $def[1] );
            $fmt = function ( $v ) use ( $def )
            {
                if ( $v === null )
                    return '–';
                switch ( $def[2] )
                {
                    case 'rate': return number_format( $v, 1 ) . ' %';
                    case 'duration': return self::formatDuration( abs( $v ) );
                    case 'number': return number_format( $v, 1 );
                }
                return number_format( $v );
            };
            $diff = '–';
            if ( $d['diff'] !== null )
            {
                $sign = $d['diff'] > 0 ? '+' : ( $d['diff'] < 0 ? '−' : '±' );
                $diff = $sign . ( $def[2] === 'rate' ? number_format( abs( $d['diff'] ), 1 ) . ' pt' : ltrim( $fmt( abs( $d['diff'] ) ), '-' ) )
                    . ( $d['pct'] !== null && $def[2] !== 'rate' ? ' (' . ( $d['pct'] > 0 ? '+' : ( $d['pct'] < 0 ? '−' : '' ) ) . number_format( abs( $d['pct'] ), 1 ) . ' %)' : '' );
            }
            $out[] = array( 'name' => $name, 'label' => self::tr( $def[0], array() ), 'delta' => $d,
                'current_text' => $fmt( $current[$name] ), 'previous_text' => $fmt( $previous[$name] ), 'diff_text' => $diff );
        }
        return $out;
    }

    /** @return string[] the columns of the CSV export of the per-import table */
    public static function csvColumns()
    {
        return array( 'import_id', 'identifier', 'name', 'source_type', 'runs', 'completed', 'with_errors', 'failed_runs', 'success_rate',
                      'records', 'created', 'updated', 'unchanged', 'skipped', 'failed_records', 'failure_rate', 'avg_duration_s',
                      'p95_duration_s', 'max_duration_s', 'records_per_second', 'top_error', 'top_error_count', 'failing_field',
                      'failing_field_share', 'slow_since' );
    }

    /** @return array the cells of one line of perImport() in the order of csvColumns() */
    public static function csvCells( array $i, $tz = null )
    {
        $s = $i['stats'];
        $n = function ( $v, $d = 1 ) { return $v === null ? '' : number_format( $v, $d, '.', '' ); };
        return array( $i['import_id'], $i['identifier'], $i['name'], $i['source_type'], $s['runs'], $s['completed'], $s['with_errors'],
            $s['failed_runs'], $n( $s['success_rate'] ), $s['rows'], $s['created'], $s['updated'] + $s['processed'], $s['unchanged'],
            $s['skipped'], $s['failed'], $n( $s['failure_rate'] ), $n( $s['avg_duration'] ), $n( $s['p95_duration'] ), $n( $s['max_duration'], 0 ),
            $n( $s['rows_per_second'] ), $i['errors'] ? $i['errors'][0]['message'] : '', $i['errors'] ? $i['errors'][0]['count'] : '',
            $i['field'] ? $i['field']['field'] : '', $i['field'] ? $n( $i['field']['share'] ) : '', $i['slow'] ? self::dayKey( $i['slow']['since'], $tz ) : '' );
    }

    // ---- loaders (database) --------------------------------------------------------------------------------------

    /**
     * The SQL condition of the runs of a period and filters (on the table alias $a).
     *
     * @param array $filter from, to, scope (a key of SCOPES), import (id), user (id), source (source type)
     * @return string
     */
    public static function where( array $filter, $a = 'r', $db = null )
    {
        $db = $db ?: expImport::db();
        $scope = isset( $filter['scope'] ) && isset( self::SCOPES[$filter['scope']] ) ? $filter['scope'] : 'production';
        $kinds = array();
        foreach ( self::SCOPES[$scope] as $kind )
            $kinds[] = "'" . $db->escapeString( $kind ) . "'";
        $sql = "$a.started_at >= " . (int)$filter['from'] . " AND $a.started_at < " . (int)$filter['to'] . " AND $a.run_kind IN (" . implode( ', ', $kinds ) . ')';
        if ( !empty( $filter['import'] ) )
            $sql .= " AND $a.import_id = " . (int)$filter['import'];
        if ( !empty( $filter['user'] ) )
            $sql .= " AND $a.user_id = " . (int)$filter['user'];
        if ( isset( $filter['source'] ) && $filter['source'] !== '' )
            $sql .= " AND $a.import_id IN (SELECT d.id FROM sqliimport_definition d WHERE d.source_type = '" . $db->escapeString( (string)$filter['source'] ) . "')";
        return $sql;
    }

    /** @return array the normalized runs of a period (oldest first) */
    public static function loadRuns( array $filter )
    {
        $db = expImport::db();
        $rows = $db->arrayQuery( 'SELECT r.id, r.import_id, r.user_id, r.run_kind, r.status, r.started_at, r.finished_at, r.created_count, r.updated_count,'
            . ' r.unchanged_count, r.skipped_count, r.failed_count, r.processed_count, r.undone_count FROM sqliimport_run r WHERE '
            . self::where( $filter, 'r', $db ) . ' ORDER BY r.started_at ASC, r.id ASC', array( 'limit' => self::MAX_RUNS ) );
        $out = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
            $out[] = self::normalizeRun( $row );
        return $out;
    }

    /**
     * The messages of the failed records of a period (the newest MAX_MESSAGES), and the errors of the log of runs
     * that failed as a whole.
     *
     * @return array rows (run id => messages of failed records), logs (run id => errors of the log)
     */
    public static function loadMessages( array $filter )
    {
        $db = expImport::db();
        $out = array( 'rows' => array(), 'logs' => array() );
        $rows = $db->arrayQuery( "SELECT rr.run_id, rr.message FROM sqliimport_run_row rr INNER JOIN sqliimport_run r ON r.id = rr.run_id"
            . " WHERE rr.outcome = 'failed' AND " . self::where( $filter, 'r', $db ) . ' ORDER BY rr.id DESC', array( 'limit' => self::MAX_MESSAGES ) );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $out['rows'][(int)$row['run_id']][] = (string)$row['message'];
        }
        $rows = $db->arrayQuery( "SELECT l.run_id, l.message FROM sqliimport_run_log l INNER JOIN sqliimport_run r ON r.id = l.run_id"
            . " WHERE l.severity = 'error' AND r.status = 'failed' AND r.failed_count = 0 AND " . self::where( $filter, 'r', $db ) . ' ORDER BY l.id DESC',
            array( 'limit' => 2000 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $out['logs'][(int)$row['run_id']][] = (string)$row['message'];
        }
        return $out;
    }

    /** @return array import id => name, identifier, source_type */
    public static function loadImports()
    {
        $out = array();
        $rows = expImport::db()->arrayQuery( 'SELECT id, name, identifier, source_type FROM sqliimport_definition ORDER BY name ASC, id ASC' );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $out[(int)$row['id']] = array( 'name' => (string)$row['name'], 'identifier' => (string)$row['identifier'], 'source_type' => (string)$row['source_type'] );
        }
        return $out;
    }

    /**
     * The choices of the filters: the imports, source types and users that have runs (GROUP BY).
     * @return array imports, sources, users: lists of array( value, label, runs )
     */
    public static function loadFilterOptions()
    {
        $db = expImport::db();
        $imports = self::loadImports();
        $out = array( 'imports' => array(), 'sources' => array(), 'users' => array() );
        $rows = $db->arrayQuery( 'SELECT import_id, COUNT(*) AS n FROM sqliimport_run GROUP BY import_id' );
        $counts = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $counts[(int)$row['import_id']] = (int)$row['n'];
        }
        foreach ( $imports as $id => $i )
            $out['imports'][] = array( 'value' => $id, 'label' => $i['name'], 'runs' => isset( $counts[$id] ) ? $counts[$id] : 0 );
        $rows = $db->arrayQuery( 'SELECT d.source_type, COUNT(*) AS n FROM sqliimport_run r INNER JOIN sqliimport_definition d ON d.id = r.import_id GROUP BY d.source_type ORDER BY d.source_type' );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $out['sources'][] = array( 'value' => (string)$row['source_type'], 'label' => strtoupper( (string)$row['source_type'] ), 'runs' => (int)$row['n'] );
        }
        $rows = $db->arrayQuery( 'SELECT user_id, COUNT(*) AS n FROM sqliimport_run GROUP BY user_id ORDER BY user_id', array( 'limit' => 200 ) );
        foreach ( is_array( $rows ) ? $rows : array() as $row )
        {
            $row = array_change_key_case( $row, CASE_LOWER );
            $id = (int)$row['user_id'];
            if ( !$id )
                continue;
            $user = class_exists( 'eZUser' ) ? eZUser::fetch( $id ) : null;
            $out['users'][] = array( 'value' => $id, 'label' => $user ? (string)$user->attribute( 'login' ) : '#' . $id, 'runs' => (int)$row['n'] );
        }
        return $out;
    }

    // ---- what the pages show -------------------------------------------------------------------------------------

    /**
     * The statistics block of the dashboard: the last N days of production runs.
     * @return array has_data, period, chart (records per day by outcome), summary with texts, slowest run, top failing imports
     */
    public static function overview( $days = 30 )
    {
        $tz = self::zone();
        $end = self::addDays( self::dayStart( self::dayKey( expImport::now(), $tz ), $tz ), 1, $tz );
        $period = self::periodArray( self::addDays( $end, -(int)$days, $tz ), $end, false, $tz );
        $filter = array( 'from' => $period['from'], 'to' => $period['to'], 'scope' => 'production' );
        $runs = self::loadRuns( $filter );
        $imports = self::loadImports();
        $s = self::summarize( $runs );
        $buckets = self::buckets( $period['from'], $period['to'], 'day', $tz );
        $trends = self::trends( $runs, $buckets, 'day', $tz );
        $failing = array();
        if ( $s['failed'] > 0 || $s['with_errors'] + $s['failed_runs'] > 0 )
        {
            $per = self::perImport( $runs, self::loadMessages( $filter ), $imports, $buckets, 'day', $tz );
            usort( $per, function ( $a, $b )
            {
                return ( $b['stats']['with_errors'] + $b['stats']['failed_runs'] ) <=> ( $a['stats']['with_errors'] + $a['stats']['failed_runs'] )
                    ?: $b['stats']['failed'] <=> $a['stats']['failed'];
            } );
            foreach ( $per as $i )
                if ( $i['stats']['failed'] > 0 || $i['stats']['with_errors'] + $i['stats']['failed_runs'] > 0 )
                    $failing[] = self::importRowForTemplate( $i );
            $failing = array_slice( $failing, 0, 5 );
        }
        $slowest = $s['slowest'];
        return array(
            'has_data' => $s['runs'] > 0,
            'days' => (int)$days,
            'period' => $period,
            'summary' => self::summaryForTemplate( $s ),
            'rows_chart' => $trends['rows'],
            'slowest' => $slowest ? array( 'id' => $slowest['id'], 'import_id' => $slowest['import_id'], 'duration_text' => self::formatDuration( $slowest['duration'] ),
                'name' => isset( $imports[$slowest['import_id']] ) ? $imports[$slowest['import_id']]['name'] : '#' . $slowest['import_id'] ) : null,
            'failing' => $failing,
        );
    }

    /** @return array the figures of summarize() with the texts a template shows */
    public static function summaryForTemplate( array $s )
    {
        unset( $s['slowest'] );
        $s['success_rate_text'] = $s['success_rate'] === null ? '–' : number_format( $s['success_rate'], 1 ) . ' %';
        $s['failure_rate_text'] = $s['failure_rate'] === null ? '–' : number_format( $s['failure_rate'], 1 ) . ' %';
        $s['failed_run_rate_text'] = $s['failed_run_rate'] === null ? '–' : number_format( $s['failed_run_rate'], 1 ) . ' %';
        $s['avg_duration_text'] = self::formatDuration( $s['avg_duration'] );
        $s['p95_duration_text'] = self::formatDuration( $s['p95_duration'] );
        $s['max_duration_text'] = self::formatDuration( $s['max_duration'] );
        $s['rows_per_second_text'] = $s['rows_per_second'] === null ? '–' : number_format( $s['rows_per_second'], 1 );
        $s['rows_text'] = number_format( $s['rows'] );
        $s['success_pct'] = $s['success_rate'] === null ? 0 : (int)round( $s['success_rate'] );
        return $s;
    }

    /** @return array a line of perImport() with the texts a template shows */
    public static function importRowForTemplate( array $i, $tz = null )
    {
        $i['stats'] = self::summaryForTemplate( $i['stats'] );
        $i['slow_date'] = $i['slow'] ? self::dayKey( $i['slow']['since'], $tz ) : '';
        $i['top_error'] = $i['errors'] ? $i['errors'][0]['message'] : '';
        $i['failure_pct'] = $i['stats']['failure_rate'] === null ? 0 : (int)min( 100, round( $i['stats']['failure_rate'] ) );
        return $i;
    }

    /**
     * The runs of a period for a (tool) value: Imports' own runs (sqliimport_run), and the runs of the other tools of
     * the transfer history the value names (never Imports' rows there, which would count Imports twice). The filters
     * of an import or a source type apply to Imports only, so they leave the other tools out.
     *
     * @param array $filter from, to, scope, import, user, source
     * @param string $tool '', transfer.sqliimport, all or another registry id
     * @return array normalized runs, ordered by start
     */
    public static function runsFor( array $filter, $tool )
    {
        $runs = expImportTransferRuns::includesImports( $tool ) ? self::loadRuns( $filter ) : array();
        $others = empty( $filter['import'] ) && empty( $filter['source'] ) ? expImportTransferRuns::otherTools( $tool ) : array();
        if ( $others )
        {
            $kinds = self::SCOPES[isset( $filter['scope'] ) && isset( self::SCOPES[$filter['scope']] ) ? $filter['scope'] : 'production'];
            foreach ( expImportTransferRuns::statisticsRows( $others, $filter['from'], $filter['to'] ) as $row )
            {
                $run = self::normalizeRun( $row );
                if ( !in_array( $run['run_kind'], $kinds, true ) || ( !empty( $filter['user'] ) && $run['user_id'] !== (int)$filter['user'] ) )
                    continue;
                $runs[] = $run;
            }
            usort( $runs, function ( $a, $b ) { return $a['started_at'] === $b['started_at'] ? $a['id'] - $b['id'] : $a['started_at'] - $b['started_at']; } );
        }
        return $runs;
    }

    /**
     * The figures of each tool of a set of runs (the table of the other tools on the Statistics page).
     *
     * @param array $runs normalized runs
     * @param array $names tool id => name
     * @return array list of array( tool, name, summary (summaryForTemplate) ), Imports first
     */
    public static function perTool( array $runs, array $names = array() )
    {
        $by = array();
        foreach ( $runs as $run )
            $by[$run['tool']][] = $run;
        uksort( $by, function ( $a, $b )
        {
            if ( $a === 'transfer.sqliimport' || $b === 'transfer.sqliimport' )
                return $a === 'transfer.sqliimport' ? -1 : ( $b === 'transfer.sqliimport' ? 1 : 0 );
            return strcmp( $a, $b );
        } );
        $out = array();
        foreach ( $by as $tool => $list )
            $out[] = array( 'tool' => $tool, 'name' => isset( $names[$tool] ) ? $names[$tool] : ( $tool === 'transfer.sqliimport' ? 'Imports' : $tool ),
                            'summary' => self::summaryForTemplate( self::summarize( $list ) ) );
        return $out;
    }

    /**
     * Everything the Statistics page shows.
     *
     * @param array $params days, from, to, import, source, user, scope, compare, cfrom, cto, tool
     * @return array
     */
    public static function page( array $params )
    {
        $tz = self::zone();
        $period = self::period( $params, expImport::now(), $tz );
        $scope = isset( $params['scope'] ) && isset( self::SCOPES[$params['scope']] ) ? (string)$params['scope'] : 'production';
        $filter = array(
            'from' => $period['from'], 'to' => $period['to'], 'scope' => $scope,
            'import' => isset( $params['import'] ) ? max( 0, (int)$params['import'] ) : 0,
            'user' => isset( $params['user'] ) ? max( 0, (int)$params['user'] ) : 0,
            'source' => isset( $params['source'] ) && preg_match( '/^[a-z0-9_]{1,40}$/', (string)$params['source'] ) ? (string)$params['source'] : '',
        );
        $toolOptions = expImportTransferRuns::toolOptions();
        $tool = isset( $params['tool'] ) ? (string)$params['tool'] : '';
        if ( $tool !== 'all' && $tool !== expImportTransferRuns::TOOL && !in_array( $tool, array_map( function ( $o ) { return (string)$o['id']; }, $toolOptions ), true ) )
            $tool = '';
        $filter['tool'] = $tool;
        $compareMode = isset( $params['compare'] ) && in_array( $params['compare'], array( 'previous', 'custom', 'none' ), true ) ? (string)$params['compare'] : 'previous';
        $other = null;
        if ( $compareMode === 'custom' )
        {
            $c = self::period( array( 'from' => isset( $params['cfrom'] ) ? $params['cfrom'] : '', 'to' => isset( $params['cto'] ) ? $params['cto'] : '' ), expImport::now(), $tz );
            $other = $c['custom'] ? $c : null;
            if ( !$other )
                $compareMode = 'previous';
        }
        if ( $compareMode === 'previous' )
            $other = self::previousPeriod( $period, $tz );

        $unit = self::unitFor( $period['days'] );
        $buckets = self::buckets( $period['from'], $period['to'], $unit, $tz );
        $runs = self::runsFor( $filter, $tool );
        // the breakdowns of imports (per import, the messages of failed records) are of Imports' own runs
        $ownRuns = array_values( array_filter( $runs, function ( $r ) { return $r['tool'] === 'transfer.sqliimport'; } ) );
        $imports = self::loadImports();
        $messages = expImportTransferRuns::includesImports( $tool ) ? self::loadMessages( $filter ) : array( 'rows' => array(), 'logs' => array() );
        $s = self::summarize( $runs );
        $per = self::perImport( $ownRuns, $messages, $imports, $buckets, $unit, $tz );
        $suggestions = array();
        foreach ( self::suggestions( $per ) as $sg )
            $suggestions[] = $sg + array( 'text' => self::suggestionText( $sg, $tz ) );
        $allMessages = array();
        foreach ( array_merge( array_values( $messages['rows'] ), array_values( $messages['logs'] ) ) as $list )
            foreach ( $list as $m )
                $allMessages[] = $m;

        $comparison = array();
        if ( $other )
        {
            $otherRuns = self::runsFor( array( 'from' => $other['from'], 'to' => $other['to'] ) + $filter, $tool );
            $comparison = self::compare( $s, self::summarize( $otherRuns ) );
        }
        $rows = array();
        foreach ( $per as $i )
            $rows[] = self::importRowForTemplate( $i, $tz );
        $names = array( expImportTransferRuns::TOOL => 'Imports' );
        foreach ( $toolOptions as $o )
            $names[(string)$o['id']] = (string)$o['name'];
        return array(
            'period' => $period,
            'filter' => $filter,
            'compare' => $compareMode,
            'other' => $other,
            'unit' => $unit,
            'runs_capped' => count( $runs ) >= self::MAX_RUNS,
            'summary' => self::summaryForTemplate( $s ),
            'trends' => self::trends( $runs, $buckets, $unit, $tz ),
            'imports' => $rows,
            'raw_imports' => $per,
            'tools' => $tool !== '' && $tool !== expImportTransferRuns::TOOL ? self::perTool( $runs, $names ) : array(),
            'tool_options' => $toolOptions,
            'errors' => self::topMessages( $allMessages, 5 ),
            'suggestions' => $suggestions,
            'comparison' => $comparison,
            'options' => self::loadFilterOptions(),
        );
    }

    /**
     * Writes the per-import table of a page as CSV.
     *
     * @param array $page page()
     * @param resource $handle
     */
    public static function writeCsv( array $page, $handle, $bom = true )
    {
        if ( $bom )
            fwrite( $handle, "\xEF\xBB\xBF" );
        fwrite( $handle, expImportReport::line( self::csvColumns() ) );
        foreach ( $page['raw_imports'] as $i )
            fwrite( $handle, expImportReport::line( self::csvCells( $i ) ) );
    }

    /** @return string the file name of the CSV export of a period */
    public static function csvFileName( array $period )
    {
        return 'sqliimport-statistics-' . preg_replace( '/[^0-9-]/', '', $period['from_date'] ) . '-' . preg_replace( '/[^0-9-]/', '', $period['to_date'] ) . '.csv';
    }
}
