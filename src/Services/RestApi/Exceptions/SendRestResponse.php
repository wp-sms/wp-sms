<?php

namespace WPSmsTwoWay\Services\RestApi\Exceptions;

class SendRestResponse extends \Exception
{
    /**
     * Response data
     *
     * @var array
     */
    private $data;

    /**
     * Constructor
     *
     * @param array $data
     * @param integer $code
     */
    public function __construct(array $data, int $code)
    {
        $this->data = $data;
        $this->code = $code;
    }

    public function getData()
    {
        return $this->data;
    }
}
