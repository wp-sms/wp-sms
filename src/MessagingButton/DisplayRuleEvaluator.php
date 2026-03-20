<?php

namespace WSms\MessagingButton;

defined('ABSPATH') || exit;

class DisplayRuleEvaluator
{
    /**
     * Determine whether the widget should display on the current page.
     */
    public function shouldDisplay(array $rules): bool
    {
        if (!($rules['auto_inject'] ?? true)) {
            return false;
        }

        if (!$this->passesVisibilityCheck($rules['visibility'] ?? 'everyone')) {
            return false;
        }

        $currentUrl = $this->getCurrentUrl();

        if (!empty($rules['include_urls']) && !$this->matchesAnyPattern($currentUrl, $rules['include_urls'])) {
            return false;
        }

        if (!empty($rules['exclude_urls']) && $this->matchesAnyPattern($currentUrl, $rules['exclude_urls'])) {
            return false;
        }

        return true;
    }

    private function passesVisibilityCheck(string $visibility): bool
    {
        return match ($visibility) {
            'logged_in' => is_user_logged_in(),
            'logged_out' => !is_user_logged_in(),
            default => true,
        };
    }

    private function getCurrentUrl(): string
    {
        $protocol = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return $protocol . '://' . $host . $uri;
    }

    /**
     * Check if the URL matches any of the given patterns.
     * Supports exact match, wildcard (*), and path prefix matching.
     */
    private function matchesAnyPattern(string $url, array $patterns): bool
    {
        $path = wp_parse_url($url, PHP_URL_PATH) ?: '/';

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);

            if ($pattern === '') {
                continue;
            }

            // Wildcard pattern
            if (str_contains($pattern, '*')) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
                if (preg_match($regex, $path) || preg_match($regex, $url)) {
                    return true;
                }
                continue;
            }

            // Full URL match
            if (str_starts_with($pattern, 'http')) {
                if (strcasecmp(rtrim($url, '/'), rtrim($pattern, '/')) === 0) {
                    return true;
                }
                continue;
            }

            // Path prefix match
            if (str_starts_with($path, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
