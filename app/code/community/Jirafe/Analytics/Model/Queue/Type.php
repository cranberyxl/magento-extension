<?php

/**
 * Queue Type Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Queue_Type extends Mage_Core_Model_Abstract
{
    const CART = 1;
    
    const CATEGORY = 2;
    
    const CUSTOMER = 3;
    
    const ORDER = 4;
    
    const PRODUCT = 5;
    
    const USER = 6;
    
    protected function _construct()
    {
        $this->_init('jirafe_analytics/queue_type');
    }

}