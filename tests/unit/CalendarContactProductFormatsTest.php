<?php

use PHPUnit\Framework\TestCase;

/**
 * The formats iCalendar, vCard, Google Merchant, Shopify CSV and WooCommerce CSV, read from their example files and
 * from small files of the tests' own (folding, escapes, time zones, cursors, refusals).
 */
class CalendarContactProductFormatsTest extends TestCase
{
    /** @var string[] files written by a test */
    protected $files = array();

    protected function tearDown(): void
    {
        foreach ( $this->files as $file )
            if ( is_file( $file ) )
                unlink( $file );
        $this->files = array();
    }

    protected static function example( $name ): string
    {
        return dirname( __DIR__, 2 ) . '/share/examples/' . $name;
    }

    protected function file( string $content, string $extension ): string
    {
        $file = tempnam( sys_get_temp_dir(), 'sqliimport-test-' ) . '.' . $extension;
        file_put_contents( $file, $content );
        $this->files[] = $file;
        return $file;
    }

    /** @return expImportRecord[] */
    protected static function read( expImportSourceInterface $source, array $cursor = array() ): array
    {
        $out = array();
        foreach ( $source->records( $cursor ) as $record )
            $out[] = $record;
        return $out;
    }

    // ---- iCalendar ---------------------------------------------------------------------------------------------------

    public function testIcalEvents(): void
    {
        $records = self::read( new expImportIcalSource( array( 'file' => self::example( 'events.ics' ), 'key' => 'id', 'timezone' => 'Europe/Berlin' ) ) );
        $this->assertCount( 4, $records, 'four events, the task and the alarm are left out' );
        $first = $records[0]->data;
        $this->assertSame( 'weekly-meetup-1@example.org', $records[0]->ref );
        $this->assertSame( 'Weekly user group meetup', $first['summary'] );
        $this->assertSame( "Talks, demos and questions.\nBring your laptop; the room has power outlets.", $first['description'], 'unfolded and unescaped' );
        $this->assertSame( 'Example Hall, Room 2, 1 Example Street', $first['location'] );
        $this->assertSame( '20261105T190000', $first['start'] );
        $this->assertSame( '2026-11-05T18:00:00Z', $first['start_iso'], 'Europe/Berlin is UTC+1 in November' );
        $this->assertSame( (string)gmmktime( 18, 0, 0, 11, 5, 2026 ), $first['start_timestamp'] );
        $this->assertSame( 'Europe/Berlin', $first['start_timezone'] );
        $this->assertSame( '2026-11-05T20:00:00Z', $first['end_iso'] );
        $this->assertSame( '0', $first['all_day'] );
        $this->assertSame( 'FREQ=WEEKLY;BYDAY=TH;COUNT=8', $first['rrule'] );
        $this->assertSame( array( '2026-11-26T18:00:00Z' ), $first['exdates'] );
        $this->assertSame( array( 'Meetup', 'Community' ), $first['categories'] );
        $this->assertSame( 'events@example.org', $first['organizer'] );
        $this->assertSame( 'Example Events Team', $first['organizer_name'] );
        $this->assertSame( 'confirmed', $first['status'] );
        $this->assertSame( '2026-10-02T09:15:00Z', $first['last_modified'] );
        $this->assertSame( 'Example events', $first['calendar'] );
        $this->assertSame( 'event', $first['type'] );
        $this->assertArrayNotHasKey( 'trigger', $first['raw'], 'the alarm\'s lines are not the event\'s' );

        $moved = $records[1]->data;
        $this->assertSame( 'weekly-meetup-1@example.org@2026-11-12T18:00:00Z', $moved['id'], 'a changed occurrence has its own id' );
        $this->assertSame( '2026-11-12T20:00:00Z', $moved['end_iso'], 'the end from DURATION' );

        $allDay = $records[2]->data;
        $this->assertSame( '1', $allDay['all_day'] );
        $this->assertSame( '2026-11-21', $allDay['start_iso'] );
        $this->assertSame( '2026-11-22', $allDay['end_iso'] );
        $this->assertSame( (string)( gmmktime( 0, 0, 0, 11, 21, 2026 ) - 3600 ), $allDay['start_timestamp'], 'midnight in the option\'s zone' );
        $this->assertSame( array( 'Open day', 'Family' ), $allDay['categories'], 'repeated CATEGORIES add up' );

        $utc = $records[3]->data;
        $this->assertSame( '2026-12-03T15:00:00Z', $utc['start_iso'] );
        $this->assertSame( 'UTC', $utc['start_timezone'] );
        $this->assertSame( '52.520008', $utc['geo_lat'] );
    }

