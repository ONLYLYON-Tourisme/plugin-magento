<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Magento. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class Lyra_Payzen_Model_Payment_Standard extends Lyra_Payzen_Model_Payment_Abstract
{
    protected $_code = 'payzen_standard';
    protected $_formBlockType = 'payzen/standard';

    protected $_canSaveCc = true;

    protected function _setExtraFields($order)
    {
        $info = $this->getInfoInstance();

        if (! $this->_getHelper()->isAdmin() && ($this->isLocalCcType() || $this->isLocalCcInfo())) {
            // Set payment_cards.
            $this->_payzenRequest->set('payment_cards', $info->getCcType());

            if ($info->getCcType() === 'BANCONTACT') {
                // May not disable 3DS for Bancontact Mistercash.
                $this->_payzenRequest->set('threeds_mpi', null);
            }
        } else {
            // Payment_cards is given as csv by Magento.
            $paymentCards = explode(',', $this->getConfigData('payment_cards'));
            $paymentCards = in_array('', $paymentCards) ? '' : implode(';', $paymentCards);

            if ($paymentCards && $this->getConfigData('use_oney_in_standard')) {
                $testMode = $this->_payzenRequest->get('ctx_mode') == 'TEST';

                // Add FacilyPay Oney payment cards.
                $paymentCards .= ';' . ($testMode ? 'ONEY_SANDBOX' : 'ONEY');
            }

            $this->_payzenRequest->set('payment_cards', $paymentCards);
        }

        if ($this->_getHelper()->isAdmin()) {
            // Set payment_src to MOTO for backend payments.
            $this->_payzenRequest->set('payment_src', 'MOTO');
            return;
        }

        $session = Mage::getSingleton('payzen/session');
        if ($this->isIframeMode() && ! $session->getPayzenOneclickPayment() /* no iframe in 1-Click */) {
            // Iframe enabled and this is not 1-Click.
            $this->_payzenRequest->set('action_mode', 'IFRAME');

            // Enable automatic redirection.
            $this->_payzenRequest->set('redirect_enabled', '1');
            $this->_payzenRequest->set('redirect_success_timeout', '0');
            $this->_payzenRequest->set('redirect_error_timeout', '0');

            $returnUrl = $this->_payzenRequest->get('url_return');
            $this->_payzenRequest->set('url_return', $returnUrl . '?iframe=true');
        }

        if (! $this->getConfigData('one_click_active') || ! $order->getCustomerId()) {
            $this->_setCcInfo();
        } else {
            // 1-Click enabled and customer logged-in
            $customer = Mage::getModel('customer/customer');
            $customer->load($order->getCustomerId());

            if ($this->isIdentifierPayment($customer)) {
                // Customer has an identifier and wants to use it.
                $this->_getHelper()->log('Customer ' . $customer->getEmail() . ' has an identifier and chose to use it for payment.' . $customer->getPayzenIdentifier());
                $this->_payzenRequest->set('identifier', $customer->getPayzenIdentifier());
            } else {
                if ($this->isLocalCcInfo() && $info->getAdditionalData()) { // Additional_data is used to stock cc_register flag.
                    // Customer wants to register card data.
                    if ($customer->getPayzenIdentifier()) {
                        // Customer has already an identifier.
                        $this->_getHelper()->log('Customer ' . $customer->getEmail() . ' has an identifier and chose to update it with new card info.');
                        $this->_payzenRequest->set('identifier', $customer->getPayzenIdentifier());
                        $this->_payzenRequest->set('page_action', 'REGISTER_UPDATE_PAY');
                    } else {
                        $this->_getHelper()->log('Customer ' . $customer->getEmail() . ' has not identifier and chose to register his card info.');
                        $this->_payzenRequest->set('page_action', 'REGISTER_PAY');
                    }
                } elseif (! $this->isLocalCcInfo()) {
                    // Card data entry on payment page, let's ask customer for data registration.
                    $this->_getHelper()->log('Customer ' . $customer->getEmail() . ' will be asked for card data registration on payment page.');
                    $this->_payzenRequest->set('page_action', 'ASK_REGISTER_PAY');
                }

                $this->_setCcInfo();
            }
        }
    }

    protected function _setCcInfo()
    {
        if (! $this->isLocalCcInfo()) {
            return;
        }

        $info = $this->getInfoInstance();

        $cardData = explode(' - ', $info->getCcNumber());

        $this->_payzenRequest->set('cvv', $cardData[0]);
        $this->_payzenRequest->set('card_number', $cardData[1]);
        $this->_payzenRequest->set('expiry_year', $info->getCcExpYear());
        $this->_payzenRequest->set('expiry_month', $info->getCcExpMonth());

        // Override action_mode.
        $this->_payzenRequest->set('action_mode', 'SILENT');
    }

    protected function _proposeOney()
    {
        $info = $this->getInfoInstance();

        return (! $info->getCcType() && $this->getConfigData('use_oney_in_standard'))
            || in_array($info->getCcType(), array('ONEY_SANDBOX', 'ONEY'));
    }

    /**
     * Return available card types
     *
     * @return string
     */
    public function getAvailableCcTypes()
    {
        // All cards.
        $allCards = Lyra_Payzen_Model_Api_Api::getSupportedCardTypes();

        // Selected cards from module configuration.
        $cards = $this->getConfigData('payment_cards');

        if (! empty($cards)) {
            $cards = explode(',', $cards);
        } else {
            $cards = array_keys($allCards);
            $cards = array_diff($cards, array('ONEY_SANDBOX', 'ONEY'));
        }

        if (! $this->_getHelper()->isAdmin() && $this->isLocalCcType()
            && $this->getConfigData('use_oney_in_standard')
        ) {
            $testMode = $this->_getHelper()->getCommonConfigData('ctx_mode') == 'TEST';

            $cards[] = $testMode ? 'ONEY_SANDBOX' : 'ONEY';
        }

        $availCards = array();
        foreach ($allCards as $code => $label) {
            if (in_array($code, $cards)) {
                $availCards[$code] = $label;
            }
        }

        return $availCards;
    }

    public function isOneclickAvailable()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        // No 1-Click.
        if (! $this->getConfigData('one_click_active')) {
            return false;
        }

        if ($this->_getHelper()->isAdmin()) {
            return false;
        }

        $session = Mage::getSingleton('customer/session');

        // Customer not logged in.
        if (! $session->isLoggedIn()) {
            return false;
        }

        // Customer has not gateway identifier.
        $customer = $session->getCustomer();
        if (! $customer || ! $customer->getPayzenIdentifier()) {
            return false;
        }

        return true;
    }

    public function isIdentifierPayment($customer)
    {
        $info = $this->getInfoInstance();

        // Payment by identifier.
        return $customer->getPayzenIdentifier()
            && $info->getAdditionalInformation(Lyra_Payzen_Helper_Payment::IDENTIFIER);
    }

    /**
     * Assign data to info model instance
     *
     * @param  mixed $data
     * @return Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (! ($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();

        if ($data->getPayzenStandardUseIdentifier()) {
            $info->setCcType(null)
                ->setCcLast4(null)
                ->setCcNumber(null)
                ->setCcCid(null)
                ->setCcExpMonth(null)
                ->setCcExpYear(null)
                ->setAdditionalData(null)
                ->setAdditionalInformation(Lyra_Payzen_Helper_Payment::IDENTIFIER, true); // Payment by identifier.
        } else {
            // Set card info.
            $info->setCcType($data->getPayzenStandardCcType())
                ->setCcLast4(substr($data->getPayzenStandardCcNumber(), -4))
                ->setCcNumber($data->getPayzenStandardCcNumber())
                ->setCcCid($data->getPayzenStandardCcCvv())
                ->setCcExpMonth($data->getPayzenStandardCcExpMonth())
                ->setCcExpYear($data->getPayzenStandardCcExpYear())
                ->setAdditionalData($data->getPayzenStandardCcRegister()) // Wether to register data.
                ->setAdditionalInformation(Lyra_Payzen_Helper_Payment::IDENTIFIER, false);
        }

        return $this;
    }

    /**
     * Prepare info instance for save
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function prepareSave()
    {
        $info = $this->getInfoInstance();
        if ($this->_canSaveCc) {
            if ($info->getCcNumber()) {
                $info->setCcNumberEnc($info->encrypt($info->getCcCid() . ' - ' . $info->getCcNumber()));
            } else {
                $info->setCcNumberEnc(null);
            }
        }

        $info->setCcNumber(null);
        $info->setCcCid(null);
        return $this;
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function initialize($paymentAction, $stateObject)
    {
        parent::initialize($paymentAction, $stateObject);

        if ($this->_getHelper()->isAdmin() && $this->_getHelper()->isCurrentlySecure()) {
            // Do instant payment by WS.
            $stateObjectResult = $this->_doInstantPayment($this->getInfoInstance());

            $stateObject->setState($stateObjectResult->getState());
            $stateObject->setStatus($stateObjectResult->getStatus());
            $stateObject->setIsNotified($stateObjectResult->getIsNotified());
        }

        return $this;
    }

    /**
     * The URL the customer is redirected to after clicking on "Confirm order".
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        if ($this->isIframeMode()) {
            return Mage::getUrl('payzen/payment/iframe', array('_secure' => true));
        }

        return parent::getOrderPlaceRedirectUrl();
    }

    /**
     * Call gateway by WS to do an instant payment
     *
     * @param  Mage_Sales_Model_Order_Payment $payment
     * @return Varien_Object
     */
    protected function _doInstantPayment($payment)
    {
        $order = $payment->getOrder();
        $storeId = $order->getStore()->getId();

        $this->_getHelper()->log("Instant payment using WS for order #{$order->getId()}.");

        $requestId = '';

        try {
            $wsApi = $this->checkAndGetWsApi($storeId);

            $timestamp = time();

            // Common request generation.
            $commonRequest = new \Lyra\Payzen\Model\Api\Ws\CommonRequest();
            $commonRequest->setPaymentSource('MOTO');
            $commonRequest->setSubmissionDate(new DateTime("@$timestamp"));

            // Amount in current order currency.
            $amount = $order->getGrandTotal();

            // Retrieve currency.
            $currency = Lyra_Payzen_Model_Api_Api::findCurrencyByAlphaCode($order->getOrderCurrencyCode());
            if ($currency == null) {
                // If currency is not supported, use base currency.
                $currency = Lyra_Payzen_Model_Api_Api::findCurrencyByAlphaCode($order->getBaseCurrencyCode());

                // ... and order total in base currency
                $amount = $order->getBaseGrandTotal();
            }

            // Payment request generation.
            $paymentRequest = new \Lyra\Payzen\Model\Api\Ws\PaymentRequest();
            $paymentRequest->setTransactionId(Lyra_Payzen_Model_Api_Api::generateTransId($timestamp));
            $paymentRequest->setAmount($currency->convertAmountToInteger($amount));
            $paymentRequest->setCurrency($currency->getNum());

            $captureDelay = $this->getConfigData('capture_delay', $storeId); // Get submodule specific param.
            if (! is_numeric($captureDelay)) {
                // Get general param.
                $captureDelay = $this->_getHelper()->getCommonConfigData('capture_delay', $storeId);
            }

            if (is_numeric($captureDelay)) {
                $paymentRequest->setExpectedCaptureDate(
                    new DateTime('@' . strtotime("+$captureDelay days", $timestamp))
                );
            }

            $validationMode = $this->getConfigData('validation_mode', $storeId); // Get submodule specific param.
            if ($validationMode === '-1') {
                // Get general param.
                $validationMode = $this->_getHelper()->getCommonConfigData('validation_mode', $storeId);
            }

            if ($validationMode !== '') {
                $paymentRequest->setManualValidation($validationMode);
            }

            // Order request generation.
            $orderRequest = new \Lyra\Payzen\Model\Api\Ws\OrderRequest();
            $orderRequest->setOrderId($order->getIncrementId());

            // Card request generation.
            $cardRequest = new \Lyra\Payzen\Model\Api\Ws\CardRequest();
            $info = $this->getInfoInstance();
            $cardRequest->setNumber($info->getCcNumber());
            $cardRequest->setScheme($info->getCcType());
            $cardRequest->setCardSecurityCode($info->getCcCid());
            $cardRequest->setExpiryMonth($info->getCcExpMonth());
            $cardRequest->setExpiryYear($info->getCcExpYear());

            // Billing details generation.
            $billingDetailsRequest = new \Lyra\Payzen\Model\Api\Ws\BillingDetailsRequest();
            $billingDetailsRequest->setReference($order->getCustomerId());

            if ($order->getBillingAddress()->getPrefix()) {
                $billingDetailsRequest->setTitle($order->getBillingAddress()->getPrefix());
            }

            $billingDetailsRequest->setFirstName($order->getBillingAddress()->getFirstname());
            $billingDetailsRequest->setLastName($order->getBillingAddress()->getLastname());
            $billingDetailsRequest->setPhoneNumber($order->getBillingAddress()->getTelephone());
            $billingDetailsRequest->setCellPhoneNumber($order->getBillingAddress()->getTelephone());
            $billingDetailsRequest->setEmail($order->getCustomerEmail());

            $address = $order->getBillingAddress()->getStreet(1) . ' ' . $order->getBillingAddress()->getStreet(2);
            $billingDetailsRequest->setAddress(trim($address));

            $billingDetailsRequest->setZipCode($order->getBillingAddress()->getPostcode());
            $billingDetailsRequest->setCity($order->getBillingAddress()->getCity());
            $billingDetailsRequest->setState($order->getBillingAddress()->getRegion());
            $billingDetailsRequest->setCountry($order->getBillingAddress()->getCountryId());

            // Language.
            $currentLang = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
            if (Lyra_Payzen_Model_Api_Api::isSupportedLanguage($currentLang)) {
                $language = $currentLang;
            } else {
                $language = $this->_getHelper()->getCommonConfigData('language', $storeId);
            }

            $billingDetailsRequest->setLanguage($language);

            // Shipping details generation.
            $shippingDetailsRequest = new \Lyra\Payzen\Model\Api\Ws\ShippingDetailsRequest();

            $address = $order->getShippingAddress();
            if (is_object($address)) { // Deliverable order.
                $shippingDetailsRequest->setFirstName($address->getFirstname());
                $shippingDetailsRequest->setLastName($address->getLastname());
                $shippingDetailsRequest->setPhoneNumber($address->getTelephone());
                $shippingDetailsRequest->setAddress($address->getStreet(1));
                $shippingDetailsRequest->setAddress2($address->getStreet(2));
                $shippingDetailsRequest->setZipCode($address->getPostcode());
                $shippingDetailsRequest->setCity($address->getCity());
                $shippingDetailsRequest->setState($address->getRegion());
                $shippingDetailsRequest->setCountry($address->getCountryId());
            }

            // Extra details generation.
            $extraDetailsRequest = new \Lyra\Payzen\Model\Api\Ws\ExtraDetailsRequest();
            $extraDetailsRequest->setIpAddress($this->_getHelper()->getIpAddress());

            // Customer request generation.
            $customerRequest = new \Lyra\Payzen\Model\Api\Ws\CustomerRequest();
            $customerRequest->setBillingDetails($billingDetailsRequest);
            $customerRequest->setShippingDetails($shippingDetailsRequest);
            $customerRequest->setExtraDetails($extraDetailsRequest);

            // Create payment object generation.
            $createPayment = new \Lyra\Payzen\Model\Api\Ws\CreatePayment();
            $createPayment->setCommonRequest($commonRequest);
            $createPayment->setPaymentRequest($paymentRequest);
            $createPayment->setOrderRequest($orderRequest);
            $createPayment->setCardRequest($cardRequest);
            $createPayment->setCustomerRequest($customerRequest);

            // Do createPayment WS call.
            $requestId = $wsApi->setHeaders();
            $createPaymentResponse = $wsApi->createPayment($createPayment);

            $wsApi->checkAuthenticity();
            $wsApi->checkResult(
                $createPaymentResponse->getCreatePaymentResult()->getCommonResponse(),
                array(
                    'INITIAL', 'NOT_CREATED', 'AUTHORISED', 'AUTHORISED_TO_VALIDATE',
                    'WAITING_AUTHORISATION', 'WAITING_AUTHORISATION_TO_VALIDATE'
                )
            );

            // Check operation type (0: debit, 1 refund).
            $transType = $createPaymentResponse->getCreatePaymentResult()->getPaymentResponse()->getOperationType();
            if ($transType != 0) {
                throw new Exception("Unexpected transaction type returned ($transType).");
            }

            // Update authorized amount.
            $payment->setAmountAuthorized($order->getTotalDue());
            $payment->setBaseAmountAuthorized($order->getBaseTotalDue());

            $wrapper = new Lyra_Payzen_Model_Api_Ws_ResultWrapper(
                $createPaymentResponse->getCreatePaymentResult()->getCommonResponse(),
                $createPaymentResponse->getCreatePaymentResult()->getPaymentResponse(),
                $createPaymentResponse->getCreatePaymentResult()->getAuthorizationResponse(),
                $createPaymentResponse->getCreatePaymentResult()->getCardResponse(),
                $createPaymentResponse->getCreatePaymentResult()->getThreeDSResponse(),
                $createPaymentResponse->getCreatePaymentResult()->getFraudManagementResponse()
            );

            // Retrieve new order state and status.
            $stateObject = $this->_getPaymentHelper()->nextOrderState($wrapper, $order);
            $this->_getHelper()->log("Order #{$order->getId()}, new state : {$stateObject->getState()}, new status : {$stateObject->getStatus()}.");

            $order->setState($stateObject->getState(), $stateObject->getStatus(), $wrapper->getMessage());
            if ($stateObject->getState() == Mage_Sales_Model_Order::STATE_HOLDED) { // For magento 1.4.0.x
                $stateObject->setState($stateObject->getBeforeState());
                $stateObject->setStatus($stateObject->getBeforeStatus());
            }

            // Save gateway responses.
            $this->_getPaymentHelper()->updatePaymentInfo($order, $wrapper);

            // Try to create invoice.
            $this->_getPaymentHelper()->createInvoice($order);

            $stateObject->setIsNotified(true);
            return $stateObject;
        } catch(Lyra_Payzen_Model_WsException $e) {
            $this->_getHelper()->log("[$requestId] {$e->getMessage()}", Zend_Log::WARN);

            $warn = $this->_getHelper()->__('Please correct this error to use PayZen web services.');
            $this->_getAdminSession()->addWarning($warn);
            $this->_getAdminSession()->addError($this->_getHelper()->__($e->getMessage()));
            Mage::throwException('');
        } catch(\SoapFault $f) {
            $this->_getHelper()->log(
                "[$requestId] SoapFault with code {$f->faultcode}: {$f->faultstring}.",
                Zend_Log::WARN
            );

            $warn = $this->_getHelper()->__('Please correct this error to use PayZen web services.');
            $this->_getAdminSession()->addWarning($warn);
            $this->_getAdminSession()->addError($f->faultstring);
            Mage::throwException('');
        } catch(\UnexpectedValueException $e) {
            $this->_getHelper()->log(
                "[$requestId] createPayment error with code {$e->getCode()}: {$e->getMessage()}.",
                Zend_Log::ERR
            );

            if ($e->getCode() === -1) {
                $this->_getAdminSession()->addError($this->_getHelper()->__('Authentication error ! '));
            } else {
                $this->_getAdminSession()->addError($e->getMessage());
            }

            Mage::throwException('');
        } catch (Exception $e) {
            $this->_getHelper()->log(
                "[$requestId] Exception with code {$e->getCode()}: {$e->getMessage()}",
                Zend_Log::ERR
            );

            $this->_getAdminSession()->addError($e->getMessage());
            Mage::throwException('');
        }
    }

    /**
     * Check if the card data entry on merchant site option is selected
     *
     * @return boolean
     */
    public function isLocalCcInfo()
    {
        return $this->_getHelper()->isCurrentlySecure() // This is a double check, it's also done on backend side.
            && $this->getConfigData('card_info_mode') == 3;
    }

    /**
     * Return true if iframe mode is enabled.
     *
     * @return string
     */
    public function isIframeMode()
    {
        return $this->getConfigData('card_info_mode') == 4;
    }

    /**
     * Check if the local card type selection option is choosen
     *
     * @return boolean
     */
    public function isLocalCcType()
    {
        return $this->getConfigData('card_info_mode') == 2;
    }
}
