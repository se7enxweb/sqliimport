<?php
/**
 * File containing the expImportCron class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 2024 - 2026 7x
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * When a schedule runs next. Pure: the time zone, daylight saving time and the cron expression are worked out with
 * PHP's DateTime, nothing is read from the database.
 *
 * Schedule kinds (sqliimport_schedule.kind):
 *   once      at run_at
 *   interval  every interval_minutes minutes (at least 5) counted from run_at
 *   hourly    every hour at run_at's minute
 *   daily     every day at run_at's time of day
 *   weekly    every week on run_at's weekday and time
 *   monthly   every month on run_at's day (the last day in a shorter month) and time
 *   cron      a five-field cron expression: minute hour day-of-month month day-of-week, with *, lists (1,15),
 *             ranges (1-5), steps (*\/10, 0-30/5) and names (jan, mon); day 0 and 7 are Sunday. When both the day of
 *             the month and the day of the week are restricted, either one matches (as in cron).
 * The times of day are those of the schedule's time zone, so "daily at 02:30" stays at 02:30 across a change of
 * daylight saving time; a time that does not exist on the day of the change (02:30 when clocks jump from 02:00 to
 * 03:00) is skipped that day, and a time that exists twice runs once.
 */
class expImportCron
{
    const KINDS = array( 'once', 'interval', 'hourly', 'daily', 'weekly', 'monthly', 'cron' );

    /** The shortest interval, in minutes (the cron part runs every minute; less would only pile up runs) */
    const MIN_INTERVAL = 5;

    /**
     * Checks a schedule.
     *
     * @param array $s kind, run_at, interval_minutes, cron_expr, timezone
     * @return string[] errors
     */
    public static function validate( array $s )
    {
        $errors = array();
        $kind = isset( $s['kind'] ) ? (string)$s['kind'] : '';
        if ( !in_array( $kind, self::KINDS, true ) )
            $errors[] = expImport::tr( 'extension/sqliimport/schedule', 'unknown schedule kind %kind', array( '%kind' => $kind ) );
        try
        {
            new DateTimeZone( isset( $s['timezone'] ) && $s['timezone'] !== '' ? (string)$s['timezone'] : 'UTC' );
        }
        catch ( Exception $e )
        {
            $errors[] = expImport::tr( 'extension/sqliimport/schedule', 'unknown time zone %zone', array( '%zone' => $s['timezone'] ) );
        }
        if ( $kind === 'interval' && ( !isset( $s['interval_minutes'] ) || (int)$s['interval_minutes'] < self::MIN_INTERVAL ) )
            $errors[] = expImport::tr( 'extension/sqliimport/schedule', 'an interval is at least %n minutes', array( '%n' => self::MIN_INTERVAL ) );
        if ( $kind === 'cron' )
        {
            try
            {
                self::parse( isset( $s['cron_expr'] ) ? (string)$s['cron_expr'] : '' );
            }
            catch ( InvalidArgumentException $e )
            {
                $errors[] = $e->getMessage();
            }
        }
        if ( $kind !== 'cron' && empty( $s['run_at'] ) )
            $errors[] = expImport::tr( 'extension/sqliimport/schedule', 'choose the first date and time' );
        return $errors;
    }

