<?php
/**
 *  Copernica Marketing Software
 *
 *  NOTICE OF LICENSE
 *
 *  This source file is subject to the Open Software License (OSL 3.0).
 *  It is available through the world-wide-web at this URL:
 *  http://opensource.org/licenses/osl-3.0.php
 *  If you are unable to obtain a copy of the license through the
 *  world-wide-web, please send an email to copernica@support.cream.nl
 *  so we can send you a copy immediately.
 *
 *  DISCLAIMER
 *
 *  Do not edit or add to this file if you wish to upgrade this software
 *  to newer versions in the future. If you wish to customize this module
 *  for your needs please refer to http://www.magento.com/ for more
 *  information.
 *
 *  @category       Copernica
 *  @package        Copernica_Integration
 *  @copyright      Copyright (c) 2011-2012 Copernica & Cream. (http://docs.cream.nl/)
 *  @license        http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  @documentation  public
 */

/**
 *  A specific report class. Basically it's a wrapped aroung raw data produced
 *  by QueueReported class. It's meant to be used internally only.
 */
class Copernica_Integration_Model_QueueReport
{
    /**
     *  The actual data. Look at what is done in QueueReporter class.
     *
     *  @param strClass
     */
    private $data;

    /**
     *  Construct queue report instance
     *
     *  @param  stdClass 
     */
    public function __construct(stdClass $dataObject)
    {
        $this->data = $dataObject;
    }

    /**
     *  Get the start time of specific reoport
     *
     *  @return string
     */
    public function getStartTime()
    {
        return $this->data->startDate;
    }

    /**
     *  Get the end time of specific report
     *
     *  @return string
     */
    public function getEndTime()
    {
        return $this->data->finishDate;
    }

    /**
     *  Get number of processed tasks.
     *
     *  @return int
     */
    public function getProcessedTasks()
    {
        return $this->data->total;
    }
}
