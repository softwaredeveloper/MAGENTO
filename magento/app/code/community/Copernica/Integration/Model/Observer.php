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
 * Observer object.
 */
class Copernica_Integration_Model_Observer
{
    /**
     *  Check if event is added in store that we want to sync.
     *  @return bool
     */
    protected function isValidStore()
    {
        // get current store Id
        $currentStoreId = Mage::app()->getStore()->getId();

        // check if current store is enabled stores
        return Mage::helper('integration/config')->isEnabledStore($currentStoreId);
    }

    /**
     * Is the Copernica module enabled?
     *
     * @return boolean
     */
    protected function enabled()
    {
        // get the result from the helper
        return Mage::helper('integration')->enabled();
    }

    /**
     *  Synchronize an action for a given model
     *
     *  @param  object  the model the action occured on
     *  @param  string  the action to take, can be either 'store' or 'remove'
     */
    private function synchronize(Mage_Core_Model_Abstract $object, $action = 'store')
    {
        // retrieve the queue
        $queue = Mage::getModel('integration/queue');

        // set the object that needs to be synchronized
        $queue->setObject($object);

        // set the action that occured
        $queue->setAction($action);

        // store the queue
        $queue->save();
    }

    /**
     *  Method for the following events:
     *
     *  'checkout_controller_onepage_save_shipping_method'
     *  'checkout_controller_multishipping_shipping_post'
     *
     *  This method is fired during checkout process, after the
     *  customer has entered billing address and saved the shipping method
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function checkoutSaveStep(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($quote = $observer->getEvent()->getQuote())
        {
            // we only synchronize quotes with a valid customer
            if (!$quote->getCustomerId()) return;

            // add the quote to the synchronize queue
            $this->synchronize($quote);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'sales_quote_item_delete_before'
     *
     *  This method is fired when an item is removed
     *  from a quote.
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function quoteItemRemoved(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($item = $observer->getEvent()->getItem())
        {
            /**
             *  If this quote item has a parent, an update event will be
             *  triggered for this parent item and we need not synchronize
             *  this quote item now to avoid unnecessary communication
             */
            if ($item->getParentItemId()) return;

            // if there is no valid customer we do not care about the quote
            if (!$item->getQuote()->getCustomerId()) return;

            // add the item to the synchronize queue
            $this->synchronize($item, 'remove');
        }
    }

    /**
     *  Method for the following events:
     *
     *  'sales_quote_item_save_after'
     *
     *  This method is fired when an item is added to a quote
     *  or when a quote item is modified.
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function quoteItemModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($item = $observer->getEvent()->getItem())
        {
            /**
             *  If this quote item has a parent, an update event will be
             *  triggered for this parent item and we need not synchronize
             *  this quote item now to avoid unnecessary communication
             */
            if ($item->getParentItemId()) return;

            // if there is no valid customer we do not care about the quote
            if (!$item->getQuote()->getCustomerId()) return;

            // add the item to the synchronize queue
            $this->synchronize($item);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'sales_order_save_after'
     *
     *  This method is fired when an order is added or modified
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function orderModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($order = $observer->getEvent()->getOrder())
        {
            // if an order has no state, it will get one in the next call (usually a few seconds later)
            if (!$order->getState()) return;

            // if there is no valid customer we do not care about the order
            if (!$order->getCustomerId()) return;

            // add the order to the synchronize queue
            $this->synchronize($order);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'sales_order_item_save_after':
     *
     *  This method is fired when an order item is added or modified
     *
     *  @param  Varien_Event_Observer   observer object
     */
    public function orderItemModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // do we have a valid item?
        if ($item = $observer->getEvent()->getItem())
        {
            // do we have a valid customer with the order?
            if (!$item->getOrder()->getCustomerId()) return;

            // add the item to the synchronize queue
            $this->synchronize($item);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'newsletter_subscriber_delete_before'
     *
     *  This method is fired when a newsletter subscription is removed
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function newsletterSubscriptionRemoved(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($subscriber = $observer->getEvent()->getSubscriber())
        {
            // if there is no valid customer we do not care about the subscription
            if (!$subscriber->getCustomerId()) return;

            // add the subscription to the synchronize queue
            $this->synchronize($subscriber, 'remove');
        }
    }

    /**
     *  Method for the following events:
     *
     *  'newsletter_subscriber_save_after'.
     *
     *  This method is fired when a newsletter subscription is added or modified.
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function newsletterSubscriptionModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($subscriber = $observer->getEvent()->getSubscriber())
        {
            /**
             *  An event is triggered every time the object is saved, even when nothing has changed
             *  for example, when an item is added to the quote.
             *
             *  However, the update date may have changed (even by 1 second)
             *  which will trigger a new queue item any way. Even so, we do
             *  prevent at least some unnecessary synchronisations this way.
             */
            if (!$subscriber->hasDataChanges()) return;

            // if there is no valid customer we do not care about the subscription
            if (!$subscriber->getCustomerId()) return;

            // add the subscription to the synchronization queue
            $this->synchronize($subscriber);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'customer_delete_before'
     *
     *  This method is triggered when a customer gets removed.
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function customerRemoved(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($customer = $observer->getEvent()->getCustomer())
        {
            // we only care if this is a valid customer
            if (!$customer->getId()) return;

            // add this customer to the synchronize queue
            $this->synchronize($customer);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'customer_save_after'.
     *
     *  This method is triggered when a customer gets added or modified.
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function customerModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($customer = $observer->getEvent()->getCustomer())
        {
            // we only care if this is a valid customer
            if (!$customer->getId()) return;

            // add this customer to the synchronize queue
            $this->synchronize($customer);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'customer_address_save_after'.
     *
     *  This method is triggered when a customer updates one of
     *  his or her addresses
     *
     *  @param  Varien_Event_Observer   observer object
     */
    public function addressModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // do we have a valid address?
        if ($address = $observer->getEvent()->getCustomerAddress())
        {
            // we only care about valid addresses belonging to valid customer
            if (!$address->getId() || !$address->getCustomerId()) return;

            // add this customer to the synchronize queue
            $this->synchronize($address);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'catalog_product_save_after'
     *
     *  This method is triggered when a product is created or updated
     *
     *  @param  Varien_Event_Observer   observer object
     */
    public function productModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // do we have a valid product
        if ($product = $observer->getEvent()->getProduct())
        {
            // we only care about valid products
            if (!$product->getId()) return;

            // add this product to the synchronize queue
            $this->synchronize($product);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'core_store_save_after'
     *
     *  This method is triggered when a store is created or updated
     *  
     *  @param  Varien_Event_Observer   observer object
     */
    public function storeModified(Varien_Event_Observer $observer)
    {
        // if the plugin is not enabled, skip this
        if (!$this->enabled()) return;

        // do we have a valid store
        if ($store = $observer->setEvent()->getStore() && $store->getID())
        {
            // add this store to the synchronize queue
            $this->synchronize($store);
        }
    }

    /**
     *  Method for the following events:
     *
     *  'catalog_controller_product_view'
     *
     *  This method is triggered when a customer views a product
     *
     *  @param Varien_Event_Observer    observer object
     */
    public function productViewed(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($item = $observer->getEvent()->getProduct())
        {
            // get current customer instance and Id
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $customerId = $customer->getId();

            // this item cannot be linked to a customer, so is not relevant at this moment
            if (!$customerId) return;

            // TODO: synchronize to copernica
        }
    }

}
