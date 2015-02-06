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
 *  This class will be a base for our visual constrollers.
 */
class Copernica_Integration_Controller_Base extends Mage_Adminhtml_Controller_Action
{
    /**
     *  index action.
     */
    public function _construct()
    {
        // get queue
        $queue = Mage::getResourceModel('integration/queue_collection');

        // check current queue state
        $this->checkQueueSize($queue);

        // check current queue time
        $this->checkQueueTime($queue);
    }

    /**
     *  Check if queue have too many items. This method will output messages on
     *  admin session to inform him about current state.
     *  @param  Copernica_Integration_Model_Mysql4_Queue_Collection
     */
    private function checkQueueSize($queue)
    {
        // get queue size
        $queueSize = $queue->getSize();

        // we will not panic if queue size is below 100
        if ($queueSize < 100) return;

        // add warning to admin session
        Mage::getSingleton('adminhtml/session')->addWarning(Mage::helper('integration')->__("There is queue of %s local modification waiting to be processed", $queueSize));
    }

    /**
     *  Check if queue have too old items.
     *  @param  Copernica_Integration_Model_Mysql4_Queue_Collection
     */
    private function checkQueueTime($queue)
    {
        // get queue oldest item timestamp
        $oldestItemTimestamp = $queue->getQueueStartTime();

        // check if there is an oldest timestamp
        if (is_null($oldestItemTimestamp)) return;

        // if oldest item age is less than 24 hours we will not panic
        if (time() - strtotime($oldestItemTimestamp) < 60*60*24) return;

        // we want to get a printable time
        $printableTime = Mage::helper('core')->formatDate($oldestItemTimestamp, 'short', true);

        // alert admin
        Mage::getSingleton('adminhtml/session')->addWarning(Mage::helper('integration')->__("There is still a modification of %s that is not synchronized with Copernica.", $printableTime));
    }
}
