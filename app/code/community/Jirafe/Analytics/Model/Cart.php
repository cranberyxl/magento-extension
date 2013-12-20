<?php

/**
 * Cart Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Cart extends Jirafe_Analytics_Model_Abstract
{


    /**
     * Convert cart array into JSON object
     *
     * @param  array $quote
     * @param  boolean $isEvent
     * @return mixed
     */
    public function getJson( $quote = null, $isEvent = true   )
    {
        if ($quote) {
            return json_encode( $this->getArray( $quote, $isEvent ) );
        } else {
            return false;
        }
    }

    /**
     * Create cart array of data required by Jirafe API
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param  boolean $isEvent
     * @return mixed
     */

    public function getArray( $quote = null, $isEvent = true  )
    {
        try {
            if ($quote) {

                $items = Mage::getModel('jirafe_analytics/cart_item')->getItems( $quote['entity_id'], $quote['store_id'] );

                /**
                 * Get field map array
                 */
                $fieldMap = $this->_getFieldMap( 'cart', $quote );

                $previousItems = $this->_getPreviousItems( $quote['entity_id'] );

                $data = array(
                     $fieldMap['id']['api'] => $fieldMap['id']['magento'],
                     $fieldMap['create_date']['api'] => $fieldMap['create_date']['magento'],
                     $fieldMap['change_date']['api'] => $fieldMap['change_date']['magento'],
                     $fieldMap['subtotal']['api'] => $fieldMap['subtotal']['magento'],
                     $fieldMap['total']['api'] => $fieldMap['total']['magento'] ,
                     $fieldMap['total_tax']['api'] => $fieldMap['total_tax']['magento'],
                     $fieldMap['total_shipping']['api'] => $fieldMap['total_shipping']['magento'],
                     $fieldMap['total_payment_cost']['api'] => 0,
                     $fieldMap['total_discounts']['api'] => $fieldMap['total_discounts']['magento'],
                     $fieldMap['currency']['api'] => $fieldMap['currency']['magento'],
                    'cookies' => $isEvent ?  : (object) null,
                    'items' => $items,
                    'previous_items' => $isEvent && $previousItems ? $previousItems : array(),
                    'customer' => $this->_getCustomer( $quote, false ),
                    );



                if ( $isEvent && $cookies = $this->_getCookies() ) {
                    $data['cookies'] = $cookies;
                }

                if ( $isEvent && $visit = $this->_getVisit() ) {
                    $data['visit'] = $visit;
                }

                Mage::getSingleton('core/session')->setJirafePrevQuoteId( $quote['entity_id'] );
                Mage::getSingleton('core/session')->setJirafePrevQuoteItems( $items );

                return $data;
            } else {
                return false;
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log( 'ERROR', 'Jirafe_Analytics_Model_Cart::getArray()', $e);
            return false;
        }
    }

    /**
     * Get items from previous instance of cart from session
     *
     * @param string $quoteId
     * @return mixed
     */

    protected function _getPreviousItems ( $quoteId = null )
    {
        try {
            if ($quoteId == Mage::getSingleton('core/session')->getJirafePrevQuoteId()) {
                return Mage::getSingleton('core/session')->getJirafePrevQuoteItems();
            } else {
                return array();
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log( 'ERROR', 'Jirafe_Analytics_Model_Cart::_getPreviousItems()', $e);
            return false;
        }
    }

    /**
     * Create array of cart historical data
     *
     * @param string $filter
     * @return array
     */
    public function getHistoricalData( $filter = null )
    {
        try {

            $lastId = isset($filter['last_id']) ? (is_numeric($filter['last_id']) ?  $filter['last_id'] : null): null;
            $startDate = isset($filter['start_date']) ? $filter['start_date'] : null;
            $endDate = isset($filter['end_date']) ? $filter['end_date'] : null;

            $columns = $this->_getAttributesToSelect( 'cart' );
            $columns[] = 'store_id';

            /**
             * After an quote is converted to an order, tax, shipping
             * and discount values are added to quote. After these additions,
             * the quote represents the cart object.
             */

            if( ( $key = array_search('grand_total', $columns)) !== false ) {
                unset($columns[$key]);
            }

            $columns[] = 'subtotal as grand_total';

            $collection = Mage::getModel('sales/quote')
                ->getCollection()
                ->getSelect()
                ->reset(Zend_Db_Select::COLUMNS)
                ->columns( $columns );

            if ( $lastId ) {
                $where = "main_table.entity_id <= $lastId";
            } else if ( $startDate && $endDate ) {
                $where = "created_at BETWEEN '$startDate' AND '$endDate'";
            } else if ( $startDate && !$endDate ){
                $where = "created_at >= '$startDate'";
            } else if ( !$startDate && $endDate ){
                $where = "created_at <= 'endDate'";
            } else {
                $where = null;
            }

            if ($where) {
                $collection->where( $where );
            }

            $data = array();

            //Mage::helper('jirafe_analytics')->log('DEBUG', 'Jirafe_Analytics_Model_Order::getHistoricalData()', 'Preparing pagination of cart query', null);

            // Cart Query
            //Mage::helper('jirafe_analytics')->log('DEBUG', 'Jirafe_Analytics_Model_Order::getHistoricalData()', 'Cart Query: '. $collection->__toString(), null);


            // Paginator
            $currentPage = 1;
            $paginator = Zend_Paginator::factory($collection);
            $paginator->setItemCountPerPage(100)
                ->setCurrentPageNumber($currentPage);
            $pages = $paginator->count();

            $message = sprintf('Page Size: %d', $pages);
            Mage::helper('jirafe_analytics')->log('DEBUG', 'Jirafe_Analytics_Model_Cart::getHistoricalData()', $message, null);

            do{
                //$message = sprintf('Iteration # %d', $currentPage);
                //Mage::helper('jirafe_analytics')->log('DEBUG', 'Jirafe_Analytics_Model_Cart::getHistoricalData()', $message, null);

                $paginator->setCurrentPageNumber($currentPage);

                foreach($paginator as $item) {
                    $data[] = array(
                           'type_id' => Jirafe_Analytics_Model_Data_Type::CART,
                           'store_id' => $item['store_id'],
                           'json' => $this->getJson( $item, false )
                       );
                }

                $currentPage++;
                // 100 milliseconds
                usleep(100 * 1000);
            } while ($currentPage <= $pages);

            return $data;
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Cart::getHistoricalData()', $e);
            return false;
        }
    }

}
