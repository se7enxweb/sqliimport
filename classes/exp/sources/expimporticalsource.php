<?php
/**
 * File containing the expImportIcalSource class.
 *
 * @copyright Copyright (C) 2010 SQLi Agency, Jérôme Vieilledent; Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0
 * @package sqliimport
 */

/**
 * iCalendar files (.ics, RFC 5545), as calendars, booking systems and event platforms publish them, from a file or an
 * address. Every VEVENT is one record (and every VTODO, when the option components asks for them):
 *
 *   id            the UID, with the RECURRENCE-ID for a changed occurrence of a series ("uid@2026-11-12T18:00:00Z");
 *                 without a UID a hash of summary and start
 *   uid, type     the UID as written; event or todo
 *   summary, description, location, url, status, priority, class, sequence
 *   start, end    the value as written ("20261105T190000"), *_iso in ISO 8601 UTC ("2026-11-05T18:00:00Z", for an
 *                 all-day date "2026-11-05"), *_timestamp, *_timezone (the TZID); end from DTEND, DUE or DURATION
 *   all_day       1 for a date without a time, else 0
 *   rrule         the recurrence rule as written ("FREQ=WEEKLY;BYDAY=TU"), exdates (a list, ISO 8601)
 *   categories    a list; organizer (the address without mailto:), organizer_name (its CN), attendees (addresses)
 *   created, last_modified   ISO 8601 UTC
 *   geo_lat, geo_lon, calendar (X-WR-CALNAME), raw (every property by name, as text)
 *
 * A time with a TZID is converted with PHP's zone database; a time without a zone ("floating") and an all-day date
 * are read in the zone of the option timezone. Alarms inside an event are left out.
 */
class expImportIcalSource extends expImportContentLineSource
{
    public static function identifier()
    {
        return 'ical';
    }

    public static function label()
    {
        return 'iCalendar (.ics)';
    }

    public static function extensions()
    {
        return array( 'ics', 'ical', 'ifb', 'txt' );
    }

    public static function description()
    {
        return 'The calendar format of calendar apps, booking systems and event sites, from a file or a subscription address. '
             . 'Every event is a record with title, description, place, start and end (as written, in ISO 8601 UTC and as a '
             . 'timestamp, time zones and all-day dates included), recurrence rule, categories and organizer. Tasks can be read too.';
    }

    public static function group()
    {
        return 'content';
    }

    public static function examples()
    {
        return array( array( 'label' => 'A calendar of four events: a series, an all-day event, one in UTC and a changed occurrence', 'file' => 'events.ics' ) );
    }

    public static function preset()
    {
        return array(
            'class' => 'event',
            'match' => array( 'type' => 'remote_id', 'pattern' => 'ical-{id}' ),
            'fields' => array(
                array( 'source' => 'summary', 'attribute' => 'title', 'transforms' => array( array( 'name' => 'trim' ) ) ),
                array( 'source' => 'description', 'attribute' => 'text', 'transforms' => array( array( 'name' => 'richtext' ) ) ),
                array( 'source' => 'categories', 'attribute' => 'category', 'transforms' => array( array( 'name' => 'join', 'args' => array( 'separator' => ', ' ) ) ) ),
                array( 'source' => 'start_timestamp', 'attribute' => 'from_time', 'transforms' => array( array( 'name' => 'date' ) ) ),
                array( 'source' => 'end_timestamp', 'attribute' => 'to_time', 'transforms' => array( array( 'name' => 'date' ) ) ),
            ),
        );
    }

    public static function schema()
    {
        return array(
            'components' => array( 'type' => 'enum', 'label' => 'Records', 'default' => 'events',
                                   'values' => array( 'events' => 'Events (VEVENT)', 'events_todos' => 'Events and tasks (VEVENT, VTODO)', 'todos' => 'Tasks (VTODO)' ) ),
            'timezone' => array( 'type' => 'string', 'label' => 'Time zone', 'default' => 'UTC',
                                 'help' => 'The zone of times without a zone and of all-day dates, e.g. Europe/Berlin' ),
            'key' => array( 'type' => 'string', 'label' => 'Key field', 'default' => 'id',
                            'help' => 'The field that names a record. id: the UID, with the date of a changed occurrence.' ),
        );
    }

    /** @return DateTimeZone the zone of floating times */
    protected function defaultZone()
    {
        $name = isset( $this->config['timezone'] ) ? trim( (string)$this->config['timezone'] ) : '';
        try
        {
            return new DateTimeZone( $name !== '' ? $name : 'UTC' );
        }
        catch ( Exception $e )
        {
            $this->detected['timezone'] = 'unknown zone ' . $name . ', UTC used';
            return new DateTimeZone( 'UTC' );
        }
    }

