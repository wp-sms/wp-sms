<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class AwesomeSupportTicketNotification extends Notification
{
    protected $ticket;

    protected $variables = [
        '%ticket_content%'  => 'getTicketContent',
        '%ticket_title%'    => 'getTicketTitle',
        '%ticket_username%' => 'getTicketCreator',
        '%ticket_status%'   => 'getTicketStatus'
    ];

    public function __construct($ticketId = false)
    {
        if ($ticketId) {
            $this->ticket = get_post($ticketId);
        }
    }

    public function getTicketContent()
    {
        return $this->ticket->post_content;
    }

    public function getTicketTitle()
    {
        if ($this->ticket->post_parent) {
            $parentTicket = get_post($this->ticket->post_parent);
            return $parentTicket->post_title;
        } else {
            return $this->ticket->post_title;
        }
    }

    public function getTicketCreator()
    {
        if ($this->ticket->post_parent) {
            $parentTicket = get_post($this->ticket->post_parent);
            $user         = get_userdata($parentTicket->post_author);
        } else {
            $user = get_userdata($this->ticket->post_author);
        }
        return isset($user->user_login) ? $user->user_login : '';
    }

    public function getTicketStatus()
    {
        return $this->ticket->post_status;
    }
}