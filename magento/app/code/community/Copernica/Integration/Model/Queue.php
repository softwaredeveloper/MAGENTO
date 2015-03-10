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

        /**
         *  Whole quote system in magento is messed up for various reasons. This
         *  is another one. When we load quote by Id it will return empty quote
         *  if no active webstore is selected. Since, we don't want to select a
         *  webstore (cause this part most likely will be executed inside lightweight
         *  cli environment), we have to explicitly tell magento to load quote
         *  by ID without store.
         */
        if (parent::getData('entity_model') == 'sales/quote') $object = $model->loadByIdWithoutStore(parent::getData('entity_id'));

        /**
         *  Seems that rest of objects are loaded properly.
         */
        else $object = $model->load(parent::getData('entity_id'));

        // return fetched object
        return $object;
    }

    /**
     *  Get the object model Id.
     *
     *  @return int
     */
    public function getObjectId()
    {
        return parent::getData('entity_id');
    }

    /**
     *  Get resource indentifier.
     *
     *  @return string
     */
    public function getObjectResourceName()
    {
        return parent::getData('entity_model');
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
     *  Check if queue item with same parameters already exists on queue.
     *
     *  @return boolean
     */
    private function isDuplicate()
    {
        /**
         *  As duplicated items we recognize ones that have same action, 
         *  entity_model, entity_id and have no result set.
         */
        $queue = Mage::getResourceModel('integration/queue_collection')
            ->addFieldToFilter('action', array('eq' => parent::getData('action')))
            ->addFieldToFilter('entity_model', array('eq' => parent::getData('entity_model')))
            ->addFieldToFilter('entity_id', array('eq' => parent::getData('entity_id')))
            ->addFieldToFilter('result', array('null' => true));

        // if there is at least one duplicated item we tell that item is a duplicate
        if ($queue->getSize()) return true;

        // no duplicates currently on queue
        return false;
    }

    /**
     *  Function to save the correct queue time
     *
     *  @return Copernica_Integration_Model_Queue
     */
    public function save()
    {
        /**
         *  If we want to store a full sync event when there is one already
         *  doing it's magic there is no point of storing it. Thus, we have to
         *  check if there is such event already on the queue and leap out if 
         *  we have such.
         */
        $queue = Mage::getResourceModel('integration/queue_collection')->addFieldToFilter('action', array ('eq' => 'start_sync'));
        if (count($queue) > 0) return $this;

        /**
         *  It's just purely stupid how many events Magento can produce when 
         *  making simple actions (like placing order or requesting shipping cost).
         *  Most of such events are duplicated and they will not make reasonable
         *  effect on final data form. Thus, we can skip this item if there is 
         *  a duplicate on queue. This way data will be stored and we will not
         *  process same data couple of times.
         */
        if ($this->isDuplicate()) return $this;

        // save the queuetime
        $this->setQueueTime(date("Y-m-d H:i:s"));

        // rely on parent
        return parent::save();
    }
}