    public function records( array $cursor = array() )
    {
        $mode = isset( $this->config['components'] ) ? (string)$this->config['components'] : 'events';
        $names = $mode === 'todos' ? array( 'VTODO' ) : ( $mode === 'events_todos' ? array( 'VEVENT', 'VTODO' ) : array( 'VEVENT' ) );
        $index = isset( $cursor['index'] ) ? (int)$cursor['index'] : 0;
        $calendar = isset( $cursor['calendar'] ) ? (string)$cursor['calendar'] : '';
        $zone = $this->defaultZone();
        $seenCalendar = !empty( $cursor['pos'] );
        $outside = function ( array $property ) use ( &$calendar, &$seenCalendar )
        {
            if ( $property['name'] === 'X-WR-CALNAME' && $calendar === '' )
                $calendar = self::text( $property );
            if ( $property['name'] === 'VERSION' || $property['name'] === 'PRODID' )
                $seenCalendar = true;
        };
        foreach ( $this->components( $cursor, $names, $outside ) as $component )
        {
            list( $name, $lines, $end ) = $component;
            $seenCalendar = true;
            $data = $this->component( $name, $lines, $zone );
            $data['calendar'] = $calendar;
            yield new expImportRecord( $this->refOf( $data, $index ), $index, $data, array( 'pos' => $end, 'index' => $index + 1, 'calendar' => $calendar ) );
            $index++;
        }
        if ( !$seenCalendar && $index === 0 )
            throw new expImportSourceException( 'the file is not an iCalendar file (no BEGIN:VCALENDAR)' );
    }

    /**
     * The record of one VEVENT or VTODO.
     *
     * @param string $name VEVENT or VTODO
     * @param array $lines its parsed lines
     * @param DateTimeZone $zone the zone of floating times
     * @return array
     */
    public function component( $name, array $lines, DateTimeZone $zone )
    {
        $props = self::byName( $lines );
        $text = function ( $key ) use ( $props )
        {
            return isset( $props[$key] ) ? trim( self::text( $props[$key][0] ) ) : '';
        };
        $start = isset( $props['DTSTART'] ) ? $this->dateValue( $props['DTSTART'][0], $zone ) : self::noDate();
        $endKey = isset( $props['DTEND'] ) ? 'DTEND' : ( isset( $props['DUE'] ) ? 'DUE' : '' );
        if ( $endKey !== '' )
            $end = $this->dateValue( $props[$endKey][0], $zone );
        elseif ( isset( $props['DURATION'] ) && $start['timestamp'] !== '' )
            $end = self::shifted( $start, self::durationSeconds( $props['DURATION'][0]['value'] ) );
        elseif ( $start['all_day'] && $start['timestamp'] !== '' )
            $end = self::shifted( $start, 86400 ); // RFC 5545: an all-day event without an end lasts the day
        else
            $end = $start;
        $uid = $text( 'UID' );
        $recurrence = isset( $props['RECURRENCE-ID'] ) ? $this->dateValue( $props['RECURRENCE-ID'][0], $zone ) : self::noDate();
        $id = $uid !== '' ? $uid : 'ical-' . substr( sha1( $text( 'SUMMARY' ) . '|' . $start['raw'] ), 0, 16 );
        if ( $recurrence['iso'] !== '' )
            $id .= '@' . $recurrence['iso'];
        $categories = array();
        foreach ( isset( $props['CATEGORIES'] ) ? $props['CATEGORIES'] : array() as $line )
            foreach ( self::split( self::decoded( $line ), ',' ) as $category )
                if ( trim( $category ) !== '' )
                    $categories[] = trim( $category );
        $exdates = array();
        foreach ( isset( $props['EXDATE'] ) ? $props['EXDATE'] : array() as $line )
            foreach ( explode( ',', $line['value'] ) as $value )
            {
                $d = $this->dateValue( array( 'name' => 'EXDATE', 'params' => $line['params'], 'value' => trim( $value ) ), $zone );
                if ( $d['iso'] !== '' )
                    $exdates[] = $d['iso'];
            }
        $attendees = array();
        foreach ( isset( $props['ATTENDEE'] ) ? $props['ATTENDEE'] : array() as $line )
            $attendees[] = self::address( $line['value'] );
        $geo = isset( $props['GEO'] ) ? explode( ';', $props['GEO'][0]['value'] ) : array();
        $raw = array();
        foreach ( $props as $key => $list )
            $raw[strtolower( $key )] = trim( self::text( $list[0] ) );
        return array(
            'id' => $id,
            'uid' => $uid,
            'type' => $name === 'VTODO' ? 'todo' : 'event',
            'summary' => $text( 'SUMMARY' ),
            'description' => $text( 'DESCRIPTION' ),
            'location' => $text( 'LOCATION' ),
            'url' => $text( 'URL' ),
            'start' => $start['raw'],
            'start_iso' => $start['iso'],
            'start_timestamp' => $start['timestamp'],
            'start_timezone' => $start['timezone'],
            'end' => $end['raw'],
            'end_iso' => $end['iso'],
            'end_timestamp' => $end['timestamp'],
            'end_timezone' => $end['timezone'],
            'all_day' => $start['all_day'] ? '1' : '0',
            'recurrence_id' => $recurrence['iso'],
            'rrule' => isset( $props['RRULE'] ) ? trim( $props['RRULE'][0]['value'] ) : '',
            'exdates' => $exdates,
            'categories' => $categories,
            'organizer' => isset( $props['ORGANIZER'] ) ? self::address( $props['ORGANIZER'][0]['value'] ) : '',
            'organizer_name' => isset( $props['ORGANIZER'][0]['params']['CN'] ) ? self::toUtf8( $props['ORGANIZER'][0]['params']['CN'] ) : '',
            'attendees' => $attendees,
            'status' => strtolower( $text( 'STATUS' ) ),
            'priority' => $text( 'PRIORITY' ),
            'class' => strtolower( $text( 'CLASS' ) ),
            'sequence' => $text( 'SEQUENCE' ),
            'created' => isset( $props['CREATED'] ) ? $this->dateValue( $props['CREATED'][0], $zone )['iso'] : '',
            'last_modified' => isset( $props['LAST-MODIFIED'] ) ? $this->dateValue( $props['LAST-MODIFIED'][0], $zone )['iso'] : '',
            'geo_lat' => isset( $geo[1] ) ? trim( $geo[0] ) : '',
            'geo_lon' => isset( $geo[1] ) ? trim( $geo[1] ) : '',
            'raw' => $raw,
        );
    }

