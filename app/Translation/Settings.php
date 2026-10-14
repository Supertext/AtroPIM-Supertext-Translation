<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for AtroPIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace SupertextTranslation\Translation;

use SupertextTranslation\Api\SupertextClient;
use SupertextTranslation\Api\SupertextException;
use SupertextTranslation\Sdk\Http\CurlTransport;
use SupertextTranslation\Sdk\V1\Languages;
use SupertextTranslation\Sdk\V1\SupertextException as SdkException;

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

    /** @var array<string, string> AtroCore language code => Supertext language code (the connection's language mapping) */
    private array $languageCodes;

    /** @param bool|null $versionReports the connection's "Send version information to Supertext"; null: not set (on) */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $environment = 'live',
        private readonly string $customUrl = '',
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
        string $languageCodes = '',
        private readonly ?bool $versionReports = null,
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
            isset($data['supertextVersionReports']) ? (bool) $data['supertextVersionReports'] : null,
        );
    }

    /** The "Send version information to Supertext" setting; null when it was never saved (on). */
    public function versionReports(): ?bool
    {
        return $this->versionReports;
    }

    /** @return array<string, string> the language mapping: AtroCore language => Supertext code */
    public function languageMapping(): array
    {
        return $this->languageCodes;
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

    /**
     * Supertext target code for an AtroCore language: the connection's language mapping, else the
     * Supertext code it equals apart from case and _/- (de_CH → de-CH, es → es). No guessing: anything
     * else throws unsupported_language and nothing is sent.
     *
     * @throws SupertextException key unsupported_language
     */
    public function languageCode(string $language): string
    {
        try {
            return Languages::target($language, $this->languageCodes);
        } catch (SdkException $e) {
            throw SupertextClient::fromSdk($e);
        }
    }

    /**
     * Supertext source code for an AtroCore language, from the same mapping (a mapped de-CH is sent as
     * de), else the matching Supertext source language (en_US → en).
     *
     * @throws SupertextException key unsupported_language
     */
    public function sourceCode(string $language): string
    {
        try {
            return Languages::source($language, $this->languageCodes);
        } catch (SdkException $e) {
            throw SupertextClient::fromSdk($e);
        }
    }

    /** The target code, or null when the language has none (not mapped). */
    public function languageCodeOrNull(string $language): ?string
    {
        try {
            return $this->languageCode($language);
        } catch (SupertextException) {
            return null;
        }
    }

    /**
     * One line per AtroCore language for the connection's "Languages" field: its Supertext code (and
     * the source code for the main language), "not mapped", and a note where Supertext offers no
     * formal/informal tone.
     *
     * @param list<string> $languages the main language first
     */
    public function languageStatus(array $languages, string $mainLanguage, Messages $messages): string
    {
        $lines = [];

        foreach ($languages as $language) {
            $target = $this->languageCodeOrNull($language);

            if ($target === null) {
                $line = $messages->text('language_not_mapped', ['language' => $language]);
            } else {
                $line = $messages->text('language_mapped', ['language' => $language, 'code' => $target]);

                if (Languages::supportsPoliteness($target) === false) {
                    $line .= ' ' . $messages->text('language_no_politeness');
                } elseif (!Languages::isSupportedTarget($target)) {
                    $line .= ' ' . $messages->text('language_unlisted');
                }
            }

            if ($language === $mainLanguage) {
                try {
                    $line .= ' ' . $messages->text('language_source', ['code' => $this->sourceCode($language)]);
                } catch (SupertextException) {
                    $line .= ' ' . $messages->text('language_source_not_mapped');
                }
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    public function client(): SupertextClient
    {
        return new SupertextClient($this->apiKey(), $this->baseUrl(), new CurlTransport(), $this->timeout());
    }

    /**
     * "de_DE = de-CH" per line (also "de_DE: de-CH", "zh_CN = zh-Hans", "gsw_CH = gsw-u-sd-chzh");
     * invalid lines are ignored.
     *
     * @return array<string, string>
     */
    public static function parseLanguageCodes(string $text): array
    {
        $codes = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match('/^\s*([a-z]{2,3}(?:_[A-Za-z0-9]+)*)\s*[=:]\s*([A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8})*)\s*$/', $line, $m)) {
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