    public function testIcalCursorAndTasks(): void
    {
        $file = self::example( 'events.ics' );
        $records = self::read( new expImportIcalSource( array( 'file' => $file, 'key' => 'id' ) ) );
        $rest = self::read( new expImportIcalSource( array( 'file' => $file, 'key' => 'id' ) ), $records[1]->cursor );
        $this->assertSame( array( 'open-day-2026@example.org', 'webinar-42@example.com' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );
        $this->assertSame( 2, $rest[0]->index );
        $this->assertSame( 'Example events', $rest[0]->data['calendar'], 'the calendar name travels in the cursor' );

        $both = self::read( new expImportIcalSource( array( 'file' => $file, 'components' => 'events_todos', 'key' => 'id' ) ) );
        $this->assertCount( 5, $both );
        $todo = $both[4]->data;
        $this->assertSame( 'todo', $todo['type'] );
        $this->assertSame( '2026-11-01', $todo['end_iso'], 'DUE is the end of a task' );
        $this->assertSame( '2', $todo['priority'] );
        $this->assertCount( 1, self::read( new expImportIcalSource( array( 'file' => $file, 'components' => 'todos' ) ) ) );
    }

    public function testIcalCrlfFloatingAndUnknownZone(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Floating\\, local\r\nDTSTART:20260701T090000\r\n"
             . "DTEND;TZID=Not/AZone:20260701T100000\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $source = new expImportIcalSource( array( 'file' => $this->file( $ics, 'ics' ), 'timezone' => 'America/New_York', 'key' => 'id' ) );
        $records = self::read( $source );
        $this->assertCount( 1, $records );
        $data = $records[0]->data;
        $this->assertSame( 'Floating, local', $data['summary'] );
        $this->assertSame( '2026-07-01T13:00:00Z', $data['start_iso'], 'a floating time is read in the option\'s zone (EDT)' );
        $this->assertSame( '2026-07-01T14:00:00Z', $data['end_iso'], 'an unknown TZID falls back to it' );
        $this->assertStringStartsWith( 'ical-', $data['id'], 'without a UID the id is a hash' );
        $this->assertSame( $data['id'], self::read( $source )[0]->data['id'], 'the same each time' );
        $this->assertSame( 5400, expImportIcalSource::durationSeconds( 'PT1H30M' ) );
        $this->assertSame( -900, expImportIcalSource::durationSeconds( '-PT15M' ) );
        $this->assertSame( 1209600, expImportIcalSource::durationSeconds( 'P2W' ) );
    }

    public function testIcalRefusesOtherFiles(): void
    {
        $this->expectException( expImportSourceException::class );
        self::read( new expImportIcalSource( array( 'file' => self::example( 'products.csv' ) ) ) );
    }

    public function testContentLineParsing(): void
    {
        $line = expImportContentLineSource::parseLine( 'item1.ATTENDEE;CN="Doe, Jane";ROLE=CHAIR:mailto:jane@example.com' );
        $this->assertSame( 'ATTENDEE', $line['name'] );
        $this->assertSame( array( 'CN' => 'Doe, Jane', 'ROLE' => 'CHAIR' ), $line['params'] );
        $this->assertSame( 'mailto:jane@example.com', $line['value'] );
        $this->assertSame( array( 'a;b', 'c,d', "e\nf\\" ), expImportContentLineSource::split( 'a\\;b;c\\,d;e\\nf\\\\', ';' ) );
        $this->assertNull( expImportContentLineSource::parseLine( 'no colon here' ) );
    }

    // ---- vCard -------------------------------------------------------------------------------------------------------

    public function testVcardContacts(): void
    {
        $records = self::read( new expImportVcardSource( array( 'file' => self::example( 'contacts.vcf' ), 'key' => 'id' ) ) );
        $this->assertCount( 3, $records );
        $a = $records[0]->data;
        $this->assertSame( '4fbe8971-0bc3-424c-9c26-36c3e1eff6b1', $records[0]->ref, 'urn:uuid: is taken off' );
        $this->assertSame( '4.0', $a['version'] );
        $this->assertSame( 'Alex Example', $a['fn'] );
        $this->assertSame( array( 'family' => 'Example', 'given' => 'Alex', 'additional' => 'J.', 'prefix' => 'Dr.', 'suffix' => '' ), $a['n'] );
        $this->assertSame( 'Example Corp', $a['org'] );
        $this->assertSame( 'Research', $a['org_unit'] );
        $this->assertSame( 'alex@example.com', $a['email'] );
        $this->assertSame( array( 'alex@example.com', 'alex.home@example.org' ), $a['emails'] );
        $this->assertSame( 'alex.home@example.org', $a['email_home'] );
        $this->assertSame( '+1-555-0100', $a['tel_work'] );
        $this->assertSame( '+1-555-0101', $a['tel_cell'] );
        $this->assertSame( '100 Example Avenue', $a['adr']['street'] );
        $this->assertSame( '55501', $a['adr']['postal_code'] );
        $this->assertSame( 'Suite 4, 100 Example Avenue, 55501 Springfield, EX, United States', $a['adr_text'] );
        $this->assertSame( "Met at the example conference, 2026.\nLikes long walks in example parks.", $a['note'] );
        $this->assertSame( '1985-04-12', $a['bday_iso'] );
        $this->assertSame( array( 'Customer', 'Speaker' ), $a['categories'] );
        $this->assertSame( 'https://example.com/photos/alex.jpg', $a['photo'] );

        $b = $records[1]->data;
        $this->assertSame( 'sam@example.org', $b['email'], 'a grouped property (item1.EMAIL) is read' );
        $this->assertSame( '555-0123', $b['tel_home'] );
        $this->assertSame( '555-0124', $b['tel_fax'] );
        $this->assertSame( '--07-04', $b['bday_iso'], 'a birthday without a year' );
        $this->assertStringStartsWith( 'vcard-', $b['id'], 'no UID: a hash of name and address' );
        $this->assertSame( 'Exampleland', $b['adr']['country'] );

        $c = $records[2]->data;
        $this->assertSame( '', $c['photo'], 'an embedded photo is not kept' );
        $this->assertSame( '1', $c['photo_embedded'] );

        $rest = self::read( new expImportVcardSource( array( 'file' => self::example( 'contacts.vcf' ), 'key' => 'id' ) ), $records[0]->cursor );
        $this->assertSame( array( $b['id'], 'contact-3@example.net' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );
    }

    public function testVcardQuotedPrintable(): void
    {
        $vcf = "BEGIN:VCARD\nVERSION:2.1\nN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:M=C3=BCller;Kim\nTEL;WORK;VOICE:555-0199\nEND:VCARD\n";
        $records = self::read( new expImportVcardSource( array( 'file' => $this->file( $vcf, 'vcf' ) ) ) );
        $this->assertSame( 'Müller', $records[0]->data['family_name'] );
        $this->assertSame( 'Kim Müller', $records[0]->data['fn'], 'the name is made from N when FN is missing' );
        $this->assertSame( '555-0199', $records[0]->data['tel_work'], 'bare 2.1 types' );
    }

    public function testVcardRefusesOtherFiles(): void
    {
        $this->expectException( expImportSourceException::class );
        self::read( new expImportVcardSource( array( 'file' => self::example( 'events.ics' ) ) ) );
    }

    // ---- product feeds -----------------------------------------------------------------------------------------------

    public function testMoney(): void
    {
        $this->assertSame( array( 'amount' => '24.90', 'currency' => 'EUR' ), expImportCommerce::money( '24.90 EUR' ) );
        $this->assertSame( array( 'amount' => '1299.00', 'currency' => 'USD' ), expImportCommerce::money( 'USD 1,299.00' ) );
        $this->assertSame( array( 'amount' => '1299.00', 'currency' => 'EUR' ), expImportCommerce::money( '1.299,00 EUR' ) );
        $this->assertSame( array( 'amount' => '39.00', 'currency' => 'EUR' ), expImportCommerce::money( '39,00', 'eur' ) );
        $this->assertSame( '1299', expImportCommerce::decimal( '1,299' ) );
        $this->assertSame( '', expImportCommerce::decimal( 'free' ) );
        $this->assertSame( array( 'a', 'b, c', 'd' ), expImportCommerce::cellList( 'a, b\\, c,d,' ) );
    }

    public function testGoogleMerchantFeed(): void
    {
        $records = self::read( new expImportGoogleMerchantSource( array( 'file' => self::example( 'products-google.xml' ), 'key' => 'id' ) ) );
        $this->assertSame( array( 'EX-1001', 'EX-1002', 'EX-1003' ), array_map( function ( $r ) { return $r->ref; }, $records ) );
        $mug = $records[0]->data;
        $this->assertSame( 'Example travel mug', $mug['title'] );
        $this->assertStringContainsString( '<strong>six hours</strong>', $mug['description'] );
        $this->assertSame( '24.90', $mug['price'] );
        $this->assertSame( 'EUR', $mug['currency'] );
        $this->assertSame( '19.90', $mug['sale_price'] );
        $this->assertSame( 'in_stock', $mug['availability'] );
        $this->assertSame( '4006381333931', $mug['gtin'] );
        $this->assertSame( array( 'Home', 'Kitchen', 'Mugs' ), $mug['categories'] );
        $this->assertCount( 2, $mug['additional_image_links'] );
        $this->assertCount( 3, $mug['images'] );
        $this->assertSame( array( array( 'country' => 'DE', 'region' => '', 'service' => 'Standard', 'price' => '4.95', 'currency' => 'EUR' ) ), $mug['shipping'] );
        $this->assertSame( '19', $mug['vat'] );
        $this->assertSame( array( 'Bestseller', 'Winter' ), $mug['tags'] );
        $notebook = $records[1]->data;
        $this->assertSame( 'Example notebook, A5', $notebook['title'], 'g:title as well as title' );
        $this->assertSame( '1299.00', $notebook['price'] );
        $this->assertSame( 'USD', $notebook['currency'] );
        $this->assertSame( '', $notebook['sale_price'] );
        $this->assertSame( '39.00', $records[2]->data['price'], 'a decimal comma' );
        $rest = self::read( new expImportGoogleMerchantSource( array( 'file' => self::example( 'products-google.xml' ), 'key' => 'id' ) ), $records[1]->cursor );
        $this->assertSame( array( 'EX-1003' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );
    }

    public function testShopifyGroupsRowsByHandle(): void
    {
        $file = self::example( 'products-shopify.csv' );
        $records = self::read( new expImportShopifyCsvSource( array( 'file' => $file, 'key' => 'id', 'currency' => 'USD' ) ) );
        $this->assertSame( array( 'example-hoodie', 'example-gift-card' ), array_map( function ( $r ) { return $r->ref; }, $records ) );
        $hoodie = $records[0]->data;
        $this->assertSame( 'Example hoodie', $hoodie['title'] );
        $this->assertSame( array( 'winter', 'cotton', 'bestseller' ), $hoodie['tags'] );
        $this->assertSame( '1', $hoodie['published'] );
        $this->assertSame( array( 'Size' ), $hoodie['options'] );
        $this->assertCount( 3, $hoodie['variants'] );
        $this->assertSame( array( 'HOODIE-S', 'HOODIE-M', 'HOODIE-L' ), array_column( $hoodie['variants'], 'sku' ) );
        $this->assertSame( array( 'Small', 'Medium', 'Large' ), array_column( $hoodie['variants'], 'option1' ) );
        $this->assertSame( 'HOODIE-S', $hoodie['sku'] );
        $this->assertSame( '49.00', $hoodie['price'] );
        $this->assertSame( '59.00', $hoodie['compare_at_price'] );
        $this->assertSame( '49.00', $hoodie['price_min'] );
        $this->assertSame( '52.00', $hoodie['price_max'] );
        $this->assertSame( 'USD', $hoodie['currency'] );
        $this->assertSame( '1', $hoodie['taxable'] );
        $this->assertSame( array( 'https://cdn.example.com/hoodie-front.jpg', 'https://cdn.example.com/hoodie-back.jpg' ), $hoodie['images'] );
        $this->assertSame( 'Hoodie from the front', $hoodie['image_alt'] );
        $gift = $records[1]->data;
        $this->assertSame( array(), $gift['options'], 'the option "Title" of a product without variants is no option' );
        $this->assertSame( '', $gift['variants'][0]['option1'] );
        $this->assertSame( '1', $gift['gift_card'] );
        $rest = self::read( new expImportShopifyCsvSource( array( 'file' => $file, 'key' => 'id' ) ), $records[0]->cursor );
        $this->assertSame( array( 'example-gift-card' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );
        $this->assertSame( 1, $rest[0]->index );
        $this->assertSame( 2, ( new expImportShopifyCsvSource( array( 'file' => $file ) ) )->count() );
    }

    public function testShopifyRefusesOtherCsv(): void
    {
        $this->expectException( expImportSourceException::class );
        self::read( new expImportShopifyCsvSource( array( 'file' => self::example( 'products.csv' ) ) ) );
    }

    public function testWooCommerceVariations(): void
    {
        $file = self::example( 'products-woocommerce.csv' );
        $records = self::read( new expImportWooCommerceCsvSource( array( 'file' => $file, 'key' => 'id', 'currency' => 'EUR' ) ) );
        $this->assertSame( array( '101', '102', '105' ), array_map( function ( $r ) { return $r->ref; }, $records ) );
        $mug = $records[0]->data;
        $this->assertSame( 'simple', $mug['type'] );
        $this->assertSame( 'WC-MUG', $mug['sku'] );
        $this->assertSame( 'Example mug', $mug['title'] );
        $this->assertSame( "Holds 350 ml.\nDishwasher safe.", $mug['description'], 'WooCommerce\'s \\n is a line break' );
        $this->assertSame( '12.50', $mug['price'] );
        $this->assertSame( 'EUR', $mug['currency'] );
        $this->assertSame( 'taxable', $mug['tax_status'] );
        $this->assertSame( array( 'Kitchen > Mugs', 'Gifts' ), $mug['categories'] );
        $this->assertSame( 'Mugs', $mug['category'] );
        $this->assertSame( array( 'mug', 'ceramic' ), $mug['tags'] );
        $this->assertSame( 'https://shop.example.net/img/mug.jpg', $mug['image'] );
        $this->assertCount( 2, $mug['images'] );
        $this->assertSame( '0.4', $mug['weight'] );
        $this->assertSame( array( array( 'name' => 'Color', 'values' => array( 'Blue', 'Green' ), 'visible' => '1', 'global' => '1' ) ), $mug['attributes'] );
        $this->assertSame( array( '_example_origin' => 'warehouse-a' ), $mug['meta'] );

        $tee = $records[1]->data;
        $this->assertSame( 'variable', $tee['type'] );
        $this->assertSame( 'reduced-rate', $tee['tax_class'] );
        $this->assertSame( array( 'WC-TEE-S', 'WC-TEE-M' ), array_column( $tee['variants'], 'sku' ) );
        $this->assertSame( '15.00', $tee['variants'][1]['sale_price'] );
        $this->assertSame( '18.00', $tee['price'], 'a variable product takes the lowest price of its variations' );

        $pen = $records[2]->data;
        $this->assertSame( 'Example pen, black', $pen['title'] );
        $this->assertSame( '-1', $pen['published'] );
        $this->assertSame( '3.00', $pen['regular_price'] );
        $this->assertSame( '2.50', $pen['sale_price'] );
        $this->assertSame( '2.50', $pen['current_price'] );
        $this->assertSame( array( 'WC-MUG' ), $pen['upsells'] );

        $rest = self::read( new expImportWooCommerceCsvSource( array( 'file' => $file, 'key' => 'id' ) ), $records[0]->cursor );
        $this->assertSame( array( '102', '105' ), array_map( function ( $r ) { return $r->ref; }, $rest ) );

        $flat = self::read( new expImportWooCommerceCsvSource( array( 'file' => $file, 'key' => 'id', 'variations' => 'records' ) ) );
        $this->assertCount( 5, $flat );
        $this->assertSame( '102', $flat[2]->data['parent_id'] );
    }

    public function testPresetsUseTheWebshopAndEventClasses(): void
    {
        foreach ( array( 'expImportGoogleMerchantSource', 'expImportShopifyCsvSource', 'expImportWooCommerceCsvSource' ) as $class )
        {
            $preset = $class::preset();
            $this->assertSame( 'product', $preset['class'], $class );
            $this->assertSame( array( 'name', 'product_number', 'description', 'price', 'tags' ),
                               array_values( array_diff( array_column( $preset['fields'], 'attribute' ), array( 'short_description' ) ) ), $class );
        }
        $this->assertSame( array( 'title', 'text', 'category', 'from_time', 'to_time' ), array_column( expImportIcalSource::preset()['fields'], 'attribute' ) );
        $this->assertSame( array(), expImportVcardSource::preset(), 'a standard installation has no contact class' );
        $record = new expImportRecord( 'x', 0, array( 'price' => '49.00' ) );
        $this->assertSame( '49.00|1|1', expImportTransforms::applyChain( '49.00', array( array( 'name' => 'template', 'args' => array( 'text' => '{price}|1|1' ) ) ), $record ) );
    }
}
