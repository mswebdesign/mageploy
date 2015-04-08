<?php

/**
 * Doesn't handle file uploads yet
 *
 * @author Alessandro Ronchi <aronchi at webgriffe.com>
 */
class PugMoRe_Mageploy_Model_Action_Catalog_Category extends PugMoRe_Mageploy_Model_Action_Abstract {

    const VERSION = '1';

    protected $_code = 'catalog_category';
    protected $_blankableParams = array('key', /*'isAjax',*/ 'isIframe', 'form_key',
        'active_tab_id', 'page', 'limit', 'in_category', 'entity_id', 'name', 'sku', 'price', 'position');

    protected function _getVersion() {
        return Mage::helper('pugmore_mageploy')->getVersion(2).'.'.self::VERSION;
    }

//    protected function _loadCategoryByPath($path) {
//        return Mage::getModel('catalog/category')
//                        ->getCollection()
//                        ->addAttributeToSelect('*')
//                        ->addPathFilter($path)
//                        ->getFirstItem();
//    }

    protected function _getCategoryIdFromPath($path) {
        return Mage::helper('pugmore_mageploy')
            ->getCategoryIdFromPath($path);
    }

    protected function _getCategoryUuidFromPath($path, $append = null) {
        return Mage::helper('pugmore_mageploy')
            ->getCategoryUuidFromPath($path, $append, self::UUID_SEPARATOR);
    }

    /*
     * Pay Attention
     * We are assuming that siblings will never have the same name.
     */
    protected function _getCategoryFromParentIdAndName($parentId, $name) {
        return Mage::helper('pugmore_mageploy')
            ->_getCategoryFromParentIdAndName($parentId, $name);
    }

    protected function _getCategoryPathFromUuid($uuid) {
        return Mage::helper('pugmore_mageploy')
            ->getCategoryPathFromUuid($uuid, self::UUID_SEPARATOR);
    }

