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
 *  In our config we have an option to input minutes. This is a backend model 
 *  that will validate users input.
 */
class Copernica_Integration_Model_MinutesConfigData extends Mage_Core_Model_Config_Data
{
    /**
     *  Overload save method so we can create our own validation.
     */
    public function save()
    {
        // get entered value
        $value = $this->getValue();
        
        // is entered value a int?
        if (!is_numeric($value) || (int)$value != $value || $value < 0) {
            
            // inform user that entered value is no good
            Mage::getSingleton('core/session')->addError('Amount of minutes should be a positive number without fractions.');
        
            // do not save
            return;
        } 
        
        // we can go and save the value
        parent::save();
    }
}
