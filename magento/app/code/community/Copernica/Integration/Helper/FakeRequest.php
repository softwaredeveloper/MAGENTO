<?php
/**
 *  Copernica Marketing Software
 *
 *  @category       Copernica
 *  @package        Copernica_Integration
 *  @documentation  public
 */

/**
 *  Fake request handler. This class is useful for debug sessions with magento.
 *  It provide same interface as Copernice_Integration_Helper_RESTRequest
 *  would provide, but instead of pushing date to Copernica server it will format
 *  data and push it into logfile.
 */
class Copernica_Integration_Helper_FakeRequest extends Mage_Core_Helper_Abstract
{
    /**
     *  The name of the log file
     *
     *  @var    string
     */
    private $logFile = 'copernica-api.log';

    /**
     *  Object holding request data.
     *
     *  @var    stdClass
     */
    private $lastRequest;

    /**
     *  Helper function to write data to log file.
     *
     *  @param  string  Type of request (GET, POST, PUT, DELETE)
     *  @param  string  The APi url
     *  @param  mixed   The data that should be pushed to API
     *  @param  mixed   The query string
     */
    private function writeData($type, $url, $data = null, $qsa = null)
    {
        // create the request for log file
        $formattedRequest =  "$type $url".PHP_EOL;
        $formattedRequest .= json_encode($qsa).PHP_EOL;
        $formattedRequest .= json_encode($data).PHP_EOL;
        $formattedRequest .= PHP_EOL;

        // log the request
        Mage::log($formattedRequest, null, $this->logFile);

        // start creating request
        $request = new stdClass;
        $request->method = $type;
        $request->url = $url;
        $request->data = $data;
        $request->qsa = $qsa;

        // store request for further usage
        $this->lastReqeust = $request;
    }

    /**
     *  Function to get last request.
     *
     *  @return stdClass|null
     */
    public function getLastRequest()
    {
        // the request
        return $this->lastRequest;
    }

    /**
     *  The GET request.
     *
     *  @param  string  The request url
     *  @param  array   The query string
     */
    public function get($request, array $data = array())
    {
        // write data
        $this->writeData('GET', $request, null, $data);

        // return null
        return null;
    }

    /**
     *  The POST request
     *
     *  @param  string  The requets url
     *  @param  mixed   The data
     *  @param  array   The query string
     */
    public function post($request, $data = null, array $query = array())
    {
        // write data
        $this->writeData('POST', $request, $data, $query);

        // return nothing
        return null;
    }

    /**
     *  The PUT request
     *
     *  @param  string  The request url
     *  @param  mixed   The data
     *  @param  array   The query string
     */
    public function put($request, $data = null, array $query = array())
    {
        // write data
        $this->writeData('PUT', $request, $data, $query);

        // return nothing
        return null;
    }

    /**
     *  The DELETE request
     *
     *  @param  string  The request url
     *  @param  mixed   The data
     *  @param  array   The query string
     */
    public function delete($request, $data = null, array $query = array())
    {
        // write data
        $this->writeData('DELETE', $request, $data, $query);

        // return nothing
        return null;
    }
}
