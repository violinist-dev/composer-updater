<?php

namespace Violinist\ComposerUpdater\Tests\Integration;

class DrupalLeagueOauthLoginTest extends IntegrationBase
{
    protected $package = 'drupal/league_oauth_login';
    protected $directory = 'drupal-league_oauth_login';

    public function testEndToEnd()
    {
        if (version_compare(phpversion(), "7.1.0", "<=")) {
            self::assertTrue(true, 'Skipping DrupalLeagueOauthLoginTest for version ' . phpversion());
            return;
        }
        if (getenv('COMPOSER_VERSION') == 2) {
            self::assertTrue(true, 'Skipping test on composer version 2');
            return;
        }
        parent::testEndToEnd();
    }
}
