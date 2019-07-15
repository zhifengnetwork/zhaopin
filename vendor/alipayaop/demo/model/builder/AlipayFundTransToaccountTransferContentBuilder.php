<?php
/**
 * Created by PhpStorm.
 * User: junying.wei
 * Date: 17/11/05
 * Time: 下午2:09
 */
require_once 'ContentBuilder.php';

class AlipayFundTransToaccountTransferContentBuilder extends ContentBuilder
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
            private $payeeType;

    public function getPayeeType()
    {
        return $this->payeeType;
    }

    public function setPayeeType($payeeType)
    {
        $this->payeeType = $payeeType;
        $this->bizContentarr['payee_type'] = $payeeType;
    }
            private $payeeAccount;

    public function getPayeeAccount()
    {
        return $this->payeeAccount;
    }

    public function setPayeeAccount($payeeAccount)
    {
        $this->payeeAccount = $payeeAccount;
        $this->bizContentarr['payee_account'] = $payeeAccount;
    }
            private $amount;

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;
        $this->bizContentarr['amount'] = $amount;
    }
            private $payerRealName;

    public function getPayerRealName()
    {
        return $this->payerRealName;
    }

    public function setPayerRealName($payerRealName)
    {
        $this->payerRealName = $payerRealName;
        $this->bizContentarr['payer_real_name'] = $payerRealName;
    }
            private $payerShowName;

    public function getPayerShowName()
    {
        return $this->payerShowName;
    }

    public function setPayerShowName($payerShowName)
    {
        $this->payerShowName = $payerShowName;
        $this->bizContentarr['payer_show_name'] = $payerShowName;
    }
            private $payeeRealName;

    public function getPayeeRealName()
    {
        return $this->payeeRealName;
    }

    public function setPayeeRealName($payeeRealName)
    {
        $this->payeeRealName = $payeeRealName;
        $this->bizContentarr['payee_real_name'] = $payeeRealName;
    }
            private $remark;

    public function getRemark()
    {
        return $this->remark;
    }

    public function setRemark($remark)
    {
        $this->remark = $remark;
        $this->bizContentarr['remark'] = $remark;
    }
            private $extParam;

    public function getExtParam()
    {
        return $this->extParam;
    }

    public function setExtParam($extParam)
    {
        $this->extParam = $extParam;
        $this->bizContentarr['ext_param'] = $extParam;
    }
        

}

?>