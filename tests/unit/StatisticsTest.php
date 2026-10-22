<?php

use PHPUnit\Framework\TestCase;

/**
 * The statistics of the run history (expImportStatistics) without a database: periods and buckets in a time zone
 * with daylight saving time, percentiles, rates, deltas, stacked chart heights, the change point of a duration
 * series, the most common error messages, the failing field and the suggestions.
 */
class StatisticsTest extends TestCase
{
    const TZ = 'Europe/Berlin';

    private static function aRun( array $set ): array
    {
        return expImportStatistics::normalizeRun( $set + array( 'id' => 1, 'import_id' => 1, 'run_kind' => 'import', 'status' => 'completed',
            'started_at' => 1767225600, 'finished_at' => 1767225660 ) );
    }

    public function testDaysFollowTheTimeZone(): void
    {
        // 2026-03-28 23:30 UTC is already the 29th in Berlin (UTC+1)
        $this->assertSame( '2026-03-29', expImportStatistics::dayKey( gmmktime( 23, 30, 0, 3, 28, 2026 ), self::TZ ) );
        $this->assertSame( '2026-03-28', expImportStatistics::dayKey( gmmktime( 23, 30, 0, 3, 28, 2026 ), 'UTC' ) );
        // the day daylight saving time starts has 23 hours, and stepping over it lands on midnight again
        $start = expImportStatistics::dayStart( '2026-03-29', self::TZ );
        $next = expImportStatistics::addDays( $start, 1, self::TZ );
        $this->assertSame( 23 * 3600, $next - $start );
        $this->assertSame( 0, expImportStatistics::dayStart( '2026-02-30', self::TZ ), 'a date that does not exist' );
        $this->assertSame( 0, expImportStatistics::dayStart( "2026-01-01' OR 1=1", self::TZ ) );
    }

    public function testPeriods(): void
    {
        $now = gmmktime( 10, 0, 0, 10, 22, 2026 );
        $p = expImportStatistics::period( array( 'days' => 7 ), $now, self::TZ );
        $this->assertSame( 7, $p['days'] );
        $this->assertSame( '2026-10-16', $p['from_date'] );
        $this->assertSame( '2026-10-22', $p['to_date'] );
        $this->assertFalse( $p['custom'] );

        $this->assertSame( 30, expImportStatistics::period( array( 'days' => 12 ), $now, self::TZ )['days'], 'an unknown length falls back to 30' );

        $c = expImportStatistics::period( array( 'from' => '2026-03-01', 'to' => '2026-03-31', 'days' => 7 ), $now, self::TZ );
        $this->assertTrue( $c['custom'] );
        $this->assertSame( 31, $c['days'] );
        $prev = expImportStatistics::previousPeriod( $c, self::TZ );
        $this->assertSame( '2026-01-29', $prev['from_date'] );
        $this->assertSame( '2026-02-28', $prev['to_date'] );
        $this->assertSame( $c['from'], $prev['to'] );

        $bad = expImportStatistics::period( array( 'from' => '2026-04-01', 'to' => '2026-03-01' ), $now, self::TZ );
        $this->assertFalse( $bad['custom'], 'from after to is ignored' );
    }

    public function testBuckets(): void
    {
        $this->assertSame( 'day', expImportStatistics::unitFor( 30 ) );
        $this->assertSame( 'week', expImportStatistics::unitFor( 90 ) );
        $this->assertSame( 'month', expImportStatistics::unitFor( 365 ) );
        $from = expImportStatistics::dayStart( '2026-10-01', self::TZ );
        $to = expImportStatistics::dayStart( '2026-11-01', self::TZ );
        $days = expImportStatistics::buckets( $from, $to, 'day', self::TZ );
        $this->assertCount( 31, $days, 'the 25-hour day of October 25 is one bucket' );
        $this->assertSame( '10/1', reset( $days )['label'] );
        $weeks = expImportStatistics::buckets( $from, $to, 'week', self::TZ );
        $this->assertSame( '2026-09-28', array_key_first( $weeks ), 'a week starts on Monday' );
        $this->assertCount( 5, $weeks );
        $months = expImportStatistics::buckets( expImportStatistics::dayStart( '2026-01-15', self::TZ ), $to, 'month', self::TZ );
        $this->assertSame( array( '2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01',
                                  '2026-09-01', '2026-10-01' ), array_keys( $months ) );
    }

