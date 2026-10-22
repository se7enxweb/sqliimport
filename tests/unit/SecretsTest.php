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

    /**
     * Without the kernel's secret store (Exponential before 6.0.15) the tool's own references work as before, a
     * value in an address (rawurlencoded) is masked too, and there is no Secrets page to link to.
     */
    public function testWithoutTheKernelStore(): void
    {
        expImportSecrets::$kernelStore = false;
        try
        {
            putenv( 'SQ688_TEST_SECRET=p@ss word/1' );
            $this->assertSame( 'p@ss word/1', expImportSecrets::get( 'env:SQ688_TEST_SECRET' ) );
            $this->assertSame( 'GET https://u:[secret env:SQ688_TEST_SECRET]@h/ failed',
                               expImportSecrets::mask( 'GET https://u:' . rawurlencode( 'p@ss word/1' ) . '@h/ failed', array( 'env:SQ688_TEST_SECRET' ) ) );
            $this->assertSame( '', expImportSecrets::manageUrl() );
            $this->assertFalse( expImportSecrets::kernelStore() );
            try
            {
                expImportSecrets::required( 'env:SQ688_NO_SUCH_VARIABLE', 'Test' );
                $this->fail( 'a missing secret is an error' );
            }
            catch ( expImportSourceException $e )
            {
                $this->assertStringContainsString( "the secret 'env:SQ688_NO_SUCH_VARIABLE' is not set", $e->getMessage() );
                $this->assertStringNotContainsString( 'Setup > Secrets', $e->getMessage() );
            }
        }
        finally
        {
            putenv( 'SQ688_TEST_SECRET' );
            expImportSecrets::$kernelStore = null;
        }
        // the kernel store is used only where its class exists (guarded, never required)
        $this->assertSame( class_exists( 'expSecrets' ), expImportSecrets::kernelStore() );
        $source = (string)file_get_contents( dirname( __DIR__, 2 ) . '/classes/exp/expimportsecrets.php' );
        $this->assertStringContainsString( "return class_exists( 'expSecrets' );", $source );
        $this->assertStringContainsString( 'expSecrets::get( $ref, self::iniSecrets() )', $source, 'the INI secrets are the fallback of the kernel store' );
    }
}
