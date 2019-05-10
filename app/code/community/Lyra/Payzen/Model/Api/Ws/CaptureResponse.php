<?php
/**
 * PayZen V2-Payment Module version 1.9.3 for Magento 1.4-1.9. Support contact : support@payzen.eu.
 *
 * @category  Payment
 * @package   Payzen
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @copyright 2014-2019 Lyra Network and contributors
 * @license   
 */

namespace Lyra\Payzen\Model\Api\Ws;

class CaptureResponse
{
    /**
     * @var \DateTime $date
     */
    private $date = null;

    /**
     * @var int $number
     */
    private $number = null;

    /**
     * @var int $reconciliationStatus
     */
    private $reconciliationStatus = null;

    /**
     * @var int $refundAmount
     */
    private $refundAmount = null;

    /**
     * @var int $refundCurrency
     */
    private $refundCurrency = null;

    /**
     * @var boolean $chargeback
     */
    private $chargeback = null;

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        if ($this->date == null) {
            return null;
        } else {
            try {
                return new \DateTime($this->date);
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    /**
     * @param \DateTime $date
     * @return CaptureResponse
     */
    public function setDate(\DateTime $date = null)
    {
        if ($date == null) {
            $this->date = null;
        } else {
            $this->date = $date->format(\DateTime::ATOM);
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param int $number
     * @return CaptureResponse
     */
    public function setNumber($number)
    {
        $this->number = $number;
        return $this;
    }

    /**
     * @return int
     */
    public function getReconciliationStatus()
    {
        return $this->reconciliationStatus;
    }

    /**
     * @param int $reconciliationStatus
     * @return CaptureResponse
     */
    public function setReconciliationStatus($reconciliationStatus)
    {
        $this->reconciliationStatus = $reconciliationStatus;
        return $this;
    }

    /**
     * @return int
     */
    public function getRefundAmount()
    {
        return $this->refundAmount;
    }

    /**
     * @param int $refundAmount
     * @return CaptureResponse
     */
    public function setRefundAmount($refundAmount)
    {
        $this->refundAmount = $refundAmount;
        return $this;
    }

    /**
     * @return int
     */
    public function getRefundCurrency()
    {
        return $this->refundCurrency;
    }

    /**
     * @param int $refundCurrency
     * @return CaptureResponse
     */
    public function setRefundCurrency($refundCurrency)
    {
        $this->refundCurrency = $refundCurrency;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getChargeback()
    {
        return $this->chargeback;
    }

    /**
     * @param boolean $chargeback
     * @return CaptureResponse
     */
    public function setChargeback($chargeback)
    {
        $this->chargeback = $chargeback;
        return $this;
    }
}
