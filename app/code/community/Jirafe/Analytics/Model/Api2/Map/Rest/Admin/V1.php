<?php

/**
 * Api2 Map Rest Admin V1 Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Api2_Map_Rest_Admin_V1 extends Jirafe_Analytics_Model_Api2_Map_Rest
{
    
    /**
     * Update field map
     *
     * @param array $data
     */
    protected function _update(array $data)
    {
        $map = $this->_getMap();
        $obj = Mage::getModel('jirafe_analytics/map');
        $element = $this->getRequest()->getParam('element');
        $magento = isset($data['magento']) ? trim($data['magento']) : '';
        $continue = true;
        
        if ( $magento && $element && !$obj->validateField( $element, $magento ) ) {
            $this->_critical( self::FIELD_MAPPING_ERROR_INVALID_FIELD );
        } else {
            $api = isset($data['api']) ? strtolower( trim($data['api']) ) : '';
            $type = isset($data['type']) ? strtolower( trim($data['type']) ) : '';
            $default = isset($data['default']) ? trim($data['default']) : '';
            
            
            if ( $type ) {
                if ( strrpos(Jirafe_Analytics_Model_Map::VALID_FIELD_TYPES, $type) ) {
                    $map->setType( $type );
                } else {
                    $this->_critical( self::FIELD_MAPPING_ERROR_INVALID_TYPE );
                    $continue = false;
                }
            }
            
            if ( $api) {
                $map->setApi( $api );
            }
            
            if ( $continue ) {
                
                $map->setDefault( $default );
                $map->setMagento( $magento );
                $map->setUpdatedDt( $obj->getCurrentDt() );
                $map->save();
                
                $this->_successMessage( self::FIELD_MAPPING_UPDATE_SUCCESSFUL, Mage_Api2_Model_Server::HTTP_OK );
                
                $cache = Mage::app()->getCache();
                
                /**
                 *  Remove root map from cache. It will be repopulated next time it's called.
                 */
                $cache->remove('jirafe_analytics_map');
                
                /**
                 *  Remove select fields from cache. It will be repopulated next time it's called.
                 */
                $types = Mage::getModel('jirafe_analytics/map')
                    ->getCollection()
                    ->addFieldToSelect('element')
                    ->distinct(true);
                
                foreach ( $types as $element ) {
                    $cache->remove("jirafe_analytics_fields_$element");
                }
                
                
            }
        }
    }
}
