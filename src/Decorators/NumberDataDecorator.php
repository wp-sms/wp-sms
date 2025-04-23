<?php

namespace WP_SMS\Decorators;

use Exception;
use stdClass;

class NumberDataDecorator
{
    /**
     * @var array
     */
    private $number;

    /**
     * @param array|stdClass $numberData
     */
    public function __construct($numberData)
    {
        // Convert stdClass to array if necessary
        if ($numberData instanceof stdClass) {
            $numberData = $this->convertStdClassToArray($numberData);
        }

        // Ensure the input is an array
        if (!is_array($numberData)) {
            throw new \InvalidArgumentException(
                __('Number data must be an array or stdClass object.', 'wp-sms')
            );
        }

        $this->number = $numberData;
    }

    /**
     * Convert stdClass to array
     *
     * @param stdClass $object
     * @return array
     */
    private function convertStdClassToArray(stdClass $object)
    {
        return json_decode(json_encode($object), true);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return (int)$this->number['id'];
    }

    /**
     * @return mixed
     */
    public function getNumber()
    {
        return $this->number['number'];
    }

    /**
     * @return mixed
     */
    public function getCountryCode()
    {
        return $this->number['country_code'];
    }

    /**
     * @return mixed|null
     */
    public function getFirstName()
    {
        return isset($this->number['first_name']) ? $this->number['first_name'] : null;
    }

    /**
     * @return mixed|null
     */
    public function getLastName()
    {
        return isset($this->number['last_name']) ? $this->number['last_name'] : null;
    }

    /**
     * @return mixed|string
     */
    public function getDisplayName()
    {
        if (isset($this->number['display_name'])) {
            return $this->number['display_name'];
        }
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return (int)$this->number['user_id'];
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->number['status'];
    }

    /**
     * @return bool
     */
    public function isUnsubscribed()
    {
        return (bool)$this->number['unsubscribed'];
    }

    /**
     * @return bool
     */
    public function isVerified()
    {
        return (bool)$this->number['verified'];
    }

    /**
     * @return mixed|null
     */
    public function getSource()
    {
        return isset($this->number['source']) ? $this->number['source'] : null;
    }

    /**
     * @return mixed|null
     */
    public function getMeta()
    {
        return isset($this->number['meta']) ? $this->number['meta'] : null;
    }

    /**
     * @return mixed|null
     */
    public function getSecondaryNumber()
    {
        return isset($this->number['secondary_number']) ? $this->number['secondary_number'] : null;
    }

    /**
     * @throws Exception
     */
    public function getLastSentAt()
    {
        return isset($this->number['last_sent_at']) ? new \DateTime($this->number['last_sent_at']) : null;
    }

    /**
     * @return int
     */
    public function getSuccessCount()
    {
        return (int)$this->number['success_count'];
    }

    /**
     * @return int
     */
    public function getFailCount()
    {
        return (int)$this->number['fail_count'];
    }

    /**
     * @throws Exception
     */
    public function getOptInDate()
    {
        return isset($this->number['opt_in_date']) ? new \DateTime($this->number['opt_in_date']) : null;
    }

    /**
     * @throws Exception
     */
    public function getOptOutAt()
    {
        return isset($this->number['opt_out_at']) ? new \DateTime($this->number['opt_out_at']) : null;
    }

    /**
     * @throws Exception
     */
    public function getCreatedAt()
    {
        return new \DateTime($this->number['created_at']);
    }

    /**
     * @throws Exception
     */
    public function getUpdatedAt()
    {
        return new \DateTime($this->number['updated_at']);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->number;
    }
}