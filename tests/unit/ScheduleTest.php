<?php

use PHPUnit\Framework\TestCase;

/**
 * When a schedule runs next: every kind, cron expressions, time zones and the changes of daylight saving time.
 */
class ScheduleTest extends TestCase
{
    private static function ts( string $local, string $zone = 'UTC' ): int
    {
        return ( new DateTime( $local, new DateTimeZone( $zone ) ) )->getTimestamp();
    }

    private static function local( int $ts, string $zone = 'UTC' ): string
    {
        $d = new DateTime( '@' . $ts );
        $d->setTimezone( new DateTimeZone( $zone ) );
        return $d->format( 'Y-m-d H:i' );
    }

    public function testOnceAndInterval(): void
    {
        $runAt = self::ts( '2026-10-22 10:00' );
        $this->assertSame( $runAt, expImportCron::next( array( 'kind' => 'once', 'run_at' => $runAt ), $runAt - 1 ) );
        $this->assertSame( 0, expImportCron::next( array( 'kind' => 'once', 'run_at' => $runAt ), $runAt ) );
        $s = array( 'kind' => 'interval', 'run_at' => $runAt, 'interval_minutes' => 15 );
        $this->assertSame( '2026-10-22 10:45', self::local( expImportCron::next( $s, self::ts( '2026-10-22 10:31' ) ) ) );
        $this->assertSame( '2026-10-22 10:15', self::local( expImportCron::next( $s, $runAt ) ) );
    }

    public function testDailyWeeklyMonthlyKeepTheLocalTimeAcrossDaylightSavingTime(): void
    {
        $zone = 'Europe/Berlin';
        $daily = array( 'kind' => 'daily', 'run_at' => self::ts( '2026-10-20 02:30', $zone ), 'timezone' => $zone );
        // clocks go back on 2026-10-25 at 03:00 CEST: 02:30 exists twice, and runs once
        $first = expImportCron::next( $daily, self::ts( '2026-10-24 12:00', $zone ) );
        $this->assertSame( '2026-10-25 02:30', self::local( $first, $zone ) );
        $this->assertSame( '2026-10-26 02:30', self::local( expImportCron::next( $daily, $first ), $zone ) );
        // clocks go forward on 2026-03-29 at 02:00: 02:30 does not exist that day
        $spring = array( 'kind' => 'daily', 'run_at' => self::ts( '2026-03-01 02:30', $zone ), 'timezone' => $zone );
        $this->assertSame( '2026-03-30 02:30', self::local( expImportCron::next( $spring, self::ts( '2026-03-28 12:00', $zone ) ), $zone ) );

        $weekly = array( 'kind' => 'weekly', 'run_at' => self::ts( '2026-10-19 08:00', $zone ), 'timezone' => $zone ); // a Monday
        $this->assertSame( '2026-10-26 08:00', self::local( expImportCron::next( $weekly, self::ts( '2026-10-22 00:00', $zone ) ), $zone ) );

        $monthly = array( 'kind' => 'monthly', 'run_at' => self::ts( '2026-01-31 06:00' ), 'timezone' => 'UTC' );
        $this->assertSame( '2026-02-28 06:00', self::local( expImportCron::next( $monthly, self::ts( '2026-02-01 00:00' ) ) ), 'the last day of a shorter month' );
        $this->assertSame( '2026-03-31 06:00', self::local( expImportCron::next( $monthly, self::ts( '2026-02-28 06:00' ) ) ) );
    }

    public function testCronExpressions(): void
    {
        $from = self::ts( '2026-10-22 10:07' ); // a Thursday
        $next = function ( $expr, $after = null ) use ( $from )
        {
            return self::local( expImportCron::next( array( 'kind' => 'cron', 'cron_expr' => $expr ), $after ?? $from ) );
        };
        $this->assertSame( '2026-10-22 10:10', $next( '*/10 * * * *' ) );
        $this->assertSame( '2026-10-23 06:00', $next( '0 6 * * mon-fri' ) );
        $this->assertSame( '2026-10-26 06:00', $next( '0 6 * * 1' ) );
        $this->assertSame( '2026-10-25 00:00', $next( '0 0 * * 7' ), '7 is Sunday' );
        $this->assertSame( '2026-11-01 00:00', $next( '@monthly' ) );
        $this->assertSame( '2026-10-26 00:00', $next( '0 0 1 * mon' ), 'day of month or day of week: the next Monday comes first' );
        $this->assertSame( '2026-12-24 18:30', $next( '30 18 24 dec *' ) );
        $this->assertSame( '1970-01-01 00:00', self::local( expImportCron::next( array( 'kind' => 'cron', 'cron_expr' => '0 0 30 2 *' ), $from ) ), 'never' );
    }

    public function testValidation(): void
    {
        $this->assertSame( array(), expImportCron::validate( array( 'kind' => 'cron', 'cron_expr' => '5 4 * * sun', 'timezone' => 'Europe/Berlin' ) ) );
        $this->assertNotEmpty( expImportCron::validate( array( 'kind' => 'cron', 'cron_expr' => '61 * * * *' ) ) );
        $this->assertNotEmpty( expImportCron::validate( array( 'kind' => 'cron', 'cron_expr' => '* * *' ) ) );
        $this->assertNotEmpty( expImportCron::validate( array( 'kind' => 'interval', 'interval_minutes' => 2, 'run_at' => 1 ) ) );
        $this->assertNotEmpty( expImportCron::validate( array( 'kind' => 'daily', 'run_at' => 1, 'timezone' => 'Mars/Olympus' ) ) );
        $this->assertNotEmpty( expImportCron::validate( array( 'kind' => 'daily' ) ) );
    }
}
