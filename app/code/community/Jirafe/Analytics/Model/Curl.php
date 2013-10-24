<?php

/**
 * Curl Model
 *
 * Magneto to Jirafe connectivity via REST API
 * 
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 * 
 *  
 *  @property string $eventApiUrl     Jirafe URL for event API
 *  
 *  @property string $accessToken     Jirafe oauth1 access token
 *  
 *  @property boolean $logging        logging toggle
 *  
 *  @property int $batchSize          number of threads for multithreaded cURL
 *  
 *  @property int $maxExecutionTime   php.ini max_execution_time override
 *  
 *  @property int $memoryLimit        php.ini memory_limit override
 *  
 *  @property int $procNice           php.ini proc_nice override
 *  
 *  @property string $threading       single or multi curl
 *  
 *  @property int $maxAttempts        maximum number of records to process
 *  
 *  @property int $pos                array iterator
 */

class Jirafe_Analytics_Model_Curl extends Jirafe_Analytics_Model_Abstract
{
    
    protected $_isEnabled = false;
    
    public $eventApiUrl = null;
    
    public $accessToken = null;
    
    public $logging = false;
    
    public $batchSize = null;
    
    public $maxExecutionTime = null;
    
    public $memoryLimit = null;
    
    public $procNice = null;
    
    public $threading = null;
    
    public $maxAttempts = null;
    
    public $pos = 1;
    
    /**
     * Object constructor
     * 
     * Load user configurable variables from Mage::getStoreConfig() into object property scope
     */
    
    public function _construct() 
    {
        
        if ( $this->_isEnabled = Mage::getStoreConfig('jirafe_analytics/general/enabled') ) {
            
            /**
             * Set account properties to Mage::getStoreConfig() values
             */
            
            $this->orgId = Mage::getStoreConfig('jirafe_analytics/general/org_id');
            $this->siteId = Mage::getStoreConfig('jirafe_analytics/general/site_id');
            
            /**
             * Set debug properties to Mage::getStoreConfig() values
             */
            
            $this->logging = Mage::getStoreConfig('jirafe_analytics/debug/logging');
            
            /**
             * Set api URL property to Mage::getStoreConfig() values
             */
            
            $this->eventApiUrl = 'https://' . Mage::getStoreConfig('jirafe_analytics/general/event_api_url');
            
            
            /**
             * Set PHP override properties to Mage::getStoreConfig() values
             */
            
            $this->maxExecutionTime = Mage::getStoreConfig('jirafe_analytics/php/max_execution_time');
            $this->memoryLimit = Mage::getStoreConfig('jirafe_analytics/php/memory_limit');
            $this->procNice = Mage::getStoreConfig('jirafe_analytics/php/proc_nice');
            
            /**
             * Set cURL properties to Mage::getStoreConfig() values
             */
            
            $this->threading  = Mage::getStoreConfig('jirafe_analytics/curl/threading');
            $this->batchSize = Mage::getStoreConfig('jirafe_analytics/curl/batch_size');
            $this->maxAttempts = Mage::getStoreConfig('jirafe_analytics/curl/max_attempts');
            
            /**
             * If batchSize not supplied by user, set to default
             */
            
            if (!is_numeric($this->batchSize)) {
                $this->batchSize = 5;
            }
        }
    }
    
    /**
     * Prepare data to pass to either single of mutli-threaded cURL
     *
     * @param array $data    data from jirafe_analytics_queue that is ready to be sent to Jirafe
     * @return array
     * @throws Exception if logging or calling of single or multi-threaded cURL fails
     */
    
    public function sendJson( $data = null ) 
    {
        /**
         * @var array $resource   resource info after cURL completion for logging 
         * @var int $count        array iterator
         * @var array $batch      json data from param segmented into batches
         * @var boolean $stop     loop interupt
         * @var array $row        single record from param data set
         * @var array $item       single record array used for single-threaded cURL
         */
        
        if ( $this->_isEnabled ) {
            try {
                
                /**
                 * Store curl resource information for queue, queue_attempt and queue_error
                 */
                
                $resource = array();
                
                if (count( $data )) {
                    if ( $this->logging ) {
                        $startTime = time();
                        $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'BEGIN');
                        $this->_logServerLoad( 'Jirafe_Analytics_Model_Curl::sendJson');
                        $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'START TIME = ' . date("H:i:s", $startTime) . ' UTC');
                        $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'EVENT API URL = ' . $this->eventApiUrl);
                        $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'BATCH SIZE = ' . $this->batchSize);
                    }
                    
                    $this->_overridePhpSettings();
                    
