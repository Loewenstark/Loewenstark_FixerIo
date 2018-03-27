<?php

class Loewenstark_FixerIo_Model_System_Config_Source_Mode
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'free',
                'label' =>  Mage::helper('lomfixerio')->__('Free API (deprecated)')
            ),
            array(
                'value' => 'freeaccount',
                'label' => Mage::helper('lomfixerio')->__('Free Account (API-Key required)')
            ),
            array(
                'value' => 'paid',
                'label' => Mage::helper('lomfixerio')->__('Paid Account (API-Key required)')
            ),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $result = array();
        foreach ($this->toOptionArray() as $_opt)
        {
            $result[$_opt['value']] = $_opt['label'];
        }
        return $result;
    }
}