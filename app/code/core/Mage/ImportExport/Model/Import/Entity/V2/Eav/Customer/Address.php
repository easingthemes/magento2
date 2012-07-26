<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_ImportExport
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Import entity customer address model
 *
 * @category    Mage
 * @package     Mage_ImportExport
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Address
    extends Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Abstract
{
    /**#@+
     * Permanent column names
     *
     * Names that begins with underscore is not an attribute.
     * This name convention is for to avoid interference with same attribute name.
     */
    const COLUMN_EMAIL      = '_email';
    const COLUMN_ADDRESS_ID = '_entity_id';
    /**#@-*/

    /**#@+
     * Required column names
     */
    const COLUMN_REGION     = 'region';
    const COLUMN_COUNTRY_ID = 'country_id';
    /**#@-*/

    /**#@+
     * Particular columns that contains of customer default addresses
     */
    const COLUMN_DEFAULT_BILLING  = '_address_default_billing_';
    const COLUMN_DEFAULT_SHIPPING = '_address_default_shipping_';
    /**#@-*/

    /**#@+
     * Error codes
     */
    const ERROR_ADDRESS_ID_IS_EMPTY = 'addressIdIsEmpty';
    const ERROR_ADDRESS_NOT_FOUND   = 'addressNotFound';
    const ERROR_INVALID_REGION      = 'invalidRegion';
    /**#@-*/

    /**
     * Default addresses column names to appropriate customer attribute code
     *
     * @var array
     */
    protected static $_defaultAddressAttributeMapping = array(
        self::COLUMN_DEFAULT_BILLING  => 'default_billing',
        self::COLUMN_DEFAULT_SHIPPING => 'default_shipping'
    );

    /**
     * Permanent entity columns
     *
     * @var array
     */
    protected $_permanentAttributes = array(self::COLUMN_WEBSITE, self::COLUMN_EMAIL, self::COLUMN_ADDRESS_ID);

    /**
     * Existing addresses
     *
     * [customer ID] => array(
     *     address ID 1,
     *     address ID 2,
     *     ...
     *     address ID N
     * )
     *
     * @var array
     */
    protected $_addresses = array();

    /**
     * Attributes with index (not label) value
     *
     * @var array
     */
    protected $_indexValueAttributes = array(self::COLUMN_COUNTRY_ID);

    /**
     * Customer entity DB table name
     *
     * @var string
     */
    protected $_entityTable;

    /**
     * Countries and regions
     *
     * array(
     *   [country_id_lowercased_1] => array(
     *     [region_code_lowercased_1]         => region_id_1,
     *     [region_default_name_lowercased_1] => region_id_1,
     *     ...,
     *     [region_code_lowercased_n]         => region_id_n,
     *     [region_default_name_lowercased_n] => region_id_n
     *   ),
     *   ...
     * )
     *
     * @var array
     */
    protected $_countryRegions = array();

    /**
     * Region ID to region default name pairs
     *
     * @var array
     */
    protected $_regions = array();

    /**
     * Column names that holds values with particular meaning
     *
     * @var array
     */
    protected $_particularAttributes = array(
        self::COLUMN_WEBSITE,
        self::COLUMN_EMAIL,
        self::COLUMN_ADDRESS_ID,
        self::COLUMN_DEFAULT_BILLING,
        self::COLUMN_DEFAULT_SHIPPING
    );

    /**
     * Customer entity
     *
     * @var Mage_Customer_Model_Customer
     */
    protected $_customerEntity;

    /**
     * Date/time format to import
     *
     * @var string
     */
    protected $_dateTimeFormat;

    /**
     * Entity ID incremented value
     *
     * @var int
     */
    protected $_nextEntityId;

    /**
     * Array of region parameters
     *
     * @var array
     */
    protected $_regionParameters;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        /** @var $addressResource Mage_Customer_Model_Resource_Address */
        $addressResource = Mage::getModel('Mage_Customer_Model_Address')->getResource();
        $this->_entityTable = $addressResource->getEntityTable();

        /** @var $helper Mage_ImportExport_Helper_Data */
        $helper = Mage::helper('Mage_ImportExport_Helper_Data');
        $this->addMessageTemplate(self::ERROR_ADDRESS_ID_IS_EMPTY,
            $helper->__('Customer address id column is not specified')
        );
        $this->addMessageTemplate(self::ERROR_ADDRESS_NOT_FOUND,
            $helper->__("Customer address for such customer doesn't exist")
        );
        $this->addMessageTemplate(self::ERROR_INVALID_REGION, $helper->__('Region is invalid'));

        $this->_initAttributes();
        $this->_initAddresses()
            ->_initCountryRegions();
    }

    /**
     * Customer entity getter
     *
     * @return Mage_Customer_Model_Customer
     */
    protected function _getCustomerEntity()
    {
        if (!$this->_customerEntity) {
            $this->_customerEntity = Mage::getModel('Mage_Customer_Model_Customer');
        }
        return $this->_customerEntity;
    }

    /**
     * Get date/time format string
     *
     * @return string
     */
    protected function _getDateTimeFormat()
    {
        if (!$this->_dateTimeFormat) {
            $this->_dateTimeFormat = Varien_Date::convertZendToStrftime(
                Varien_Date::DATETIME_INTERNAL_FORMAT, true, true
            );
        }
        return $this->_dateTimeFormat;
    }

    /**
     * Get region parameters
     *
     * @return array
     */
    protected function _getRegionParameters()
    {
        if (!$this->_regionParameters) {
            $this->_regionParameters = array();
            /** @var $regionConfig Mage_Eav_Model_Config */
            $regionConfig = Mage::getSingleton('Mage_Eav_Model_Config');
            /** @var $regionIdAttribute Mage_Customer_Model_Attribute */
            $regionIdAttribute = $regionConfig->getAttribute($this->getEntityTypeCode(), 'region_id');
            $this->_regionParameters['table']        = $regionIdAttribute->getBackend()->getTable();
            $this->_regionParameters['attribute_id'] = $regionIdAttribute->getId();
        }
        return $this->_regionParameters;
    }

    /**
     * Get next address entity ID
     *
     * @return int
     */
    protected function _getNextEntityId()
    {
        if (!$this->_nextEntityId) {
            /** @var $addressResource Mage_Customer_Model_Resource_Address */
            $addressResource     = Mage::getModel('Mage_Customer_Model_Address')->getResource();
            $addressTable        = $addressResource->getEntityTable();
            $this->_nextEntityId = Mage::getResourceHelper('Mage_ImportExport')->getNextAutoincrement($addressTable);
        }
        return $this->_nextEntityId++;
    }

    /**
     * Initialize existent addresses data
     *
     * @return Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Address
     */
    protected function _initAddresses()
    {
        /** @var $address Mage_Customer_Model_Address */
        foreach (Mage::getResourceModel('Mage_Customer_Model_Resource_Address_Collection') as $address) {
            $customerId = $address->getParentId();
            if (!isset($this->_addresses[$customerId])) {
                $this->_addresses[$customerId] = array();
            }
            $addressId = $address->getId();
            if (!in_array($addressId, $this->_addresses[$customerId])) {
                $this->_addresses[$customerId][] = $addressId;
            }
        }
        return $this;
    }

    /**
     * Get region collection
     *
     * @return Mage_Customer_Model_Resource_Customer_Collection
     */
    protected function _getRegionCollection()
    {
        /** @var $collection Mage_Directory_Model_Resource_Region_Collection */
        $collection = Mage::getResourceModel('Mage_Directory_Model_Resource_Region_Collection');
        return $collection;
    }

    /**
     * Initialize country regions hash for clever recognition
     *
     * @return Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Address
     */
    protected function _initCountryRegions()
    {
        $collection = $this->_getRegionCollection();
        /** @var $region Mage_Directory_Model_Region */
        foreach ($collection->getItems() as $region) {
            $countryNormalized = strtolower($region->getCountryId());
            $regionCode = strtolower($region->getCode());
            $regionName = strtolower($region->getDefaultName());
            $this->_countryRegions[$countryNormalized][$regionCode] = $region->getId();
            $this->_countryRegions[$countryNormalized][$regionName] = $region->getId();
            $this->_regions[$region->getId()] = $region->getDefaultName();
        }
        return $this;
    }

    /**
     * Import data rows
     *
     * @abstract
     * @return boolean
     */
    protected function _importData()
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $addUpdateRows = array();
            $attributes    = array();
            $defaults      = array(); // customer default addresses (billing/shipping) data
            $deleteRowIds  = array();

            foreach ($bunch as $rowNumber => $rowData) {
                // check row data
                if (!$this->validateRow($rowData, $rowNumber)) {
                    continue;
                }

                if ($this->getBehavior($rowData) == Mage_ImportExport_Model_Import::BEHAVIOR_V2_ADD_UPDATE) {
                    $addUpdateResult = $this->_prepareDataForUpdate($rowData);
                    $addUpdateRows[] = $addUpdateResult['entity_row'];
                    $attributes = $this->_mergeEntityAttributes($addUpdateResult['attributes'], $attributes);
                    $defaults   = $this->_mergeEntityAttributes($addUpdateResult['defaults'], $defaults);
                } elseif ($this->getBehavior($rowData) == Mage_ImportExport_Model_Import::BEHAVIOR_V2_DELETE) {
                    $deleteRowIds[] = $rowData[self::COLUMN_ADDRESS_ID];
                }
            }

            $this->_saveAddressEntities($addUpdateRows)
                ->_saveAddressAttributes($attributes)
                ->_saveCustomerDefaults($defaults);

            $this->_deleteAddressEntities($deleteRowIds);
        }
        return true;
    }

    /**
     * Merge attributes
     *
     * @param array $newAttributes
     * @param array $attributes
     * @return array
     */
    protected function _mergeEntityAttributes(array $newAttributes, array $attributes)
    {
        foreach ($newAttributes as $tableName => $tableData) {
            foreach ($tableData as $entityId => $entityData) {
                foreach ($entityData as $attributeId => $attributeValue) {
                    $attributes[$tableName][$entityId][$attributeId] = $attributeValue;
                }
            }
        }
        return $attributes;
    }

    /**
     * Prepare data for add/update action
     *
     * @param array $rowData
     * @return array
     */
    protected function _prepareDataForUpdate(array $rowData)
    {
        $email      = strtolower($rowData[self::COLUMN_EMAIL]);
        $websiteId  = $this->_websiteCodeToId[$rowData[self::COLUMN_WEBSITE]];
        $customerId = $this->_customers[$email][$websiteId];

        $regionParameters    = $this->_getRegionParameters();
        $regionIdTable       = $regionParameters['table'];
        $regionIdAttributeId = $regionParameters['attribute_id'];

        // get address attributes
        $addressAttributes = array();
        foreach ($this->_attributes as $attributeAlias => $attributeParams) {
            if (isset($rowData[$attributeAlias]) && strlen($rowData[$attributeAlias])) {
                if ('select' == $attributeParams['type']) {
                    $value = $attributeParams['options'][strtolower($rowData[$attributeAlias])];
                } elseif ('datetime' == $attributeParams['type']) {
                    $value = gmstrftime($this->_getDateTimeFormat(), strtotime($rowData[$attributeAlias]));
                } else {
                    $value = $rowData[$attributeAlias];
                }
                $addressAttributes[$attributeParams['id']] = $value;
            }
        }

        // get address id
        if (isset($this->_addresses[$customerId])
            && in_array($rowData[self::COLUMN_ADDRESS_ID], $this->_addresses[$customerId])
        ) {
            $addressId = $rowData[self::COLUMN_ADDRESS_ID];
        } else {
            $addressId = $this->_getNextEntityId();
        }

        // entity table data
        $entityRow = array(
            'entity_id'      => $addressId,
            'entity_type_id' => $this->getEntityTypeId(),
            'parent_id'      => $customerId,
            'created_at'     => now(),
            'updated_at'     => now()
        );

        // attribute values
        $attributes = array();
        foreach ($this->_attributes as $attributeParams) {
            if (isset($addressAttributes[$attributeParams['id']])) {
                $attributes[$attributeParams['table']][$addressId][$attributeParams['id']]
                    = $addressAttributes[$attributeParams['id']];
            }
        }

        // customer default addresses
        $defaults = array();
        foreach (self::getDefaultAddressAttributeMapping() as $columnName => $attributeCode) {
            if (!empty($rowData[$columnName])) {
                /** @var $attribute Mage_Eav_Model_Entity_Attribute_Abstract */
                $attribute = $this->_getCustomerEntity()->getAttribute($attributeCode);
                $defaults[$attribute->getBackend()->getTable()][$customerId][$attribute->getId()] = $addressId;
            }
        }

        // let's try to find region ID
        if (!empty($rowData[self::COLUMN_REGION])) {
            $countryNormalized = strtolower($rowData[self::COLUMN_COUNTRY_ID]);
            $regionNormalized  = strtolower($rowData[self::COLUMN_REGION]);

            if (isset($this->_countryRegions[$countryNormalized][$regionNormalized])) {
                $regionId = $this->_countryRegions[$countryNormalized][$regionNormalized];
                $attributes[$regionIdTable][$addressId][$regionIdAttributeId] = $regionId;
                $tableName = $this->_attributes[self::COLUMN_REGION]['table'];
                $regionColumnNameId = $this->_attributes[self::COLUMN_REGION]['id'];
                $attributes[$tableName][$addressId][$regionColumnNameId] = $this->_regions[$regionId];
            }
        }

        return array(
            'entity_row' => $entityRow,
            'attributes' => $attributes,
            'defaults'   => $defaults,
        );
    }

    /**
     * Update and insert data in entity table
     *
     * @param array $entityRows Rows for insert
     * @return Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Address
     */
    protected function _saveAddressEntities(array $entityRows)
    {
        if ($entityRows) {
            $this->_connection->insertOnDuplicate($this->_entityTable, $entityRows, array('updated_at'));
        }
        return $this;
    }

    /**
     * Save customer address attributes
     *
     * @param array $attributesData
     * @return Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Address
     */
    protected function _saveAddressAttributes(array $attributesData)
    {
        foreach ($attributesData as $tableName => $data) {
            $tableData = array();
            foreach ($data as $addressId => $attributeData) {
                foreach ($attributeData as $attributeId => $value) {
                    $tableData[] = array(
                        'entity_id'      => $addressId,
                        'entity_type_id' => $this->getEntityTypeId(),
                        'attribute_id'   => $attributeId,
                        'value'          => $value
                    );
                }
            }
            $this->_connection->insertOnDuplicate($tableName, $tableData, array('value'));
        }
        return $this;
    }

    /**
     * Save customer default addresses
     *
     * @param array $defaults
     * @return Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Address
     */
    protected function _saveCustomerDefaults(array $defaults)
    {
        /** @var $entity Mage_Customer_Model_Customer */
        $entity = Mage::getModel('Mage_Customer_Model_Customer');
        $entityTypeId = $entity->getEntityTypeId();

        foreach ($defaults as $tableName => $data) {
            $tableData = array();
            foreach ($data as $customerId => $attributeData) {
                foreach ($attributeData as $attributeId => $value) {
                    $tableData[] = array(
                        'entity_id'      => $customerId,
                        'entity_type_id' => $entityTypeId,
                        'attribute_id'   => $attributeId,
                        'value'          => $value
                    );
                }
            }
            $this->_connection->insertOnDuplicate($tableName, $tableData, array('value'));
        }
        return $this;
    }

    /**
     * Delete data from entity table
     *
     * @param array $entityRowIds Row IDs for delete
     * @return Mage_ImportExport_Model_Import_Entity_V2_Eav_Customer_Address
     */
    protected function _deleteAddressEntities(array $entityRowIds)
    {
        if ($entityRowIds) {
            $this->_connection->delete($this->_entityTable, array('entity_id IN (?)' => $entityRowIds));
        }
        return $this;
    }

    /**
     * EAV entity type code getter
     *
     * @abstract
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'customer_address';
    }

    /**
     * Customer default addresses column name to customer attribute mapping array
     *
     * @static
     * @return array
     */
    public static function getDefaultAddressAttributeMapping()
    {
        return self::$_defaultAddressAttributeMapping;
    }

    /**
     * Validate row for add/update action
     *
     * @param array $rowData
     * @param int $rowNumber
     * @return null
     */
    protected function _validateRowForUpdate(array $rowData, $rowNumber)
    {
        if ($this->_checkUniqueKey($rowData, $rowNumber)) {
            $email      = strtolower($rowData[self::COLUMN_EMAIL]);
            $website    = $rowData[self::COLUMN_WEBSITE];
            $addressId  = $rowData[self::COLUMN_ADDRESS_ID];

            if (!$this->_getCustomerId($email, $website)) {
                $this->addRowError(self::ERROR_CUSTOMER_NOT_FOUND, $rowNumber);
            } else {
                $websiteId  = $this->_websiteCodeToId[$rowData[self::COLUMN_WEBSITE]];
                $customerId = $this->_customers[$email][$websiteId];

                // check simple attributes
                foreach ($this->_attributes as $attributeCode => $attributeParams) {
                    if (in_array($attributeCode, $this->_ignoredAttributes)) {
                        continue;
                    }
                    if (isset($rowData[$attributeCode]) && strlen($rowData[$attributeCode])) {
                        $this->isAttributeValid($attributeCode, $attributeParams, $rowData, $rowNumber);
                    } elseif ($attributeParams['is_required'] && (!isset($this->_addresses[$customerId])
                        || !in_array($addressId, $this->_addresses[$customerId]))
                    ) {
                        $this->addRowError(self::ERROR_VALUE_IS_REQUIRED, $rowNumber, $attributeCode);
                    }
                }

                if (isset($rowData[self::COLUMN_COUNTRY_ID]) && isset($rowData[self::COLUMN_REGION])) {
                    $countryRegions = isset($this->_countryRegions[strtolower($rowData[self::COLUMN_COUNTRY_ID])])
                        ? $this->_countryRegions[strtolower($rowData[self::COLUMN_COUNTRY_ID])]
                        : array();

                    if (!empty($rowData[self::COLUMN_REGION])
                        && !empty($countryRegions)
                        && !isset($countryRegions[strtolower($rowData[self::COLUMN_REGION])])
                    ) {
                        $this->addRowError(self::ERROR_INVALID_REGION, $rowNumber, self::COLUMN_REGION);
                    }
                }
            }
        }
    }

    /**
     * Validate row for delete action
     *
     * @param array $rowData
     * @param int $rowNumber
     * @return null
     */
    protected function _validateRowForDelete(array $rowData, $rowNumber)
    {
        if ($this->_checkUniqueKey($rowData, $rowNumber)) {
            $email     = strtolower($rowData[self::COLUMN_EMAIL]);
            $website   = $rowData[self::COLUMN_WEBSITE];
            $addressId = $rowData[self::COLUMN_ADDRESS_ID];

            if (!$this->_getCustomerId($email, $website)) {
                $this->addRowError(self::ERROR_CUSTOMER_NOT_FOUND, $rowNumber);
            } else {
                $websiteId  = $this->_websiteCodeToId[$rowData[self::COLUMN_WEBSITE]];
                $customerId = $this->_customers[$email][$websiteId];

                if (!strlen($addressId)) {
                    $this->addRowError(self::ERROR_ADDRESS_ID_IS_EMPTY, $rowNumber);
                } elseif (!in_array($addressId, $this->_addresses[$customerId])) {
                    $this->addRowError(self::ERROR_ADDRESS_NOT_FOUND, $rowNumber);
                }
            }
        }
    }

    /**
     * Retrieve entity attribute EAV collection
     *
     * @return Mage_Eav_Model_Resource_Attribute_Collection
     */
    protected function _getAttributeCollection()
    {
        /** @var $addressCollection Mage_Customer_Model_Resource_Address_Attribute_Collection */
        $addressCollection = Mage::getResourceModel('Mage_Customer_Model_Resource_Address_Attribute_Collection');
        $addressCollection->addSystemHiddenFilter()
            ->addExcludeHiddenFrontendFilter();
        return $addressCollection;
    }
}