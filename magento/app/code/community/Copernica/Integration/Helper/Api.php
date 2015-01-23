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
 *  Coppernica REST API class.
 *  This class holds methods to communicate with Copernica REST API. It's also
 *  a facade for valication and creation classes.
 */
class Copernica_Integration_Helper_Api extends Mage_Core_Helper_Abstract
{
    /**
     *  Request object that handles raw REST requests
     *  @var    Copernica_Integration_Helper_RESTRequest
     */
    protected $request = null;

    /**
     *  Public, standard PHP constructor. Mage_Core_Helper_Abstract is not a child
     *  of Varien_Object, so we want to use good old PHP constructor.
     */
    public function __construct()
    {
        // create the request handler
        $this->request = Mage::helper('integration/RESTRequest');
    }

    /**
     *  Check if this API instance is valid.
     *
     *  @return boolean
     */
    public function check()
    {
        // just check the request
        return $this->request->check();
    }

    /**
     *  Upgrade request token data into access token via REST call.
     *
     *  @param  string  Code that we did get from Copernica authorization page
     *  @param  string  Our landing page for state handling
     *  @return string  The access token or false when we can not upgrade
     */
    public function upgradeRequest($code, $redirectUri)
    {
        // make an upgrade request
        $output = $this->request->get('token', array(
            'client_id'     =>  'fccd12ee5499739753fd12a170998549',
            'client_secret' =>  '8f65b8fd2cd80c6563f973fa3ca18952',
            'code'          =>  $code,
            'redirect_uri'  =>  $redirectUri
        ));

        // check for a valid access tokne
        if (empty($output['access_token'])) return false;

        // update the access token in the request
        $this->request->setAccessToken($output['access_token']);

        // return the new access token
        return $output['access_token'];
    }

    /**
     *  Retrieve information about the account we are linked to
     *
     *  @return map an array with the properties 'id, 'name', 'description' and 'company'
     */
    public function account()
    {
        // retrieve information from the request
        return $this->request->get('identity');
    }

    /**
     *  Store collection of models
     *  
     *  @param  Varien_Data_Collection_Db
     */
    public function storeCollection (Varien_Data_Collection_Db $collection)
    {
        // if we don't have a proper collection or don't have anything inside 
        // collection we can bail out
        if (!is_object($collection) && $collection->count() == 0) return;

        // get resource name of 1st item
        $resourceName = $collection->getFirstItem()->getResourceName();

        // store collection according to resource name
        switch ($resourceName) {
            case 'sales/quote': foreach ($collection as $quote) $this->storeQuote($quote); break;
            case 'sales/quote_item': foreach ($collection as $item) $this->storeQuoteItem($item); break;
            case 'sales/order': foreach ($collection as $order) $this->storeOrder($order); break;
            case 'sales/order_item': foreach ($collection as $item) $this->storeOrderItem($item); break;
            case 'newsletter/subscriber': foreach ($collection as $subscriber) $this->storeSubscriber($subscriber); break;
            case 'core/store': foreach ($collection as $store) $this->storeStore($store); break;

            /** 
             *  Products collection does load product objects with some of the
             *  needed data, that is why we want to reload product instance
             *  via Mage::getModel() method.
             */
            case 'catalog/product': 
                foreach ($collection as $product) 
                {
                    // reload product
                    $product = Mage::getModel('catalog/product')->load($product->getId());

                    // store reloaded product
                    $this->storeProduct($product); 
                }
                break;

            /** 
             *  Address collection does load address objects with some of the
             *  needed data, that is why we want to reload address instance
             *  via Mage::getModel() method.
             */
            case 'customer/address': 
                foreach ($collection as $address)
                {
                    // reload address data
                    $address = Mage::getModel('customer/address')->load($address->getId());

                    // store address
                    $this->storeAddress($address);  
                } 

                // we are done here
                break;

            /** 
             *  Customer collection does load customer objects with some of the
             *  needed data, that is why we want to reload customer instance
             *  via Mage::getModel() method.
             */
            case 'customer/customer': 
                foreach ($collection as $customer) 
                {
                    // reload customer data
                    $customer = Mage::getModel('customer/customer')->load($customer->getId());

                    // store customer
                    $this->storeCustomer($customer); 
                }

                // we are done here
                break;
        }
    }

    /**
     *  Register a product with copernica
     *
     *  @param  Mage_Catalog_Model_Product  the product that was added or modified
     */
    public function storeProduct(Mage_Catalog_Model_Product $product)
    {
        // we will need store instance to get the currency code
        $store = Mage::getModel('core/store')->load($product->getStoreId());

        // store the product
        $this->request->put("magento/product/{$product->getId()}", array(
            'sku'           =>  $product->getSku(),
            'name'          =>  $product->getName(),
            'description'   =>  $product->getDescription(),
            'currency'      =>  $store->getCurrentCurrencyCode(),
            'price'         =>  $product->getPrice(),
            'weight'        =>  $product->getWeight(),
            'modified'      =>  $product->getUpdatedAt(),
            'uri'           =>  $product->getProductUrl(),
            'image'         =>  $product->getImageUrl(),
        ));
    }

