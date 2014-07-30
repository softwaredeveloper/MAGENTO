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
 *  This class will represent item from error queue.
 */
class Copernica_Integration_Model_ErrorQueue extends Mage_Core_Model_Abstract
{
    /**
     *  Construct the model
     */
    protected function _construct()
    {
        $this->_init('integration/ErrorQueue');
    }

    /**
     *  Get object instance.
     *  @return object
     */
    public function getObject()
    {
        // retrieve the model and load it
        return Mage::getModel(parent::getData('entity_model'))->load(parent::getData('entity_id'));
    }

    /**
     *  Set object instance
     *  @param  object
     */
    public function setObject($object)
    {
        // store the model name and identifier
        parent::setData('entity_model', $object->getResourceName());
        parent::setData('entity_id', $object->getId());
    }

    /**
     *  Save model
     */
    public function save()
    {
        return parent::save();
    }

    /**
     *  This method will create instance from processing queue item
     *  @param Copernica_Integration_Model_Queue
     *  @param Copernica_Integration_Model_ErrorQueue
     */
    static public function createFromQueueItem(Copernica_Integration_Model_Queue $queueItem)
    {
        // this will create a new error item model
        $errorModel = Mage::getModel('integration/errorqueue');

        // store object
        $errorModel->setObject($queueItem->getObject());

        // store old Id
        $errorModel->setOldId($queueItem->getId());

        // store action
        $errorModel->setAction($queueItem->getAction());

        // store queue time
        $errorModel->setQueueTime($queueItem->getQueueTime());

        // store result
        $errorModel->setResult($queueItem->getResult());

        // store result time
        $errorModel->setResultTime($queueItem->getResultTime());

        // return created error model
        return $errorModel;
    }
}
