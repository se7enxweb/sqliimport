<?php

use PHPUnit\Framework\TestCase;

/**
 * The InfluxDB source against a scripted server (expImportPlatformHttp::$transport): the Flux check (packages,
 * to(), interpolation, regular expressions), annotated CSV with several tables and an error table, the InfluxQL
 * check and its paging per series, Test connection with masked errors, secrets by name, hosts and drivers().
 */
class InfluxDbSourceTest extends TestCase
{
    /** @var string */
    protected $dir;

    /** @var array[] the requests: method, url, headers, body (decoded) */
    protected $requests = array();

    const CSV = "#datatype,string,long,dateTime:RFC3339,dateTime:RFC3339,dateTime:RFC3339,double,string,string,string\r\n"
              . "#group,false,false,true,true,false,false,true,true,true\r\n"
              . "#default,_result,,,,,,,,\r\n"
              . ",result,table,_start,_stop,_time,_value,_field,_measurement,room\r\n"
              . ",,0,2026-06-01T00:00:00Z,2026-06-02T00:00:00Z,2026-06-01T08:00:00Z,19.2,temperature,climate,store\r\n"
              . ",,0,2026-06-01T00:00:00Z,2026-06-02T00:00:00Z,2026-06-01T12:00:00Z,21.7,temperature,climate,store\r\n"
              . "\r\n"
              . "#datatype,string,long,dateTime:RFC3339,boolean,unsignedLong,string\r\n"
              . "#group,false,false,false,true,false,false\r\n"
              . "#default,_result,,,,,\r\n"
              . ",result,table,_time,open,count,note\r\n"
              . ",,1,2026-06-01T08:00:00Z,true,18446744073709551615,\"a, \"\"quoted\"\" note\"\r\n"
              . ",,1,2026-06-01T09:00:00Z,false,7,\r\n";

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sqliimport-influxtest-' . getmypid() . '-' . mt_rand();
        mkdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->setVariable( 'InfluxDbSourceSettings', 'AllowedHosts', array( 'influx.example.invalid' ) );
        expImportSqlSource::$workerFile = $this->dir . '/worker.json';
        putenv( 'SQ688_INFLUX_TOKEN=tok-Influx-Secret-81' );
        expImportPlatformHttp::$transport = array( $this, 'answer' );
    }

    protected function tearDown(): void
    {
        expImportPlatformHttp::$transport = null;
        expImportInfluxDbSource::$environment = null;
        expImportSqlSource::$workerFile = null;
        foreach ( (array)glob( $this->dir . '/*' ) as $file )
            unlink( $file );
        rmdir( $this->dir );
        eZINI::instance( 'sqliimport.ini' )->removeSetting( 'InfluxDbSourceSettings', 'AllowedHosts' );
        putenv( 'SQ688_INFLUX_TOKEN' );
    }

    /** The scripted server: Flux (annotated CSV) and InfluxQL over two series of 5 points. */
    public function answer( $method, $url, array $headers, $body )
    {
        $data = $body === null ? null : json_decode( (string)$body, true );
        $this->requests[] = array( 'method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $data );
        $path = (string)parse_url( $url, PHP_URL_PATH );
        if ( $path === '/api/v2/query' )
        {
            if ( strpos( $data['query'], 'fail' ) !== false )
                return array( 'status' => 200, 'headers' => array( 'X-Influxdb-Version: v2.7.10' ),
                              'body' => "#datatype,string,string\r\n#group,true,true\r\n#default,,\r\n,error,reference\r\n,bucket \"fail\" not found (token tok-Influx-Secret-81),897\r\n" );
            return array( 'status' => 200, 'headers' => array( 'X-Influxdb-Version: v2.7.10' ), 'body' => self::CSV );
        }
        if ( $path === '/query' )
        {
            parse_str( (string)parse_url( $url, PHP_URL_QUERY ), $params );
            if ( strpos( $params['q'], 'fail' ) !== false )
                return array( 'status' => 200, 'headers' => array(), 'body' => json_encode( array( 'results' => array( array( 'statement_id' => 0, 'error' => 'measurement not found' ) ) ) ) );
            $limit = preg_match( '/LIMIT (\d+) OFFSET (\d+)$/', $params['q'], $m ) ? (int)$m[1] : 5;
            $offset = isset( $m[2] ) ? (int)$m[2] : 0;
            $series = array();
            foreach ( array( 'store', 'office' ) as $room )
            {
                $values = array();
                for ( $i = $offset; $i < min( 5, $offset + $limit ); $i++ )
                    $values[] = array( '2026-06-01T0' . $i . ':00:00Z', 20 + $i );
                $series[] = array( 'name' => 'climate', 'tags' => array( 'room' => $room ), 'columns' => array( 'time', 'mean' ), 'values' => $values );
            }
            return array( 'status' => 200, 'headers' => array( 'X-Influxdb-Version: 1.8.10' ),
                          'body' => json_encode( array( 'results' => array( array( 'statement_id' => 0, 'series' => $series ) ) ) ) );
        }
        return array( 'status' => 404, 'headers' => array(), 'body' => '{"code":"not found","message":"path not found"}' );
    }

    /** @return array the options of an import, validated */
    protected function options( array $given ): array
    {
        list( $values, $errors ) = expImportOptionSchema::validate( expImportInfluxDbSource::schema(), $given + array(
            'base_url' => 'https://influx.example.invalid:8086', 'org' => 'example', 'token_secret' => 'env:SQ688_INFLUX_TOKEN',
            'query' => "from(bucket: \"sensors\")\n  |> range(start: -1d)\n  |> filter(fn: (r) => r._measurement == \"climate\")" ) );
        $this->assertSame( array(), $errors );
        return $values;
    }

    /** @return array[] the snapshot fetch() writes */
    protected function fetched( array $options, $limit = 0 ): array
    {
        $file = $this->dir . '/snapshot.ndjson';
        expImportInfluxDbSource::fetch( $options, $file, $limit );
        $out = array();
        foreach ( ( new expImportInfluxDbSource( array( 'file' => $file ) ) )->records() as $record )
            $out[] = $record->data;
        return $out;
    }

    public function testSchemaExamplesAndTheExampleSnapshot(): void
    {
        $this->assertSame( array(), expImportOptionSchema::check( expImportInfluxDbSource::schema() ) );
        $this->assertSame( 'nosql', expImportInfluxDbSource::group() );
        foreach ( expImportInfluxDbSource::examples() as $example )
        {
            list( , $errors ) = expImportOptionSchema::validate( expImportInfluxDbSource::schema(), $example['options'] );
            $this->assertSame( array(), $errors, $example['label'] );
        }
        $records = array();
        foreach ( ( new expImportInfluxDbSource( array( 'file' => dirname( __DIR__, 2 ) . '/share/examples/influxdb-climate.ndjson', 'key' => '_time' ) ) )->records() as $record )
            $records[$record->ref] = $record->data;
        $this->assertSame( array( '2026-06-01T08:00:00Z', '2026-06-01T12:00:00Z', '2026-06-01T16:00:00Z' ), array_keys( $records ) );
    }

    public function testOnlyReadingFluxPasses(): void
    {
        foreach ( array(
            'from(bucket: "sensors") |> range(start: -1h)',
            "import \"strings\"\nimport \"influxdata/influxdb/schema\"\nfrom(bucket: \"x\") |> range(start: -1h) |> map(fn: (r) => ({r with t: strings.toUpper(v: r.room)}))",
            'from(bucket: "x") |> range(start: -1h) |> filter(fn: (r) => r.room =~ /st\/o\/\/re/) |> toFloat()',
            'from(bucket: "to()") |> range(start: -1h) // |> to(bucket: "y")',
            'from(bucket: "x") |> range(start: -1h) |> aggregateWindow(every: 1h, fn: mean) |> yield(name: "m")',
        ) as $query )
            $this->assertNull( expImportInfluxDbSource::checkFlux( $query ), $query );
        foreach ( array(
            'from(bucket: "x") |> range(start: -1h) |> to(bucket: "y")',
            'from(bucket: "x") |> range(start: -1h) |> influxdb.wideTo(bucket: "y")',
            "import \"experimental\"\nfrom(bucket: \"x\") |> range(start: -1h) |> experimental.to(bucket: \"y\")",
            "import e \"experimental\"\nf = e.to\nfrom(bucket: \"x\") |> range(start: -1h) |> f(bucket: \"y\")",
            "import \"http\"\nhttp.post(url: \"https://example.invalid\", data: bytes(v: \"x\"))",
            "import \"sql\"\nsql.from(driverName: \"postgres\", dataSourceName: \"x\", query: \"SELECT 1\")",
            "import \"slack\"\nslack.message(url: \"https://example.invalid\", channel: \"x\", text: \"y\")",
            "import \"contrib/sranka/telegram\"\ntelegram.message(token: \"x\", channel: \"y\", text: \"z\")",
            "import \"influxdata/influxdb/secrets\"\nsecrets.get(key: \"x\")",
            "import \"experimental/csv\"\ncsv.from(url: \"https://example.invalid/x.csv\")",
            "import \"experimental/mqtt\"\nmqtt.to(broker: \"tcp://x:1883\")",
            "import \"pagerduty\"\npagerduty.sendEvent()",
            'from(bucket: "x") |> range(start: -1h) |> map(fn: (r) => ({r with s: "${r.x}"}))',
            'from(bucket: "x") |> range(start: -1h) |> filter(fn: (r) => r.x =~ /a\//) |> to(bucket: "y")',
            'from(bucket: "x" |> range(start: -1h)) |> sleep(duration: 1s)',
            'from(bucket: "x) |> range(start: -1h)',
            '',
        ) as $query )
            $this->assertNotNull( expImportInfluxDbSource::checkFlux( $query ), $query );
    }

    public function testFluxAndAnnotatedCsv(): void
    {
        $records = $this->fetched( $this->options( array( 'pivot' => true ) ) );
        $this->assertCount( 4, $records );
        $request = $this->requests[0];
        $this->assertSame( 'https://influx.example.invalid:8086/api/v2/query?org=example', $request['url'] );
        $this->assertContains( 'Authorization: Token tok-Influx-Secret-81', $request['headers'] );
        $this->assertContains( 'Accept: application/csv', $request['headers'] );
        $this->assertSame( 'flux', $request['body']['type'] );
        $this->assertSame( array( 'datatype', 'group', 'default' ), $request['body']['dialect']['annotations'] );
        $this->assertStringEndsWith( '|> pivot(rowKey: ["_time"], columnKey: ["_field"], valueColumn: "_value")', $request['body']['query'] );
        $this->assertSame( array( '_start' => '2026-06-01T00:00:00Z', '_stop' => '2026-06-02T00:00:00Z', '_time' => '2026-06-01T12:00:00Z', '_value' => 21.7,
                                  '_field' => 'temperature', '_measurement' => 'climate', 'room' => 'store' ), $records[1] );
        $this->assertSame( array( '_time' => '2026-06-01T08:00:00Z', 'open' => true, 'count' => '18446744073709551615', 'note' => 'a, "quoted" note' ), $records[2] );
        $this->assertSame( array( '_time' => '2026-06-01T09:00:00Z', 'open' => false, 'count' => 7, 'note' => null ), $records[3] );
        // the editor's sample limits the query
        $this->requests = array();
        $this->assertCount( 2, $this->fetched( $this->options( array() ), 2 ) );
        $this->assertStringEndsWith( '|> limit(n: 2)', $this->requests[0]['body']['query'] );
    }

    public function testInfluxQlPagesPerSeries(): void
    {
        foreach ( array( 'SELECT mean("t") FROM "climate" WHERE time > now() - 1d GROUP BY time(1h)', 'SHOW MEASUREMENTS', "SELECT * FROM \"climate\" WHERE note = 'DROP x'" ) as $query )
            $this->assertNull( expImportInfluxDbSource::checkInfluxQl( $query ), $query );
        foreach ( array( 'SELECT * INTO "copy" FROM "climate"', 'DROP MEASUREMENT climate', 'DELETE FROM climate', 'CREATE DATABASE x', 'SET PASSWORD FOR x = \'y\'',
                         'SELECT * FROM climate; DROP DATABASE x', 'GRANT ALL TO x', 'KILL QUERY 1', 'ALTER RETENTION POLICY x ON y DURATION 1d', '' ) as $query )
            $this->assertNotNull( expImportInfluxDbSource::checkInfluxQl( $query ), $query );
        $options = $this->options( array( 'language' => 'influxql', 'database' => 'sensors', 'retention_policy' => 'autogen', 'auth' => 'basic', 'user' => 'reader',
                                          'password_secret' => 'env:SQ688_INFLUX_TOKEN', 'page_size' => 2, 'query' => 'SELECT mean FROM "climate"' ) );
        $records = $this->fetched( $options );
        $this->assertCount( 10, $records );
        $this->assertCount( 3, $this->requests );
        parse_str( (string)parse_url( $this->requests[0]['url'], PHP_URL_QUERY ), $params );
        $this->assertSame( array( 'q' => 'SELECT mean FROM "climate" LIMIT 2 OFFSET 0', 'db' => 'sensors', 'rp' => 'autogen' ), $params );
        $this->assertContains( 'Authorization: Basic ' . base64_encode( 'reader:tok-Influx-Secret-81' ), $this->requests[0]['headers'] );
        $this->assertSame( array( '_measurement' => 'climate', 'room' => 'office', '_time' => '2026-06-01T01:00:00Z', 'mean' => 21 ), $records[3] );
        // a query with its own LIMIT is read once
        $this->requests = array();
        $this->fetched( $this->options( array( 'language' => 'influxql', 'database' => 'sensors', 'query' => 'SELECT mean FROM "climate" LIMIT 3' ) ) );
        $this->assertCount( 1, $this->requests );
    }

    public function testTestConnectionHostsAndSecrets(): void
    {
        $result = expImportInfluxDbSource::test( $this->options( array() ) );
        $this->assertTrue( $result['ok'], $result['message'] );
        $this->assertSame( 'InfluxDB v2.7.10 (Flux)', $result['driver'] );
        $this->assertSame( array( '_start', '_stop', '_time', '_value', '_field', '_measurement', 'room' ), $result['columns'] );
        $result = expImportInfluxDbSource::test( $this->options( array( 'query' => 'from(bucket: "fail") |> range(start: -1h)' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'not found', $result['message'] );
        $this->assertStringNotContainsString( 'tok-Influx-Secret-81', $result['message'] );
        $this->assertStringContainsString( '[secret env:SQ688_INFLUX_TOKEN]', $result['message'] );
        $result = expImportInfluxDbSource::test( $this->options( array( 'language' => 'influxql', 'query' => 'SELECT * FROM fail' ) ) );
        $this->assertFalse( $result['ok'] );
        $this->assertStringContainsString( 'measurement not found', $result['message'] );
        $this->requests = array();
        $result = expImportInfluxDbSource::test( $this->options( array( 'token_secret' => 'sq688_no_such_token' ) ) );
        $this->assertStringContainsString( "'sq688_no_such_token' is not set", $result['message'] );
        $this->assertFalse( expImportInfluxDbSource::test( $this->options( array( 'base_url' => 'http://172.16.0.4:8086' ) ) )['ok'] );
        $this->assertSame( array(), $this->requests, 'nothing is sent to a refused server' );
    }

    public function testDrivers(): void
    {
        expImportInfluxDbSource::$environment = array( 'allow_url_fopen' => true, 'openssl' => true );
        $drivers = expImportInfluxDbSource::drivers();
        $this->assertSame( array( 'http', 'https' ), array_keys( $drivers ) );
        foreach ( array( 'label', 'available', 'via', 'worker', 'worker_php', 'needs' ) as $key )
            $this->assertArrayHasKey( $key, $drivers['http'] );
    }
}
