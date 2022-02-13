<?php

namespace WPSmsTwoWay\Models;

use WPSmsTwoWay\Enums\CommandStatus;
use WPSmsTwoWay\Services\Action\ActionManager;
use WPSmsTwoWay\Services\Command\Exceptions\StoreCommand;

class Command extends AbstractModel
{
    protected $table = 'sms_two_way_commands';

    protected $fillable = [
        'post_id'
    ];

    protected $casts = [
        'response_data' => 'object'
    ];

    public function createTable($table)
    {
        $table->id();
        $table->unsignedBigInteger('post_id');
        $table->foreign('post_id')->references('ID')->on('posts')->onUpdate('cascade')->onDelete('cascade');
        $table->enum('status', CommandStatus::toValues());
        $table->string('command_name');
        $table->string('action_reference');
        $table->json('response_data');
        $table->timestamps();
    }

    /**
     * Find command's action
     *
     * @return \WPSmsTwoWay\Services\Action\Action|false on failure
     */
    public function action()
    {
        return WPSmsTwoWay()->getPlugin()->get(ActionManager::class)->findAction($this->action_reference);
    }

    /**
     * Store Status in model object
     *
     * @param string $value
     * @return void
     */
    public function storeStatus(string $value)
    {
        // Validation
        if (!in_array($value, ['enabled', 'disabled'])) {
            throw new StoreCommand('Unauthorized value for status');
        }

        $this->status = $value;
    }

    /**
     * Store command's name meta
     *
     * @param string $value
     * @return void
     */
    public function storeName(string $value)
    {
        // Sanitization
        $value = trim($value);
        $value = sanitize_text_field($value);
        $value = str_replace(' ', '-', $value);

        // Validation
        if (empty($value)) {
            throw new StoreCommand('Command name must be filled');
        }
        if ($this->command_name != $value && self::where('command_name', $value)->exists()) {
            throw new StoreCommand('Command name must be unique');
        }

        $this->command_name = $value;
    }

    /**
     * Store command's action
     *
     * @param string $value
     * @return void
     */
    public function storeAction(string $value)
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        $allReferences = $plugin->get(ActionManager::class)->getAllActionReferences();

        // Validation
        if (!in_array($value, $allReferences)) {
            throw new StoreCommand('Unauthorized value for action');
        }

        $this->action_reference = $value;
    }

    /**
     * Store command's response data
     *
     * @param array $values
     * @return void
     */
    public function storeResponse(array $values)
    {
        $this->response_data = [
            'success' => [
                'status' => isset($values['success']['text']) ? 'enabled' : 'disabled',
                'text'   => isset($values['success']['text']) ? sanitize_textarea_field($values['success']['text']) : $this->response_data->success->text
            ],
            'failure' => [
                'status' => isset($values['failure']['text']) ? 'enabled' : 'disabled',
                'text'   => isset($values['failure']['text']) ? sanitize_textarea_field($values['failure']['text']) : $this->response_data->failure->text
            ]
        ];
    }

    /**
     * Find command by name
     *
     * @param string $commandName
     * @return \WPSmsTwoWay\Models\Command|null on failure.
     */
    public static function findCommandByName(string $commandName)
    {
        return self::where('command_name', $commandName)->first();
    }

    /**
     * Get command's post object
     *
     * @return WP_Post|null
     */
    public function getPostObject()
    {
        return get_post($this->post_id);
    }
}
