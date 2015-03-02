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
 *  This class will help with long synchronizations, such as 1st time synchronization
 *  or when configuration options changes and there is need to resync all data.
 */
class Copernica_Integration_Model_SyncProcessor
{
    /**
     *  This array will determine what kind of models we should process and 
     *  order in which we will be processing such models.
     *  @var    array
     */
    private $models = array (
        'core/store',
        'catalog/category',
        'customer/customer',
        'catalog/product',
        'sales/quote_address',
        'sales/quote',
        'sales/quote_item',
        'sales/order_address',
        'sales/order',
        'sales/order_item',
        'newsletter/subscriber',
        'customer/address',
    );

    /** 
     *  Current model resource name.
     *  @var    string
     */
    private $currentModel = 'core/store';

    /**
     *  Id of last model that was processed.
     *  @return int
     */
    private $lastModelId = 0;

    /**
     *  How many models we should process in one fetch?
     *  @var    int
     */
    private $batch = 100;

    /**
     *  Cached Api helper
     *  @var    Copernica_Integration_Helper_Api
     */
    private $api = null;

    /**
     *  Should we switch collection for next one?
     *  @var    bool
     */
    private $switchCollection = false;

    /**
     *  Construct synchronization processor
     */
    public function __construct()
    {
        // cache Api helper
        $this->api = Mage::helper('integration/Api');
    }

    /**
     *  Load last state.
     */
    private function loadState ()
    {
        // get state from config
        $state = json_decode(Mage::helper('integration/config')->getSyncState());

        // if we have a null state then we will just use default state
        if (is_null($state)) return;

        // assign current model from state
        $this->currentModel = $state->model;

        // assign last model id from state
        $this->lastModelId = $state->id;
    }

    /** 
     *  Store current state
     */
    private function storeState()
    {
        // create current state object
        $state = array('model' => $this->currentModel, 'id' => $this->lastModelId);

        // store state object as JSON in config
        Mage::helper('integration/config')->setSyncState(json_encode($state));
    }

    /**
     *  Reset current state
     */
    private function resetState()
    {
        // unset sync state
        Mage::helper('integration/config')->unsSyncState();
    }

    /**
     *  Process current synchronization step.
     */
    public function process()
    {
        // load state
        $this->loadState();

        // amount of items processed in this run
        $counter = 0;

        // when we are syncing small collections we should continue syncing
        while ($counter < $this->batch)
        {
            // get current collection to sync
            $collection = $this->currentCollection();

            // tell api to sync collection
            $this->api->storeCollection($collection);

            // add to counter amount of items in collection
            $counter += $collection->count();

            // should we switch collection?
            if ($this->switchCollection) $this->nextCollection();

            // are we complete?
            if ($this->isComplete()) 
            {
                // reset state
                $this->resetState();

                // we are really done here
                return;
            }
        }

        // we are done so we can store current state
        $this->storeState();
    }

    /** 
     *  Did SyncProcessor process all available data?
     *  @return bool
     */
    public function isComplete()
    {
        return is_null($this->currentModel);
    }

    /**
     *  Cause quote items collection is somehow badly designed, we have to handle
     *  that case in more specialized way. Instead of getting all quotes items 
     *  and traversing over whole collection we will ask every quote for it's items.
     *  This way all needed informations for quote items collection should be set 
     *  propery.
     *  @return Mage_Sales_Model_Resource_Quote_Item_Collection
     */
    private function getQuoteItemsCollection()
    {
        // get quote collection
        $quoteCollection = Mage::getModel('sales/quote')->getCollection();

        // we will fetch one quote
        // $quoteCollection->setPageSize(2);

        // we want a quote with id larger than last one
        $quoteCollection->addFieldToFilter('entity_id', array (
            'gt' => $this->lastModelId
        ));

        // when there is no more quotes to sync we should switch collection
        if ($quoteCollection->count() == 1) $this->switchCollection = true;

        // get 1st quote that we can use
        $quote = $quoteCollection->getFirstItem();

        // assign last model id with new value
        $this->lastModelId = $quote->getId();

        // return quote items collection
        return Mage::getModel('sales/quote_item')->getCollection()->setQuote($quote);
    }

    /**
     *  Get current collection that should be processed.
     *  @return Varien_Data_Collection_Db
     */
    private function currentCollection()
    {
        /*
         *  We can not process quote items collection in same way that we do 
         *  with other collections (cause they are not usable when there is 
         *  no quote assigned to such collection). So we have to make it a little
         *  bit more custom. 
         */
        if ($this->currentModel == 'sales/quote_item') return $this->getQuoteItemsCollection();

        // get collection
        $collection = Mage::getModel($this->currentModel)->getCollection();

        // @see docblock for self::collectionPrimaryKey()
        $primaryKey = $this->collectionPrimaryKey($collection);

        // add filter to get models with id greater than last id
        $collection->addFieldToFilter($primaryKey, array (
            'gt' => $this->lastModelId
        ));    

        // set batch
        $collection->setPageSize($this->batch);

        // if we have smaller collection than requested that means we can 
        // switch collection to next one
        if ($collection->count() < $this->batch) $this->switchCollection = true;

        // get last item Id
        $this->lastModelId = $collection->getLastItem()->getId();

        // return prepared collection
        return $collection;
    }

    /**
     *  In most of data models out there primary key is called 'id'. That is 
     *  just perfectly fine and resonable. In magento, core developers decided
     *  to make a mess, so they decided to call primary keys like 'entity_id',
     *  'subscriber_id', 'some-non-standard-key_id'. Cause of that we have to 
     *  detect what kind of collection we have and adjust filter with proper 
     *  name for primary key.
     *  @param  Varien_Data_Collection_Db
     *  @return string
     */
    private function collectionPrimaryKey(Varien_Data_Collection_Db $collection) 
    {
        switch (get_class($collection)) {
            case 'Mage_Core_Model_Resource_Store_Collection': return 'store_id';
            case 'Mage_Sales_Model_Resource_Quote_Item_Collection': return 'item_id';
            case 'Mage_Sales_Model_Resource_Order_Item_Collection': return 'item_id';
            case 'Mage_Newsletter_Model_Resource_Subscriber_Collection': return 'subscriber_id';
            case 'Mage_Sales_Model_Resource_Quote_Address_Collection': return 'address_id';
            default: return 'entity_id';
        }
    }

    /**
     *  Process to next collection
     */
    private function nextCollection()
    {
        // get next model index
        $nextModelIdx = array_search($this->currentModel, $this->models) + 1;

        // set next model name
        $this->currentModel = ($nextModelIdx < count($this->models)) ? $this->models[$nextModelIdx] : null;

        // since we want to make a switch we should reset last model Id
        $this->lastModelId = 0;

        // we just switched collection
        $this->switchCollection = false;
    }
}