    /**
     *  Register a quote with copernica
     *
     *  @param  Mage_Sales_Model_Quote  the quote that was created or modified
     */
    public function storeQuote(Mage_Sales_Model_Quote $quote)
    {
        // get the shipping and billing addresses
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress  = $quote->getBillingAddress();

        // get quote totals
        $totals = $quote->getTotals();

        // store the quote
        $this->request->put("magento/quote/{$quote->getId()}", array(
            'customer'          =>  $quote->getCustomerId(),
            'store'             =>  $quote->getStoreId(),
            'shipping_address'  =>  is_null($shippingAddress)   ? null : $shippingAddress->getId(),
            'billing_address'   =>  is_null($billingAddress)    ? null : $billingAddress->getId(),
            'weight'            =>  is_null($shippingAddress)   ? null : $shippingAddress->getWeight(),
            'active'            =>  (bool)$quote->getIsActive(),
            'quantity'          =>  $quote->getItemsQty(),
            'currency'          =>  $quote->getQuoteCurrencyCode(),
            'shipping_cost'     =>  $quote->getShippingAmount(),
            'tax'               =>  isset($totals['tax']) ? $totals['tax']->getValue() : 0,
            'ip_address'        =>  $quote->getRemoteIp(),
            'last_modified'     =>  $quote->getUpdatedAt(),
        ));
    }

    /**
     *  Remove a quote item from copernica
     *
     *  @param  Mage_Sales_Model_Quote_Item the quote item that was removed
     */
    public function removeQuoteItem(Mage_Sales_Model_Quote_Item $item)
    {
        // remove the quote item
        $this->request->delete("magento/quoteitem/{$item->getId()}");
    }

    /**
     *  Register a quote item with copernica
     *
     *  @param  Mage_Sales_Model_Quote_Item the quote item that was created or modified
     */
    public function storeQuoteItem(Mage_Sales_Model_Quote_item $item)
    {
        // load the accompanying quote by id, since the getQuote method
        // seems to be severely borken in some magento versions
        // Quote is a store entity. And just cause of that magento doing funky
        // stuff when fetching quote just by id. To fetch quote with any kind 
        // of useful data we have to explicitly say to magento that we want a 
        // quote without store.
        $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($item->getQuoteId());

        // item-quote relation is super broken
        $item->setQuote($quote);

        // store the quote item
        $this->request->put("magento/quoteitem/{$item->getId()}", array(
            'quote'     =>  $item->getQuoteId(),
            'product'   =>  $item->getProductId(),
            'quantity'  =>  $item->getQty(),
            'price'     =>  $item->getPrice(),
            'currency'  =>  $quote->getQuoteCurrencyCode(),
            'weight'    =>  $item->getWeight(),
        ));
    }

    /**
     *  Register an order with copernica
     *
     *  @param  Mage_Sales_Model_Order  the order that was created or modified
     */
    public function storeOrder(Mage_Sales_Model_Order $order)
    {
        // get the shipping and billing addresses
        $shippingAddress = $order->getShippingAddress();
        $billingAddress  = $order->getBillingAddress();

        // store the quote
        $this->request->put("magento/order/{$order->getId()}", array(
            'quote'             =>  $order->getQuoteId(),
            'customer'          =>  $order->getCustomerId(),
            'store'             =>  $order->getStoreId(),
            'shipping_address'  =>  is_null($shippingAddress)   ? null : $shippingAddress->getId(),
            'billing_address'   =>  is_null($billingAddress)    ? null : $billingAddress->getId(),
            'state'             =>  $order->getState(),
            'status'            =>  $order->getStatus(),
            'weight'            =>  $order->getWeight(),
            'quantity'          =>  $order->getTotalQtyOrdered(),
            'currency'          =>  $order->getOrderCurrencyCode(),
            'shipping_cost'     =>  $order->getShippingAmount(),
            'tax'               =>  $order->getTaxAmount(),
            'ip_address'        =>  $order->getRemoteIp(),
        ));
    }

    /**
     *  Register an order item with copernica
     *
     *  @param  Mage_Sales_Model_Order_item the item that was created or modified
     */
    public function storeOrderItem(Mage_Sales_Model_Order_Item $item)
    {
        // store the order item
        $this->request->put("magento/orderitem/{$item->getId()}", array(
            'order'     =>  $item->getOrderId(),
            'product'   =>  $item->getProductId(),
            'quantity'  =>  $item->getData('qty_ordered'),
            'price'     =>  $item->getPrice(),
            'currency'  =>  $item->getOrder()->getOrderCurrencyCode(),
            'weight'    =>  $item->getWeight(),
        ));
    }

