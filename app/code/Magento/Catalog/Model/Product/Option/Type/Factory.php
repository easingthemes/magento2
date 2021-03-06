<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */

/**
 * Catalog product option factory
 */
namespace Magento\Catalog\Model\Product\Option\Type;

class Factory
{
    /**
     * Object Manager
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * Construct
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager)
    {
        $this->_objectManager = $objectManager;
    }

    /**
     * Create product option
     *
     * @param string $className
     * @param array $data
     * @return \Magento\Catalog\Model\Product\Option\Type\DefaultType
     * @throws \Magento\Framework\Model\Exception
     */
    public function create($className, array $data = [])
    {
        $option = $this->_objectManager->create($className, $data);

        if (!$option instanceof \Magento\Catalog\Model\Product\Option\Type\DefaultType) {
            throw new \Magento\Framework\Model\Exception(
                $className . ' doesn\'t extends \Magento\Catalog\Model\Product\Option\Type\DefaultType'
            );
        }
        return $option;
    }
}
