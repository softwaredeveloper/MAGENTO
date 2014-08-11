<?php
/**
 *  Copernica Marketing Software
 *
 *  @category       Copernica
 *  @package        Copernica_Integration
 *  @documentation  public
 */

/**
 *  Copernica REST request helper. This helper is a reusable API request. To
 *  utilize REST API use 4 basic methods: self::get, self::post(), self::put(),
 *  self::delete().
 */
class Copernica_Integration_Helper_RESTRequest extends Mage_Core_Helper_Abstract
{
    /**
     *  Access token that we want to use with our request.
     *  @var    string
     */
    protected $accessToken = '';

    /**
     *  The curl objects
     *  @var    array
     */
    protected $children = array();

    /**
     *  Multi curl interface
     *  @var    resource
     */
    protected $multi = null;

    /**
     *  Constructor
     *
     *  We use normal PHP constructor cause Helpers are not childs of
     *  Varien_Object class, so no _construct is called.
     */
    public function __construct()
    {
        // store the access token for quick access
        $this->accessToken = Mage::helper('integration/config')->getAccessToken();
    }

    /**
     *  Destructor for this object.
     */
    public function __destruct()
    {
        // close any multi-request curl instance we may have
        if (!is_null($this->multi)) curl_multi_close($this->multi);
    }

    /**
     *  Update the access token when we receive a new one
     *
     *  @param  string  new access token
     */
    public function setAccessToken($accessToken)
    {
        file_put_contents('/tmp/data', "Setting access token to {$accessToken}\n", FILE_APPEND);

        // store the new access token
        $this->accessToken = $accessToken;
    }

    /**
     *  Check request instance. This method will check all essentials to make an
     *  API call.
     *  @return bool
     */
    public function check()
    {
        // we must have a valid access token
        return !empty($this->accessToken);
    }

    /**
     *  Helper method to build up a query string
     *
     *  This is an alternative to http_build_query, because the
     *  built-in function "forgets" to stringify objects and it
     *  doesn't look like this bug will be fixed any time soon,
     *  if at all.
     *
     *  @param  assoc
     *  @return string
     */
    protected function buildQueryString(array $data)
    {
        // start result parts
        $parts = array();

        // iterate over whole data
        foreach ($data as $key => $value)
        {
            // check if our parameter is an array
            if (is_array($value))
            {
                // iterate over all value items
                foreach ($value as $valueItem) {
                    $key.'[]='.urlencode(strval($valueItem));
                }
            }

            // if we don't have an array we can just use string value
            else $parts[] = $key.'='.urlencode(strval($value));
        }

        // return result
        return '?'.implode('&', $parts);
    }

    /**
     *  Get a new curl instance to perform a request
     *
     *  @param  string  request to perform
     *  @param  map     parameters for the action
     *  @return resource
     */
    private function curl($request, array $query = array())
    {
        // if we have an access token we should add it to the query
        if ($this->accessToken) $query['access_token'] = $this->accessToken;

        // initialize curl instance
        $curl = curl_init();

        // we want the result of the transfer to be returned
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // set HTTP headers
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'content-type: application/json',
            'accept: application/json',
        ));

        // set the URI we need to open
        curl_setopt($curl, CURLOPT_URL, "https://api.copernica.com/{$request}{$this->buildQueryString($query)}");

        // return the fresh curl instance
        return $curl;
    }

    /**
     *  Finish the request.
     *
     *  This function will either immediately execute the request,
     *  or, in the case of a multi-request being available, queue
     *  it to be executed at a later time
     *
     *  @param  resource    curl resource
     */
    private function finish($curl)
    {
        // is a multi-request available?
        if ($this->multi)
        {
            // store the request so it can be removed later
            $this->children[] = $curl;

            // attach to the multi-request
            curl_multi_add_handle($this->multi, $curl);
        }
        else
        {
            // execute the request
            curl_exec($curl);

            // close curl handle
            curl_close($curl);
        }
    }

    /**
     *  Perform a GET request
     *
     *  @param  string  Request string
     *  @param  map     (Optional) Data to be passed with request
     *  @return map     Decoded JSON from
     */
    public function get($request, array $data = array())
    {
        // get curl instance
        $curl = $this->curl($request, $data);

        // we need to do a GET request
        curl_setopt($curl, CURLOPT_HTTPGET, true);

        // decode output
        $output = json_decode(curl_exec($curl), true);

        // close curl
        curl_close($curl);

        // get the output
        return $output;
    }

    /**
     *  Make a POST request
     *  @param  string  Request string
     *  @param  assoc   (Optional) Data to be passed with request
     *  @param  assoc   (Optional) Extra query parameters to be passed
     */
    public function post($request, $data = null, array $query = array())
    {
        // get curl instance
        $curl = $this->curl($request, $query);

        // we want to make POST
        curl_setopt($curl, CURLOPT_POST, true);

        // set custom method
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");

        // set data
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        // finish the request
        $this->finish($curl);
    }

    /**
     *  Make a PUT request
     *  @param  string  Request string
     *  @param  map     (Optional) Data to be passed with request
     */
    public function put($request, $data = null, array $query = array())
    {
        // get curl instance
        $curl = $this->curl($request, $query);

        // make a PUT request
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");

        // set data
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        // finish the request
        $this->finish($curl);
    }

    /**
     *  Make a DELETE request
     *  @param  string  Request string
     */
    public function delete($request)
    {
        // get curl instance
        $curl = $this->curl($request);

        // we want to set custom request
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");

        // finish the request
        $this->finish($curl);
    }

    /**
     *  This method will start preparing calls to execute
     *  them later on with 'multi' interface.
     */
    public function prepare()
    {
        // create multi-request resource
        $this->multi = curl_multi_init();

        // allow chaining
        return $this;
    }

    /**
     *  Commit all prepared calls
     */
    public function commit()
    {
        // if there is no multi-request resource we cannot do anything
        if (!$this->multi) return;

        // track whether we ought to be running
        $running = true;

        // process all requests
        while ($running)
        {
            // wait until one of the registered handles is ready to send or receive
            curl_multi_select($mh);

            /**
             *  If curl_multi_exec returns CULM_CALL_MULTI_PERFORM
             *  it has more data available that can be processed
             *  without checking the sockets for data
             */
            while (curl_multi_exec($this->multi, $running) == CURLM_CALL_MULTI_PERFORM) continue;
        }

        // free all children
        foreach($this->children as $child) curl_multi_remove_handle($this->multi, $child);

        // clean up
        curl_multi_close($this->multi);

        // set multi interface to null
        $this->multi = null;

        // allow chaining
        return $this;
    }
}
