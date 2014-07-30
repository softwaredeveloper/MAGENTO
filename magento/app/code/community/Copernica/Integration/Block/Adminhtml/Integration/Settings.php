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
 *  Settings Block
 *
 */
class Copernica_Integration_Block_Adminhtml_integration_Settings extends Mage_Core_Block_Template
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('integration/settings.phtml');
    }

    /**
     *  Get a random state value.
     *
     *  This value will stay the same for the same session in
     *  the same day. It is used to verify responses to our
     *  API authorization request.
     *
     *  @return string
     */
    protected function getState()
    {
        // get the encrypted session id, add the current date to it and hash it with md5
        return md5(Mage::getSingleton('adminhtml/session')->getEncryptedSessionId().date('dmY'));
    }

    /**
     *  Get the URI to authorize with the Copernica REST API
     *
     *  @return string
     */
    public function getAuthorizeURI()
    {
        // components of the URI
        $base  = 'https://www.copernica.com/en/authorize';
        $key   = 'fccd12ee5499739753fd12a170998549';

        // build and return the URI
        return "{$base}?client_id={$key}&amp;state={$this->getState()}&amp;redirect_uri={$this->getStateUrl()}&amp;scope=all&amp;response_type=code";
    }

    /**
     * Returns the post URL.
     *
     * @return string
     */
    public function getPostUrl()
    {
        return $this->getUrl('*/*/send', array('_secure' => true));
    }

    /**
     *  Returns the state URL
     *  @return string
     */
    public function getStateUrl()
    {
        return Mage::helper('adminhtml')->getUrl('*/*/state');
    }

    /**
     *  Returns the queue URL
     *  @return string
     */
    public function getQueuePostUrl()
    {
        return Mage::helper('adminhtml')->getUrl('*/*/queue');
    }
}
