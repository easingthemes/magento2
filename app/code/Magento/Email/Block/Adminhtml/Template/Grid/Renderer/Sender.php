<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */
namespace Magento\Email\Block\Adminhtml\Template\Grid\Renderer;

/**
 * Adminhtml system templates grid block sender item renderer
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Sender extends \Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer
{
    /**
     * Render grid column
     *
     * @param \Magento\Framework\Object $row
     * @return string
     */
    public function render(\Magento\Framework\Object $row)
    {
        $str = '';

        if ($row->getTemplateSenderName()) {
            $str .= htmlspecialchars($row->getTemplateSenderName()) . ' ';
        }

        if ($row->getTemplateSenderEmail()) {
            $str .= '[' . $row->getTemplateSenderEmail() . ']';
        }

        if ($str == '') {
            $str .= '---';
        }

        return $str;
    }
}
