<?php

namespace steevanb\DoctrineReadOnlyHydrator\Exception;

class DefaultValueCantBeRenderedException extends \Exception
{
    /**
     * @param object $defaultValue
     */
    public function __construct($defaultValue)
    {
        $message = 'Default value of type ' . get_class($defaultValue) . ' can\'t be rendered as a ';
        $message .= 'constant expression in a read only proxy. Use null as default value instead.';

        parent::__construct($message);
    }
}
