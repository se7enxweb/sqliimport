<?php

use PHPUnit\Framework\TestCase;

/**
 * Secrets are referenced by name; an environment variable is one way to give one.
 */
class SecretsTest extends TestCase
{
    public function testEnvironmentSecrets(): void
    {
        putenv( 'SQ688_TEST_SECRET=s3cr3t-value' );
        $this->assertSame( 's3cr3t-value', expImportSecrets::get( 'env:SQ688_TEST_SECRET' ) );
        $this->assertNull( expImportSecrets::get( 'env:SQ688_NO_SUCH_VARIABLE' ) );
        $this->assertNull( expImportSecrets::get( 'bad name with spaces' ) );
        $this->assertSame( 'login failed for [secret env:SQ688_TEST_SECRET]', expImportSecrets::mask( 'login failed for s3cr3t-value', array( 'env:SQ688_TEST_SECRET' ) ) );
        putenv( 'SQ688_TEST_SECRET' );
    }
}
