<?php

namespace WPSmsTwoWay\Models;

use WPSmsTwoWay\Services\RestApi\Exceptions\SendRestResponse;
use WPSmsTwoWay\Services\Webhook\Exceptions\NoCommandFound;
use WPSmsTwoWay\Services\Action\Exceptions\ActionException;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomingMessage extends AbstractModel
{
    use SoftDeletes;

    protected $table = 'sms_two_way_incoming_messages';
    public $timestamps = false;

    public function createTable($table)
    {
        //* INFO received_at and read_at are stored as Unix timestamps, to keep things unified
        $table->id();
        $table->string('sender_number');
        $table->string('gateway');
        $table->text('text')->nullable();
        $table->foreignId('command_id')->nullable();  //* if null, message is not a valid command
        $table->string('command_name');
        $table->json('command_args');
        $table->json('action_status')->nullable();
        $table->integer('received_at');
        $table->integer('read_at')->nullable();       //* if null, message is not read yet
        $table->softDeletes();
    }

    protected $casts = [
        'command_args'  => 'array',
        'action_status' => 'object'
    ];

    protected $fillable = [
        'read_at',
    ];


    /*=========================================== Accessors ===========================================*/

    /**
     * Localize the unix timestamp stored in database to local wordpress time
     *
     * @param integer $value
     * @return string|null
     */
    public function getReceivedAtAttribute($value)
    {
        return isset($value) ? wp_date("Y-m-d H:i:s", $value) : null;
    }

    /**
     * Localize the unix timestamp stored in database to local wordpress time
     *
     * @param integer $value
     * @return string|null
     */
    public function getReadAtAttribute($value)
    {
        return isset($value) ? wp_date("Y-m-d H:i:s", $value) : null;
    }



    /**
     * Get the associated command with this message
     *
     * @return void
     */
    public function command()
    {
        return $this->belongsTo(Command::class, 'command_id');
    }

    /**
     * Mark a single message as read by updating it's read_at field, if message is not read yet
     *
     * @return void
     */
    public function markAsRead()
    {
        if (!isset($this->read_at)) {
            $this->update(['read_at'=> time()]);
        }
    }

    /**
     * Get unread messages total count
     *
     * @return int
     */
    public static function countOfUnreadMessages()
    {
        //! This function has external use, and there are scenarios that the plugin may not be booted.
        try {
            return self::where('read_at', null)->count();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Create a new incoming message
     *
     * @param string $gateway
     * @param string|null $text
     * @param string|null $senderNumber
     * @return void
     */
    public static function newMessage($gateway, $text, $senderNumber)
    {
        $message = new self();
        $message->sender_number = sanitize_text_field($senderNumber);
        $message->gateway = $gateway;
        $message->text = trim(sanitize_text_field($text));
        $message->received_at = time();
        $message->parseMessage();

        $command = Command::findCommandByName($message->command_name);
        if ($command) {
            $message->command_id = $command->id;
        }

        return $message;
    }

    /**
     * Parse the message text body
     *
     * Extract the command name and arguments from message text body
     *
     * @return array
     */
    private function parseMessage()
    {
        $message = explode(' ', $this->text);
        $this->command_name = $message[0];
        $this->command_args = array_slice($message, 1);
    }

    /**
     * Send sms response
     *
     * @return void
     */
    private function sendSmsResponse()
    {
        if ($this->action_status->success) {                           // Action is fired successfully, then send success message if enabled
            $responseData = $this->command->response_data;
            if ($responseData->success->status == 'enabled') {
                wp_sms_send([$this->sender_number], $responseData->success->text);
            }
        } else {                                                       // Action is not fired successfully, then send failure message if enabled
            $responseData = $this->command->response_data;
            if ($responseData->failure->status == 'enabled') {
                wp_sms_send([$this->sender_number], $responseData->failure->text);
            }
        }
    }

    /**
     * Fire message's action if message has a valid command
     *
     * @return void
     */
    public function fireAction()
    {
        if (!$this->command || $this->command->status == 'disabled') {
            $this->action_status = [
                'success' => false,
                'message' => 'No active command found'
            ];
            return;
        }

        try {
            $this->command->action()->call($this);
            $this->action_status = [
                'success' => true,
                'message' => 'Action is fired successfully'
            ];
        } catch (ActionException $exception) {
            $this->action_status = [
                'success'  => false,
                'message' => $exception->getMessage()
            ];
        } catch (\Throwable $exception) {
            $this->action_status = [
                'success'   => false,
                'unexpected'  => true,
                'message'   => $exception->getMessage()
            ];
        } finally {
            $this->sendSmsResponse();
        }
    }
}
