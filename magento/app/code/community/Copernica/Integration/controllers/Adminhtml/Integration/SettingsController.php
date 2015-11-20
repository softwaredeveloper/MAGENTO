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
        
        // check extension state
        $this->checkExtensionState();
    }

    /**
     *  Put a full sync event on event queue. Such event will try to synchronize
     *  all needed data to Copernica.
     */
    private function publishFullSyncEvent()
    {
        Mage::getModel('integration/queue')
            ->setAction('start_sync')
            ->save();
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

        /** 
         *  Fetch current access token and account name. Depending on state of 
         *  such we may want to perform additional actions, like start 
         *  synchronization, so user will not have to bother with it.
         */
        $currentAccessToken = Mage::getStoreConfig('copernica_options/apiconnection/apiaccesstoken');
        $currentAccount = Mage::getStoreConfig('copernica_options/apiconnection/apiaccount_id');

        // store access token inside magento config system
        Mage::getConfig()->saveConfig('copernica_options/apiconnection/apiaccesstoken', $accessToken);

        // retrieve account info and store account name inside hidden config
        $info = Mage::helper('integration/api')->account();

        /**
         *  If we had an empty access token till now that means no data was synced,
         *  so it would be wise to start synchronization. 
         *  When current account does not match with one that we just fetched 
         *  we should start full sync as well, cause we assume that there is no 
         *  data in that account ot it's really outdated.
         */
        if (empty($currentAccessToken) || $currentAccount != $info['id']) $this->publishFullSyncEvent();

        // store new account related configs
        Mage::getConfig()->saveConfig('copernica_options/apiconnection/apiaccount', $info['name']);
        Mage::getConfig()->saveConfig('copernica_options/apiconnection/apiaccount_id', $info['id']);
        
        /**
         *  It would be really intuitive to have some kind of ::flush or ::commit
         *  or ::apply call (or have the actual config instance flush it's cache),
         *  but it seems that nothing like that is around there. Thus, every 
         *  Magento dev has to remember to clear config cache by himself, so 
         *  changes will be applied/included.
         */
        Mage::app()->getCacheInstance()->cleanType('config');
        
        // return this
        return $this->_redirect('*/*', array('response' => 'new-access-token'));
    }
}
