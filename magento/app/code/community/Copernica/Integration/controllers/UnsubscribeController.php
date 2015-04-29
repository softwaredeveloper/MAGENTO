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
 *  When someone unsubscribes from newsletter via Copernica state is changed 
 *  on Copernica side. Initially Magento doesn't know about that change. It's 
 *  dangerous cause with next sync of such entity subscription status stored 
 *  inside Magento will override one stored in Copernica. This controller 
 *  presents a way to inform Magento about subscription change.
 */
class Copernica_Integration_UnsubscribeController extends Mage_Core_Controller_Front_Action
{
    /**
     *  Process action.
     *
     *  Change action expects that 
     */
    public function changeAction()
    {
        // get data
        $data = json_decode(file_get_contents("php://input"));

        // if no real data we can just leapout
        if(is_null($data) || !property_exists($data, 'email')) return;

        // fetch subscriber that should be unsubsribed
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($data->email);

        // if we don't have proper subscriber Id we know that subscriber doesn't 
        // exists and we don't have to care about it's proper subscription status
        if (!$subscriber->getId()) return;

        // get object that will help us with API communication
        $request = Mage::helper('integration/RESTRequest');

        // ask API what is current subscriber state
        $data = $request->get('/magento/subscriber/'.$subscriber->getId());

        // we could received NULL data
        if (is_null($data)) return;

        // set subscriber status
        $subscriber->setStatus($data->status);
    }   
}