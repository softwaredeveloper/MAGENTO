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
 *  In our config we have an option to specify a file path that will be used to 
 *  store last queue processor runs. This is a backend model that will validate 
 *  users input.
 */
class Copernica_Integration_Model_ReportFileConfigData extends Mage_Core_Model_Config_Data
{
    /**
     *  Overload save method so we can create our own validation.
     */
    public function save()
    {
        // get entered value
        $value = $this->getValue();

        // construct file path
        $filePath = Mage::getBaseDir().DIRECTORY_SEPARATOR.$value;

        /**
         *  Ensure that file is there and if it's not there we can try to make
         *  it. If we can't touch a file that means current script can't make 
         *  the file so no report will be created. We should inform user about 
         *  that.
         */
        if (!is_file($filePath) && !touch($filePath))
        {
            // inform user that entered value is no good
            Mage::getSingleton('core/session')->addError('Report file can\'t be created.');

            // don't do any thing
            return;
        }

        // at this point there should be a file
        if (!is_file($filePath)) return;

        // file should be writable
        if (!is_writable($filePath)) 
        {
            // inform user that entered value is no good
            Mage::getSingleton('core/session')->addError('File is not writable.');

            // don't do any thing
            return;   
        }

        // call parent
        return parent::save();
    }
}