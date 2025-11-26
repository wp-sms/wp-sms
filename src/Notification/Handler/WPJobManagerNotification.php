<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class WPJobManagerNotification extends Notification
{
    /**
     * The job data.
     *
     * @var array
     */
    protected $jobData;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%job_id%'          => 'getJobId',
        '%job_title%'       => 'getJobTitle',
        '%job_description%' => 'getJobDescription',
        '%job_location%'    => 'getJobLocation',
        '%job_type%'        => 'getJobType',
        '%job_mobile%'      => 'getJobMobile',
        '%company_name%'    => 'getCompanyName',
        '%website%'         => 'getWebsite',
    ];

    /**
     * WPJobManagerNotification constructor.
     *
     * @param array $jobData
     */
    public function __construct($jobData)
    {
        $this->jobData = $jobData;
    }

    /**
     * Get job ID.
     *
     * @return mixed|null
     */
    public function getJobId()
    {
        return $this->jobData['job_id'] ?? null;
    }

    /**
     * Get job title.
     *
     * @return string|null
     */
    public function getJobTitle()
    {
        return $this->jobData['job_title'] ?? null;
    }

    /**
     * Get job description.
     *
     * @return string|null
     */
    public function getJobDescription()
    {
        return $this->jobData['job_description'] ?? null;
    }

    /**
     * Get job location.
     *
     * @return string|null
     */
    public function getJobLocation()
    {
        return $this->jobData['job_location'] ?? null;
    }

    /**
     * Get job type.
     *
     * @return string|null
     */
    public function getJobType()
    {
        return $this->jobData['job_type'] ?? null;
    }

    /**
     * Get job mobile number.
     *
     * @return string|null
     */
    public function getJobMobile()
    {
        return $this->jobData['job_mobile'] ?? null;
    }

    /**
     * Get company name.
     *
     * @return string|null
     */
    public function getCompanyName()
    {
        return $this->jobData['company_name'] ?? null;
    }

    /**
     * Get company website.
     *
     * @return string|null
     */
    public function getWebsite()
    {
        return $this->jobData['website'] ?? null;
    }
}