    /**
     * The next run strictly after $after.
     *
     * @param array $s kind, run_at, interval_minutes, cron_expr, timezone
     * @param int $after a timestamp
     * @return int a timestamp, 0 when the schedule will not run again
     */
    public static function next( array $s, $after )
    {
        $after = (int)$after;
        $kind = isset( $s['kind'] ) ? (string)$s['kind'] : 'once';
        $zone = new DateTimeZone( isset( $s['timezone'] ) && $s['timezone'] !== '' ? (string)$s['timezone'] : 'UTC' );
        $runAt = isset( $s['run_at'] ) ? (int)$s['run_at'] : 0;
        switch ( $kind )
        {
            case 'once':
                return $runAt > $after ? $runAt : 0;
            case 'interval':
                $step = max( self::MIN_INTERVAL, (int)$s['interval_minutes'] ) * 60;
                if ( $runAt > $after )
                    return $runAt;
                return $runAt + ( intdiv( $after - $runAt, $step ) + 1 ) * $step;
            case 'cron':
                return self::nextCron( self::parse( (string)$s['cron_expr'] ), $after, $zone );
        }
        $anchor = new DateTime( '@' . $runAt );
        $anchor->setTimezone( $zone );
        if ( $runAt > $after )
            return $runAt;
        $minute = (int)$anchor->format( 'i' );
        $hour = (int)$anchor->format( 'G' );
        $expr = array(
            'hourly' => "$minute * * * *",
            'daily' => "$minute $hour * * *",
            'weekly' => "$minute $hour * * " . $anchor->format( 'w' ),
        );
        if ( isset( $expr[$kind] ) )
            return self::nextCron( self::parse( $expr[$kind] ), $after, $zone );
        if ( $kind === 'monthly' )
        {
            $day = (int)$anchor->format( 'j' );
            $t = new DateTime( '@' . $after );
            $t->setTimezone( $zone );
            for ( $i = 0; $i < 14; $i++ )
            {
                $y = (int)$t->format( 'Y' );
                $m = (int)$t->format( 'n' );
                $last = (int)( new DateTime( sprintf( '%04d-%02d-01', $y, $m ), $zone ) )->format( 't' );
                $candidate = new DateTime( sprintf( '%04d-%02d-%02d %02d:%02d:00', $y, $m, min( $day, $last ), $hour, $minute ), $zone );
                if ( $candidate->getTimestamp() > $after )
                    return $candidate->getTimestamp();
                $t = new DateTime( sprintf( '%04d-%02d-01 00:00:00', $y, $m ), $zone );
                $t->modify( '+1 month' );
            }
        }
        return 0;
    }

