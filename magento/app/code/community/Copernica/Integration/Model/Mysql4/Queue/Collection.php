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
 *  Collection of items to synchronize
 */
class Copernica_Integration_Model_Mysql4_Queue_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     *  Construct and initialize object
     */
    protected function _construct()
    {
        $this->_init('integration/queue');
    }

    /**
     *  Add a default order, sorted ascending by queue time
     *  @return Copernica_Integration_Model_Mysql4_Queue_Collection
     */
    public function addDefaultOrder()
    {
        // If a result was processed before, we should process it if we have nothing
        // else to process, we want to import the queue items without an result_time
        // first and then in order of queue time.
        return $this->addOrder('result_time', self::SORT_ORDER_ASC)
                ->addOrder('queue_time', self::SORT_ORDER_ASC);
    }

    /**
     *  Get the time of the oldest record
     *  @return string  mysql formatted date timestamp
     */
    public function getQueueStartTime()
    {
        return $this->addDefaultOrder()->setPageSize(1)->getFirstItem()->getQueueTime();
    }

    /**
     *  Get the result of the oldest record
     *  @return string  message
     */
    public function getOldestResult()
    {
        return $this->addDefaultOrder()->setPageSize(1)->getFirstItem()->getResult();
    }
}