    public function testPercentileMeanRate(): void
    {
        $this->assertNull( expImportStatistics::percentile( array(), 95 ) );
        $this->assertSame( 7.0, expImportStatistics::percentile( array( 7 ), 95 ) );
        $this->assertEqualsWithDelta( 95.05, expImportStatistics::percentile( range( 1, 100 ), 95 ), 1e-9 );
        $this->assertEqualsWithDelta( 4.6, expImportStatistics::percentile( array( 5, 1, 3, 2, 4 ), 90 ), 1e-9 );
        $this->assertSame( 12.5, expImportStatistics::rate( 1, 8 ) );
        $this->assertSame( 0.0, expImportStatistics::rate( 3, 0 ) );
        $this->assertSame( 0, expImportStatistics::pct( 0, 10 ) );
        $this->assertSame( 1, expImportStatistics::pct( 1, 1000 ), 'a value that is not zero stays visible' );
        $this->assertSame( 100, expImportStatistics::pct( 10, 10 ) );
    }

    public function testDelta(): void
    {
        $d = expImportStatistics::delta( 120, 100 );
        $this->assertSame( 20.0, (float)$d['diff'] );
        $this->assertSame( 20.0, $d['pct'] );
        $this->assertSame( 'up', $d['direction'] );
        $this->assertSame( 'good', $d['kind'] );
        $this->assertSame( 'bad', expImportStatistics::delta( 30, 10, false )['kind'], 'a longer duration is bad' );
        $this->assertNull( expImportStatistics::delta( 5, 0 )['pct'] );
        $this->assertSame( 'same', expImportStatistics::delta( 4, 4 )['direction'] );
        $this->assertNull( expImportStatistics::delta( null, 4 )['diff'] );
    }

    public function testSummarize(): void
    {
        $runs = array(
            self::aRun( array( 'id' => 1, 'created_count' => 10, 'updated_count' => 5, 'finished_at' => 1767225600 + 10 ) ),
            self::aRun( array( 'id' => 2, 'status' => 'completed_with_errors', 'updated_count' => 8, 'failed_count' => 2, 'finished_at' => 1767225600 + 30 ) ),
            self::aRun( array( 'id' => 3, 'status' => 'failed', 'finished_at' => 1767225600 + 20 ) ),
            self::aRun( array( 'id' => 4, 'status' => 'running', 'finished_at' => 0, 'skipped_count' => 3 ) ),
        );
        $s = expImportStatistics::summarize( $runs );
        $this->assertSame( 4, $s['runs'] );
        $this->assertSame( 3, $s['ended'] );
        $this->assertSame( 33.3, $s['success_rate'] );
        $this->assertSame( 66.7, $s['failed_run_rate'] );
        $this->assertSame( 28, $s['rows'] );
        $this->assertSame( 7.1, $s['failure_rate'] );
        $this->assertEqualsWithDelta( 20.0, $s['avg_duration'], 1e-9 );
        $this->assertSame( 30, $s['max_duration'] );
        $this->assertSame( 2, $s['slowest']['id'] );
        $this->assertSame( 0.4, $s['rows_per_second'], '25 records in 60 seconds' );
        $this->assertNull( $runs[3]['duration'], 'a running run has no duration yet' );
    }

