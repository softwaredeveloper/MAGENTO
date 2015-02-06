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
 *  The base helper for the Copernica integration plug-in
 */
class Copernica_Integration_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Get the version of this extension.
     *
     * @return string version number
     */
    public function getExtensionVersion()
    {
        // Get the config and return the version from the config
        $config = Mage::getConfig()->getModuleConfig('Copernica_Integration')->asArray();
        return $config['version'];
    }

    /**
     *  Does the queue contain the magic token, which indicates that the synchronisation
     *  should be started?
     *  @return boolean
     */
    public function isSynchronisationStartScheduled()
    {
        // Construct a new resource for this because caching fucks it all up
        $count = Mage::getResourceModel('integration/queue_collection')
            ->addFieldToFilter('action', 'start_sync')
            ->getSize();

        // Reset the count
        return ($count > 0);
    }

    /**
     *  Get last run report.
     *  
     *  @return Copernica_Integration_Model_QueueReport
     */
    public function getLastReport()
    {
        // fetch report file
        $reportFile = Mage::getBaseDir().DIRECTORY_SEPARATOR.Mage::getStoreConfig('copernica_options/apistorage/reportfile');

        // check if report files exists.
        if (!is_file($reportFile)) return null;

        // get json data
        $data = json_decode(file_get_contents(($reportFile)));  

        // check if we have last run or not
        if (count($data) == 0) return null;

        // create a QueueReport instance with last run data
        return Mage::getModel('integration/QueueReport', end($data));
    }

    /**
     *  Is the Copernica module enabled?
     *  @return boolean
     */
    public function enabled()
    {
        // Get the setting from 'advanced/modules_disable_output/Copernica_Integration'
        return (Mage::getConfig()->getNode('advanced/modules_disable_output/Copernica_Integration', 'default', 0) == 0);
    }
}
