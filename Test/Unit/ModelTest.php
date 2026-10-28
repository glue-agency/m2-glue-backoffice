<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Test\Unit;

use GlueAgency\Backoffice\Model\Client;
use GlueAgency\Backoffice\Model\InstallUid;
use GlueAgency\Backoffice\Model\Language;
use GlueAgency\Backoffice\Model\Plugins;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    public function testLanguageFromLocale(): void
    {
        $this->assertSame('nl-BE', Language::fromLocale('nl_BE'));
        $this->assertSame('en-US', Language::fromLocale(null));
    }

    public function testCorePackageFilter(): void
    {
        $this->assertTrue(Plugins::isCorePackage('magento/module-catalog'));
        $this->assertFalse(Plugins::isCorePackage('mollie/magento2'));
        $this->assertFalse(Plugins::isCorePackage('glue-agency/m2-glue-auth'));
    }

    public function testIsUnder(): void
    {
        $this->assertTrue(Plugins::isUnder('/var/www/app/code/Acme/Foo', '/var/www/app/code'));
        $this->assertFalse(Plugins::isUnder('/var/www/app/codex/Foo', '/var/www/app/code'));
    }

    public function testVendorOf(): void
    {
        $this->assertSame('Mollie', Plugins::vendorOf('Mollie_Payment'));
    }

    public function testUidIsV4(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            InstallUid::generate()
        );
    }

    public function testErrorsAreFlattened(): void
    {
        $errors = Client::errors(422, ['success' => false, 'errors' => ['title' => ['Required.', 'Too long.']]], '');
        $this->assertSame(['title: Required.', 'title: Too long.'], $errors);
        $this->assertStringContainsString('HTTP 502', Client::errors(502, null, 'Bad gateway')[0]);
    }
}
