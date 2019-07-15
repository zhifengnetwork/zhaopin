<?php


namespace app\common\logic\wechat;

/**
 * 微信小程序第三方平台操作类
 */
class WxCommon
{
    protected $errorMsg = '微信默认错误信息';  //错误字符串信息
    protected $debug = false;   //是否开启调试
    protected $isLogFile = false; //是否记录错误到文本
    protected $logFile = "./wechat-debug.log"; //记录错误的文本路径

    public function getError() 
    {
        return $this->errorMsg;
    }
    
    protected function setError($error)
    {
        if (!is_string($error)) {
            $error = json_encode($error, JSON_UNESCAPED_UNICODE);
        }
        $this->errorMsg = $error;
    }
    
    public function isDedug()
    {
        return $this->debug;
    }
    
    public function logDebugFile($content)
    {
        if (!$this->isLogFile) {
            return;
        }
        if (!is_string($content)) {
            $encode = json_encode($content, JSON_UNESCAPED_UNICODE);
            $encode && $content = $encode;
        }
        file_put_contents($this->logFile, date('Y-m-d H:i:s').' -- '.$content."\n", FILE_APPEND);
    }
    
    /**
     * http请求
     * @param string $url
     * @param string $method
     * @param string|array $fields
     * @return string
     */
    protected function httpRequest($url, $method = 'GET', $fields = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $method = strtoupper($method);
        if ($method == 'GET' && !empty($fields)) {
            is_array($fields) && $fields = http_build_query($fields);
            $url = $url . (strpos($url,"?")===false ? "?" : "&") . $fields;
        }
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($method != 'GET') {
            $hadFile = false;
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($fields)) {
                if (is_array($fields)) {
                    /* 支持文件上传 */
                    if (class_exists('\CURLFile')) {
                        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
                        foreach ($fields as $key => $value) {
                            if ($this->isPostHasFile($value)) {
                                $fields[$key] = new \CURLFile(realpath(ltrim($value, '@')));
                                $hadFile = true;
                            }
                        }
                    } elseif (defined('CURLOPT_SAFE_UPLOAD')) {
                        foreach ($fields as $key => $value) {
                            if ($this->isPostHasFile($value)) {
                                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
                                $hadFile = true;
                                break;
                            }
                        }
                    }
                }
                $fields = (!$hadFile && is_array($fields)) ? http_build_query($fields) : $fields;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            }
        }

        /* 关闭https验证 */
        if ("https" == substr($url, 0, 5)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }
    
    protected function isPostHasFile($value)
    {
        if (is_string($value) && strpos($value, '@') === 0 && is_file(realpath(ltrim($value, '@')))) {
            return true;
        }
        return false;
    }

    /**
     * 请求并对结果进行初步检查
     * @param string $url 请求的url
     * @param string $method 请求的方式
     * @param array|string $fields 请求的值
     * @param bool $result_json 是否对结果进行json处理
     * @return bool|mixed|string
     */
    protected function requestAndCheck($url, $method = 'GET', $fields = [], $result_json = true)
    {
        $return = $this->httpRequest($url, $method, $fields);
        if ($return === false) {
            $this->setError("http请求出错！");
            return false;
        }

        $wxdata = json_decode($return, true);
        if (!$result_json && $wxdata === null) {
            return $return; //不能解码，如图片
        }

        $this->logDebugFile(['url' => $url,'fields' => $fields,'wxdata' => $wxdata]);
        if (isset($wxdata['errcode']) && $wxdata['errcode'] != 0) {
            $errmsg = WxCode::getItem($wxdata['errcode']);
            if ($this->debug) {
                $this->setError("微信错误码：{$wxdata['errcode']};<br>中文信息：$errmsg<br>原信息：{$wxdata['errmsg']}<br>链接：$url");
            } else {
                if ($errmsg === false) {
                    $errmsg = $wxdata['errmsg'];
                    if (($pos = strpos($errmsg, ' hint')) > 0) {
                        $errmsg = substr($errmsg, 0, $pos);
                    }
                }
                $this->setError("微信提醒：{$errmsg}[{$wxdata['errcode']}]");
            }
            return false;
        }

        if (strtoupper($method) === 'GET' && empty($wxdata)) {
            if ($this->debug) {
                $this->setError("微信http请求返回为空！请求链接：$url");
            } else {
                $this->setError("微信http请求返回为空！操作失败");
            }
            return false;
        }

        return $wxdata;
    }
    
    public function toJson($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param $url 小程序创建二维码url
     * @param string $method 请求方式
     * @param $data json数据
     * @return 二进制流数据
     */
    public function  requestMinAppQrcode($url, $method ='POST', $data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }else{
            // var_dump($tmpInfo);
            return $tmpInfo;
        }
    }
}