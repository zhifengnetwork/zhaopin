<?php

/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * QueryRefund.php.
 *
 * Part of Overtrue\Wechat.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Overtrue\Wechat\Payment;

use Overtrue\Wechat\Http;
use Overtrue\Wechat\Utils\SignGenerator;
use Overtrue\Wechat\Utils\XML;

class QueryRefund
{
    /**
     * 退款查询接口链接：https://api.mch.weixin.qq.com/pay/refundquery.
     */
    const QUERYREFUND_URL = 'https://api.mch.weixin.qq.com/pay/refundquery';

    /**
     * 商户信息.
     *
     * @var Business
     */
    protected $business;

    /**
     * 退款查询订单必填项.
     * 商户订单号必选
     * @var array
     */
    protected static $required = array('out_refund_no');

    /**
     * 退款订单查询选填项.
     *
     * @var array
     */
    protected static $optional = array('transaction_id', 'out_refund_no', 'refund_id');

    /**
     * @var array
     */
    protected static $params  = null;
    protected static $allowParams = array();

    /**
     * 退款查询返回信息.
     *
     * @var array
     */
    protected $queryRefundInfo = null;

    public function __construct(Business $business = null)
    {
        if (!is_null($business)) {
            $this->setBusiness($business);
        }
        if (sizeof(static::$allowParams) == 0) {
            static::$allowParams = array_merge(static::$required, static::$optional);
        }
    }

    /**
     * 设置商户.
     *
     * @param Business $business
     *
     * @return $this
     *
     * @throws Exception
     */
    public function setBusiness(Business $business)
    {
        if (!is_null($business)) {
            try {
                $business->checkParams();
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
            $this->business = $business;
            $this->queryRefundInfo = null;
        }

        return $this;
    }

    /**
     * 获取商户.
     *
     * @return Business
     */
    public function getBusiness()
    {
        return $this->business;
    }

    /**
     * 获取查询退款结果.
     *
     * @return array
     *
     * @throws Exception
     */
    public function getResponse()
    {
        if (is_null($this->business)) {
            throw new Exception('Business is required');
        }

        static::$params['appid'] = $this->business->appid;
        static::$params['mch_id'] = $this->business->mch_id;
        $this->checkParams();
        $signGenerator = new SignGenerator(static::$params);
        $signGenerator->onSortAfter(function (SignGenerator $that) {
            $that->key = $this->business->mch_key;
        });
        static::$params['sign'] = $signGenerator->getResult();

        $request = XML::build(static::$params);

        pft_log('xiaomi/query_refund_pc','查询退款请求原始数据：' . $request);

        //设置Http使用的证书
        $options['sslcert_path'] = $this->business->getClientCert();
        $options['sslkey_path'] = $this->business->getClientKey();

        $http = new Http();
        $response = $http->request(static::QUERYREFUND_URL, Http::POST, $request, $options);

        if (empty($response)) {
            throw new Exception('Get QueryRefund Failure:');
        }
        $queryRefundOrder = XML::parse($response);

        pft_log('xiaomi/query_refund_pc','查询退款请求原始响应：' . json_encode($queryRefundOrder));

        if (isset($queryRefundOrder['return_code']) &&
            $queryRefundOrder['return_code'] === 'FAIL') {
            throw new Exception($queryRefundOrder['return_code'].': '.$queryRefundOrder['return_msg']);
        }

        //返回签名数据校验
        if (empty($queryRefundOrder) || empty($queryRefundOrder['sign'])) {
            throw new Exception('param sign is missing or empty');
        }
        $sign = $queryRefundOrder['sign'];
        unset($queryRefundOrder['sign']);
        $signGenerator = new SignGenerator($queryRefundOrder);
        $signGenerator->onSortAfter(function (SignGenerator $that) {
            $that->key = $this->business->mch_key;
        });
        if ($sign !== $signGenerator->getResult()) {
            throw new Exception('check sign error');
        }

        //返回结果判断
        if (isset($queryRefundOrder['result_code']) &&
            ($queryRefundOrder['result_code'] === 'FAIL')) {
            throw new Exception($queryRefundOrder['err_code'].': '.$queryRefundOrder['err_code_des']);
        }

        if (isset($queryRefundOrder['return_code']) &&
            $queryRefundOrder['return_code'] === 'FAIL') {
            throw new Exception($queryRefundOrder['return_code'].': '.$queryRefundOrder['return_msg']);
        }

        return $this->queryRefundInfo = $queryRefundOrder;
    }

    /**
     * 检测参数值是否有效.
     *
     * @throws Exception
     */
    public function checkParams()
    {
        foreach (static::$required as $paramName) {
            if (!array_key_exists($paramName, static::$params)) {
                throw new Exception(sprintf('"%s" is required', $paramName));
            }
        }

        if (!array_key_exists('nonce_str', static::$params)) {
            static::$params['nonce_str'] = md5(uniqid(microtime()));
        }

        if (!array_key_exists('op_user_id', static::$params)) {
            static::$params['op_user_id'] = $this->business->mch_id;
        }
    }

    public function __set($property, $value)
    {
        if (!in_array($property, static::$allowParams)) {
            throw new Exception(sprintf('"%s" is not required', $property));
        }

        return static::$params[$property] = $value;
    }
}
