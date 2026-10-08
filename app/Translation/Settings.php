<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

use SupertextTranslation\Api\CurlTransport;
use SupertextTranslation\Api\SupertextClient;
use SupertextTranslation\Api\SupertextException;

/**
 * The settings of one Supertext connection (Administration → Connections, type "Supertext"),
 * with the environment variables SUPERTEXT_API_KEY and SUPERTEXT_API_URL as fallback and
 * override. No AtroCore dependencies: the values are passed in.
 */
final class Settings
{
    public const SIGNUP_URL = 'https://www.supertext.com/person/en/account/signin';
    public const API_KEY_URL = 'https://www.supertext.com/en/integrations/api';
    public const DEFAULT_TIMEOUT = 180;
    public const KEY_HELP = 'No Supertext account yet? Create one at ' . self::SIGNUP_URL . '. Generate your API key at ' . self::API_KEY_URL . ' (supertext.com → Integrations → API; requires the Admin role).';

    /** @var array<string, string> AtroCore language code => Supertext language code */
    private array $languageCodes;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $environment = 'live',
        private readonly string $customUrl = '',
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
        string $languageCodes = '',
    ) {
        $this->languageCodes = self::parseLanguageCodes($languageCodes);
    }

    /** @param array<string, mixed> $data the connection's data fields */
    public static function fromConnectionData(array $data, string $decryptedApiKey): self
    {
        return new self(
            $decryptedApiKey,
            (string) ($data['supertextEnvironment'] ?? 'live'),
            (string) ($data['supertextApiUrl'] ?? ''),
            (int) ($data['supertextTimeout'] ?? self::DEFAULT_TIMEOUT),
            (string) ($data['supertextLanguageCodes'] ?? ''),
        );
    }

    public function apiKey(): string
    {
        $key = SupertextClient::normalizeKey($this->apiKey);

        return $key !== '' ? $key : SupertextClient::normalizeKey(self::env('SUPERTEXT_API_KEY'));
    }

    public function apiKeySource(): string
    {
        if (SupertextClient::normalizeKey($this->apiKey) !== '') {
            return 'connection';
        }

        return $this->apiKey() !== '' ? 'environment' : '';
    }

    public function baseUrl(): string
    {
        $override = self::env('SUPERTEXT_API_URL');

        if ($override !== '') {
            return $override;
        }

        return SupertextClient::baseUrlFor($this->environment, $this->environment === 'custom' ? $this->customUrl : '');
    }

    public function timeout(): int
    {
        return $this->timeout >= 30 && $this->timeout <= 1800 ? $this->timeout : self::DEFAULT_TIMEOUT;
    }

    /** Supertext language for an AtroCore language: an override from the connection, else de_CH -> de-CH. */
    public function languageCode(string $language): string
    {
        return $this->languageCodes[$language] ?? Planner::supertextCode($language);
    }

    public function client(): SupertextClient
    {
        return new SupertextClient($this->apiKey(), $this->baseUrl(), new CurlTransport(), $this->timeout());
    }

    /**
     * "de_DE = de-CH" per line (also "de_DE: de-CH"); invalid lines are ignored.
     *
     * @return array<string, string>
     */
    public static function parseLanguageCodes(string $text): array
    {
        $codes = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match('/^\s*([a-z]{2,3}(?:_[A-Za-z0-9]+)*)\s*[=:]\s*([A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*)\s*$/', $line, $m)) {
                $codes[$m[1]] = $m[2];
            }
        }

        return $codes;
    }

    /** The message for an API error; authentication errors get the account and API key links. */
    public static function explain(SupertextException $e): string
    {
        $message = $e->getMessage();

        if (\in_array($e->getCode(), [401, 403], true)) {
            $message .= ' ' . self::KEY_HELP;
        }

        return $message;
    }

    private static function env(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) ? trim($value) : '';
    }
}
