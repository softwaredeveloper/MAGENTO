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
 *  Magento has it's own abstract class for creating shell scripts. We don't
 *  want to go against magento standards so we will use it as well. 
 */
require_once 'abstract.php';

/**
 *  Class that will be used to process shell script.
 */
class Copernica_Integration_Shell_Copernica extends Mage_Shell_Abstract
{
    /**
     *  Main method that will decide what to do.
     */
    public function run()
    {
        // decide that to do
        switch($this->getArg('command'))
        {
            case 'status':      $output = $this->processStatus(); break;
            case 'sync-start':  $output = $this->processSync(); break;
            case 'process':     $output = $this->processProcess(); break;
            default: $output = 'invalid command'; break;
        }

        // get output format type
        $format = $this->getArg('format');

        // ensure that we have proper format type
        if (!in_array($format, array('pretty', 'json'))) $format = 'pretty';

        // print output data in desired format
        echo $this->format($output, $format).PHP_EOL;
    }

    /**
     *  Format output into desired format.
     *
     *  @param  mixed       The actual data
     *  @param  string      The output type. Allowed values are: 
     *                      'pretty' - will use print_r formatting
     *                      'json' - will convert output data into json string 
     *  @return string
     */
    protected function format($data, $type = 'pretty')
    {
        // no data? don't do anything
        if (!$data) return;

        // what kind of output we are expecting
        switch($type)
        {
            case 'pretty': return print_r($data, true); break;
            case 'json': return json_encode($data); break;
        }
    }

    /**
     *  Process status command. That means we have to show some detailed informations
     *  about current synchronization status.
     *
     *  @return stdClass
     */
    protected function processStatus()
    {
        // new data class
        $data = new stdClass;

        // check if synchronization is started
        if (Mage::helper('integration')->isSynchronisationStartScheduled())
        {
            // create new data object
            $data->synchronization = new stdClass;

            // aggregate data
            $data->synchronization->started = true;
            $data->synchronization->progress = Mage::helper('integration/config')->getSyncProgress();
            $data->synchronization->total = Mage::helper('integration/config')->getSyncTotal();
            $data->synchronization->complete = $data->synchronization->progress / $data->synchronization->total;
        }

        // synchronization not started
        else $data->synchronization = false;

        // get queue size
        $data->queue = Mage::getModel('integration/queue')->getCollection()->getSize();

        // output report file location
        $data->reportfile = Mage::getBaseDir().DIRECTORY_SEPARATOR.Mage::getStoreConfig('copernica_options/apistorage/reportfile');

        // return data object
        return $data;
    }

    /**
     *  Put new start_sync event on queue to process.
     */
    protected function processSync()
    {
        // create new start sync task
        Mage::getModel('integration/queue')->setAction('start_sync')->save();

        // create and return output data
        $data = new stdClass;
        $data->started = true;
        return $data;
    }

    /**
     *  Process `process` command.
     */
    protected function processProcess()
    {
        // get the processor
        $processor = Mage::getModel('integration/QueueProcessor');

        // process the queue
        $processor->processQueue();

        // prepare the output
        $data = new stdClass;
        $data->processed = $processor->getProcessedTasks();

        // return output
        return $data;
    }

    /**
     *  Text that will be displayed when user don't know what to do.
     */
    public function usageHelp()
    {
        return  <<<USAGE
    Usage: php copernica.php --command <command>

    Available commands:

    status      - show current synchronization status
    sync-start  - start full synchronization 

USAGE;
    }
}

/**
 *  There is no auto run mechanism. We have to create our shell script instance
 *  and explicitly call ::run() method on it.
 */
$instance = new Copernica_Integration_Shell_Copernica();
$instance->run();