    /** @return string an address without mailto: */
    protected static function address( $value )
    {
        return (string)preg_replace( '/^mailto:/i', '', trim( (string)$value ) );
    }

    /** @return array an empty date */
    protected static function noDate()
    {
        return array( 'raw' => '', 'iso' => '', 'timestamp' => '', 'timezone' => '', 'all_day' => false );
    }

    /** @return array a date moved by seconds (all-day dates stay dates) */
    protected static function shifted( array $date, $seconds )
    {
        $ts = (int)$date['timestamp'] + (int)$seconds;
        $out = $date;
        $out['raw'] = '';
        $out['timestamp'] = (string)$ts;
        if ( $date['all_day'] )
        {
            $d = new DateTime( '@' . $ts );
            $d->setTimezone( new DateTimeZone( $date['timezone'] !== '' ? $date['timezone'] : 'UTC' ) );
            $out['iso'] = $d->format( 'Y-m-d' );
        }
        else
            $out['iso'] = gmdate( 'Y-m-d\TH:i:s\Z', $ts );
        return $out;
    }

    /**
     * A DATE or DATE-TIME property.
     *
     * @param array $property a parsed line
     * @param DateTimeZone $zone the zone of floating times and dates
     * @return array the keys raw, iso, timestamp, timezone and all_day
     */
    public function dateValue( array $property, DateTimeZone $zone )
    {
        $raw = trim( (string)$property['value'] );
        $out = array( 'raw' => $raw, 'iso' => '', 'timestamp' => '', 'timezone' => '', 'all_day' => false );
        $tzid = isset( $property['params']['TZID'] ) ? ltrim( trim( $property['params']['TZID'] ), '/' ) : '';
        if ( preg_match( '/^(\d{8})$/', $raw ) || ( isset( $property['params']['VALUE'] ) && strtoupper( $property['params']['VALUE'] ) === 'DATE' ) )
        {
            $d = DateTime::createFromFormat( '!Ymd', substr( $raw, 0, 8 ), $zone );
            if ( !$d )
                return $out;
            $out['iso'] = $d->format( 'Y-m-d' );
            $out['timestamp'] = (string)$d->getTimestamp();
            $out['timezone'] = $zone->getName();
            $out['all_day'] = true;
            return $out;
        }
        if ( !preg_match( '/^(\d{8})T(\d{4,6})(Z?)$/i', $raw, $m ) )
            return $out;
        $time = str_pad( $m[2], 6, '0' );
        if ( strtoupper( $m[3] ) === 'Z' )
            $use = new DateTimeZone( 'UTC' );
        elseif ( $tzid !== '' )
        {
            try
            {
                $use = new DateTimeZone( $tzid );
            }
            catch ( Exception $e )
            {
                $use = $zone;
                $this->detected['unknown_timezones'][$tzid] = true;
            }
        }
        else
            $use = $zone;
        $d = DateTime::createFromFormat( '!YmdHis', $m[1] . $time, $use );
        if ( !$d )
            return $out;
        $out['timestamp'] = (string)$d->getTimestamp();
        $out['iso'] = gmdate( 'Y-m-d\TH:i:s\Z', $d->getTimestamp() );
        $out['timezone'] = strtoupper( $m[3] ) === 'Z' ? 'UTC' : ( $tzid !== '' ? $tzid : $zone->getName() );
        return $out;
    }

    /** @return int the seconds of an iCalendar duration ("PT1H30M", "P1D", "-PT15M", "P2W") */
    public static function durationSeconds( $value )
    {
        if ( !preg_match( '/^([+-]?)P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/i', trim( (string)$value ), $m ) )
            return 0;
        $n = function ( $i ) use ( $m )
        {
            return isset( $m[$i] ) && $m[$i] !== '' ? (int)$m[$i] : 0;
        };
        $seconds = $n( 2 ) * 604800 + $n( 3 ) * 86400 + $n( 4 ) * 3600 + $n( 5 ) * 60 + $n( 6 );
        return $m[1] === '-' ? -$seconds : $seconds;
    }
}
