<?php

/**
 * Api2 Log Rest Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Api2_Log_Rest extends Jirafe_Analytics_Model_Api2_Log
{
    
    /**
     * Get all data
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $data = array();
        
        $collection = Mage::getModel('jirafe_analytics/log')->getCollection();
        
        foreach ($collection->getData() as $item) {
            $data[] = $item;
        }
        return $data;
    }
    
    /**
     * Retrieve entity data not available
     *
     * @throws Mage_Api2_Exception
     * @return array
     */
    protected function _retrieve()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }
    
    /**
     * Data create not available
     *
     * @param array $data
     */
    protected function _create(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }
    
    /**
     * Update entity not available
     *
     * @return array
     */
    protected function _update()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }
    
    /**
     * Data delete not available
     */
    protected function _delete()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }
    
}
