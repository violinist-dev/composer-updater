<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

class DrupalCommerceTest extends IntegrationBase
{
    protected $package = 'drupal/commerce';
    protected $directory = 'drupal-commerce';

    public function testEndToEnd() 
    {
        if (getenv('COMPOSER_VERSION') == 2) {
            self::assertTrue(true, 'Skipping test on composer version 2');
            return;
        }
        parent::testEndToEnd();
    }
}
