<?php

namespace WPSmsTwoWay\Services\Setting;

use WPSmsTwoWay\Utils\WPListTable;
use WPSmsTwoWay\Models\IncomingMessage;
use WPSmsTwoWay\Models\Command;

class InboxPage extends WPListTable
{
    const TW_MESSAGES_LIMIT = 15;

    /**
     * Nonce for single action
     *
     * @var string
     */
    private $singleActionNonce;


    public function __construct()
    {
        parent::__construct([
            'plural'   => __('incoming-messages', 'wp-sms-two-way'),
            'singular' => __('incoming-message', 'wp-sms-two-way'),
            'ajax'     => false
        ]);

        $this->setSingleActionNonce();
        $this->setColumnHeaders();
        $this->setPaginationArgs();
        $this->handleRowActions();
        $this->handleBulkActions();
    }




    /**========================================================================
     *                           Setting up Data
     *========================================================================**/


    /**
     * Define table columns
     *
     * Overriding parent
     *
     * @return array
     */
    public function get_columns()
    {
        return [
            'cb'            => 'cb',
            'id'            => __('ID', 'wp-sms-two-way'),
            'sender_number' => __('Sender Number', 'wp-sms-two-way'),
            'text'          => __('Text', 'wp-sms-two-way'),
            'command_title' => __('Command', 'wp-sms-two-way'),
            'action_status' => __('Command Status', 'wp-sms-two-way'),
            'gateway'       => __('Gateway', 'wp-sms-two-way'),
            'received_at'   => __('Received At', 'wp-sms-two-way'),
            'row_actions'   => ''
        ];
    }


    /**
     * Define which columns are hidden
     *
     * Overriding parent
     *
     * @return array
     */
    public function get_hidden_columns()
    {
        return [];
    }

