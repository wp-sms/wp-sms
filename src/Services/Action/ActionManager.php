<?php

namespace WPSmsTwoWay\Services\Action;

class ActionManager
{
    private const ACTION_CLASSES =[
        Actions\WooCommerce\Wrapper::class,
        Actions\WPSMS\Wrapper::class,
    ];

    /**
     * All registered actions
     *
     * @var array
     */
    private $actions;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registerInternalActions();
        $this->registerExternalActions();
    }

    /**
     * Register internal action classes
     *
     * @return void
     */
    private function registerInternalActions()
    {
        foreach (self::ACTION_CLASSES as $actionClass) {
            $actionClass = new $actionClass;
            $this->actions[$actionClass->getName()] = $actionClass;
        }
    }

    /**
     * Register external action classes via 'wpsms-tw-additional-actions' filter
     *
     * @return void
     */
    private function registerExternalActions()
    {
        $externalActions = apply_filters('wpsms-tw-external-actions', []);
        $this->actions = array_merge($this->actions, $externalActions);
    }

    /**
     * Get all actions
     *
     * @return array
     */
    public function getAllActions()
    {
        return $this->actions;
    }

    /**
     * Get action
     *
     * Returns action if exists and parent class is active
     *
     * @param string $className
     * @param string $actionName
     *
     * @return WPSmsTwoWay\Services\Action\Action|false
     */
    public function getAction(string $className, string $actionName)
    {
        if ($this->actions[$className]->isActive()) {
            return $this->actions[$className]->getAction($actionName) ?? false;
        }
        return false;
    }

    /**
     * Get all action references
     *
     * @return array
     */
    public function getAllActionReferences()
    {
        $references = [];
        foreach ($this->actions as $className => $class) {
            foreach ($class->getActions() as $actionName => $action) {
                $references[] = "$className/$actionName";
            }
        }
        return $references;
    }

    /**
     * Find an action's callback by its name and class name
     *
     * @param string $className
     * @return callable|false callable on success and false on failure
     */
    public function findAction(string $actionReference)
    {
        $className  = explode('/', $actionReference)[0] ?? null;
        $actionName = explode('/', $actionReference)[1] ?? null;
        $action = $this->getAction($className, $actionName);
        if ($action) {
            return $action;
        }
        throw new Exceptions\ActionException('No active action found');
    }
}
