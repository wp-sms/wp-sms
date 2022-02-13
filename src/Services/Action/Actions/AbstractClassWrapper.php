<?php

namespace WPSmsTwoWay\Services\Action\Actions;

use WPSmsTwoWay\Services\Action\Action;
use WPSmsTwoWay\Models\IncomingMessage;
use WPSmsTwoWay\Core\Helper;

abstract class AbstractClassWrapper implements \jsonSerializable
{
    /**
     * @var string
     */
    private $name;

    /**
     * Class status
     *
     * @var bool
     */
    private $isActive;

    /**
     * Class actions
     *
     * @var array
     */
    private $actions;

    /**
     * Check action class's requirements
     *
     * @return bool
     */
    abstract protected static function checkRequirements():bool;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->isActive = $this->checkRequirements();
        $this->setName();
        $this->findActions();
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
     * Check if action class is active
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * Get class's description
     *
     * @return string|null
     */
    public static function getDescription()
    {
        return static::DESCRIPTION;
    }

    /**
     * Set identical name based on class name
     *
     * @return void
     */
    public function setName()
    {
        $nameSpace = (new \ReflectionClass($this))->getNamespaceName();
        $this->name = basename(str_replace('\\', '/', $nameSpace));
    }

    /**
     * Get class's name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Export all actions in class
     *
     * @return \WPSmsTwoWay\Services\Action\Actions\AbstractAction[]
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Get a single action
     *
     * @param string $actionName
     * @return WPSmsTwoWay\Services\Action\Actions\AbstractAction
     */
    public function getAction(string $actionName)
    {
        return $this->actions[$actionName] ?? null;
    }

    /**
     * Find actions inside class's directory
     *
     * @return void
     */
    private function findActions()
    {
        $classDir     = dirname((new \ReflectionClass(static::class))->getFileName());
        $actionsInDir = Helper::findAllClassesInDir($classDir);

        foreach ($actionsInDir as $action) {
            if (is_subclass_of($action, 'WPSmsTwoWay\Services\Action\Actions\AbstractAction')) {
                // TODO BadDefinitions may happen and must be caught and logged
                $action     = new $action;
                $this->actions[$action->getName()] = new $action;
            }
        }
    }
}
