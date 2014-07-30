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
 * Controls the product actions.
 *
 *
 */
class Copernica_Integration_ProductController extends Mage_Core_Controller_Front_Action
{
    /**
     * Handles a request to copernica/product/xml
     * Prints a XML with product information.
     */
    public function xmlAction()
    {
        //TODO: some security
        $request = $this->getRequest();
        if ($request->getParam('identifier') == "sku") {
            $product = $this->_getProductBySku($request->getParam('id'));
        } else {
            $product = $this->_getProduct($request->getParam('id'));
        }

        // Use attribute codes or labels?
        $useAttribCode = false;
        if ($request->getParam('attribkey') == 'code') {
            $useAttribCode = true;
        }

        if ($product != NULL) {
            $xml = $this->_buildProductXML(array($product), $useAttribCode);
            $this->_prepareResponse($xml);
        }
        elseif ($request->getParam('new'))
        {
            // Today it is:
            $todayDate = date('Y-m-d H:i:s');

            // Get the collection, add the filters and select all data
            $collection = Mage::getResourceModel('catalog/product_collection')
                            ->addAttributeToFilter('news_from_date', array(
                                'date' => true,
                                'to' => $todayDate)
                            )
                            ->addAttributeToFilter('news_to_date', array(
                                'or'=> array(
                                    0 => array('date' => true, 'from' => $todayDate),
                                    1 => array('is' => new Zend_Db_Expr('null')))
                                ), 'left'
                            )
                            ->addAttributeToSelect('id');

            // construct the XML
            $xml = $this->_buildProductXML($collection, $useAttribCode);
            $this->_prepareResponse($xml);
        } else {
            $this->norouteAction();
        }
    }

    /**
     * Constructs an XML object for the given product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param bool $useAttribCode
     * @return SimpleXMLElement
     */
    protected function _buildProductXML($collection, $useAttribCode = false)
    {
        $xml = new SimpleXMLElement('<products/>');

        // iterate over the collection
        foreach ($collection as $product)
        {
            // Add a product node
            $element = $xml->addChild('product');

            // wrap the product
            $_product = new Copernica_Integration_Model_Abstraction_Product($product);

            // Collection of relevant fields
            $fields = array(
                'id',
                'sku',
                'name',
                'description',
                'price',
                'specialPrice',
                'modified',
                'created',
                'productUrl',
                'imageUrl',
                'weight',
                'isNew',
                'categories',
                'attributes'
            );

            // Add the internal product fields to the database
            foreach ($fields as $name)
            {
                // Get the value
                $value = $_product->$name();

                // Get the attributes of the attributes
                if ($name == 'attributes') $value = $value->attributes($useAttribCode);

                if (is_bool($value))
                {
                    $element->addChild($name, htmlspecialchars(html_entity_decode($value ? 'yes' : 'no')));
                    continue;
                }
                elseif (!is_array($value))
                {
                    if ($name == 'price' || $name == 'specialPrice') {
                        $value = Mage::helper('core')->currency($value, true, false);
                    }

                    $element->addChild($name, htmlspecialchars(html_entity_decode((string)$value)));
                    continue;
                }

                // We have an array here

                // Add an element, to bundle all the elements of the array
                $node = $element->addChild($name);

                // we have an array here
                foreach ($value as $key => $attribute)
                {
                    // prepare the key
                    if (is_numeric($key)) $key = 'items';
                    else $key = str_replace(' ', '_', $key);

                    // special treatment for categories and empty values
                    if ($name == 'categories') $attribute = implode(' > ', $attribute);
                    elseif (trim($attribute) === '') continue;

                    // Add the child
                    $node->addChild($key, htmlspecialchars(html_entity_decode((string)$attribute)));
                }
            }

            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());

            if (isset($parentIds[0])) {
                $_product = new Copernica_Integration_Model_Abstraction_Product(Mage::getModel('catalog/product')->load($parentIds[0]));

                // Add a product node
                $element = $xml->addChild('configurable_product');

                // Add the internal product fields to the database
                foreach ($fields as $name)
                {
                    // Get the value
                    $value = $_product->$name();

                    // Get the attributes of the attributes
                    if ($name == 'attributes') $value = $value->attributes($useAttribCode);

                    if (is_bool($value))
                    {
                        $element->addChild($name, htmlspecialchars(html_entity_decode($value ? 'yes' : 'no')));
                        continue;
                    }
                    elseif (!is_array($value))
                    {
                        if ($name == 'price' || $name == 'specialPrice') {
                            $value = Mage::helper('core')->currency($value, true, false);
                        }

                        $element->addChild($name, htmlspecialchars(html_entity_decode((string)$value)));
                        continue;
                    }

                    // We have an array here

                    // Add an element, to bundle all the elements of the array
                    $node = $element->addChild($name);

                    // we have an array here
                    foreach ($value as $key => $attribute)
                    {
                        // prepare the key
                        if (is_numeric($key)) $key = 'items';
                        else $key = str_replace(' ', '_', $key);

                        // special treatment for categories and empty values
                        if ($name == 'categories') $attribute = implode(' > ', $attribute);
                        elseif (trim($attribute) === '') continue;

                        // Add the child
                        $node->addChild($key, htmlspecialchars(html_entity_decode((string)$attribute)));
                    }
                }
            }
        }

        return $xml;
    }

    /**
     * Prepare response based on the given XML object
     *
     * @param SimpleXMLElement $xml
     */
    protected function _prepareResponse(SimpleXMLElement $xml)
    {
        $response = $this->getResponse();

        //set correct header
        $response->setHeader('Content-Type', 'text/xml', true);

        //clear anything another controller may have set
        $response->clearBody();

        //send headers
        $response->sendHeaders();

        //set xml content
        $response->setBody($xml->asXML());
    }

    /**
     * Retrieves a product by ID
     *
     * @param int $productId
     * @return Mage_Catalog_Model_Product
     */
    protected function _getProduct($productId)
    {
        $product = Mage::getModel('catalog/product')->load($productId);

        // only a product with an id exists
        return $product->getId() ? $product : null;
    }

    /**
    * Retrieves a product by SKU
    *
    * @param String $productSku
    * @return Mage_Catalog_Model_Product
    */
    protected function _getProductBySku($productSku)
    {
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku',$productSku);

        // only a product with an id exists
        return $product->getId() ? $product : null;
    }
}
