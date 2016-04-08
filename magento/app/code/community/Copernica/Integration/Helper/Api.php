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
            case 'sales/quote':             foreach ($collection as $quote) $this->storeQuote($quote); break;
            case 'sales/quote_item':        foreach ($collection as $item) $this->storeQuoteItem($item); break;
            case 'sales/order':             foreach ($collection as $order) $this->storeOrder($order); break;
            case 'sales/order_item':        foreach ($collection as $item) $this->storeOrderItem($item); break;
            case 'newsletter/subscriber':   foreach ($collection as $subscriber) $this->storeSubscriber($subscriber); break;
            case 'core/store':              foreach ($collection as $store) $this->storeStore($store); break;
            case 'customer/group':          foreach ($collection as $group) $this->storeGroup($group); break;
            case 'wishlist/wishlist':       foreach ($collection as $wishlist) $this->storeWishlist($wishlist); break;
            case 'wishlist/item':           foreach ($collection as $item) $this->storeWishlistItem($item); break;
            case 'catalog/category':        foreach ($collection as $item) $this->storeCategory($item); break;
            case 'catalog/product':         foreach ($collection as $item) $this->storeProduct($item); break;
            case 'sales/order_address':
            case 'sales/quote_address':
            case 'customer/address':        foreach ($collection as $item) $this->storeAddress($item); break;
            case 'customer/customer':       foreach ($collection as $item) $this->storeCustomer($item); break;
        }

        /**
         *  When dealing with whole collections it's better to finalize current
         *  set of API requests. This way we can safely continue to next requests.
         */
        $this->request->commit();
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

        /**
         *  Previously we used Mage_Catalog_Model_Product::getImageUrl() to fetch
         *  product image url. It's wrong for 2 reasons: url is not to original
         *  image but to modified image and that image is stored inside cache.
         *  When image is stored inside cache it may present problems when flushing
         *  media cache. Some of the links may lead to non existing files.
         *
         *  Thus, we will use proper helper to get image url.
         */
        try
        {
            $imageUrl = strval(Mage::helper('catalog/image')->init($product, 'image'));
        }

        // handle the exception
        catch (\Exception $e)
        {
            $imageUrl = null;
        }

        // prepare data
        $data = array(
            'sku'           =>  $product->getSku(),
            'name'          =>  $product->getName(),
            'description'   =>  $product->getDescription(),
            'currency'      =>  $store->getCurrentCurrencyCode(),
            'price'         =>  $product->getPrice(),
            'weight'        =>  $product->getWeight(),
            'modified'      =>  $product->getUpdatedAt(),
            'uri'           =>  $product->getProductUrl(),
            'image'         =>  $imageUrl,
            'categories'    =>  $product->getCategoryIds(),
            'type'          =>  $product->getTypeId(),
        );

        /**
         *  We want to synchronize attributes along the basic product data. We will
         *  send all attributes as objects inside one array.
         */
        $data['attributes'] = array();

        /**
         *  Beside basic product data we also want to sync attributes information
         *  for each product.
         */
        foreach ($product->getAttributes() as $attribute)
        {
            $data['attributes'][] = array(
                'code'              => $attribute->getAttributeCode(),
                'frontendLabel'     => $attribute->getFrontendLabel(),
                'value'             => $attribute->getFrontend()->getValue($product),
            );
        }

        // we will store them as simple array
        $data['options'] = array();

        // get all product options
        foreach ($product->getOptions() as $option)
        {
            /**
             *  Important parts about the options is the option title (customer
             *  friendly version), type of the field, ofcourse, the potential
             *  values that can be assigned to option.
             */
            $optionData = array(
                'id'            => $option->getId(),
                'title'         => $option->getTitle(),
                'type'          => $option->getType(),
                'required'      => $option->getIsRequire(),
                'sortOrder'     => $option->getSortOrder(),
                'maxCharacters' => $option->getMaxCharacters(),
                'values'        => array(),
            );

            /**
             *  With file type we can have additional data to sync.
             */
            if ($option->getType() == 'file')
            {
                $optionData['imageSizeX'] = $option->getImageSizeX();
                $optionData['imageSizeY'] = $option->getImageSizeY();
                $optionData['fileExtension'] = $option->getFileExtension();
            }

            /**
             *  Iterate over all options values and assign them to values property.
             */
            foreach ($option->getValues() as $value)
            {
                // get value data
                $optionData['values'][] = array (
                    'title'     => $value->getTitle(),
                    'sku'       => $value->getSku(),
                    'price'     => $value->getPrice(),
                    'priceType' => $value->getPriceType(),
                    'sortOrder' => $value->getSortOrder(),
                );
            }

            // assign options data
            $data['options'][] = $optionData;
        }

        // store the product
        $this->request->put("magento/product/{$product->getId()}", $data);
    }

    /**
     *  Register a quote with copernica
     *
     *  @param  Mage_Sales_Model_Quote  the quote that was created or modified
     */
    public function storeQuote(Mage_Sales_Model_Quote $quote)
    {
        // check if store is disabled for sync
        if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $quote->getStoreId())) return;

        // get the shipping and billing addresses
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress  = $quote->getBillingAddress();

        // get quote totals
        $totals = $quote->getTotals();

        // store the quote
        $this->request->put("magento/quote/{$quote->getId()}", array(
            'customer'          =>  $quote->getCustomerId(),
            'webstore'          =>  $quote->getStoreId(),
            'shipping_address'  =>  is_null($shippingAddress)   ? null : $shippingAddress->getId(),
            'billing_address'   =>  is_null($billingAddress)    ? null : $billingAddress->getId(),
            'weight'            =>  is_null($shippingAddress)   ? null : $shippingAddress->getWeight(),
            'active'            =>  (bool)$quote->getIsActive(),
            'quantity'          =>  $quote->getItemsQty(),
            'currency'          =>  $quote->getQuoteCurrencyCode(),
            'shipping_cost'     =>  $quote->getShippingAmount(),
            'tax'               =>  isset($totals['tax']) ? $totals['tax']->getValue() : 0,
            'subtotal'          =>  isset($totals['subtotal']) ? $totals['subtotal']->getValue() : 0,
            'grand_total'       =>  isset($totals['grand_total']) ? $totals['grand_total']->getValue() : 0,
            'ip_address'        =>  $quote->getRemoteIp(),
            'last_modified'     =>  $quote->getUpdatedAt(),
        ));
    }

    /**
     *  Remove a quote item from copernica
     *
     *  @param  int     The quote item Id
     */
    public function removeQuoteItem($id)
    {
        // remove the quote item
        $this->request->delete("magento/quoteitem/{$id}");
    }

    /**
     *  Register a quote item with copernica
     *
     *  @param  Mage_Sales_Model_Quote_Item the quote item that was created or modified
     */
    public function storeQuoteItem(Mage_Sales_Model_Quote_item $item)
    {
        /**
         *  Load the accompanying quote by id, since the getQuote method
         *  seems to be severely borken in some magento versions
         *  Quote is a store entity. And just cause of that magento doing funky
         *  stuff when fetching quote just by id. To fetch quote with any kind
         *  of useful data we have to explicitly say to magento that we want a
         *  quote without store.
         */
        $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($item->getQuoteId());

        // check if store is disabled for sync
        if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $quote->getStoreId())) return;

        // item-quote relation is super broken
        $item->setQuote($quote);

        /**
         *  Something about magento address handling. It's possible to set
         *  shipping address for each quote item to completely different places.
         *  The 'multi shipping'. Really nice feature. Thus, you can not ask
         *  the quote item to where it will be shipped. Instead you have to
         *  ask the quote address to where item will be shipped (by ::getItemByQuoteItemId())
         *  and then you will be given a address object or false value when
         *  item does not have any special destination.
         *  For regular person, false value would mean that item does not have
         *  a shipping destination.
         */

        // get quote item shipping address
        $quoteItemShippingAddress = $quote->getShippingAddress()->getItemByQuoteItemId($item->getId());

        // start preparing data
        $data = array(
            'quote'         =>  $item->getQuoteId(),
            'product'       =>  $item->getProductId(),
            'quantity'      =>  $item->getQty(),
            'price'         =>  $item->getPrice(),
            'currency'      =>  $quote->getQuoteCurrencyCode(),
            'weight'        =>  $item->getWeight(),
            'address'       =>  is_object($quoteItemShippingAddress) ? $quoteItemShippingAddress->getAddress()->getId() : null,
            'parentItem'    =>  ($parentId = $item->getParentItemId()) ? intval($parentId) : null,
            'options'       =>  array(),
        );

        /**
         *  Quote items can have selected number of custom options. Quote item
         *  class has method ::getOptions(). It doesn't work in expected way.
         *  Even when quote item has options set it will return null regardless.
         *  Thus, to get the actual options we will have to make additioanal
         *  processing. First we will have to get options data.
         */
        $options = Mage::getResourceModel('sales/quote_item_option_collection');
        $options->addItemFilter($item->getId());
        foreach($options as $option) {

            /**
             *  Options collection contains options data that is quite odd. It
             *  contains options with codes like "info_buyRequest" and "option_ids".
             *  Such are not really options so we can skip them.
             */
            if ($option->getCode() == 'info_buyRequest' || $option->getCode() == 'option_ids') continue;

            // for copernica the option id is important and not the 'code' value.
            $matchResult = preg_match('/option_([0-9]+)/', $option->getCode(), $matches);

            // if no matches we can proceed further
            if (!$matchResult) continue;

            /**
             *  At this point we are nearly done. We have option id and we have
             *  the value. But we could encounter serialized data as value.
             *  Yes, serialized data. So we should try to unserialize data.
             */
            $value = unserialize($option->getValue());

            /**
             *  In most of the cases data should be a scalar. This will cause
             *  unserialization process to fail and return false. In such cases
             *  we will go back and use original data.
             */
            if ($value === false) $value = $option->getValue();

            /**
             *  With custom option we can end up with file option. We should
             *  provide a url to that file. Thus, magento is being really
             *  uncooperative in this manner. At this time we are just sending
             *  all data with the result, but we are missing the actual url that
             *  can be used to show that file.
             *  @todo feature missing
             */

            $optionId = $matches[1];

            /**
             *  It may happen that inside database there is reference to option
             *  instance that is no longer existing. Thus we should check if
             *  such option is still in database. If so then we will send it to
             *  Copernica API.
             */
            if (!$optionId) continue;

            /**
             *  Seems that magento doesn't fetch options titles automatically.
             *  It doest that when options are fetched from collection. And it
             *  does so magic stuff inside collection. Instead of making it really
             *  complicated we will just ask the database directly.
             */
            $resource = Mage::getSingleton('core/resource');
            $tableName = $resource->getTableName('catalog_product_option_title');
            $read = $resource->getConnection('core_read');
            $sql = "SELECT title FROM {$tableName} WHERE store_id = 0 AND option_id = {$optionId}";
            $title = $read->fetchOne($sql);

            /**
             *  Inside file option magento can store quite an amount of information.
             *  We don't want to sent it all (as is) to Copernica.
             */
            if (Mage::getModel('catalog/product_option')->load($optionId)->getType() == 'file') $value = $value['title'];

            // add another option to the result
            $data['options'][] = array (
                'id'    => $optionId,
                'label' => $title,
                'value' => $value,
            );
        }

        // store the quote item
        $this->request->put("magento/quoteitem/{$item->getId()}", $data);
    }

    /**
     *  Register an order with copernica
     *
     *  @param  Mage_Sales_Model_Order  the order that was created or modified
     */
    public function storeOrder(Mage_Sales_Model_Order $order)
    {
        // check if store is disabled for sync
        if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $order->getStoreId())) return;

        // get the shipping and billing addresses
        $shippingAddress = $order->getShippingAddress();
        $billingAddress  = $order->getBillingAddress();

        // determine the gender of the customer
        $gender = strtolower(Mage::getResourceSingleton('customer/customer')->getAttribute('gender')->getSource()->getOptionText($order->getCustomerGender()));

        // if we do not get a gender, something went wrong (or we don't know the gender)
        if (empty($gender)) $gender = null;

        // store the quote
        $this->request->put("magento/order/{$order->getId()}", array(
            'increment'             =>  $order->getIncrementId(),
            'quote'                 =>  $order->getQuoteId(),
            'customer'              =>  $order->getCustomerId(),
            'webstore'              =>  $order->getStoreId(),
            'shipping_address'      =>  is_object($shippingAddress)   ? $shippingAddress->getId()   : null,
            'billing_address'       =>  is_object($billingAddress)    ? $billingAddress->getId()    : null,
            'state'                 =>  $order->getState(),
            'status'                =>  $order->getStatus(),
            'weight'                =>  $order->getWeight(),
            'quantity'              =>  $order->getTotalQtyOrdered(),
            'currency'              =>  $order->getOrderCurrencyCode(),
            'shipping_cost'         =>  $order->getShippingAmount(),
            'grand_total'           =>  $order->getGrandTotal(),
            'subtotal'              =>  $order->getSubtotal(),
            'tax'                   =>  $order->getTaxAmount(),
            'ip_address'            =>  $order->getRemoteIp(),
            'updated'               =>  $order->getUpdatedAt(),
            'created'               =>  $order->getCreatedAt(),
            'customer_gender'       =>  $gender,
            'customer_groupid'      =>  $order->getCustomerGroupId(),
            'customer_subscription' =>  $order->getCustomerSubscription(),
            'customer_email'        =>  $order->getCustomerEmail(),
            'customer_firstname'    =>  $order->getCustomerFirstname(),
            'customer_middlename'   =>  $order->getCustomerMiddlename(),
            'customer_prefix'       =>  $order->getCustomerPrefix(),
            'customer_lastname'     =>  $order->getCustomerLastname(),

            // @todo eventually API will not accept below line
            'customer_groupname'    =>  $order->getCustomerGroupname(),
        ));
    }

    /**
     *  Register an order item with copernica
     *
     *  @param  Mage_Sales_Model_Order_item the item that was created or modified
     */
    public function storeOrderItem(Mage_Sales_Model_Order_Item $item)
    {
        // check if store is disabled for sync
        if (!Mage::getStoreConfig('copernica_options/apisync/enabled', Mage::getModel('sales/order')->load($item->getOrderId())->getStoreId())) return;

        // store the order item
        $this->request->put("magento/orderitem/{$item->getId()}", array(
            'order'         =>  $item->getOrderId(),
            'product'       =>  $item->getProductId(),
            'quantity'      =>  $item->getData('qty_ordered'),
            'price'         =>  $item->getPrice(),
            'currency'      =>  $item->getOrder()->getOrderCurrencyCode(),
            'weight'        =>  $item->getWeight(),
            'parentItem'    =>  ($parentId = $item->getParentItemId()) ? intval($parentId) : null,
            'quoteItem'     =>  $item->getQuoteItemId(),
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
        // check if store is disabled for sync
        if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $subscriber->getStoreId())) return;

        // store the subscriber
        $this->request->put("magento/subscriber/{$subscriber->getId()}", array(
            'customer'  =>  $subscriber->getCustomerId(),
            'email'     =>  $subscriber->getEmail(),
            'modified'  =>  $subscriber->getChangeStatusAt(),
            'status'    =>  $this->subscriptionStatus($subscriber),
            'webstore'  =>  $subscriber->getStoreId(),
        ));
    }

    /**
     *  Remove a newsletter subscriber from copernica
     *
     *  @param  int     The subscriber Id
     */
    public function removeSubscriber($id)
    {
        // remove the quote
        $this->request->delete("magento/subscriber/{$id}");
    }

    /**
     *  Store a customer in copernica
     *
     *  @param  Mage_Customer_Model_Customer    the customer that was added or modified
     */
    public function storeCustomer(Mage_Customer_Model_Customer $customer)
    {
        // check if store is disabled for sync
        if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $customer->getStoreId())) return;

        // determine the gender of the customer
        $gender = strtolower(Mage::getResourceSingleton('customer/customer')->getAttribute('gender')->getSource()->getOptionText($customer->getGender()));

        // if we do not get a gender something went wrong (or we don't know the gender)
        if (empty($gender)) $gender = null;

        // store the customer
        $this->request->put("magento/customer/{$customer->getId()}", array(
            'webstore'      =>  $customer->getStoreId(),
            'firstname'     =>  $customer->getFirstname(),
            'prefix'        =>  $customer->getPrefix(),
            'middlename'    =>  $customer->getMiddlename(),
            'lastname'      =>  $customer->getLastname(),
            'email'         =>  $customer->getEmail(),
            'gender'        =>  $gender,
            'group'         =>  $customer->getGroupId(),
        ));
    }

    /**
     *  Remove a customer from copernica
     *
     *  @param  int     The customer Id
     */
    public function removeCustomer($id)
    {
        // remove the customer
        $this->request->delete("magento/customer/{$id}");
    }

    /**
     *  Store an address in copernica
     *
     *  @param  Mage_Customer_Model_Address_Abstract the address that was added or modified
     */
    public function storeAddress(Mage_Customer_Model_Address_Abstract $address)
    {
        /**
         *  Magento has a little mess with address handling. Basically there
         *  can be several types of address that will have common structure.
         *  Semantically they mean same this: a real place in the world. It would
         *  be wise to put them inside one table and have only one class that will
         *  describe such basic thing. Magento core team decided to separate
         *  such entities and make separata ID sequences for customer, order and
         *  quote address (maybe there are more, but they don't concern us right now),
         *  making whole address handling very ambiguous.
         *  To make things easier we will limit ourselfs to customer, order and quote
         *  address and assign them a 'type' that will describe from what kind
         *  of magento address copernica address came.
         *  If we will encounter any other type of address we will just ignore it
         *  it since we don't have any means ofhadnling such.
         *
         *  And since customer, order, quote flavors of common address classes
         *  are pretty much separate they have different interfaces for fetching
         *  common data like customer id or shipping and billing flags. Thus we
         *  have to parse them in correct manner.
         */
        if ($address instanceof Mage_Customer_Model_Address)
        {
            // get customer instance
            $customer = $address->getCustomer();

            // check if store is disabled for sync
            if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $customer->getStoreId())) return;

            // set address type, customer, billing and shipping flag
            $metaData = array (
                'type'              => 'customer',
                'billingAddress'    => $customer->getDefaultBilling() == $address->getId(),
                'deliveryAddress'   => $customer->getDefaultShipping() == $address->getId(),
                'customer'          => $customer->getId(),
            );
        }
        else if ($address instanceof Mage_Sales_Model_Order_Address)
        {
            // get order instance
            $order = $address->getOrder();

            // check if store is disabled for sync
            if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $order->getStoreId())) return;

            // set address type, customer, billing and shipping flag
            $metaData = array(
                'type'              => 'order',
                'billingAddress'    => $order->getData('billing_address_id') == $address->getId(),
                'deliveryAddress'   => $order->getData('shipping_address_id') == $address->getId(),
                'order'             => $order->getId(),
                'customer'          => $order->getData('customer_id'),
            );
        }
        else if ($address instanceof Mage_Sales_Model_Quote_Address)
        {
            // get quote Id
            $quoteId = $address->getQuoteId();

            /**
             *  This part is really retarded. When data is fetched by magento,
             *  from database into Mage_Sales_Model_Quote_Address instance.
             *  'quote_id' is not set despite that it's has a value in mysql
             *  table.
             *
             *  Thus, to fix it we have to make very specific sql query.
             */
            if (!$quoteId)
            {
                $resource = Mage::getSingleton('core/resource');
                $connRead = $resource->getConnection('core_read');

                // get quote Id
                $quoteId = $connRead->fetchOne("SELECT quote_id FROM {$resource->getTableName('sales/quote_address')} WHERE `address_id` = :address", array('address' => $address->getId()));
            }

            // load quote instance
            $quote = Mage::getModel('sales/quote')->load($quoteId);

            // check if store is disabled for sync
            if (!Mage::getStoreConfig('copernica_options/apisync/enabled', $quote->getStoreId())) return;

            // set address type, customer, billing and shipping flag
            $metaData = array(
                'type'              => 'quote',
                'billingAddress'    => $address->getData('address_type') == 'billing',
                'deliveryAddress'   => $address->getData('address_type') == 'shipping',
                'customer'          => $address->getData('customer_id'),
                'quote'             => $quoteId,
            );
        }

        // we have some unknown address type. We will not do anything good with it
        else return;

        // store the address
        $this->request->put("magento/address/{$address->getId()}", array_merge( $metaData, array(
            'country'           =>  (string)$address->getCountry(),
            'street'            =>  (string)$address->getStreetFull(),
            'city'              =>  (string)$address->getCity(),
            'zipcode'           =>  (string)$address->getPostcode(),
            'state'             =>  (string)$address->getRegion(),
            'phone'             =>  (string)$address->getTelephone(),
            'fax'               =>  (string)$address->getFax(),
            'company'           =>  (string)$address->getCompany(),
        )));
    }

    /**
     *  Remove magento address from copernica platform
     *  @param  int     The address Id
     *  @param  string  Type of address. It should be one of ['customer', 'order', 'quote']
     */
    public function removeAddress($id, $type)
    {
        // we can handle only customer, order or quote addresses
        if (!in_array($type, array('customer', 'order', 'quote'))) return;

        // remove address
        $this->request->delete("magento/address", array( 'ID' => $id, 'type' => $type));
    }

    /**
     *  Store a store in copernica
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
            'groupName'     => $group->getName(),
            'rootCategory'  => $store->getRootCategoryId(),
            'url'           => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK),
        ));
    }

    /**
     *  Store magento category in copernica
     *  @param  Mage_Catalog_Model_Category
     */
    public function storeCategory(Mage_Catalog_Model_Category $category)
    {
        $this->request->put("magento/category/{$category->getId()}", array(
            'name'      =>  $category->getName(),
            'created'   =>  $category->getCreatedAt(),
            'modified'  =>  $category->getUpdatedAt(),
            'parent'    =>  $category->getParentCategory()->getId(),
        ));
    }

    /**
     *  Remove magento category in copernica
     *  @param int  The category Id
     */
    public function removeCategory($id)
    {
        $this->request->delete("magento/category/{$id}");
    }

    /**
     *  Store magento group in copernica
     *  @param  Mage_Customer_Model_Group
     */
    public function storeGroup(Mage_Customer_Model_Group $group)
    {
        $this->request->put("magento/group/{$group->getId()}", array(
            'name'  => $group->getCustomerGroupCode(),
        ));
    }

    /**
     *  Remove magento group in copernica
     *  @param  int
     */
    public function removeGroup($id)
    {
        $this->request->delete("magento/group/{$id}");
    }

    /**
     *  Store wishlist
     *  @param  Mage_Wishlist_Model_Wishlist
     */
    public function storeWishlist(Mage_Wishlist_Model_Wishlist $wishlist)
    {
        $this->request->put("magento/wishlist/{$wishlist->getId()}", array(
            'customerId'    => $wishlist->getCustomerId(),
            'shared'        => (bool)$wishlist->getShared(),
            'sharingCode'   => $wishlist->getSharingCode(),
            'updatedAt'     => $wishlist->getUpdatedAt(),
            'webstoreId'    => $wishlist->getStoreId(),
        ));
    }

    /**
     *  Store wishlist item
     *  @param Mage_Wishlist_Model_Item
     */
    public function storeWishlistItem(Mage_Wishlist_Model_Item $item)
    {
        $this->request->put("magento/wishlistitem/{$item->getId()}", array(
            'wishlistId'    => $item->getWishlistId(),
            'productId'     => $item->getProductId(),
            'addedAt'       => $item->getAddedAt(),
            'webstoreId'    => $item->getStoreId(),
            'description'   => $item->getDescription(),
            'quantity'      => $item->getQty(),
        ));
    }

    /**
     *  Store product view.
     *  @param  Copernica_Integration_Model_ViewedProduct
     */
    public function storeProductView(Copernica_Integration_Model_ProductView $view)
    {
        $this->request->post("magento/productview", array(
            "customer_id"   => $view->getCustomerId(),
            "product_id"    => $view->getProductId(),
            "webstore_id"   => $view->getStoreId(),
            "viewed_at"     => $view->getViewedAt(),
        ));
    }

    /**
     *  Remove magento wishlist in copernica
     *  @param  int
     */
    public function removeWishlist($id)
    {
        $this->request->delete("magento/wishlist/{$id}");
    }

    /**
     *  Remove magento wishlist item in copernica
     *  @param  int
     */
    public function removeWishlistItem($id)
    {
        $this->request->delete("magento/wishlistitem/{$id}");
    }

    /**
     *  Store progress sync progress inside API.
     *  @param  array   Assoc array with data to be sent to API
     */
    public function updateSyncStatus()
    {
        // get config helper into local scope
        $config = Mage::helper('integration/config');

        // get total number of models that should be synced
        $total = $config->getSyncTotal();

        // if we have total number we can go and report it to Copernica
        if ($total) $this->request->put("magento/sync", array (
            'total'     => $total,
            'processed' => $config->getSyncProgress()
        ));

        // remove sync entity (as there is no initial sync going on)
        else $this->request->delete("magento/sync");
    }
}
