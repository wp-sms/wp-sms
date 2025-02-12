<?php

namespace WP_SMS\Admin\OnBoarding;

class WizardHelper
{
    public static function slugToClassName($slug)
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    }

    public static function generateStepUrl($stepSlug, $wizardSlug)
    {
        return admin_url("admin.php?page={$wizardSlug}&step={$stepSlug}");
    }

    public static function redirectToStep($stepSlug, $wizardSlug)
    {
        $url = self::generateStepUrl($stepSlug, $wizardSlug);
        wp_redirect($url);
        exit;
    }
}