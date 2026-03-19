<?php

namespace WSms\Messaging\Inbound;

defined('ABSPATH') || exit;

class KeywordMatcher
{
    private const DEFAULT_STOP_KEYWORDS = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit'];
    private const DEFAULT_START_KEYWORDS = ['start', 'yes', 'unstop'];
    private const DEFAULT_HELP_KEYWORDS = ['help', 'info'];

    /** @var string[] */
    private readonly array $stopKeywords;
    /** @var string[] */
    private readonly array $startKeywords;
    /** @var string[] */
    private readonly array $helpKeywords;

    /**
     * @param string[] $customStopKeywords  Additional STOP keywords
     * @param string[] $customStartKeywords Additional START keywords
     */
    public function __construct(array $customStopKeywords = [], array $customStartKeywords = [])
    {
        $this->stopKeywords = array_merge(
            self::DEFAULT_STOP_KEYWORDS,
            array_map('strtolower', $customStopKeywords),
        );
        $this->startKeywords = array_merge(
            self::DEFAULT_START_KEYWORDS,
            array_map('strtolower', $customStartKeywords),
        );
        $this->helpKeywords = self::DEFAULT_HELP_KEYWORDS;
    }

    /**
     * Match message body against known keywords.
     *
     * CTIA requires exact single-word match — "Please stop" does NOT match.
     *
     * @return 'stop'|'start'|'help'|null
     */
    public function match(string $messageBody): ?string
    {
        $word = strtolower(trim($messageBody));

        if ($word === '') {
            return null;
        }

        if (in_array($word, $this->stopKeywords, true)) {
            return 'stop';
        }

        if (in_array($word, $this->startKeywords, true)) {
            return 'start';
        }

        if (in_array($word, $this->helpKeywords, true)) {
            return 'help';
        }

        return null;
    }

    /** @return string[] */
    public function getStopKeywords(): array
    {
        return $this->stopKeywords;
    }

    /** @return string[] */
    public function getStartKeywords(): array
    {
        return $this->startKeywords;
    }

    /** @return string[] */
    public function getHelpKeywords(): array
    {
        return $this->helpKeywords;
    }

    /** @return string[] */
    public static function getDefaultStopKeywords(): array
    {
        return self::DEFAULT_STOP_KEYWORDS;
    }

    /** @return string[] */
    public static function getDefaultStartKeywords(): array
    {
        return self::DEFAULT_START_KEYWORDS;
    }

    /** @return string[] */
    public static function getDefaultHelpKeywords(): array
    {
        return self::DEFAULT_HELP_KEYWORDS;
    }
}
