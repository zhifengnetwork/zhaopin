<?php
/**
 * Created by PhpStorm.
 * User: xudong.ding
 * Date: 16/5/19
 * Time: 下午2:09
 */
class AlipayFundTransToaccountTransfer
{
    private $tradeStatus;
    private $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function AlipayFundTransToaccountTransferResult($response)
    {
        $this->__construct($response);
    }

    public function setTradeStatus($tradeStatus)
    {
       $this->tradeStatus = $tradeStatus;
    }

    public function getTradeStatus()
    {
        return $this->tradeStatus;
    }

    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }
}

?>