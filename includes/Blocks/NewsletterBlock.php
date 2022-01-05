<?php

namespace WP_SMS\Blocks;

use WP_SMS\Blocks\BlockAbstract;
use WP_SMS\Blocks\Helper;

class NewsletterBlock extends BlockAbstract
{
    protected $blockName = 'newsletter';
    protected $blockVersion = '1.0';

    protected function output($attributes)
    {

        return Helper::loadTemplate('subscribe-form.php', [
            'attributes' => $attributes,
        ]);
    }
}
