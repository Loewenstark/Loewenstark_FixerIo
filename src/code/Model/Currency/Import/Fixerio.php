<?php

class Loewenstark_FixerIo_Model_Currency_Import_Fixerio
extends Mage_Directory_Model_Currency_Import_Abstract
{
    protected $_currencyRates = array();
    protected $_messages = array();
    protected $_url = null;
    protected $_curl_error = false;

    public function __construct()
    {
        $key = null;
        if ($this->_getMode() != 'free')
        {
            $key = Mage::getStoreConfig('currency/lomfixerio/key');
        }
        $result = $this->_getFromFixerIo($this->_getBaseCurrency(), $this->_getCurrencyCodes(), $key);
        if (isset($result['error']) && is_array($result['error']))
        {
            $this->_curl_error = true;
            $this->_messages[] = 'Code: '.Mage::helper('core')->escapeHtml($result['error']['code'])
                    .' Message: '.Mage::helper('core')->escapeHtml($result['error']['info']);
        }

        if (isset($result['base'])
                && isset($result['rates'])
                && !empty($result['rates']))
        {
            foreach ($result['rates'] as $_cur => $_rate)
            {
                if (!isset($this->_currencyRates[$this->_getBaseCurrency()]))
                {
                    $this->_currencyRates[$this->_getBaseCurrency()] = array();
                }
                $this->_currencyRates[$this->_getBaseCurrency()][$_cur] = round($_rate, 4);
            }
        }

        if (empty($this->_currencyRates) && !$this->_curl_error)
        {
            $this->_messages[] = Mage::helper('directory')->__('Could not load data from fixer.io');
        }
    }

    /**
     * 
     * @param float $currencyFrom
     * @param float $currencyTo
     * @return float
     */
    protected function _convert($currencyFrom, $currencyTo)
    {
        if ($currencyFrom == $currencyTo || $this->_curl_error)
        {
            return;
        }
        if (isset($this->_currencyRates[$currencyFrom][$currencyTo]))
        {
            return $this->_currencyRates[$currencyFrom][$currencyTo];
        }
        if ($currencyFrom != $this->_getBaseCurrency())
        {
            $singleResult = $this->_getFromFixerIo($currencyFrom, $currencyTo);
            if (isset($singleResult['base'])
                && isset($singleResult['rates'][$currencyTo]))
            {
                return round($singleResult['rates'][$currencyTo], 4);
            }
        }
        $this->_messages[] = Mage::helper('directory')->__('Your currency "%s" is not on fixer.io', $currencyTo);
        return;
    }

    /**
     * 
     * @param string $baseCurrency
     * @param string $toCurrency
     * @return string
     */
    protected function _getFromFixerIo($baseCurrency, $toCurrency, $key = null)
    {
        if (is_array($toCurrency))
        {
            $toCurrency = implode(',', $toCurrency);
        }
        if (empty($toCurrency))
        {
            return false;
        }
        
        $fields = array(
            'base' => $baseCurrency,
            'symbols' => $toCurrency,
        );
        if(!is_null($key))
        {
            $fields['access_key'] = Mage::helper('core')->decrypt($key);
        }
        try {
            $postfields = http_build_query($fields);
            $url = $this->_getUrl().'?'.$postfields;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_getTimeout());
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->_getTimeout());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (Mage::getStoreConfig('currency/lomfixerio/tlscheck', 0))
            {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }
            $result = curl_exec($ch);
            $err = null;
            if ($result === false)
            {
                $err = curl_error($ch);
            }
            curl_close($ch);
            if ($err)
            {
                throw new Exception($err);
            }
            return json_decode($result, true);
        } catch(Exception $e) {
            $message = $e->getMessage();
            // keep key secure
            if (isset($fields['access_key']))
            {
                $message = str_replace($fields['access_key'], '####', $message);
            }
            $this->_messages[] = $message;
            $this->_curl_error = true;
        }
    }

    /**
     * 
     * @return string
     */
    protected function _getBaseCurrency()
    {
        return Mage::getStoreConfig('currency/options/base', 0);
    }

    /**
     * 
     * @return array
     */
    protected function getAllCurrencies()
    {
        $currencies = array(
            $this->_getBaseCurrency(),
            Mage::getStoreConfig('currency/options/default', 0)
        );
        $currencies = array_merge($currencies, (array)explode(',', Mage::getStoreConfig('currency/options/allow', 0)));
        foreach (Mage::app()->getStores() as $_id => $_store)
        {
            $currencies[] = Mage::getStoreConfig('currency/options/default', $_id);
            $currencies = array_merge($currencies, (array)explode(',', Mage::getStoreConfig('currency/options/allow', $_id)));
        }
        return array_unique(array_filter(array_map('trim', $currencies)));
    }

    /**
     * 
     * @return int
     */
    protected function _getTimeout()
    {
        $timeout = (int) Mage::getStoreConfig('currency/lomfixerio/timeout');
        if (empty($timeout))
        {
            $timeout = 10;
        }
        return $timeout;
    }

    /**
     * 
     * @return string
     */
    protected function _getMode()
    {
        $conf = Mage::getStoreConfig('currency/lomfixerio/mode', 0);
        switch($conf)
        {
            case 'freeaccount':
                $mode = 'freeaccount';
                break;
            case 'paid':
                $mode = 'paid';
            case 'free':
            default:
                $mode = 'free';
        }
        return $mode;
    }


    /**
     * 
     * @return string
     */
    protected function _getUrl()
    {
        if (is_null($this->_url))
        {
            $conf = $this->_getMode();
            switch($conf)
            {
                // free acount can only use the http traffic
                case 'freeaccount':
                    $this->_url = 'http://data.fixer.io/api/latest';
                    break;
                case 'paid':
                    $this->_url = 'https://data.fixer.io/api/latest';
                    break;
                case 'free':
                default:
                    $this->_url = 'https://api.fixer.io/latest';
            }
        }
        return $this->_url;
    }
}