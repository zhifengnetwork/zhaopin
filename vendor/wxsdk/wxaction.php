<?php

class JSSDK 
{
  private $appId;
  private $appSecret;

  public function __construct($appId, $appSecret) {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
  }

  public function getSignPackage() {
    $jsapiTicket = $this->getJsApiTicket();
    //$this->deleteMenu();
    //$this->createMenu();

    // 注意 URL 一定要动态获取，不能 hardcode.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    $timestamp = time();
    $nonceStr = $this->createNonceStr();

    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
      "appId"     => $this->appId,
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );
    return $signPackage; 
  }

  private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }

  private function getJsApiTicket() {
    // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
    // $data = json_decode($this->get_php_file("jsapi_ticket.php"));
    // if ($data->expire_time < time()) {
      $accessToken = $this->getAccessToken();
      // 如果是企业号用以下 URL 获取 ticket
      // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
      $res = json_decode($this->httpGet($url));
      $ticket = $res->ticket;
      // if ($ticket) {
      //   $data->expire_time = time() + 7000;
      //   $data->jsapi_ticket = $ticket;
      //   $this->set_php_file("jsapi_ticket.php", json_encode($data));
      // }
    // } else {
    //   $ticket = $data->jsapi_ticket;
    // }

    return $ticket;
  }

  private function getAccessToken() {
    // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
    // $data = json_decode($this->get_php_file("access_token.php"));
    // if ($data->expire_time < time()) {
      // 如果是企业号用以下URL获取access_token
      // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
      $urlObj = array();
      $urlObj['appid'] = $this->appId;
      $urlObj['secret'] = $this->appSecret;
      $urlObj['grant_type'] = 'client_credential';
      $queryStr = http_build_query($urlObj);
      $accessTokenUrl = 'https://api.weixin.qq.com/cgi-bin/token?' . $queryStr;
      $res = json_decode($this->httpGet($accessTokenUrl));
      $access_token = $res->access_token;
      
      // if ($access_token) {
        // $data->expire_time = time() + 7000;
        // $data->access_token = $access_token;
        // $this->set_php_file("access_token.php", json_encode($data));
      // }
    // } else {
    //   $access_token = $data->access_token;
    // }
    return $access_token;
  }

  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
    // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }

  private function get_php_file($filename) {
    return trim(substr(file_get_contents($filename), 15));
  }
  private function set_php_file($filename, $content) {
    $fp = fopen($filename, "w");
    fwrite($fp, "<?php exit();?>" . $content);
    fclose($fp);
  }
  /***
   * 下载图片文件
   */
  public function get_img($media_id,$foldername){
      if (!file_exists("./upload/picture/".$foldername)) {
        mkdir("./upload/picture/".$foldername, 0777, true);
      }
      $access_token = $this->getAccessToken();
      $url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$access_token."&media_id=".$media_id;
      $targetName = './upload/picture/'.$foldername.'/'.time().rand(1000,9999).'.jpg';
      $ch = curl_init($url); // 初始化
      $fp = fopen($targetName, 'wb'); // 打开写入
      curl_setopt($ch, CURLOPT_FILE, $fp); // 设置输出文件的位置，值是一个资源类型
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_exec($ch);
      fclose($fp);
      curl_close($ch);
      return $targetName;
 }
  
}

