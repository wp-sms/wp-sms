# WPSMS Two Way
This addon extends [WPSMS](https://github.com/veronalabs/wp-sms) functionality to receive incoming messages and do actions with them such as canceling a woocommerce order. pretty awesome, right?

# Adding a new gateway
First extend `AbstractGateway` base class :
```php
<?php
namespace WPSmsTwoWay\Services\Gateway\Gateways;

class NewGateway extends AbstractGateway
{
    protected $registerType = 'panel';

    /**
     * @param WP_REST_Request $request
     */
    protected static function validateGateway($request) :bool
    {
        // Check if the webhook is called by the gateway or not
    }

    /**
     * @param \WP_REST_Request $request
     */
    public static function extractMessageText($request) :string|false
    {
       // Extract and return message body text from incoming message request
    }

    /**
     * @param \WP_RES_Request $request
     */
    public static function extractSenderNumber($request) :string|false
    {
        // Extract and return sender number from incoming message request
    }
}
```

Then add this newly created gateway to the gateways list in `GatewayManager` :

```php
class GatewayManager
{
    public const GATEWAYS = [
        'twilio' => Gateways\Twilio::class,
        'plivo'  => Gateways\Plivo::class,
        'nexmo'  => Gateways\Nexmo::class,
    ];
    
    //...
}
```


# Adding a new action class
First extend `AbstractActionClass`

```php
<?php

namespace WPSmsTwoWay\Services\Action\ActionClasses;

class NewActionClass extends AbstractActionClass
{
    public const NAME        = 'new-action-class';              // Action class's identifier
    public const DESCRIPTION = 'Description about this class';  //
    protected const ACTIONS  = [                                // List of actions in this class
    ];

    public static function checkRequirements() :bool
    {
        // Check if this requirements are met for this class, e.g. WooCommerce is installed
    }

    // Callbacks ...
}

```

Then add the newly created action class to `ActionManager` list :

```php
class ActionManager
{
    private const ACTION_CLASSES =[
        ActionClasses\WooCommerceActions::class,
        ActionClasses\WPSmsActions::class,
    ];

    // ...
}
```

# Adding a new action
First, in the action class, declare the action callback, e.g. :
```php
<?php

namespace WPSmsTwoWay\Services\Action\ActionClasses;

use WPSmsTwoWay\Models\IncomingMessage;

class NewActionClass extends AbstractActionClass
{
    // ...

    /**========================================================================
     *                           Action Callbacks
     *========================================================================**/

    public static function newAction(IncomingMessage $message){
        // Action code here
    }
```
> NOTE : If for some reason action should be ended due to a predicted scenario, a new instance of `WPSmsTwoWay\Services\Action\Exceptions\ActionException` can be thrown.

Then inform the class about the newly created action :

```php
<?php

namespace WPSmsTwoWay\Services\Action\ActionClasses;

class NewActionClass extends AbstractActionClass
{
    // ...

    protected const ACTIONS  = [
        '<action-identifier>' => [
            'description' => '<description>',
            'callback'  => '<callback-name>',    // Resolves to self::<callback-name>
            'params' => [
                0 => [
                    'name' =>'order-id', 
                    'type' => 'int', 
                    'example' => 'order-id'     // Used in front-end as place holder
                ],
            ]
        ],
    ];

    //...
}
 
```

# Production

To create a production ready version of plugin :

```sh
php Vlabs Build --output-dir ../wp-sms-two-way-namespaced
```