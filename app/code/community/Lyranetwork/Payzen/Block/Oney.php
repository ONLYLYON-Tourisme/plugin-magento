<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Magento. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class Lyranetwork_Payzen_Block_Oney extends Lyranetwork_Payzen_Block_Oney3x4x
{
    protected $_model = 'oney';

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payzen/oney.phtml');
    }

    public function getPaymentOptions()
    {
        if ($this->_getModel()->getConfigData('enable_payment_options') != 1) {
            // Local payment options selection is not enabled.
            return false;
        }

        return parent::getPaymentOptions();
    }
}
