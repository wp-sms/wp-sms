<?php

namespace WSms\Verification;

use WSms\Branding\BrandingRepository;

defined('ABSPATH') || exit;

abstract class FormVerification
{
    use EnqueuesVerifyWidget;

    public function __construct(
        protected VerificationService $verificationService,
        protected BrandingRepository $brandingRepo,
    ) {
    }

    /**
     * Render a verify-mount container + hidden token field.
     */
    public static function renderVerifyHtml(string $channel, string $inputSelector, string $tokenName, array $options = []): string
    {
        $attrs = [
            'data-wsms-channel' => $channel,
            'data-wsms-input'   => $inputSelector,
            'data-wsms-token'   => "input[name=\"{$tokenName}\"]",
        ];
        if (!empty($options['scope'])) {
            $attrs['data-wsms-scope'] = $options['scope'];
        }
        if (!empty($options['skip'])) {
            $attrs['data-wsms-skip'] = $options['skip'];
        }

        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= sprintf(' %s="%s"', $k, esc_attr($v));
        }

        // Use <span> not <div> — CF7 wraps fields in <span>, and a <div> inside
        // a <span> causes browsers to auto-close the <span>, ejecting the mount
        // container out of the scope parent.  display:block so it still behaves
        // like a block container for the Preact widget.
        return sprintf(
            '<span class="wsms-verify-mount" style="display:block"%s></span><input type="hidden" name="%s" value="" />',
            $attrStr,
            esc_attr($tokenName),
        );
    }

    protected function enqueueAssets(): void
    {
        $this->enqueueVerifyWidget($this->brandingRepo->get('primary_color'));
        wp_enqueue_script('wsms-verify-mounter');
    }
}
