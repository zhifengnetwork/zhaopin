<?php
/**
 * Created by PhpStorm.
 * User: junying.wei
 * Date: 17/11/05
 * Time: 下午2:09
 */
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'./../../AopSdk.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'./../entites/ApiParamModel.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'./../entites/ApiInfoModel.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'../model/result/AlipayFundTransOrderQueryResult.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'../model/builder/AlipayFundTransOrderQueryContentBuilder.php';
require dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'./../config/DefaultAlipayClientFactory.php';

$req = new AlipayFundTransOrderQueryContentBuilder();

$req->setOutBizNo($_POST['outBizNo']);
    $req->setOrderId($_POST['orderId']);
    
    $request = new AlipayFundTransOrderQueryRequest();
	$request->setBizContent ( $req->getBizContent() );
	
    $ext= new DefaultAlipayClientFactory(); 
	//因为是接口服务，使用exexcute方法获取到返回值
    $response = $ext->aopclientRequestExecute ( $request, NULL ,$req->getAppAuthToken() );
    echo json_encode($response);
?>