    public function testStackedChartAndTrends(): void
    {
        $from = expImportStatistics::dayStart( '2026-01-01', 'UTC' );
        $buckets = expImportStatistics::buckets( $from, $from + 3 * 86400, 'day', 'UTC' );
        $runs = array(
            self::aRun( array( 'id' => 1, 'started_at' => $from + 3600, 'finished_at' => $from + 3700, 'created_count' => 30, 'failed_count' => 10 ) ),
            self::aRun( array( 'id' => 2, 'started_at' => $from + 86400 + 60, 'finished_at' => $from + 86400 + 80, 'updated_count' => 20 ) ),
            self::aRun( array( 'id' => 3, 'started_at' => $from - 60, 'finished_at' => $from, 'updated_count' => 999 ) ),
        );
        $t = expImportStatistics::trends( $runs, $buckets, 'day', 'UTC' );
        $this->assertSame( 40, $t['rows']['max'] );
        $first = $t['rows']['bars'][0];
        $this->assertSame( 40, $first['total'] );
        $this->assertSame( array( 'created', 75 ), array( $first['segments'][0]['name'], $first['segments'][0]['pct'] ) );
        $this->assertSame( array( 'failed', 25 ), array( $first['segments'][4]['name'], $first['segments'][4]['pct'] ) );
        $this->assertSame( 50, $t['rows']['bars'][1]['segments'][1]['pct'] );
        $this->assertSame( 0, $t['rows']['bars'][2]['total'], 'a day without runs' );
        $this->assertSame( 20, $t['rows']['sum']['updated'], 'the run before the period is not counted' );
        $this->assertSame( '25.0 %', $t['failure']['bars'][0]['text'] );
        $this->assertSame( '–', $t['duration']['bars'][2]['text'] );
        $this->assertSame( array( 0, 50, 100 ), expImportStatistics::sparkline( array( 0, 2, 4 ) ) );
    }

    public function testChangePointFindsASlowDown(): void
    {
        $points = array();
        $t = 1767225600;
        foreach ( array( 1.0, 1.1, 0.9, 1.05, 0.95, 1.0, 1.1, 0.9, 1.0, 1.02 ) as $v )
            $points[] = array( $t += 86400, $v );
        $since = $t + 86400;
        foreach ( array( 2.0, 2.2, 1.9, 2.1, 2.05, 1.95, 2.0, 2.1 ) as $v )
            $points[] = array( $t += 86400, $v );
        $cp = expImportStatistics::changePoint( $points );
        $this->assertNotNull( $cp );
        $this->assertSame( $since, $cp['since'] );
        $this->assertSame( 2.0, $cp['factor'] );
    }

    public function testChangePointIgnoresNoiseAndSpeedUps(): void
    {
        $noise = array();
        foreach ( array( 1.0, 1.4, 0.8, 1.2, 0.9, 1.3, 1.1, 0.7, 1.25, 0.95, 1.15, 0.85 ) as $i => $v )
            $noise[] = array( 1000 + $i, $v );
        $this->assertNull( expImportStatistics::changePoint( $noise ) );
        $faster = array();
        foreach ( array( 2, 2, 2.1, 1.9, 2, 1, 1, 1.1, 0.9, 1 ) as $i => $v )
            $faster[] = array( 1000 + $i, $v );
        $this->assertNull( expImportStatistics::changePoint( $faster ), 'getting faster is not a suggestion' );
        $this->assertNull( expImportStatistics::changePoint( array_slice( $noise, 0, 5 ) ), 'too few runs' );
    }

    public function testMessages(): void
    {
        $this->assertSame( "row # of file 'a2.csv': the required attribute 'price' has no value",
            expImportStatistics::normalizeMessage( "row 12 of  file 'a2.csv': the required attribute 'price' has no value" ) );
        $top = expImportStatistics::topMessages( array( 'timeout after 30 s', 'timeout after 31 s', 'bad date 2026', '', 'timeout after 5 s' ), 2 );
        $this->assertSame( array( 'message' => 'timeout after # s', 'count' => 3, 'share' => 75.0 ), $top[0] );
        $this->assertSame( 'bad date #', $top[1]['message'] );
        $this->assertSame( 'price', expImportStatistics::fieldOf( "the required attribute 'price' has no value" ) );
        $this->assertSame( 'ean', expImportStatistics::fieldOf( "the match field 'ean' is empty" ) );
        $this->assertSame( '', expImportStatistics::fieldOf( 'Error: the source cannot be read' ) );
        $f = expImportStatistics::failingField( array( "attribute 'price' x", "attribute 'price' y", "field 'ean' z", 'nothing' ) );
        $this->assertSame( array( 'field' => 'price', 'count' => 2 ), $f );
        $this->assertNull( expImportStatistics::failingField( array( 'nothing' ) ) );
    }

