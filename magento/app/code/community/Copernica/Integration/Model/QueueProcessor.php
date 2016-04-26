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
 *  This class will process task queue.
 */
class Copernica_Integration_Model_QueueProcessor
{
    /**
     *  Number of processed tasks by this processor
     *  @var int
     */
    private $processedTasks = 0;

    /**
     *  Lights-out time. After this moment the script
     *  should stop synchronizing and wait for the
     *  next cronjob to be started.
     */
    private $stopTime;

    /**
     *  How many items we want to process in one run?
     *  @var int
     */
    private $itemsLimit = 10000;

    /**
     *  For what is our timelimit for queue processing? in seconds.
     *  @var int
     */
    private $timeLimit = 275;

    /**
     *  The API connection to synchronize items
     *  @var Copernica_Integration_Helper_Api
     */
    private $api = null;

    /**
     *  Reporter for generating processor reports. By default in
     *  {{base_dir}}/var/copernica/report.json will be stored results of each
     *  run.
     *
     *  @var Copernica_Integration_Model_QueueReporter
     */
    private $reporter;

    /**
     *  Constructor
     */
    public function __construct()
    {
        // connect to the API
        $this->api = Mage::helper('integration/api');

        // create new reporter instance
        $this->reporter = Mage::getModel('integration/QueueReporter');
    }

    /**
     *  Process queue
     */
    public function processQueue()
    {
        // what is the setting for max execution time?
        $maxExecutionTime = ini_get('max_execution_time');

        /**
         *  Set unlimited time for script execution. It does not matter that much
         *  cause most time will be spent on database/curl calls and they do not
         *  extend script execution time. We are setting this just to be sure that
         *  script will not terminate in the middle of processing.
         *  This is true for Linux machines. On windows machines it is super
         *  important to set it to large value. Cause windows machines do use
         *  real time to measure script execution time. When time limit is reached
         *  it will terminate connection and will not gracefully come back to
         *  script execution. Such situation will just leave mess in database.
         */
        set_time_limit(0);

        // get queue items collection
        $queue = Mage::getResourceModel('integration/queue_collection')->addDefaultOrder()->setPageSize(max($this->itemsLimit, 150));

        // make some preparations before we start processing queue
        $this->prepareProcessor();

        // iterate over queue
        foreach ($queue as $item)
        {
            // check if we did reach limit
            if ($this->isLimitsReached()) break;

            // check if did manage to process item
            $this->processItem($item);
        }

        /**
         *  Now, some explanation why we are doing such thing.
         *  When we are processing tasks/events we are doing hell lot of
         *  database/curl calls. They do not count into execution time cause cpu
         *  is not spending time on script (it's halted). This is why we can not
         *  rely on php time counter and that is why we are making our own check.
         *  After we are done with processing, we will just reset time counter
         *  for whole magento.
         *  Note that this is true for Linux systems. On windows based machines
         *  real time is used.
         */
        set_time_limit($maxExecutionTime);
    }

    /**
     *  Make some preparations before we start processing queue
     */
    private function prepareProcessor()
    {
        // store time when we start
        $this->stopTime = microtime(true) + $this->timeLimit;
    }

    /**
     *  Check if we reached limits
     *  @return bool
     */
    private function isLimitsReached()
    {
        // check if either we did reach maximum amount of items or we we processing too long
        return $this->processedTasks > $this->itemsLimit || microtime(true) > $this->stopTime;
    }

