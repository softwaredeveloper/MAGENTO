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
 * Settings Controller, which takes care of the settings menu.
 */
class Copernica_Integration_Adminhtml_integration_SettingsController extends Copernica_Integration_Controller_Base
{
    /**
     *  indexAction() takes care of displaying the form which
     *  contains the details used for the SOAP connection
     */
    public function indexAction()
    {
        // Load the layout
        $this->loadLayout();

        // set menu
        $this->_setActiveMenu('copernica');

        // get layout
        $layout = $this->getLayout();

        // get content block
        $contentBlock = $layout->getBlock('content');

        // create settings block
        $settingsBlock = $layout->createBlock('integration/adminhtml_integration_settings');

        // append settings block to content block
        $contentBlock->append($settingsBlock);

        // set title
        $layout->getBlock('head')->setTitle($this->__('Settings / Copernica Marketing Software / Magento Admin'));

        // Render the layout
        $this->renderLayout();
    }

    /**
     *  Handle urls that contain state variable.
     *  @return Copernica_Integration_Adminhtml_integration_SettingsController
     */
    public function stateAction()
    {
        // check if we have a correct state token
        // if ($this->getRequest()->getParam('state') != $this->generateState()) return $this->_redirect('*/*', array('response' => 'invalid-state'));

        // get code parameter
        $code = $this->getRequest()->getParam('code');

        // upgrade out request code into access token
        $accessToken = Mage::helper('integration/api')->upgradeRequest($code, Mage::helper('adminhtml')->getUrl('*/*/state'));

        // if we have an error here we will just redirect to same view
        if ($accessToken === false)
        {
            // well, we have an error and we have to tell the user that we have an error
            return $this->_redirect('*/*', array('response' => 'authorize-error'));
        }

        // store access token inside magento config system
        Mage::getConfig()->saveConfig('copernica_options/apiconnection/apiaccesstoken', $accessToken);

        // retrieve account info and store account name inside hidden config
        $info = Mage::helper('integration/api')->account();
        Mage::getConfig()->saveConfig('copernica_options/apiconnection/apiaccount', $info['name']);
        
        // return this
        return $this->_redirect('*/*', array('response' => 'new-access-token'));
    }
}
