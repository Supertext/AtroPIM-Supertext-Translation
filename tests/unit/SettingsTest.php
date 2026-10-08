<?php

namespace SupertextTranslation\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SupertextTranslation\Api\SupertextClient;
use SupertextTranslation\Api\SupertextException;
use SupertextTranslation\Translation\Settings;

class SettingsTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SUPERTEXT_API_KEY');
        putenv('SUPERTEXT_API_URL');
        unset($_SERVER['SUPERTEXT_API_KEY'], $_ENV['SUPERTEXT_API_KEY'], $_SERVER['SUPERTEXT_API_URL'], $_ENV['SUPERTEXT_API_URL']);
    }

    public function testConnectionData(): void
    {
        $settings = Settings::fromConnectionData([
            'supertextEnvironment'   => 'custom',
            'supertextApiUrl'        => 'https://example.test/v1/',
            'supertextTimeout'       => 600,
            'supertextLanguageCodes' => "de_DE = de-CH\nnonsense\nfr_FR: fr-CH",
        ], 'Supertext-Auth-Key abc');

        self::assertSame('abc', $settings->apiKey());
        self::assertSame('connection', $settings->apiKeySource());
        self::assertSame('https://example.test/v1/', $settings->baseUrl());
        self::assertSame(600, $settings->timeout());
        self::assertSame('de-CH', $settings->languageCode('de_DE'));
        self::assertSame('fr-CH', $settings->languageCode('fr_FR'));
        self::assertSame('it-CH', $settings->languageCode('it_CH'));
    }

    public function testDefaultsAndEnvironmentFallback(): void
    {
        $settings = new Settings('');
        self::assertSame('', $settings->apiKeySource());
        self::assertSame(SupertextClient::LIVE, $settings->baseUrl());
        self::assertSame(180, (new Settings('', timeout: 5))->timeout());

        putenv('SUPERTEXT_API_KEY=env-key');
        putenv('SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/');
        self::assertSame('env-key', $settings->apiKey());
        self::assertSame('environment', $settings->apiKeySource());
        self::assertSame('http://127.0.0.1:8765/v1/', $settings->baseUrl());
    }

    public function testExplainAddsKeyLinksToAuthenticationErrors(): void
    {
        self::assertStringContainsString(Settings::API_KEY_URL, Settings::explain(new SupertextException('Authentication failed.', 401)));
        self::assertStringContainsString(Settings::SIGNUP_URL, Settings::explain(new SupertextException('Authentication failed.', 403)));
        self::assertSame('Timed out.', Settings::explain(new SupertextException('Timed out.')));
    }
}
