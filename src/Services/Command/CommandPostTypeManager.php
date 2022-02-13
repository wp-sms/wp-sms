<?php

namespace WPSmsTwoWay\Services\Command;

use WPSmsTwoWay\Services\Action\ActionManager;
use WPSmsTwoWay\Services\Setting\AdminMenuManager;
use WPSmsTwoWay\Models\Command;

class CommandPostTypeManager
{
    private const POST_TYPE  = 'wpsms-command';

    /**
     * Register needed hooks for command post type
     *
     * @return void
     */
    public static function register()
    {
        add_action('init', function () {
            self::registerPostType();
        });

        add_action('add_meta_boxes', function () {
            self::registerMetaBoxes();
        });

        add_action("publish_".self::POST_TYPE, function ($postId) {
            self::storeCommandMetas($postId);
        });

        add_filter("manage_".self::POST_TYPE."_posts_columns", function ($columns) {
            return self::modifyColumns($columns);
        });

        add_action("manage_".self::POST_TYPE."_posts_custom_column", function ($columnKey, $postID) {
            self::modifyColumnsCallback($columnKey, $postID);
        }, 10, 2);

        add_filter('post_row_actions', function ($actions, $post) {
            return self::modifyActionRow($actions, $post);
        }, 10, 2);
    }

    /**
     * Register the command post type
     *
     * @return void
     */
    private static function registerPostType()
    {
        $labels = [
            'name'          => _x('Commands', 'Post Type General Name', 'wp-sms-two-way'),
            'singular_name' => _x('Command', 'Post Type Singular Name', 'wp-sms-two-way'),
            'menu_name'     => __('Commands', 'wp-sms-two-way'),
            'all_items'     => __('Commands', 'wp-sms-two-way'),
            'view_item'     => __('View Command', 'wp-sms-two-way'),
            'add_new_item'  => __('Add New Command ', 'wp-sms-two-way'),
            'add_new'       => __('Add New', 'wp-sms-two-way'),
            'edit_item'     => __('Edit Command', 'wp-sms-two-way'),
            'update_item'   => __('Update Command', 'wp-sms-two-way'),
            'search_items'  => __('Search Commands', 'wp-sms-two-way'),
        ];
        $args = [
            'labels'               => $labels,
            'public'               => false,
            'show_ui'              => true,
            'capability_type'      => 'post',
            'hierarchical'         => false,
            'exclude_from_search'  => true,
            'has_archive'          => false,
            'publicly_queryable'   => false,
            'rewrite'              => false,
            '_edit_link'           => 'post.php?post=%d',
            'supports'             => ['title'],
            'show_in_menu'         => AdminMenuManager::MENU_SLUG,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register meta boxes
     *
     * @return void
     */
    private static function registerMetaBoxes()
    {
        remove_meta_box('submitdiv', self::POST_TYPE, 'side');

        $metaBoxes = [
            'wp-sms-two-way-submit-meta-box'   => ['title' => __('Submit', 'wp-sms-two-way'),   'callback'  => 'renderSubmitMetaBox' ,  'context' => 'side',     'priority' => 'high'],
            'wp-sms-two-way-actions-meta-box'  => ['title' => __('Command', 'wp-sms-two-way'),  'callback'  => 'renderActionsMetaBox',  'context' => 'advanced', 'priority' => 'high'],
            'wp-sms-two-way-response-meta-box' => ['title' => __('Response', 'wp-sms-two-way'), 'callback' => 'renderResponseMetaBox',  'context' => 'advanced', 'priority' => 'default'],
            'wp-sms-two-way-preview-meta-box'  => ['title' => __('Preview', 'wp-sms-two-way'),  'callback'  => 'renderPreviewMetaBox',  'context' => 'side',     'priority' => 'low'],
        ];

        foreach ($metaBoxes as $key => $metaBox) {
            add_meta_box($key, $metaBox['title'], [self::class, $metaBox['callback']], self::POST_TYPE, $metaBox['context'], $metaBox['priority']);
        }
    }

    /**
     * Actions meta box render callback
     *
     * @param WP_Post $post
     * @param array $metaBox
     * @return void
     */
    public static function renderActionsMetaBox($post, $metaBox)
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        $allActions = $plugin->get(ActionManager::class)->getAllActions();

        wp_enqueue_script('wpsms-tw-actions', $plugin->getUrl() . '/assets/js/actions.js', ['jquery', 'wp-i18n'], '1.0.0');
        wp_localize_script('wpsms-tw-actions', 'WPSmsTwoWayActions', $allActions);
        wp_set_script_translations('wpsms-tw-actions', 'wp-sms-two-way', $plugin->basePath('languages'));

        $command = CommandFactory::getCommandByPostId($post->ID);

        echo $plugin->blade('command.meta_boxes.actions', [
            'allActions'     => $allActions,
            'commandName'    => $command->command_name ?? null,
            'selectedAction' => $command->action_reference ?? null,
        ]);
    }

    /**
     * Response meta box render callback
     *
     * @param WP_Post $post
     * @param array $metaBox
     * @return void
     */
    public static function renderResponseMetaBox($post, $metaBox)
    {
        echo WPSmsTwoWay()->getPlugin()->blade('command.meta_boxes.response', [
            'responseData' => CommandFactory::getCommandByPostId($post->ID)->response_data ?? null,
        ]);
    }

    /**
     * Submit meta box render callback
     *
     * @param WP_Post $post
     * @param array $metaBox
     * @return void
     */
    public static function renderSubmitMetaBox($post, $metaBox)
    {
        echo  WPSmsTwoWay()->getPlugin()->blade('command.meta_boxes.submit', [
            'post'    => $post,
            'status' => CommandFactory::getCommandByPostId($post->ID)->status ?? null,
        ]);
    }

    /**
     * SMS preview render callback
     *
     * @param WP_Post $post
     * @param array $metaBox
     * @return void
     */
    public static function renderPreviewMetaBox($post, $metaBox)
    {
        global $sms;
        $siteName   = get_bloginfo('name');

        echo  WPSmsTwoWay()->getPlugin()->blade('command.meta_boxes.preview', [
            'siteName'       => $siteName,
            'wpsmsInstance'  => $sms,
        ]);
    }

    /**
     *Save command post's metas
     *
     * @param integer $postId
     * @return void
     */
    public static function storeCommandMetas(int $postId)
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        $command = Command::firstOrNew([
            'post_id' => $postId,
        ]);

        try {
            $command->post_id = $postId;
            $command->storeStatus($_POST['command-status']);
            $command->storeAction($_POST['command-action']);
            $command->storeName($_POST['command-name']);
            $command->storeResponse($_POST['command-response'] ?? []);
            $command->save();
        } catch (Exceptions\StoreCommand $exception) {
            if (!$command->exists) {
                wp_delete_post($postId, true);
            }
            $plugin
                ->redirect()
                ->back()
                ->withNotice($exception->getMessage(), 'error')
                ->now();
        }
    }

    /**
     * Modify commands table columns
     *
     * @param array $columns
     * @return array|string[]
     */
    public static function modifyColumns($columns)
    {
        return array_merge($columns, [
            'command-name'     => 'Name',
            'command-action'   => 'Linked action',
            'command-status'   => 'Status',
        ]);
    }

    /**
     * @param string $columnKey
     * @param int $postId
     */
    public static function modifyColumnsCallback($columnKey, $postId)
    {
        $command = CommandFactory::getCommandByPostId($postId);

        echo WPSmsTwoWay()->getPlugin()->blade("command.columns.{$columnKey}", [
            'status'        => $command->status ?? null,
            'commandName'   => $command->command_name ?? null,
            'commandAction' => $command->action_reference ?? null,
        ]);
    }

    /**
     * Modify post's actions row
     *
     * @param string[] $actions
     * @param WP_Post $post
     * @return array|null
     */
    public static function modifyActionRow($actions, $post)
    {
        if ($post->post_type != self::POST_TYPE) {
            return $actions;
        }
        unset($actions['inline hide-if-no-js']);
        return $actions;
    }
}
