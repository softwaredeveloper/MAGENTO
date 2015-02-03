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
 *  QueueProcessor will actually process current queue, but will not gatner any
 *  information about whole process. This class will help in such task. Reporter
 *  will expose interface for aggregating processor data. 
 *  When creating new reporter instance it will mark the creation time as 
 *  start of processing time. Then when reporter is destroyed data gathered 
 *  during processing will be inserted into a file. Check module configuration
 *  for details about where is that file.
 */
class Copernica_Integration_Model_QueueReporter
{
    /**
     *  Current sync run log. This array will store all meaningful entries in 
     *  chronological order.
     * 
     *  @var    array
     */
    private $currentLog = array();

    /**
     *  Counter for number of errors.
     *
     *  @var    int
     */
    private $errors = 0;

    /**
     *  Counter for number of successes.
     *
     *  @var    int
     */
    private $successes = 0;

    /**
     *  The date when report will start. Basically from that point reporter will
     *  receive potential data.
     *
     *  @var    DateTime
     */
    private $startDate;

    /**
     *  Construct QueueReporter
     */
    public function __construct()
    {
        // create new date time as stat date
        $this->startDate = new DateTime();
    }

    /**
     *  When current QueueReporter is destructed we should store last run data. 
     */
    public function __destruct()
    {
        // construct report file
        $reportFile = $this->getReportFile();

        /** 
         *  Let us check if report file is poiting to a directory. This can be
         *  bad for couple of reasons. Main one is that user deleted entry or
         *  something really bad happend to config files and report path is 
         *  pointing to some sort of system directory (magento root dir, unix root
         *  dir or anything similar). In such we will not even attempt to make 
         *  sense and we will just stop destruction process at once.         
         */
        if(is_dir($reportFile)) return;

        /**
         *  If file does not exists 
         */
        if (!file_exists(dirname($reportFile))) 
        {
            // create directory
            mkdir(dirname($reportFile), 0777, true);

            // create file
            touch($reportFile);
        }

        // get data content
        $data = json_decode(file_get_contents($reportFile));

        // ensure proper data
        if (!$data) $data = array();

        // create current run data
        $currentRun = new stdClass;

        // store dates
        $currentRun->startDate = $this->startDate->format(DateTime::W3C);
        $nowDate = new DateTime();
        $currentRun->finishDate = $nowDate->format(DateTime::W3C);

        // store counters
        $currentRun->total = $this->errors + $this->successes;
        $currentRun->successes = $this->successes;
        $currentRun->errors = $this->errors;

        // store current log
        $currentRun->log = $this->currentLog;

        // append current run data
        $data[] = $currentRun;

        // trim data if needed
        if (count($data) > 100) $data = array_slice($data, count($data) - 100, 100);

        // save data
        file_put_contents($reportFile, json_encode($data));
    }

    /**
     *  Get report file pathname
     *
     *  @return string
     */
    private function getReportFile()
    {
        return Mage::getBaseDir().DIRECTORY_SEPARATOR.Mage::getStoreConfig('copernica_options/apistorage/reportfile');
    }

    /**
     *  Store generic object as entry.
     *
     *  @param  stdClass
     */
    private function storeEntry(stdClass $entry)
    {
        // append entry to current log
        $this->currentLog[] = $entry;
    }

    /**
     *  Store informations about success. It's possible to attach message to 
     *  entry indicating anything worth mentioning or additional data (must
     *  be JSON serializable), that will be appended to entry data.
     *  
     *  @param  string 
     *  @param  mixed
     *  @return Copernica_Integration_Model_QueueReporter
     */
    public function storeSuccess($message = '', $additionalData = null)
    {
        // prepare entry
        $entry = new stdClass;
        $entry->type = 'success';
        if ($message) $entry->message = $message;
        if (!is_null($additionalData)) $entry->extra = $additionalData;

        // increment success counter
        $this->successes++;

        // store entry
        $this->storeEntry($entry);

        // allow chaining
        return $this;
    }

    /**
     *  Store informations about success. It's possible to attach message to 
     *  entry indicating anything worth mentioning or additional data (must
     *  be JSON serializable), that will be appended to entry data.
     *  
     *  @param  string 
     *  @param  mixed
     *  @return Copernica_Integration_Model_QueueReporter
     */
    public function storeFailure($message = '', $additionalData = null)
    {
        // prepare entry
        $entry = new stdClass;
        $entry->type = 'success';
        if ($message) $entry->message = $message;
        if (!is_null($additionalData)) $entry->extra = $additionalData;

        // increment error counter
        $this->errors++;

        // store entry
        $this->storeEntry($entry);

        // allow chaining
        return $this;
    }
}