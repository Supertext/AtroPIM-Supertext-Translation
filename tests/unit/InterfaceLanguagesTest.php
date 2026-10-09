<?php

declare(strict_types=1);

namespace SupertextTranslation\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SupertextTranslation\Api\SupertextException;
use SupertextTranslation\Translation\Messages;
use SupertextTranslation\Translation\Settings;

/** The interface texts exist in English, German, French and Italian, and the messages use them. */
final class InterfaceLanguagesTest extends TestCase
{
    private const DIR = __DIR__ . '/../../app/Resources/i18n/';

    /** @return iterable<string, array{string, string}> */
    public static function files(): iterable
    {
        foreach (['de_DE', 'fr_FR', 'it_IT'] as $language) {
            foreach (['Action', 'Connection'] as $scope) {
                yield "$language/$scope" => [$language, $scope];
            }
        }
    }

    #[DataProvider('files')]
    public function testSameKeysAndPlaceholdersAsEnglish(string $language, string $scope): void
    {
        $english = self::flatten(self::read('en_US', $scope));
        $texts   = self::flatten(self::read($language, $scope));

        self::assertSame(array_keys($english), array_keys($texts), "$language/$scope.json has other keys than English");

        foreach ($english as $key => $text) {
            self::assertNotSame('', trim($texts[$key]), "$language: $key is empty");
            self::assertSame(self::placeholders($text), self::placeholders($texts[$key]), "$language: placeholders of $key");
            self::assertSame(self::urls($text), self::urls($texts[$key]), "$language: links of $key");
        }
    }

    public function testEveryMessageKeyHasAnEnglishText(): void
    {
        $keys = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../app', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $code = (string) file_get_contents($file->getPathname());
                preg_match_all("/(?:key: |->text\\()'([a-z_]+)'/", $code, $matches);
                $keys = array_merge($keys, $matches[1]);
                // Keys chosen in expressions: $messages->text($cond ? 'a' : 'b'), the HTTP status match
                preg_match_all("/->text\\([^?;]*\\? '([a-z_]+)' : '([a-z_]+)'/", $code, $matches);
                $keys = array_merge($keys, $matches[1], $matches[2]);
                preg_match_all("/=> \\['([a-z_]+)', '/", $code, $matches);
                $keys = array_merge($keys, $matches[1]);
            }
        }

        $keys = array_unique($keys);
        self::assertGreaterThan(25, \count($keys));

        foreach ($keys as $key) {
            self::assertArrayHasKey(Messages::name($key), Messages::englishTexts(), "no English text for $key");
        }
    }

    public function testMessagesInTheUsersLanguageWithEnglishFallback(): void
    {
        $german = self::read('de_DE', 'Action')['messages'];
        $lookup = static fn (string $name): string => $name === Messages::name('kept') ? $name : ($german[$name] ?? $name);
        $de     = new Messages($lookup);

        self::assertSame('übersetzt (3 Felder)', $de->text('translated_many', ['count' => 3]));
        self::assertSame('kept, already translated', $de->text('kept'), 'falls back to English');
        self::assertSame('translated (3 fields)', (new Messages())->text('translated_many', ['count' => 3]));

        $auth = $de->exception(new SupertextException('Authentication failed.', 401, null, 'auth_failed', [], 'invalid key'));
        self::assertStringStartsWith('Die Anmeldung ist fehlgeschlagen. Prüfen Sie den Supertext-API-Schlüssel. (invalid key) Noch kein Supertext-Konto?', $auth);
        self::assertStringContainsString(Settings::API_KEY_URL, $auth);
        self::assertSame('Plain message.', $de->exception(new SupertextException('Plain message.')));
        self::assertSame('Supertext hat mit HTTP 418 geantwortet.', $de->exception(new SupertextException('x', 418, null, 'http_status', ['status' => 418])));
    }

    /** @return array<string, mixed> */
    private static function read(string $language, string $scope): array
    {
        $path = self::DIR . "$language/$scope.json";
        self::assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($data, "$path is not valid JSON");

        return $data;
    }

    /** @return array<string, string> */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $out += self::flatten($value, $prefix . $key . '.');
            } else {
                $out[$prefix . $key] = (string) $value;
            }
        }

        ksort($out);

        return $out;
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/\{\w+\}/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }

    /** @return list<string> */
    private static function urls(string $text): array
    {
        preg_match_all('#https?://[^\s,)]+#', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    }
}
