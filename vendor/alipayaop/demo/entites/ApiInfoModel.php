<?php
/**
 * Created by PhpStorm.
 * User: junying.wjy
 * Date: 17/10/31
 * Time: 下午2:09
 */
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'./ApiParamModel.php';

class ApiInfoModel
{
    
    /**
     * 接口请求
     */
    public static $INVOKE_TYPE_REQUEST = 1;
    /**
     * 页面跳转
     */
    public static $INVOKE_TYPE_REDIRECT = 2;
    
    private $apiName;
    private $apiZhName;
    /**
     * 字段说明
     * 1:接口请求
     * 2：页面跳转
     */
    private $invokeType;

    private $apiInParam;
    private $apiOutParam;
    private $bizContentarr = array();

    public function getBizContent()
    {
        if(!empty($this->bizContentarr)){
            $this->bizContent = json_encode($this->bizContentarr,JSON_UNESCAPED_UNICODE);
        }
        return $this->bizContent;
    }

    // public function getApiNameFirstLower(){
    //     return $this->apiName;
    // }

    // public function setApiNameFirstUpper(){
    //     $this->apiName = $apiName;
    //     $this->bizContentarr['apiNameFirstLower'] = $apiName;
    // }

    public function setInvokeType($invokeType) {
        $this->invokeType = $invokeType;
        $this->bizContentarr['invokeType'] = $invokeType;
    }
    public function getInvokeType() {
        return $this->invokeType;
    }

    public function getApiName() {
        return $this->apiName;
    }
    
    public function setApiName($apiName) {
        $this->apiName = $apiName;
        $this->bizContentarr['apiName'] = $apiName;
        $this->bizContentarr['apiNameFirstLower'] = str_replace(".","",$apiName);
    }

    public function getApiZhName() {
        return $this->apiZhName;
    }

    public function setApiZhName($apiZhName) {
        $this->apiZhName = $apiZhName;
        $this->bizContentarr['apiZhName'] = $apiZhName;
    }
    public function getApiInParam() {
        return $this->apiOutParam = new ApiParamModel();
    }

    public function setApiInParam($apiInParam) {
        $this->apiInParam = $apiInParam;
        $this->bizContentarr['apiInParam'] = $apiInParam;
    }

    public function getApiOutParam() {
        return $this->apiOutParam = new ApiParamModel();
    }

    public function setApiOutParam($apiOutParam) {
        $this->apiOutParam = $apiOutParam;
        $this->bizContentarr['apiOutParam'] = $apiOutParam;
    }
}

?>