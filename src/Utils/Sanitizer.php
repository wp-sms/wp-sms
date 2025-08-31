<?php

namespace WP_SMS\Utils;
class Sanitizer
{
    /**
     * @return array
     */
    public static function allowedBodyTags(): array
    {
        $allowed = [
            'a'      => ['href' => true, 'rel' => true, 'target' => true],
            'br'     => [],
            'em'     => [],
            'strong' => [],
            'p'      => [],
            'ul'     => [],
            'ol'     => [],
            'li'     => [],
            'code'   => [],
            'pre'    => [],
        ];
        return apply_filters('wpsms_email_allowed_body_tags', $allowed);
    }

    /**
     * @param string $htmlOrText
     * @return string
     */
    public static function sanitizeBody(string $htmlOrText): string
    {
        $clean = wp_kses($htmlOrText, self::allowedBodyTags());

        $clean = preg_replace_callback('#<a [^>]*href=("|\')(.*?)\\1[^>]*>#i', function ($m) {
            $href = esc_url_raw($m[2]);
            $tag  = $m[0];
            if (stripos($tag, 'rel=') === false) $tag = str_replace('>', ' rel="nofollow noopener noreferrer">', $tag);
            if (stripos($tag, 'target=') === false) $tag = str_replace('>', ' target="_blank">', $tag);
            $tag = preg_replace('#href=("|\')(.*?)\\1#i', 'href="' . $href . '"', $tag);
            return $tag;
        }, $clean);

        return $clean;
    }

    /**
     * @param string $subject
     * @return string
     */
    public static function sanitizeSubject(string $subject): string
    {
        $subject = wp_strip_all_tags($subject);
        $subject = trim(preg_replace('/\s+/', ' ', $subject));
        return $subject;
    }
}