    public function match() {
        if (!$this->_request) {
            return false;
        }

        if ($this->_request->getModuleName() == (string)Mage::getConfig()->getNode('admin/routers/adminhtml/args/frontName')) {
            if ($this->_request->getControllerName() == 'catalog_category') {
                if (in_array($this->_request->getActionName(), array('save', 'move', 'delete'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /*
     * Pay Attention
     * We are assuming that siblings will never have the same name.
     * @todo explore the possibility to use urls as UUIDs or other kind of
     * unique identifiers.
     */

    public function encode() {
        $result = parent::encode();

        if ($this->_request) {
            $params = $this->_request->getParams();

            // Id
            $newOrExisting = '';
            if (isset($params['id'])) {
                $categoryId = $params['id'];
                $newOrExisting = 'existing';
                $existingCategory = Mage::getModel('catalog/category')->load($categoryId);
                $categoryName = $existingCategory->getName();
                $path = $existingCategory->getPath();
                $categoryUuid = $this->_getCategoryUuidFromPath($path);
                $params['id'] = $categoryUuid;
            } else {
                $categoryId = false;
                $newOrExisting = 'new';
                $categoryName = $params['general']['name'];
                $parentId = $params['general']['path'];
                $parentCategory = Mage::getModel('catalog/category')->load($parentId);
                $path = $parentCategory->getPath();
                //$categoryUuid = $this->_getCategoryUuidFromPath($path, $params['general']['name']);
            }

            // Store
            if (array_key_exists('store', $params)) {
                $storeId = $params['store'];
            } else {
                $storeId = 0;
            }

            if ($storeId) {
                $storeUuid = Mage::app()->getStore($storeId)->getCode();
            } else {
                $storeUuid = $storeId;
            }
            $params['store'] = $storeUuid;

            // Parent
            if (array_key_exists('parent', $params)) {
                $parentId = $params['parent'];
            } else {
                $parentId = 0;
            }

            if ($parentId) {
                $parentCategory = Mage::getModel('catalog/category')->load($parentId);
                $parentUuid = $this->_getCategoryUuidFromPath($parentCategory->getPath());
            } else {
                $parentUuid = $parentId;
            }
            $params['parent'] = $parentUuid;

            // Associated Products
            if (array_key_exists('category_products', $params)) {
                $associatedProductIds = explode('&', $params['category_products']);
            } else {
                $associatedProductIds = array();
            }

            $associatedProductUuids = array();
            foreach ($associatedProductIds as $i => $association) {
                list($id, $position) = explode('=', $association);
                $prod = Mage::getModel('catalog/product')->load($id);
                $associatedProductUuids[] = sprintf("%s=%d", $prod->getSku(), $position);
            }
            $params['category_products'] = implode('&', $associatedProductUuids);

            // General
            if (isset($params['general']['id'])) {
                $params['general']['id'] = $params['id'];
            }
            if (isset($params['general']['path'])) {
                if (isset($params['id'])) {
                    $params['general']['path'] = $params['id'];
                } else {
                    $parentId = $params['general']['path'];
                    $parentCategory = Mage::getModel('catalog/category')->load($parentId);
                    $path = $parentCategory->getPath();
                    $params['general']['path'] = $this->_getCategoryUuidFromPath($path);
                }
            }

            // pid, paid, aid (move action)
            $moveIds = array('pid', 'aid', 'paid');
            foreach ($moveIds as $key) {
                if (isset($params[$key])) {
                    $moveNodeId = $params[$key];
                    $moveNodeCategory = Mage::getModel('catalog/category')->load($moveNodeId);
                    $moveNodeUuid = $this->_getCategoryUuidFromPath($moveNodeCategory->getPath());
                    $params[$key] = $moveNodeUuid;
                }
            }

            foreach ($this->_blankableParams as $key) {
                if (isset($params[$key])) {
                    unset($params[$key]);
                }
            }

            $result[self::INDEX_EXECUTOR_CLASS] = get_class($this);
            $result[self::INDEX_CONTROLLER_MODULE] = $this->_request->getControllerModule();
            $result[self::INDEX_CONTROLLER_NAME] = $this->_request->getControllerName();
            $result[self::INDEX_ACTION_NAME] = $this->_request->getActionName();
            $result[self::INDEX_ACTION_PARAMS] = $this->_encodeParams($params);
            $result[self::INDEX_ACTION_DESCR] = sprintf("%s %s Category named '%s'", ucfirst($this->_request->getActionName()), $newOrExisting, $categoryName);
            $result[self::INDEX_VERSION] = $this->_getVersion();
        } else {
            $result = false;
        }
        return $result;
    }

    /*
     * return Mage_Core_Controller_Request_Http
     */

    public function decode($encodedParameters, $version) {
        // The !empty() ensures that rows without a version number can be
        // executed (not without any risk).
        if (!empty($version) && $this->_getVersion() != $version) {
            throw new Exception(sprintf("Can't decode the Action encoded with %s Tracker v %s; current Category Tracker is v %s ", $this->_code, $version, $this->_getVersion()));
        }

        $parameters = $this->_decodeParams($encodedParameters);

        // Id
        if (isset($parameters['id'])) {
            $categoryUuid = $parameters['id'];

            $path = $this->_getCategoryPathFromUuid($categoryUuid);
            $categoryId = $this->_getCategoryIdFromPath($path);
            $parameters['id'] = $categoryId;
        }

        // Store
        $storeCode = $parameters['store'];
        if ($storeCode) {
            $storeId = Mage::app()->getStore($storeCode)->getId();
            $parameters['store'] = $storeId;
        }

        // Parent
        $parentUuid = $parameters['parent'];
        if ($parentUuid) {
            $parentPath = $this->_getCategoryPathFromUuid($parentUuid);
            $parentId = $this->_getCategoryIdFromPath($parentPath);
            $parameters['parent'] = $parentId;
        }

        // Associated Products
        $associatedProductUuids = explode('&', $parameters['category_products']);
        $associatedProductIds = array();
        foreach ($associatedProductUuids as $i => $association) {
            list($sku, $position) = explode('=', $association);
            $id = Mage::getModel('catalog/product')->getIdBySku($sku);
            $associatedProductIds[] = sprintf("%s=%d", $id, $position);
        }
        $parameters['category_products'] = implode('&', $associatedProductIds);

        // General
        if (isset($parameters['general']['id'])) {
            $parameters['general']['id'] = $parameters['id'];
        }
        if (isset($parameters['general']['path'])) {
            if (isset($parameters['id'])) {
                $category = Mage::getModel('catalog/category')->load($parameters['id']);
                $parameters['general']['path'] = $category->getPath();
            } else {
                $parentUuid = $parameters['general']['path'];
                $parentPath = $this->_getCategoryPathFromUuid($parentUuid);
                $parameters['general']['path'] = $this->_getCategoryIdFromPath($parentPath);
            }
        }

        // pid, paid, aid (move action)
        $moveIds = array('pid', 'aid', 'paid');
        foreach ($moveIds as $key) {
            if (isset($parameters[$key])) {
                $moveNodeUuid = $parameters[$key];
                $moveNodeCategoryPath = $this->_getCategoryPathFromUuid($moveNodeUuid);
                $parameters[$key] = $this->_getCategoryIdFromPath($moveNodeCategoryPath);
            }
        }

        $request = new Mage_Core_Controller_Request_Http();
        $request->setPost($parameters);
        #$request->setQuery($parameters);
        return $request;
    }

}