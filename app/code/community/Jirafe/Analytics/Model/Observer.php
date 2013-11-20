<?php

/**
 * Event Observer Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Observer extends Jirafe_Analytics_Model_Abstract
{

    protected $_isEnabled = false;
    
    /**
     * Class construction & variable initialization
     */
    
    protected function _construct()
    {
        $this->_isEnabled = Mage::getStoreConfig('jirafe_analytics/general/enabled');
    }
    
    /**
     * Capture cart save event
     * 
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function cartSave( Varien_Event_Observer $observer )
    {   
        if ( $this->_isEnabled ) {
            try {
                if ( Mage::getSingleton('core/session')->getJirafeProcessCart() ) {
                    $quote = $observer->getCart()->getQuote();
                    $json = Mage::getModel('jirafe_analytics/cart')->getJson( $quote, true );
                    $data = Mage::getModel('jirafe_analytics/data');
                    $data->setTypeId( Jirafe_Analytics_Model_Data_Type::CART );
                    $data->setJson( $json );
                    $data->setStoreId( $quote->getStoreId() );
                    $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                    $data->save();;
                    Mage::getSingleton('core/session')->setJirafeProcessCart( false );
                    return true;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::cartProductAdd()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture update cart item event
     *
     * @return boolean
     */
    
    public function cartUpdateItem()
    {
        if ( $this->_isEnabled ) {
            try {
                Mage::getSingleton('core/session')->setJirafeProcessCart( true );
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::cartAddItem()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture remove item cart event
     *
     * @return boolean
     */
    
    public function cartRemoveItem()
    {
        if ( $this->_isEnabled ) {
            try {
                Mage::getSingleton('core/session')->setJirafeProcessCart( true );
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::cartRemoveItem()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture category save event
     * 
     * @param Varien_Event_Observer $observer
     * @return mixed
     */
    
    public function categorySave( Varien_Event_Observer $observer ) 
    {
        if ( $this->_isEnabled ) {
            try {
                $data = Mage::getModel('jirafe_analytics/data');
                $data->setTypeId( Jirafe_Analytics_Model_Data_Type::CATEGORY );
                $data->setJson( Mage::getModel('jirafe_analytics/category')->getJson( $observer->getCategory() ) );
                $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                $data->save();
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::categorySave()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /** 
     * Capture category delete event
     * 
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function categoryDelete( Varien_Event_Observer $observer ) 
    {
        if ( $this->_isEnabled ) {
            try {
                $data = Mage::getModel('jirafe_analytics/data');
                $data->setTypeId( Jirafe_Analytics_Model_Data_Type::CATEGORY );
                $data->setJson( Mage::getModel('jirafe_analytics/category')->getDeleteJson( $observer->getCategory() ) );
                $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                $data->save();
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::categoryDelete()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture customer save event
     *
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function customerSave( Varien_Event_Observer $observer )
    {
        if ( $this->_isEnabled ) {
            try {
                if ( Mage::getSingleton('core/session')->getJirafeProcessCustomer() ) {
                    $isVisit = Mage::getSingleton('core/session')->getJirafeIsVisit();
                    $data = Mage::getModel('jirafe_analytics/data');
                    $data->setTypeId( Jirafe_Analytics_Model_Data_Type::CUSTOMER );
                    $data->setJson( Mage::getModel('jirafe_analytics/customer')->getJson( $observer->getCustomer(), $isVisit ) );
                    $data->setStoreId( $observer->getCustomer()->getStoreId() );
                    $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                    $data->save();
                    Mage::getSingleton('core/session')->setJirafeProcessCustomer( false );
                    Mage::getSingleton('core/session')->setJirafeIsVisit( false);
                    return true;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::customerSave()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture admin customer save event
     *
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function adminCustomerSave( Varien_Event_Observer $observer )
    {
        if ( $this->_isEnabled ) {
            try {
                Mage::getSingleton('core/session')->setJirafeProcessCustomer( true );
                Mage::getSingleton('core/session')->setJirafeIsVisit( false );
                $this->customerSave( $observer );
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::customerSave()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture customer load event
     *
     * @return boolean
     */
    
    public function customerLoad()
    {
        if ( $this->_isEnabled ) {
            try {
                Mage::getSingleton('core/session')->setJirafeProcessCustomer( true );
                Mage::getSingleton('core/session')->setJirafeIsVisit( true );
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::customerLoad()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture customer register event
     *
     * @return boolean
     */
    
    public function customerRegister()
    {
        if ( $this->_isEnabled ) {
            try {
                Mage::getSingleton('core/session')->setJirafeProcessCustomer( true );
                Mage::getSingleton('core/session')->setJirafeIsVisit( true );
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::customerRegister()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture order placed event
     *
     * @return boolean
     */
    
    public function orderPlaced()
    {
        if ( $this->_isEnabled ) {
            try {
                Mage::getSingleton('core/session')->setJirafeOrderPlaced( true );
                Mage::log('orderPlaced',null,'order.log');
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::orderPlaced()', $e->getMessage(), $e);
                return false;
            }
       }
    }
    
    /**
     * Capture order accepted event
     *
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function orderAccepted( Varien_Event_Observer $observer )
    {
        if ( $this->_isEnabled ) {
            try {
                $order = $observer->getOrder();
                
                /**
                 * Core bug workaround for Magento CE 1.8.0.0 with PHP 5.3.3
                 * Orders are not properly committed for sales_order_* events
                 */
                if (!$order->getEntityId()) {
                    $order->save();
                }
                
                 $data = $order->getData();
                 $payment = $order->getPayment();
                 $data['amount_paid'] = $payment->getAmountPaid();
                 $data['amount_authorized'] = $payment->getAmountAuthorized();
                 $data['jirafe_status'] = 'accepted';
                 
                 $this->_orderSave( $data );
                 return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::orderAccepted()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture order cancelled event
     *
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function orderCancelled( Varien_Event_Observer $observer )
    {
        if ( $this->_isEnabled ) {
            try {
                $order = $observer->getOrder()->getData();
                $order['jirafe_status'] = 'cancelled';
                return $this->_orderSave( $order );
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::orderAccepted()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Convert order to JSON and save
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $status
     * @return boolean
     */
    
    protected function _orderSave( $order = null )
    {
        if ( $this->_isEnabled && $order ) {
            try {
                $data = Mage::getModel('jirafe_analytics/data');
                $data->setTypeId( Jirafe_Analytics_Model_Data_Type::ORDER );
                $data->setJson( Mage::getModel('jirafe_analytics/order')->getJson( $order ) );
                $data->setStoreId( $order['store_id'] );
                $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                $data->save();
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::_orderSave()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    /**
     * Capture order cancel events
     * 
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function orderCancel( Varien_Event_Observer $observer ) 
    {
        if ( $this->_isEnabled ) {
            try {
                $order = $observer->getOrder()->getData();
                $data = Mage::getModel('jirafe_analytics/data');
                $data->setTypeId( Jirafe_Analytics_Model_Data_Type::ORDER );
                $data->setJson( Mage::getModel('jirafe_analytics/order')->getJson( $order ) );
                $data->setStoreId( $order['store_id'] );
                $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                $data->save();
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::orderPlaceAfter()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     *
     * Capture product to check for variant changes
     *
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
     
    public function productSaveAfter( Varien_Event_Observer $observer )
    {
        if ( $this->_isEnabled ) {
            try {
                $product = $observer->getProduct();
                $this->_productSave( $product );
                
                if ($product->getTypeId() === 'configurable') {
                 
                    /** 
                     * Attach or detach simple variants from configurable parents
                     */
                    $originalIds = Mage::getModel('catalog/product_type_configurable')->getUsedProductIds( $product );
                    $newIds = array_keys( $product->getConfigurableProductsData() );
                    
                    /**
                     * Get product attributes from parent configurable
                     */
                    if ( $options = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product) ) {
                        $attributes = serialize($options);
                    } else {
                        $attributes = null;
                    }
                    
                    /**
                     * Check for removed variants
                     */
                    foreach($originalIds as $id) {
                        if ( !in_array( intval($id),$newIds ) ) {
                            if ( $variant = Mage::getModel('catalog/product')->load( intval($id) ) ) {
                                $this->_productSave( $variant, $attributes );
                            }
                        }
                    }
                    
                    /**
                     * Check for added variants
                     */
                    foreach($newIds as $id) {
                        if ( !in_array( strval($id),$originalIds ) ) {
                            if ( $variant = Mage::getModel('catalog/product')->load( $id ) ) {
                             Mage::log('add new variant!',null,'variant.log');
                                $this->_productSave( $variant );
                            }
                        }
                    }
                }
                
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::productSaveBefore()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     *
     * Capture product save event
     * @param Mage_Catalog_Model_Product $product
     * @param string $attributes
     * @return boolean
     */
    
    protected function _productSave( Mage_Catalog_Model_Product $product, $attributes = null )
    {
        if ( $this->_isEnabled ) {
            try {
                $data = Mage::getModel('jirafe_analytics/data');
                $data->setTypeId( Jirafe_Analytics_Model_Data_Type::PRODUCT );
                $data->setJson( Mage::getModel('jirafe_analytics/product')->getJson( null, null, $product, $attributes ) );
                $data->setStoreId( $product->getStoreId() );
                $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                $data->save();
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::productSave()', $e->getMessage(), $e);
                return false;
            }
        }
    }
    
    /**
     * Capture admin user add and modify events
     *
     * @param Varien_Event_Observer $observer
     * @return boolean
     */
    
    public function employeeSave( Varien_Event_Observer $observer )
    {
        if ( $this->_isEnabled ) {
            try {
                $userId = $observer->getObject()->getUserId();
                $data = Mage::getModel('jirafe_analytics/data');
                $data->setTypeId( Jirafe_Analytics_Model_Data_Type::EMPLOYEE );
                $data->setJson( Mage::getModel('jirafe_analytics/employee')->getJson( null, $userId ) );
                $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt() );
                $data->save();
                return true;
            } catch (Exception $e) {
                Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Observer::employeeSave()', $e->getMessage(), $e);
                return false;
            }
        }
    }
}