    public function testPerImportAndSuggestions(): void
    {
        $runs = array();
        $messages = array();
        $t = 1767225600;
        for ( $i = 1; $i <= 12; $i++ )
        {
            $slow = $i > 6 ? 3 : 1;
            $runs[] = self::aRun( array( 'id' => $i, 'import_id' => 7, 'started_at' => $t + $i * 86400, 'finished_at' => $t + $i * 86400 + 100 * $slow + $i % 2,
                'updated_count' => 88, 'failed_count' => 12, 'status' => 'completed_with_errors' ) );
            $messages[$i] = array_fill( 0, 12, "the required attribute 'price' has no value" );
        }
        $runs[] = self::aRun( array( 'id' => 50, 'import_id' => 8, 'started_at' => $t + 86400, 'finished_at' => $t + 86460, 'unchanged_count' => 100 ) );
        $from = expImportStatistics::dayStart( '2026-01-01', 'UTC' );
        $buckets = expImportStatistics::buckets( $from, $from + 20 * 86400, 'day', 'UTC' );
        $per = expImportStatistics::perImport( $runs, $messages, array( 7 => array( 'name' => 'Products', 'identifier' => 'products', 'source_type' => 'csv' ) ),
            $buckets, 'day', 'UTC' );
        $this->assertSame( 7, $per[0]['import_id'] );
        $this->assertSame( 'Products', $per[0]['name'] );
        $this->assertSame( '#8', $per[1]['name'] );
        $this->assertSame( array( 'field' => 'price', 'count' => 144, 'share' => 12.0 ), $per[0]['field'] );
        $this->assertNotNull( $per[0]['slow'] );
        $this->assertSame( $t + 7 * 86400, $per[0]['slow']['since'] );
        $this->assertCount( 20, $per[0]['spark'] );

        $codes = array_column( expImportStatistics::suggestions( $per ), 'code' );
        $this->assertSame( array( 'field_fails', 'slow_since', 'runs_fail' ), $codes );
        $text = expImportStatistics::suggestionText( expImportStatistics::suggestions( $per )[0] );
        $this->assertSame( 'Products fails on the field price in 12.0 % of its records (144 records). Check the source values or the transform of this field.', $text );
        $slow = expImportStatistics::suggestionText( expImportStatistics::suggestions( $per )[1], 'UTC' );
        $this->assertStringContainsString( 'Products is slow since 2026-01-08', $slow );

        $cells = expImportStatistics::csvCells( $per[0], 'UTC' );
        $this->assertCount( count( expImportStatistics::csvColumns() ), $cells );
        $this->assertSame( '12.0', $cells[15] );
        $this->assertSame( '2026-01-08', end( $cells ) );
    }

    public function testComparisonAndCsvFileName(): void
    {
        $a = expImportStatistics::summarize( array( self::aRun( array( 'updated_count' => 10, 'finished_at' => 1767225600 + 20 ) ) ) );
        $b = expImportStatistics::summarize( array( self::aRun( array( 'updated_count' => 5, 'finished_at' => 1767225600 + 10 ) ) ) );
        $rows = array_column( expImportStatistics::compare( $a, $b ), null, 'name' );
        $this->assertSame( '+5 (+100.0 %)', $rows['rows']['diff_text'] );
        $this->assertSame( 'good', $rows['rows']['delta']['kind'] );
        $this->assertSame( 'bad', $rows['avg_duration']['delta']['kind'] );
        $this->assertSame( '20 s', $rows['avg_duration']['current_text'] );
        $this->assertSame( '±0.0 pt', $rows['success_rate']['diff_text'] );
        $this->assertSame( 'sqliimport-statistics-2026-01-01-2026-01-31.csv',
            expImportStatistics::csvFileName( array( 'from_date' => '2026-01-01', 'to_date' => '2026-01-31' ) ) );
        $this->assertSame( '0.4 s', expImportStatistics::formatDuration( 0.4 ) );
        $this->assertSame( '3 min 12 s', expImportStatistics::formatDuration( 192 ) );
    }
}
