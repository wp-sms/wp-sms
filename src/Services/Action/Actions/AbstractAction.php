<?php

namespace WPSmsTwoWay\Services\Action\Actions;

use WPSmsTwoWay\Services\Action\Exceptions\BadDefinition;
use WPSmsTwoWay\Models\IncomingMessage;

abstract class AbstractAction implements \jsonSerializable
{
    /**
     * Must be identical
     *
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var array
     */
    protected $callbackParams;

    /**
     * @var array
     */
    protected $responseParams;

    /**
     * Action's callback
     *
     * @param WPsmsTwoway\Models\IncomingMessage $message
     * @return void
     */
    abstract protected function callback(IncomingMessage $message);

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->checkRequirements();
        $this->setName();

        // TODO, global response variables should be merged here
    }

    /**
     * Serialize class instance into json
     *
     * @return object
     */
    public function jsonSerialize()
    {
        return (object) get_object_vars($this);
    }

    /**
     * Merge global response vars with class response vars
     *
     * @return void
     */
    private function mergeGlobalResponseVars()
    {
        // TODO, my suggestion is to store them in a file called globalResponseVars.php
    }

    /**
     * Check action requirements
     *
     * @return void
     * @throws WPSmsTwoWay\Services\Action\Exception\BadDefinition
     */
    private function checkRequirements()
    {
        // TODO
    }

    /**
     * Set action's name, based on class short name
     *
     * @return void
     */
    private function setName()
    {
        $this->name = (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Get action's name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get action's description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Get action's callback params
     *
     * @return array
     */
    public function getCallbackParams()
    {
        return $this->callbackParams;
    }

    /**
     * Get action's response params
     *
     * @return array
     */
    public function getResponseParams()
    {
        return $this->responseParams;
    }

    /**
     * Call action's callback
     *
     * @param WPSmsTwoWay\Models\IncomingMessage $message
     * @return mixed
     */
    final public function call(IncomingMessage $message)
    {
        call_user_func([$this, 'callback'], $message);
    }
}
