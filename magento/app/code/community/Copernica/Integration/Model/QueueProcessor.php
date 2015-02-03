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
 *
 *  @todo refactor this class.
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
        // get config into local scope
        $config = Mage::helper('integration/config');

        // update the last start time
        $config->setLastStartTimeCronjob(date("Y-m-d H:i:s"));

        // connect to the API
        $this->api = Mage::helper('integration/api');

        // create new reporter instance
        $this->reporter = Mage::getModel('integration/QueueReporter');
    }

    /**
     *  We want to make some final actions when this processor is beeing destroyed.
     */
    public function __destruct()
    {
        // get config into local scope
        $config = Mage::helper('integration/config');

        // update the last start time
        $config->setLastEndTimeCronjob(date("Y-m-d H:i:s"));

        // set how many items we did process in last run
        $config->setLastCronjobProcessedTasks($this->processedTasks);
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
     *  Process a queue item
     *
     *  @param  Copernica_Integration_Model_Queue item to synchronize
     */
    private function processItem(Copernica_Integration_Model_Queue $item)
    {
        // retrieve the object to synchronize and the action to log
        $object = $item->getObject();
        $action = $item->getAction();
        $resourceName = !is_null($object) ? $object->getResourceName() : '';

        try
        {
            // what type of object are we synchronizing and what happened to it?
            switch ("{$resourceName}/{$action}")
            {
                case 'catalog/product/store':           $this->api->storeProduct($object);      break;
                case 'sales/quote/store':               $this->api->storeQuote($object);        break;
                case 'sales/quote_item/remove':         $this->api->removeQuoteItem($object);   break;
                case 'sales/quote_item/store':          $this->api->storeQuoteItem($object);    break;
                case 'sales/order/store':               $this->api->storeOrder($object);        break;
                case 'newsletter/subscriber/store':     $this->api->storeSubscriber($object);   break;
                case 'newsletter/subscriber/remove':    $this->api->removeSubscriber($object);  break;
                case 'customer/customer/remove':        $this->api->removeCustomer($object);    break;
                case 'customer/customer/store':         $this->api->storeCustomer($object);     break;
                case 'customer/address/store':          $this->api->storeAddress($object);      break;
                case 'sales/order_address/store':       $this->api->storeAddress($object);      break;
                case 'sales/quote_address/stored':      $this->api->storeAddress($object);      break;
                case 'core/store/store':                $this->api->storeStore($object);        break;
                case 'catalog/category/store':          $this->api->storeCategory($object);     break;
                case 'catalog/category/remove':         $this->api->removeCategory($object);    break;

                // Start sync is a more complicated task to process.
                case '/start_sync':
                    // create sync processor that will process more items at once
                    $syncProcessor = Mage::getModel('integration/SyncProcessor');
                    $syncProcessor->process();

                    // if sync processor was not complete we should respaws task on queue
                    if (!$syncProcessor->isComplete()) 
                        Mage::getModel('integration/queue')->setAction('start_sync')->save();

                    // we are done here
                    break;
            }

            // increment processed tasks counter
            $this->processedTasks++;

            // delete the item from the queue
            $item->delete();

            // store success
            $this->reporter->storeSuccess();
        }

        // catch all exceptions
        catch (Exception $exception)
        {
            // tell magento to log exception
            Mage::logException($exception);

            // set result message on item and set result time
            $item->setResult($exception->getMessage())->setResultTime(date('Y-m-d H:i:s'));

            // store error
            $this->reporter->storeFailure($exception->getMessage(), array( 'resource' => $resourceName, 'action' => $action ));
        }
    }

    /**
     *  Transfer queue item to error queue.
     *  @param  Copernica_Integration_Queue
     *  @todo   do we really need this?
     */
    private function transferItemToErrorQueue($item)
    {
        // create error queue item
        $errorItem = Copernica_Integration_ErrorQueue::createFromQueueItem($item);

        // save error item
        $errorItem->save();

        // remove item
        $item->delete();
    }
}
