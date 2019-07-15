<?php
/**
 * Created by PhpStorm.
 * User: junying.wei
 * Date: 17/11/05
 * Time: 下午2:09
 */
require_once 'ContentBuilder.php';

class AlipayFundTransOrderQueryContentBuilder extends ContentBuilder
{

    private $bizContentarr = array();

    private $bizContent = NULL;

    public function getBizContent()
    {
        if(!empty($this->bizContentarr)){
            $this->bizContent = json_encode($this->bizContentarr,JSON_UNESCAPED_UNICODE);
        }
        return $this->bizContent;
    }

            private $outBizNo;

    public function getOutBizNo()
    {
        return $this->outBizNo;
    }

    public function setOutBizNo($outBizNo)
    {
        $this->outBizNo = $outBizNo;
        $this->bizContentarr['out_biz_no'] = $outBizNo;
    }
            private $orderId;

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
        $this->bizContentarr['order_id'] = $orderId;
    }
        

}

?>