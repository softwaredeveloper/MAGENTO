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
 * Queue object for accessing the events in the queue table.
 */
class Copernica_Integration_Model_Queue extends Mage_Core_Model_Abstract
{
    /**
     *  Constructor for the model
     */
    protected function _construct()
    {
        $this->_init('integration/queue');
    }

    /**
     *  Get the data from the model
     *
     *  @return Mage_Core_Model_Abstract|null
     */
    public function getObject()
    {
        // get model
        $model = Mage::getModel(parent::getData('entity_model'));

        // if we our model is not an object we will just return null
        if (!is_object($model)) return null;

        // retrieve the model and load it
        return $model->load(parent::getData('entity_id'));
    }

    /**
     *  Set the data to the model
     *
     *  @param  Mage_Core_Model_Abstract    the model to store
     */
    public function setObject(Mage_Core_Model_Abstract $object)
    {
        // store the model name and identifier
        parent::setData('entity_model', $object->getResourceName());
        parent::setData('entity_id', $object->getId());
    }

    /**
     *  Function to save the correct queue time
     *
     *  @return Copernica_Integration_Model_Queue
     */
    public function save()
    {
        // save the queuetime
        $this->setQueueTime(date("Y-m-d H:i:s"));

        // rely on parent
        return parent::save();
    }
}
