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

        // check if we have proper access token
        if (!empty($output['access_token'])) return $output['access_token'];

        // return output from API
        return false;
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

        // store the quote
        $this->request->put("magento/quote/{$quote->getId()}", array(
            'customer'          =>  $quote->getCustomerId(),
            'shipping_address'  =>  is_null($shippingAddress)   ? null : $shippingAddress->getId(),
            'billing_address'   =>  is_null($billingAddress)    ? null : $billingAddress->getId(),
            'weight'            =>  is_null($shippingAddress)   ? null : $shippingAddress->getWeight(),
            'active'            =>  (bool)$quote->getIsActive(),
            'quantity'          =>  $quote->getItemsQty(),
            'currency'          =>  $quote->getQuoteCurrencyCode(),
            'shipping_cost'     =>  $quote->getShippingAmount(),
            'tax'               =>  $quote->getTaxAmount(),
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
        $quote = Mage::getModel('sales/quote')->load($item->getQuoteId());

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
            'shipping_address'  =>  is_null($shippingAddress)   ? null : $shippingAddress->getId(),
            'billing_address'   =>  is_null($billingAddress)    ? null : $billingAddress->getId(),
            'weight'            =>  $order->getWeight(),
            'quantity'          =>  $order->getTotalQtyOrdered(),
            'currency'          =>  $order->getOrderCurrencyCode(),
            'shipping_cost'     =>  $order->getShippingAmount(),
            'tax'               =>  $order->getTaxAmount(),
            'ip_address'        =>  $order->getRemoteIp(),
        ));
    }

    /**
     *  Register a newsletter subscriber with copernica
     *
     *  @param  Mage_Newsletter_Model_Subscriber    the subscriber that was added or modified
     */
    public function storeSubscriber(Mage_Newsletter_Model_Subscriber $subscriber)
    {
        // get a string representation of the subscriber status
        switch ($subscriber->getStatus())
        {
            case Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED:   $status = 'subscribed';     break;
            case Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE:   $status = 'not active';     break;
            case Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED: $status = 'unsubscribed';   break;
            case Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED:  $status = 'unconfirmed';    break;
            default:                                                    $status = 'unknown';        break;
        }

        // store the subscriber
        $this->request->put("magento/subscriber/{$subscriber->getId(0)}", array(
            'customer'  =>  $subscriber->getCustomerId(),
            'email'     =>  $subscriber->getEmail(),
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

        // store the customer
        $this->request->put("magento/customer/{$customer->getId()}", array(
            'firstname'     =>  $customer->getFirstname(),
            'prefix'        =>  $customer->getPrefix(),
            'middlename'    =>  $customer->getMiddlename(),
            'lastname'      =>  $customer->getLastname(),
            'email'         =>  $customer->getEmail(),
            'gender'        =>  $gender,
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
}