                    /**
                     * Determine CURL method
                     */
                    
                    if ( $this->threading === 'multi') {
                        
                        /**
                         * Process using multithreaded cURL
                         */
                        
                        if ( $this->logging ) {
                            $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'CURL: MULTITHREADED' );
                        }
                        
                        $count = 1;
                        $batch = array();
                        $stop = false;
                        
                        /**
                         * Create batches of URLs and JSON in arrays with $this->batchSize elements
                         */
                        
                        foreach($data as $row) {
                        
                           if ($count > $this->batchSize) {
                                $resource[] = $this->_processMulti($batch);
                                $batch = array();
                                $count = 1;
                            }
                            
                           $item = array(
                                'queue_id' => $row['id'],
                                'url' => $this->eventApiUrl . $this->_getSiteId( $row['store_id'] ) . '/' . $row['type'],
                                'token' => $this->_getAccessToken( $row['store_id'] ),
                                'json' =>  $row['content'] );
                           
                           if ( $this->logging ) {
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'QUEUE ID = ' . $item['queue_id'] );
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'ACCESS TOKEN = ' . $item['token'] );
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'EVENT API URL = ' . $item['url'] );
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'JSON = ' . $item['json'] );
                            }
                            
                            $batch[] = $item;
                            $count++;
                        }
                        
                        /**
                         * Final batch may be less than $this->batchSize. 
                         * Process batch separately.
                         */
                        
                        if (count($batch) > 0 && !$stop) {
                            $resource[] = $this->_processMulti($batch);
                        }
                    } else {
                        
                        if ( $this->logging ) {
                            $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'CURL: SINGLETHREADED' );
                        }
                        
                        /**
                         * Process using standard single threaded cURL
                         */
                        
                        foreach($data as $row) {
                            
                            $item = array(
                                'queue_id' => $row['id'],
                                'url' => $this->eventApiUrl . $this->_getSiteId($row['store_id']) . '/' . $row['type'],
                                'token' => $this->_getAccessToken( $row['store_id'] ),
                                'json' =>  $json );
                            
                            if ( $this->logging ) {
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'QUEUE ID = ' . $item['queue_id'] );
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'ACCESS TOKEN = ' . $item['token'] );
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'EVENT API URL = ' . $item['url'] );
                               $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'JSON = ' . $item['json'] );
                            }
                            
                            $resource[] = $this->_processSingle( $item );
                        }
                    }
                    
                    if ($this->logging) {
                        
                        /**
                         * Log the total execution time
                         */
                        
                        $endTime = time();
                        $totalTime = $endTime - $startTime;
                        
                        $this->_logServerLoad( 'Jirafe_Analytics_Model_Curl::send');
                        $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', "TOTAL PROCESSING TIME = $totalTime seconds");
                        $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::sendJson()', 'END TIME = ' . date("H:i:s", $endTime) . ' UTC');
                       
                    }
                }
                
                return $resource;
            } catch (Exception $e) {
                Mage::throwException('CURL ERROR: Jirafe_Analytics_ModelJirafe_Analytics_Model_Curl::sendJson(): ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Send heartbeat to Jirafe via REST. Trigger by cron
     *
     * @return array
     * @throws Exception if unable to send heartbeat
     */
    public function heartbeat()
    {
        try {
            $storeId = Mage::app()->getStore('default')->getId();
            $json = json_encode( array(
                'instance_id' => (string) Mage::getStoreConfig('jirafe_analytics/general/heartbeat_id'),
                'version' => (string) Mage::getConfig()->getNode()->modules->Jirafe_Analytics->version,
                'is_enabled' => (boolean) Mage::getStoreConfig('jirafe_analytics/general/enabled')
            ) );
            $params = array(
                'url' => $this->eventApiUrl . $this->_getSiteId( $storeId ) . '/heartbeat',
                'token' => $this->_getAccessToken( $storeId ),
                'json' => $json );
            
            $response = $this->_processSingle( $params );
            
            if ( @$response['http_code'] != '200' ) {
                $this->_log( 'ERROR', 'Jirafe_Analytics_Model_Curl::heartbeat()', json_encode( $response ) );
            }
            
            return $response;
        } catch (Exception $e) {
            Mage::throwException('HEARTBEAT ERROR: Jirafe_Analytics_Model_Curl::heartbeat(): ' . $e->getMessage());
        }
    }
    /**
     * Send batch data using standard single threaded cURL
     *
     * @param array $batch    segment of data from jirafe_analytics_queue
     * @return array
     * @throws Exception if curl_exec() fails
     */
    
    protected function _processSingle( $item = null )
    {
        /**
         * @var resource $thread  cURL resource thread for one item
         * @var int $resourceId   cURL resource id
         * @var string $response  URL response json
         * @var array $info       cURL data object
         * @var array $resource   resource info after cURL completion for logging
         */
        
        try {
            
            $this->_logServerLoad('Jirafe_Analytics_Model_Curl::_processSingle');
            
            $thread = curl_init();
            curl_setopt( $thread, CURLOPT_URL, $item['url'] );
            curl_setopt( $thread, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $item['token'],
                'Content-Type: application/json',
                'Content-Length: ' . strlen($item['json'])) );
            curl_setopt( $thread, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $thread, CURLOPT_FOLLOWLOCATION, true );
            curl_setopt( $thread, CURLINFO_HEADER_OUT, true );
            curl_setopt( $thread, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt( $thread, CURLOPT_POSTFIELDS, $item['json'] );
            
            if ($this->logging) {
                curl_setopt($thread, CURLOPT_VERBOSE, true);
            }
            
            $resourceId = intval($thread);
            $resource[ $resourceId ]['created_dt'] = $this->_getCreatedDt();
            
            if (isset($item['queue_id'])) {
                $resource[ $resourceId ]['queue_id'] = $item['queue_id'];
            }
            
            $response = curl_exec($thread);
            $resource[ $resourceId ]['response'] = $response;
            
            $info = curl_getinfo($thread) ;
            $resource[ $resourceId ]['http_code'] = $info['http_code'] ;
            $resource[ $resourceId ]['total_time'] = $info['total_time'];
            
            curl_close($thread);
             $this->_logServerLoad('Jirafe_Analytics_Model_Curl::_processSingle');
            return $resource;
        } catch (Exception $e) {
           Mage::throwException('CURL ERROR: Jirafe_Analytics_Model_Curl::_processSingle(): ' . $e->getMessage());
        }
    }
    
    /**
     * Send batch data using multi-threaded cURL
     * 
     * @param array $batch    json records from jirafe_analytics_queue
     * @return array
     * @throws Exception if curl_multi execution fails
     */
    
    protected function _processMulti( $batch )
    {
        /**
         * @var resource $mh      primary cURL multihandler
         * @var array $ch         cURL threads for closing
         * @var array $resource   resource info after cURL completion for logging
         * @var resource $thread  cURL resource thread for one item
         * @var int $i            array iterator
         * @var array $info       cURL data object
         */
        
        try {
            
            $this->_logServerLoad('Jirafe_Analytics_Model_Curl::_processMulti');
            
             /**
              * Initialize multithreaded cURL handle
              */
            
            $mh = curl_multi_init();
            
            /**
             * store cURL threads in separate array 
             */
            
            $ch = array();
             
            /**
             * store resource information for logging
             */
            
            $resource = array();
            
            /**
             * Add all urls and json from batch to multithread cURL handle
             */
            
            for ($i = 0; $i < $this->batchSize; $i++) {
                
                if (isset($batch[$i])) {
                    $thread = curl_init();
                    curl_setopt($thread, CURLOPT_URL, $batch[$i]['url']);
                    curl_setopt($thread, CURLOPT_HTTPHEADER, array(
                        'Authorization: Bearer ' . $batch[$i]['token'],
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($batch[$i]['json'])));
                    curl_setopt($thread, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($thread, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($thread, CURLINFO_HEADER_OUT, true);
                    curl_setopt($thread, CURLOPT_MAXREDIRS, $this->batchSize);
                    curl_setopt($thread, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($thread, CURLOPT_POSTFIELDS,$batch[$i]['json']);
                    
                    if ($this->logging) {
                       curl_setopt($thread, CURLOPT_VERBOSE, true);
                    }
                    
                    curl_multi_add_handle($mh, $thread);
                    $ch[] = $thread;
                    $resource[intval($thread)]['queue_id'] = $batch[$i]['queue_id'];
                }
            }
            
            $thread = null;
            $still_running = null;
            
            /**
             * Run each individual thread and wait for completion
             */
            
            $this->_curl_multi_exec($mh, $still_running);
            
            do { 
                curl_multi_select($mh); 
                $this->_curl_multi_exec($mh, $still_running);
                while ($info = curl_multi_info_read($mh)) {
                    $resource[intval($info['handle'])]['response'] = curl_multi_getcontent($info['handle']);
                }
            } while ($still_running);
           
            /**
             * close the individual threads
             */
            
            foreach($ch as $thread) {
                $info = curl_getinfo($thread);
                curl_multi_remove_handle($mh, $thread);
                $resource[intval($thread)]['http_code'] = $info['http_code'] ;
                $resource[intval($thread)]['total_time'] = $info['total_time'];
                $resource[intval($thread)]['created_dt'] = $this->_getCreatedDt();
            }
            
            /**
             * close primary multi-hander
             */
            
            curl_multi_close($mh);
            $this->_logServerLoad('Jirafe_Analytics_Model_Curl::_processMulti');
            return $resource;
        } catch (Exception $e) {
            Mage::throwException('CURL ERROR: Jirafe_Analytics_Model_Curl::_processMulti(): ' . $e->getMessage());
        }
    }
    
    /**
     * Wrapper for curl_multi_exec to handle curl_multi_select wait issues
     * 
     * @param  resource $mh             primary curl multihandler
     * @param  boolean $still_running   curl subthread has completed
     * @return int
     * @throws Exception if curl_multi_exec() fails
     */
    
    protected function _curl_multi_exec( $mh, &$still_running ) 
    {
        
        /**
         * @var int $rv    A cURL code defined in the cURL Predefined Constants.
         */
        
        try {
            do {
                $rv = curl_multi_exec( $mh, $still_running );
            } while ($rv == CURLM_CALL_MULTI_PERFORM);
            return $rv;
        } catch (Exception $e) {
            Mage::throwException('CURL ERROR: Jirafe_Analytics_Model_Curl::_curl_multi_exec(): ' . $e->getMessage());
        }
    }
    
    /**
     * Determine site Id by store Id
     * 
     * If 0 (admin store) or not number, set to the default value of 1
     * 
     * @param int $storeID    Magento store id from core_store
     * @return int
     * @throws Exception if unable to determine site id
     */
    protected function _getSiteId( $storeId = null ) 
    {
        /**
         * @var int $siteId    Jirafe SiteId
         */
        
        try {
            $siteId = Mage::getStoreConfig( 'jirafe_analytics/general/site_id', $storeId );
            if (!is_numeric($siteId)) {
                $siteId = 0;
            }
            return $siteId;
        } catch (Exception $e) {
            Mage::throwException('API ERROR: Jirafe_Analytics_Model_Curl::_getSiteId(): ' . $e->getMessage());
        }
    }
    
    /**
     * Determine access token by store Id
     *
     * @param int $storeID    Magento store id from core_store
     * @return string
     * @throws Exception if unable to return access token
     */
    protected function _getAccessToken( $storeId = null )
    {
        try {
            return Mage::getStoreConfig( 'jirafe_analytics/general/access_token', $storeId );
        } catch (Exception $e) {
            Mage::throwException('API ERROR: Jirafe_Analytics_Model_Curl::_getAccessToken(): ' . $e->getMessage());
        }
    }
    
    /**
     * Override PHP settings in php.ini with user configurable values set in the admin
     * 
     * @return boolean
     * @throws Exception if php overrides ini_set('max_execution_time') , ini_set('memory_limit'), proc_nice() fail
     */
    
    protected function _overridePhpSettings() 
    {
        try {
            
            /**
             * Set PHP max_execution_time in seconds
             * Excessively large numbers or 0 (infinite) will hurt server performance
             */
            
            if (is_numeric($this->maxExecutionTime)) {
                
                ini_set('max_execution_time', $this->maxExecutionTime);
                
                if ($this->logging) {
                    $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::_overridePhpSettings()', 'max_execution_time = ' . $this->maxExecutionTime);
                }
            }
            
            /**
             * Set PHP memory_limit: Number + M (megabyte) or G (gigabyte)
             * Excessively large numbers will hurt server performance
             * Format: 1024M or 1G
             */
            
            if (strlen($this->memoryLimit) > 1) {
                
                ini_set("memory_limit", $this->memoryLimit);
                
                if ($this->logging) {
                    $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::_overridePhpSettings()', 'memory_limit = ' . $this->memoryLimit);
                }
            }
            
            /**
             * Set PHP nice value.
             * Lower numbers = lower priority
             */
            
            if (is_numeric($this->procNice)) {
            
                proc_nice($this->procNice);
            
                if ($this->logging) {
                    $this->_log( 'DEBUG', 'Jirafe_Analytics_Model_Curl::_overridePhpSettings()', 'proc_nice = ' . $this->procNice);
                }
            }
            
            return true;
        } catch (Exception $e) {
            Mage::throwException('PHP CONFIGURATION ERROR: Jirafe_Analytics_Model_Curl::_overridePhpSettings(): ' . $e->getMessage());
        }
    }
}