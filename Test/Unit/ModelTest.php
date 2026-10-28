<?php

declare(strict_types=1);

namespace GlueAgency\Backoffice\Test\Unit;

use GlueAgency\Backoffice\Model\Client;
use GlueAgency\Backoffice\Model\InstallUid;
use GlueAgency\Backoffice\Model\Packages;
use GlueAgency\Backoffice\Model\Report;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    public function testPlatformRequirementsAreNotPackages(): void
    {
        $this->assertTrue(Packages::isPlatformRequirement('php'));
        $this->assertTrue(Packages::isPlatformRequirement('ext-intl'));
        $this->assertTrue(Packages::isPlatformRequirement('lib-curl'));
        $this->assertTrue(Packages::isPlatformRequirement('composer-plugin-api'));
        $this->assertFalse(Packages::isPlatformRequirement('magento/product-community-edition'));
        $this->assertFalse(Packages::isPlatformRequirement('mollie/magento2'));
    }

    public function testEditionReadsAsAdobeNamesIt(): void
    {
        $this->assertSame('Open Source', Report::edition('Community'));
        $this->assertSame('Adobe Commerce', Report::edition('Enterprise'));
        $this->assertSame('Adobe Commerce B2B', Report::edition('B2B'));
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
