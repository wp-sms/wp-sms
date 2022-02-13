<?php

namespace WPSmsTwoWay\Services\Command;

use WPSmsTwoWay\Models\Command;

class CommandFactory
{
    /**
     * Create a new CommandDecorator instance
     *
     * @param integer $commandId
     * @return \WPSmsTwoWay\Models\Command
     */
    public static function getCommandByPostId(int $postId)
    {
        return Command::where('post_id', $postId)->first();
    }
}