    /**
     * A cron expression as sets of allowed values.
     *
     * @param string $expr
     * @return array ( 'minute' => int[], 'hour' => int[], 'dom' => int[], 'month' => int[], 'dow' => int[],
     *                'dom_any' => bool, 'dow_any' => bool )
     * @throws InvalidArgumentException
     */
    public static function parse( $expr )
    {
        $aliases = array( '@hourly' => '0 * * * *', '@daily' => '0 0 * * *', '@midnight' => '0 0 * * *',
                          '@weekly' => '0 0 * * 0', '@monthly' => '0 0 1 * *', '@yearly' => '0 0 1 1 *', '@annually' => '0 0 1 1 *' );
        $expr = strtolower( trim( (string)$expr ) );
        if ( isset( $aliases[$expr] ) )
            $expr = $aliases[$expr];
        $parts = preg_split( '/\s+/', $expr );
        if ( count( $parts ) !== 5 )
            throw new InvalidArgumentException( 'a cron expression has five fields: minute hour day-of-month month day-of-week' );
        $months = array( 'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
                         'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12 );
        $days = array( 'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6 );
        $out = array(
            'minute' => self::field( $parts[0], 0, 59, array(), 'minute' ),
            'hour' => self::field( $parts[1], 0, 23, array(), 'hour' ),
            'dom' => self::field( $parts[2], 1, 31, array(), 'day of the month' ),
            'month' => self::field( $parts[3], 1, 12, $months, 'month' ),
            'dow' => self::field( $parts[4], 0, 7, $days, 'day of the week' ),
            'dom_any' => $parts[2] === '*' || $parts[2] === '?',
            'dow_any' => $parts[4] === '*' || $parts[4] === '?',
        );
        if ( in_array( 7, $out['dow'], true ) && !in_array( 0, $out['dow'], true ) )
            $out['dow'][] = 0;
        return $out;
    }

    /** @return int[] */
    protected static function field( $text, $min, $max, array $names, $label )
    {
        $values = array();
        foreach ( explode( ',', $text ) as $item )
        {
            $step = 1;
            if ( strpos( $item, '/' ) !== false )
            {
                list( $item, $s ) = explode( '/', $item, 2 );
                if ( !ctype_digit( $s ) || (int)$s < 1 )
                    throw new InvalidArgumentException( "the step of the $label is not a positive number" );
                $step = (int)$s;
            }
            if ( $item === '*' || $item === '?' )
            {
                $from = $min;
                $to = $max;
            }
            elseif ( strpos( $item, '-' ) !== false )
            {
                list( $a, $b ) = explode( '-', $item, 2 );
                $from = self::value( $a, $names, $label );
                $to = self::value( $b, $names, $label );
            }
            else
            {
                $from = self::value( $item, $names, $label );
                $to = $step > 1 ? $max : $from;
            }
            if ( $from < $min || $to > $max || $from > $to )
                throw new InvalidArgumentException( "the $label must be between $min and $max" );
            for ( $v = $from; $v <= $to; $v += $step )
                $values[$v] = true;
        }
        ksort( $values );
        return array_keys( $values );
    }

    /** @return int */
    protected static function value( $text, array $names, $label )
    {
        if ( isset( $names[$text] ) )
            return $names[$text];
        if ( !ctype_digit( (string)$text ) )
            throw new InvalidArgumentException( "'$text' is not a valid $label" );
        return (int)$text;
    }

    /** @return int the offset of a zone from UTC at a moment, in seconds */
    protected static function offsetAt( $ts, DateTimeZone $zone )
    {
        return (int)$zone->getOffset( new DateTime( '@' . (int)$ts ) );
    }

    /** @return int the moment of a local wall-clock time (counted as if it were UTC) in a zone */
    protected static function localToTs( $local, DateTimeZone $zone )
    {
        $guess = $local - self::offsetAt( $local, $zone );
        return $local - self::offsetAt( $guess, $zone );
    }

    /**
     * The first minute after $after the parsed expression allows, in a time zone. The wall clock is worked out from
     * the zone's offset at each moment, in integers: DateTime of PHP before 8.1 showed the wrong one of two equal
     * hours when the clocks go back.
     *
     * @param array $cron from parse()
     * @param int $after
     * @param DateTimeZone $zone
     * @return int 0 when none within five years (e.g. 30 February)
     */
    public static function nextCron( array $cron, $after, DateTimeZone $zone )
    {
        $ts = intdiv( (int)$after, 60 ) * 60 + 60;
        $limit = (int)$after + 5 * 366 * 86400;
        for ( $guard = 0; $guard < 200000 && $ts <= $limit; $guard++ )
        {
            $local = $ts + self::offsetAt( $ts, $zone );
            list( $year, $month, $day, $dow, $hour, $minute ) = array_map( 'intval', explode( ' ', gmdate( 'Y n j w G i', $local ) ) );
            if ( !in_array( $month, $cron['month'], true ) )
            {
                $ts = max( $ts + 60, self::localToTs( gmmktime( 0, 0, 0, $month + 1, 1, $year ), $zone ) );
                continue;
            }
            $domOk = in_array( $day, $cron['dom'], true );
            $dowOk = in_array( $dow, $cron['dow'], true );
            if ( $cron['dom_any'] && $cron['dow_any'] )
                $dayOk = true;
            elseif ( $cron['dom_any'] )
                $dayOk = $dowOk;
            elseif ( $cron['dow_any'] )
                $dayOk = $domOk;
            else
                $dayOk = $domOk || $dowOk;
            if ( !$dayOk )
            {
                $ts = max( $ts + 60, self::localToTs( gmmktime( 0, 0, 0, $month, $day + 1, $year ), $zone ) );
                continue;
            }
            if ( !in_array( $hour, $cron['hour'], true ) )
            {
                // the next full hour of the local clock: time passes evenly, so it is this many seconds away
                $ts += 3600 - ( $local % 3600 );
                continue;
            }
            if ( !in_array( $minute, $cron['minute'], true ) )
            {
                $ts += 60;
                continue;
            }
            // the second occurrence of a wall-clock time (clocks went back an hour) is not a new run
            $earlier = $ts - 3600;
            if ( gmdate( 'Y-m-d H:i', $earlier + self::offsetAt( $earlier, $zone ) ) === gmdate( 'Y-m-d H:i', $local ) )
            {
                $ts += 60;
                continue;
            }
            return $ts;
        }
        return 0;
    }
}
