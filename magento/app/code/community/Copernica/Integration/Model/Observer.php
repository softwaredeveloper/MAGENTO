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

        // return store enabled option
        return Mage::getStoreConfig('copernica_options/apisync/enabled', $currentStoreId);
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
     *  This method is fired during checkout process, after the
     *  customer has entered billing address and saved the shipping method
     *
     *  @listen checkout_onepage_controller_success_action
     *  @param  Varien_Event_Observer    observer object
     */
    public function checkoutSaveStep(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($quote = $observer->getEvent()->getQuote())
        {
            // add the quote to the synchronize queue
            $this->synchronize($quote);
        }
    }

    /**
     *  This method is fired when quote is modified/created.  
     *
     *  @listen sales_quote_save_after
     *  @param  Varien_Event_Observer 
     */
    public function quoteModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // do we have a valid quote?
        if ($quote = $observer->getEvent()->getQuote())
        {
            // add the quote to synchronize queue
            $this->synchronize($quote);
        }
    }

    /**
     *  This method is fired when quote is removed.
     *
     *  @listen sales_quote_delete_before
     *  @param  Varien_Event_Observer
     */
    public function quoteRemoved(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        /**
         *  Do we have a valid quote? We have to check if have a valid object 
         *  and if that instance has a non zero Id.
         */
        $quote = $quote = $observer->getEvent()->getQuote();
        if (is_object($quote) && $quote->getId())
        {
            // add the quote to synchronize queue
            $this->synchronize($quote, 'remove');
        }
    }

    /**
     *  This method is fired when an item is removed
     *  from a quote.
     *
     *  @listen sales_quote_item_delete_before
     *  @param  Varien_Event_Observer    observer object
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

            // add the item to the synchronize queue
            $this->synchronize($item, 'remove');
        }
    }

    /**
     *  This method is fired when an item is added to a quote
     *  or when a quote item is modified.
     *
     *  @listen sales_quote_item_save_after
     *  @param  Varien_Event_Observer    observer object
     */
    public function quoteItemModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($item = $observer->getEvent()->getItem())
        {
            // if there is no valid customer we do not care about the quote
            if (!$item->getQuote()->getCustomerId()) return;

            // add the item to the synchronize queue
            $this->synchronize($item);
        }
    }

    /**
     *  This method is fired when an order is added or modified
     *
     *  @listen sales_order_save_after
     *  @param  Varien_Event_Observer    observer object
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

            // add the order to the synchronize queue
            $this->synchronize($order);
        }
    }

    /**
     *  This method is fired when an order item is added or modified
     *
     *  @listen 'sales_order_item_save_after'
     *  @param  Varien_Event_Observer   observer object
     */
    public function orderItemModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // do we have a valid item?
        if ($item = $observer->getEvent()->getItem())
        {
            // add the item to the synchronize queue
            $this->synchronize($item);
        }
    }

    /**
     *  This method is fired when a newsletter subscription is removed
     *
     *  @listen 'newsletter_subscriber_delete_before'
     *  @param  Varien_Event_Observer    observer object
     */
    public function newsletterSubscriptionRemoved(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // Do we have a valid item?
        if ($subscriber = $observer->getEvent()->getSubscriber())
        {
            // add the subscription to the synchronize queue
            $this->synchronize($subscriber, 'remove');
        }
    }

    /**
     *  This method is fired when a newsletter subscription is added or modified.
     *
     *  @listen 'newsletter_subscriber_save_after'
     *  @param  Varien_Event_Observer    observer object
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
            
            // add the subscription to the synchronization queue
            $this->synchronize($subscriber);
        }
    }

    /**
     *  This method is triggered when a customer gets removed.
     *
     *  @listen 'customer_delete_before'
     *  @param  Varien_Event_Observer    observer object
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
            $this->synchronize($customer, 'remove');
        }
    }

    /**
     *  This method is triggered when a customer gets added or modified.
     *
     *  @listen 'customer_save_after'
     *  @param  Varien_Event_Observer    observer object
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
     *  This method is triggered when customer group is created or modified.
     *
     *  @listen 'customer_group_after_save'
     *  @param  Varien_Event_Observer
     */
    public function groupModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled()) return;

        // do we have proper group instance?
        if ($group = $observer->getObject())
        {
            // sync this group
            $this->synchronize($group);
        }
    }

    /**
     *  This method is triggered when customer group is removed. 
     *
     *  @listen 'customer_group_delete_before'
     *  @param  Varien_Event_Observer
     */
    public function groupRemoved(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled()) return;

        // do we have proper group instance?
        if ($group = $observer->getObject())
        {
            // sync this group
            $this->synchronize($group, 'remove');
        }   
    }

    /**
     *  This method is triggered when a customer updates one of
     *  his or her addresses
     *
     *  @listen 'customer_address_save_after'
     *  @listen 'sales_order_address_save_after'
     *  @listen 'sales_quote_address_save_after'
     *  @param  Varien_Event_Observer   observer object
     */
    public function addressModified(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // do we have a valid address?
        if ($address = $observer->getEvent()->getDataObject())
        {
            // add this customer to the synchronize queue
            $this->synchronize($address);
        }
    }

    /**
     *  This method is triggered when one of customers address is removed.
     *
     *  @listen 'customer_address_delete_before'
     *  @listen 'sales_order_address_delete_before'
     *  @listen 'sales_quote_address_delete_before'
     *  @param  Varien_Event_Observer   observer object
     */
    public function addressRemoved(Varien_Event_Observer $observer)
    {
        // if the plug-in is not enabled, skip this
        if (!$this->enabled() || !$this->isValidStore()) return;

        // do we have a valid address?
        if ($address = $observer->getEvent()->getDataObject())
        {
            // remove this address
            $this->synchronize($address, 'remove');
        }
    }

    /**
     *  This method is triggered when a product is created or updated
     *
     *  @listen 'catalog_product_save_after'
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
     *  This method is triggered when a store is created or updated
     *  
     *  @listen 'core_store_save_after'
     *  @param  Varien_Event_Observer   observer object
     */
    public function storeModified(Varien_Event_Observer $observer)
    {
        /**
         *  We ignore this action only on one occassion: when whole extension
         *  is disabled. When particular store is disabled we still want to
         *  sync it's data,
         */
        if (!$this->enabled()) return;

        // do we have a valid store
        if ($store = $observer->setEvent()->getStore() && $store->getID())
        {
            // add this store to the synchronize queue
            $this->synchronize($store);
        }
    }

    /**
     *  This method is triggered when a customer views a product
     *
     *  @listen 'catalog_controller_product_view'
     *  @param  Varien_Event_Observer    observer object
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

    /**
     *  This is triggered when a category is stored.
     *
     *  @listen 'catalog_category_save_commit_after'
     *  @listen 'catalog_category_tree_move_after'
     *  @param  Varien_Event_Observer
     */ 
    public function categoryModified(Varien_Event_Observer $observer)
    {
        /**
         *  We don't sync this action only on one occassion: when the whole
         *  integration is disabled. We do ignore stores enable/disable states
         *  cause categories are pretty much global entities that don't care 
         *  about stores.
         */
        if (!$this->enabled()) return;

        // get category instance
        $category = $observer->getEvent()->getCategory();

        // do we have a valid category
        if (is_object($category) && $category->getId())
        {
            // add this category to synchronize queue
            $this->synchronize($category);
        }
    }

    /**
     *  This is triggered when a category is removed.
     *
     *  @listen 'catalog_category_delete_before'
     *  @param  Varien_Event_Observer
     */ 
    public function categoryRemoved(Varien_Event_Observer $observer)
    {
        /**
         *  We don't sync this action only on one occassion: when the whole
         *  integration is disabled. We do ignore stores enable/disable states
         *  cause categories are pretty much global entities that don't care 
         *  about stores.
         */
        if (!$this->enabled()) return;

        // get category instance
        $category = $observer->getEvent()->getCategory();

        // do we have a valid category
        if (is_object($category) && $category->getId())
        {
            // add this category to synchronize queue
            $this->synchronize($category, 'remove');
        }
    }
    
    /**
     *  This event is triggered when a wishlist is modified  
     *
     *  @listen 'wishlist_save_commit_after'
     *  @param  Varien_Event_Observer
     */
    public function wishlistModified(Varien_Event_Observer $observer)
    {
        /**
         *  We don't sync this action only on one occassion: when the whole
         *  integration is disabled. We do ignore wishlist enable/disable states
         *  cause categories are pretty much global entities that don't care 
         *  about stores.
         */
        if(!$this->enabled()) return;
        
        // get the wishlist instnace
        $wishlist = $observer->getEvent()->getObject();
        
        // do we have a valid wishlist?
        if (is_object($wishlist) && $wishlist->getId())
        {
            $this->synchronize($wishlist);
        }
    }
    
    /**
     *  This is triggered when a category is removed.
     *
     *  @listen 'wishlist_delete_before'
     *  @param  Varien_Event_Observer
     */ 
    public function wishlistRemoved(Varien_Event_Observer $observer)
    {
        /**
         *  We don't sync this action only on one occassion: when the whole
         *  integration is disabled. We do ignore wishlists enable/disable states
         *  cause categories are pretty much global entities that don't care 
         *  about stores.
         */
        if (!$this->enabled()) return;

        // get wishlist instance
        $wishlist = $observer->getEvent()->getObject();

        // do we have a valid wishlist
        if (is_object($wishlist) && $wishlist->getId())
        {
            // add this wishlist to synchronize queue
            $this->synchronize($wishlist, 'remove');
        }
    }
    
    /**
     *  This event is triggered when a wishlist item is modified  
     *
     *  @listen 'wishlist_item_save_commit_after'
     *  @param  Varien_Event_Observer
     */
    public function wishlistItemModified(Varien_Event_Observer $observer)
    {
        /**
         *  If integration or current store is disabled we don't want to sync 
         *  wishlists at all.
         */
        if(!$this->enabled() || !$this->isValidStore()) return;
        
        // get the item instnace
        $item = $observer->getEvent()->getItem();
        
        // do we have a valid item?
        if (is_object($item) && $item->getId())
        {
            $this->synchronize($item);
        }
    }
    
    /**
     *  This is triggered when a category is removed.
     *
     *  @listen 'wishlist_item_delete_before'
     *  @param  Varien_Event_Observer
     */ 
    public function wishlistItemRemoved(Varien_Event_Observer $observer)
    {
        /**
         *  Since it's a removal then we should synchronize it regardless of 
         *  store settings.
         */
        if (!$this->enabled()) return;

        // get item instance
        $item = $observer->getEvent()->getItem();

        // do we have a valid item 
        if (is_object($item) && $item->getId())
        {
            // add this item to synchronize queue
            $this->synchronize($item, 'remove');
        }
    }
}
