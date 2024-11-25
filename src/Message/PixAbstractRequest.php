<?php

namespace Omnipay\Dummy\Message;

abstract class PixAbstractRequest extends \Omnipay\Common\Message\AbstractRequest{

    public function getIdTypeSelected()
    {
        return $this->getParameter('IdTypeSelected');
    }

    public function setIdTypeSelected($value)
    {
        return $this->setParameter('IdTypeSelected', $value);
    }

    public function getIdentificationId()
    {
        return $this->getParameter('IdentificationId');
    }

    public function setIdentificationId($value)
    {
        return $this->setParameter('IdentificationId', $value);
    }
}