    /**
     * Define the sortable columns
     *
     * Overriding parent
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        return [
            'id'          => ['id', 'asc'],
            'received_at' => ['received_at', 'desc']
        ];
    }

    /**
     * Set table's colum headers
     *
     * @return void
     */
    private function setColumnHeaders()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
    }

    /**
     * Set Pagination Arguments
     *
     * @return void
     */
    private function setPaginationArgs()
    {
        $this->set_pagination_args([
            'total_items' => IncomingMessage::count(),
            'per_page'    => self::TW_MESSAGES_LIMIT,
        ]);
    }

    /**
     * Prepare the items for the table to process
     *
     * @return void
     */
    public function prepare_items()
    {
        $searchKeyWord  = $_REQUEST['s'] ?? null;

        // Check if user has requested the page as search
        if ($searchKeyWord) {
            $query = IncomingMessage::with('command')->where('text', 'LIKE', "%$searchKeyWord%")->orWhere('sender_number', 'LIKE', "%$searchKeyWord%");
        } else {
            $query = IncomingMessage::with('command');
        }

        // Query the pagination
        $orderBy        = $_GET['orderby'] ?? 'id';
        $orderDirection = $order = $_GET['order'] ?? 'desc';
        $currentPage    = $this->get_pagenum();
        $limit          = self::TW_MESSAGES_LIMIT;
        $offset         = (($currentPage-1) * $limit);

        $messages = $query->orderBy($orderBy, $order)->skip($offset)->take($limit)->get();

        // Fetch the items
        $this->items = $messages->toArray();

        // Mark unread fetched messages as read
        foreach ($messages as $message) {
            $message->markAsRead();
        };
    }

    /**========================================================================
     *                           Rendering Columns
     *========================================================================**/


    /**
     * Render id column
     *
     * @param array $item
     * @return void
     */
    public function column_id($item)
    {
        $newMessage = !isset($item['read_at']) ? 'new': null;
        return "<span class='id $newMessage'>".$item['id']."</span>";
    }

    /**
     * Render sender number column
     *
     * @param array $item
     * @return string html
     */
    public function column_sender_number($item)
    {
        return "<span class='number'>".esc_html($item['sender_number'])."</span>";
    }

    /**
     * Render text column
     *
     * @param array $item
     * @return string html
     */
    public function column_text($item)
    {
        return '<p>'.esc_html($item['text']).'</p>';
    }

    /**
     * Render command title column
     *
     * @param array $item
     * @return string html
     */
    public function column_command_title($item)
    {
        $command = Command::find($item['command_id']);
        if ($command) {
            $commandPostObj = $command->getPostObject();
            return "<a href='".get_edit_post_link($commandPostObj)."'>{$commandPostObj->post_title}</a>";
        }
        return isset($item['command_id']) ? '<p class="command-is-deleted">Command is deleted</p>' : '<p class="no-command-found">No command was found</p>';
    }

    /**
     * Render action status column
     *
     * @param array $item
     * @return string html
     */
    public function column_action_status($item)
    {
        if (is_null($item['command'])) {
            return '-';
        }
        if ($item['action_status']->success) {
            return "<p class='action-successful'>".__('Successful', 'wp-sms-two-way')."</p>";
        }
        return "<p class='action-failed'>".__('Failed', 'wp-sms-two-way')."</p>";
    }

    /**
     * Render checkbox column
     *
     * @param array $item
     * @return string html
     */
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="selected_messages[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * Render row actions columns
     *
     * @param array $item
     * @return string html
     */
    public function column_row_actions($item)
    {
        return $this->addRowActions($item);
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  array $item Data
     * @param  string $column_name Current column name
     *
     * @return string html
     */
    public function column_default($item, $column_name)
    {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : null;
    }

    /**
     * Message to be shown when there is no incoming message.
     *
     * @return string
     */
    public function no_items()
    {
        _e('No messages found.');
    }


    /**========================================================================
     *                           Action Handling
     *========================================================================**/


    private function setSingleActionNonce()
    {
        $this->singleActionNonce = wp_create_nonce('wpsms-tw-inbox-single-action');
    }

    /**
     * Add row actions
     *
     * This method should be used in one of columns
     *
     * @param array $item
     * @return string html
     */
    private function addRowActions($item)
    {
        $actions = array(
            // 'edit'      => sprintf('<a href="?page=%s&action=%s&message_id=%s">Edit</a>', $_REQUEST['page'], 'edit', $item['id']),
            'delete'    => sprintf('<a href="?page=%s&action=%s&message_id=%s&_wpnonce=%s">Delete</a>', $_REQUEST['page'], 'delete', $item['id'], $this->singleActionNonce),
        );

        return $this->row_actions($actions);
    }

    /**
     * Handle row actions
     *
     * @return void
     */
    private function handleRowActions()
    {
        $action    = $_GET['action'] ?? null;
        $messageId = $_GET['message_id'] ?? null;
        $message   = IncomingMessage::find($messageId);

        // Just to be sure
        if (!$action || !$message) {
            return;
        }

        if (! wp_verify_nonce($_REQUEST['_wpnonce'], 'wpsms-tw-inbox-single-action')) {
            wp_die('Not authorized!');
        }

        switch ($action) {
            case 'delete':
                $message->delete();
                WPSmsTwoWay()->getPlugin()->redirect()->back()->withNotice("Message #{$messageId} was successfully deleted.", 'success')->now();
        }
    }

    /**
     * Define bulk actions
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        return [
            'delete' => 'Delete'
        ];
    }


    private function handleBulkActions()
    {
        // First, lets check if user has intended to use bulk actions
        $action = $this->current_action();
        if (!$action) {
            return;
        }
        
        // Then the nonce
        if (! wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
            wp_die('Not authorized!');
        }

        // And check if any message is selected
        $selectedMessages = $_REQUEST['selected_messages'] ?? [];
        if (empty($selectedMessages)) {
            WPSmsTwoWay()->getPlugin()->redirect()->back()->withNotice("No message was selected.", 'error')->now();
        }

        // Time for action
        switch ($action) {
            case 'delete':
                IncomingMessage::whereIn('id', $selectedMessages)->delete();
                WPSmsTwoWay()->getPlugin()->redirect()->back()->withNotice("Selected messages were successfully deleted.", 'success')->now();
        }
    }
}
