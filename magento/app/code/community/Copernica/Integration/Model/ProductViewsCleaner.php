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
 *  This class will take care of old product views entries.
 */
class Copernica_Integration_Model_ProductViewsCleaner
{
    /**
     *  Clean product views
     */
    public function cleanProductViews()
    {
        // get the resource model
        $resource = Mage::getSingleton('core/resource');
        
        // get write connection
        $writeConnection = $resource->getConnection('core_write');
        
        // get oproduct view model table name
        $tableName = $resource->getTableName('integration/productView');
        
        // construct a query that will remove all viewed products that are older than 30 days
        $query = "DELETE FROM {$tableName} WHERE `viewed_at` < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))";
        
        // execute the query
        $writeConnection->query($query);
    }
}