    /**
     *  Helper method to get subscription status of a subscriber
     *  @param  Mage_Newsletter_Model_Subscriber
     *  @return string
     */
    private function subscriptionStatus(Mage_Newsletter_Model_Subscriber $subscriber)
    {
        switch ($subscriber->getStatus())
        {
            case Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED:   return 'subscribed';
            case Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE:   return 'not active';
            case Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED: return 'unsubscribed';
            case Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED:  return 'unconfirmed';
            default:                                                    return 'unknown';
        }
    }

    /**
     *  Register a newsletter subscriber with copernica
     *
     *  @param  Mage_Newsletter_Model_Subscriber    the subscriber that was added or modified
     */
    public function storeSubscriber(Mage_Newsletter_Model_Subscriber $subscriber)
    {
        // store the subscriber
        $this->request->put("magento/subscriber/{$subscriber->getId()}", array(
            'customer'  =>  $subscriber->getCustomerId(),
            'email'     =>  $subscriber->getEmail(),
            'modified'  =>  $subscriber->getChangeStatusAt(),
            'status'    =>  $this->subscriptionStatus($subscriber),
            'store'     =>  $subscriber->getStoreId(),
        ));
    }

    /**
     *  Remove a newsletter subscriber from copernica
     *
     *  @param  Mage_Newsletter_Model_Subscriber    the subscriber that was removed
     */
    public function removeSubscriber(Mage_Newsletter_Model_Subscriber $subscriber)
    {
        // remove the quote
        $this->request->delete("magento/subscriber/{$subscriber->getId()}");
    }

    /**
     *  Store a customer in copernica
     *
     *  @param  Mage_Customer_Model_Customer    the customer that was added or modified
     */
    public function storeCustomer(Mage_Customer_Model_Customer $customer)
    {
        // determine the gender of the customer
        $gender = strtolower(Mage::getResourceSingleton('customer/customer')->getAttribute('gender')->getSource()->getOptionText($customer->getGender()));

        // if we do not get a gender something went wrong (or we don't know the gender)
        if (empty($gender)) $gender = null;

        // get subscriber instance linked with current customer
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());

        // store the customer
        $this->request->put("magento/customer/{$customer->getId()}", array(
            'store'         =>  $customer->getStoreId(),
            'firstname'     =>  $customer->getFirstname(),
            'prefix'        =>  $customer->getPrefix(),
            'middlename'    =>  $customer->getMiddlename(),
            'lastname'      =>  $customer->getLastname(),
            'email'         =>  $customer->getEmail(),
            'gender'        =>  $gender,
            'subscribed'    =>  $this->subscriptionStatus($subscriber),
        ));
    }

    /**
     *  Remove a customer from copernica
     *
     *  @param  Mage_Customer_Model_Customer    the customer that was removed
     */
    public function removeCustomer(Mage_Customer_Model_Customer $customer)
    {
        // remove the customer
        $this->request->delete("magento/customer/{$customer->getId()}");
    }

    /**
     *  Store an address in copernica
     *
     *  @param  Mage_Customer_Model_Address the address that was added or modified
     */
    public function storeAddress(Mage_Customer_Model_Address $address)
    {
        // retrieve the customer this address belongs to
        $customer = $address->getCustomer();

        // store the address
        $this->request->put("magento/address/{$address->getId()}", array(
            'billingAddress'    =>  $customer->getData('default_billing') == $address->getId(),
            'deliveryAddress'   =>  $customer->getData('default_shipping') == $address->getid(),
            'customer'          =>  $customer->getId(),
            'country'           =>  (string)$address->getCountry(),
            'street'            =>  (string)$address->getStreetFull(),
            'city'              =>  (string)$address->getCity(),
            'zipcode'           =>  (string)$address->getPostcode(),
            'state'             =>  (string)$address->getRegion(),
            'phone'             =>  (string)$address->getTelephone(),
            'fax'               =>  (string)$address->getFax(),
            'company'           =>  (string)$address->getCompany(),
        ));
    }

    /**
     *  Store an store in copernica
     *  
     *  @param  Mage_Core_Model_Store
     */
    public function storeStore(Mage_Core_Model_Store $store)
    {
        // get store website
        $website = $store->getWebsite();

        // get store group
        $group = $store->getGroup();

        // store the store
        $this->request->put("magento/store/{$store->getId()}", array(
            'name'          => $store->getName(),
            'websiteId'     => $website->getId(),
            'websiteName'   => $website->getName(),
            'groupId'       => $group->getId(),
            'groupName'     => $group->getName()
        ));
    }
}
