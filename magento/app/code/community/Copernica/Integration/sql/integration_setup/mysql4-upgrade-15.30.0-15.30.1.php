<?php
/**
 *  Copernica Marketing Software
 *
 *  NOTICE OF LICENSE
 *
 *  This source file is subject to the Open Software License (OSL 3.0)
 *  that is bundled with this package in the file LICENSE.txt.
 *  It is also available through the world-wide-web at this URL:
 *  http://opensource.org/licenses/osl-3.0.php
 *  If you did not receive a copy of the license and are unable to
 *  obtain it through the world-wide-web, please send an email
 *  to copernica@support.cream.nl so we can send you a copy immediately.
 *
 *  DISCLAIMER
 *
 *  Do not edit or add to this file if you wish to upgrade Copernica Marketing Software  to newer
 *  versions in the future. If you wish to customize this module for your
 *  needs please refer to http://www.copernica.com/ for more information.
 *
 *  @category        Copernica
 *  @package         Copernica_Integration
 *  @copyright       Copyright (c) 2011-2012 Copernica & Cream. (http://docs.cream.nl/)
 *  @license         http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  @documentation  public
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

try
{    
    // start setup state
    $installer->startSetup();

    // create viewed product table
    $installer->run("
    DROP TABLE IF EXISTS `{$installer->getTable('integration/productView')}`;
    CREATE TABLE `{$installer->getTable('integration/productView')}` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `customer_id` int(10) unsigned,
        `product_id` int(10) unsigned,
        `viewed_at` timestamp NOT NULL,
        `store_id` int(10) unsigned,
        PRIMARY KEY (`id`),
        INDEX `customer_product_viewed_index` (`customer_id`, `product_id`, `store_id`)
    ) ENGINE=InnoDB default CHARSET=utf8;
    ");

    // end setup state
    $installer->endSetup();
}

catch (Exception $e)
{
    Mage::logException($e);
}
