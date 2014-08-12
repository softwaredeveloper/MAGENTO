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
$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$installer->run("
DROP TABLE IF EXISTS `{$installer->getTable('integration/queue')}`;
CREATE TABLE `{$installer->getTable('integration/queue')}` (
    `id` int(10) unsigned NOT NULL auto_increment,
    `entity_model` varchar(128) NOT NULL,
    `entity_id` int unsigned NOT NULL,
    `action` enum('store', 'remove', 'start_sync') NOT NULL DEFAULT 'store',
    `queue_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `result` text NULL,
    `result_time` timestamp NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB default CHARSET=utf8;
");

$installer->endSetup();