    /**
     *  There is number of entities that we can store. This method will detect
     *  which entity we want to store inside copernica.
     *
     *  @param  Mage_Core_Model_Abstract
     *  @throws Exception   If something really bad happens an exception will be
     *                      rised as indication of error. Such exception should
     *                      be handled by caller.
     */
    private function handleStore($model)
    {
        switch ($model->getResourceName())
        {
            // creations or updates
            case 'catalog/product':         $this->api->storeProduct($model);       break;
            case 'sales/quote':             $this->api->storeQuote($model);         break;
            case 'sales/quote_item':        $this->api->storeQuoteItem($model);     break;
            case 'sales/order':             $this->api->storeOrder($model);         break;
            case 'sales/order_item':        $this->api->storeOrderItem($model);     break;
            case 'newsletter/subscriber':   $this->api->storeSubscriber($model);    break;
            case 'customer/customer':       $this->api->storeCustomer($model);      break;
            case 'customer/address':        $this->api->storeAddress($model);       break;
            case 'sales/quote_address':     $this->api->storeAddress($model);       break;
            case 'sales/order_address':     $this->api->storeAddress($model);       break;
            case 'core/store':              $this->api->storeStore($model);         break;
            case 'catalog/category':        $this->api->storeCategory($model);      break;
            case 'customer/group':          $this->api->storeGroup($model);         break;
            case 'wishlist/wishlist':       $this->api->storeWishlist($model);      break;
            case 'wishlist/item':           $this->api->storeWishlistItem($model);  break;
            case 'integration/productView': $this->api->storeProductView($model);   break;
        }
    }

    /**
     *  There is number of entities that we can remove from Copernica. This method
     *  will call proper api call to execute removal.
     *
     *  @param  string      Entity model identifier.
     *  @param  int         Entity ID
     *  @throws Exception   If something really bad happens an exception will be
     *                      rised as indication of error. Such exception should
     *                      be handled by caller.
     */
    private function handleRemoval($entityResource, $entityId)
    {
        switch($entityResource)
        {
            case 'newsletter/subscriber':   $this->api->removeSubscriber($entityId);            break;
            case 'sales/quote_item':        $this->api->removeQuoteItem($entityId);             break;
            case 'catalog/category':        $this->api->removeCategory($entityId);              break;
            case 'customer/customer':       $this->api->removeCustomer($entityId);              break;
            case 'customer/address':        $this->api->removeAddress($entityId, 'customer');   break;
            case 'sales/order_address':     $this->api->removeAddress($entityId, 'order');      break;
            case 'sales/quote_address':     $this->api->removeAddress($entityId, 'quote');      break;
            case 'wishlist/wishlist':       $this->api->removeWishlist($entityId);              break;
            case 'wishlist/item':           $this->api->removeWishlistItem($entityId);          break;
        }
    }

    /**
     *  Full sync action is a special action. This method will initiate sync or
     *  pick up from previous state (if there is one).
     */
    private function handleSync()
    {
        // create sync processor that will process more items at once
        Mage::getModel('integration/SyncProcessor')->process();

        /**
         *  When we are done with processing this particular event we should
         *  go and update the api about the status of the process.
         */
        $this->api->updateSyncStatus();
    }

    /**
     *  Process a queue item
     *
     *  @param  Copernica_Integration_Model_Queue item to synchronize
     */
    private function processItem(Copernica_Integration_Model_Queue $item)
    {
        // increment processed tasks counter
        $this->processedTasks++;

        /**
         *  Since we are processing item right now (well about to make the
         *  processing happen), we want to remove the item from the queue. We
         *  know all the data that we need so there is no point of keeping that
         *  item around. Also, queue items check if they are duplicates when they
         *  are saved, so we want to remove queue item as soon as possible.
         */
        $item->delete();

        try
        {
            /**
             *  Every action is little bit different from other ones. We have
             *  specialized methods that will take care each of them.
             */
            switch($item->getAction())
            {
                case 'start_sync':  $this->handleSync(); break;
                case 'store':       $this->handleStore($item->getObject()); break;
                case 'remove':      $this->handleRemoval($item->getObjectResourceName(), $item->getObjectId()); break;
            }

            // store success
            $this->reporter->storeSuccess();
        }

        // catch all exceptions
        catch (Exception $exception)
        {
            // tell magento to log exception
            Mage::logException($exception);

            // store error
            $this->reporter->storeFailure($exception->getMessage(), array( 'resource' => $item->getObjectResourceName(), 'action' => $item->getAction() ));
        }
    }

    /**
     *  Get number of processed tasks so far.
     *
     *  @return int
     */
    public function getProcessedTasks()
    {
        return $this->processedTasks;
    }
}
