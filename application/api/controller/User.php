<?php
/**
 * 用户API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use think\Config;
use think\Db;

class User extends ApiBase
{
     public function __construct(){
        $this->weixin_config =  Config::get('pay_weixin');//取微获信配置
    }
    // 网页授权登录获取 OpendId
    public function GetOpenid()
    {
            //触发微信返回code码
            //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            //
            $baseUrl = urlencode('http://zfshop.zhifengwangluo.com'); //做成配置
            $url     = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址 // 跳转到微信授权页面 需要用户确认登录的页面
            // Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
            // exit();
            $this->ajaxReturn(['status' => 1 , 'msg'=>'微信授权登录地址','data' => $url]);
    }
   
    /**
     * 获取code 进行用户信息获取
     */
    public function get_code(){
            //上面获取到code后这里跳转回来
            $code  = input('code');
            if(!isset($code)){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'code不能为空！','data'=>'']);   
            }
            $data  = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid

            if(!isset($data['access_token'])){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'code必须要刷新！','data'=>'']);   
            }

            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息

            $data['city']        = $data2['city'];
            $data['nickname']    = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
            $data['sex']         = $data2['sex'];   
            $data['province']    = $data2['province']; 
            $data['head_pic']    = $data2['headimgurl']; 
            // $data['subscribe']   = $data2['subscribe']; 
            // $data['oauth_child'] = 'mp';
            // session('openid',$data['openid']);
            $data['oauth']       = 'weixin';
            if(isset($data2['unionid'])){
                $data['unionid'] = $data2['unionid'];
            }

            $this->wx_user($data);
    }
    /***
     * 绑定手机号
     */
    public function binding_mob(){
        $id     = input('id/d',0);
        $mobile = input('mobile','');
        
        if(!checkMobile($mobile)){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'手机号有问题！','data'=>'']);   
        }

        if(!$id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'参数错误！','data'=>'']);   
        }

        $wxuser = Db::name('user')->where(['id' => $id])->find();
         
        if(!$wxuser){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在请重新授权！','data'=>'']);    
        }

        $member = Db::name('member')->where(['openid' => $wxuser['openid']])->find();
        // 启动事务
        Db::startTrans();
        if($member){
        
            $res  = Db::name('member')->where(['openid' => $wxuser['openid']])->update(['mobile' => $mobile]);
            if($res === false){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在请重新授权！','data'=>'']);  
                Db::rollback();
            }
            $res2 = Db::name('user')->where(['openid' => $wxuser['openid']])->update(['uid' => $member['id'],'is_checked' => 1]);
            if($res2 === false){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在请重新授权！','data'=>'']);  
                Db::rollback(); 
            }
            $data['token']   = $this->create_token($member['id']);   
        }else{
            $insert = [
                'mobile' => $mobile,
                'openid' => $wxuser['openid'],
                'weixin' => $wxuser['wx_nickname'],
                'createtime' => time(),
            ];
           $memberid = Db::name('member')->insertGetId($insert);
           if(!$memberid){
               Db::rollback(); 
               $this->ajaxReturn(['status' => -2 , 'msg'=>'输入的手机号有误，请重新输入！','data'=>'']);  
           }
           $res1 = Db::name('user')->where(['openid' => $wxuser['openid']])->update(['uid' => $memberid,'is_checked' => 1]);

           if($res1 === false){
                Db::rollback(); 
                $this->ajaxReturn(['status' => -2 , 'msg'=>'输入的手机号有误，请重新输入！','data'=>'']);
           }
           $data['token']   = $this->create_token($memberid);   
        }
        // 提交事务
        Db::commit();
        $this->ajaxReturn(['status' => 1 , 'msg'=>'绑定成功！','data'=>$data]);   
    }
   
    
    public function wx_user($user_info){
        $wxres = Db::name('user')->where(['openid' => $user_info['openid']])->find();
       
        if($wxres){
        
            if($wxres['is_checked'] == 0){
                 $data = [
                     'id'          => $wxres['id'],
                     'token'       => '', 
                     'is_checked'  => 0,
                 ];
                 $this->ajaxReturn(['status' => 1 , 'msg'=>'授权成功！','data'=>$data]);   
            }else{
                //重写
                $member = Db::table("member")->where('id',$wxres['uid'])
                         ->field('id,mobile')
                         ->find();
                $data = [

                    'token'      => $this->create_token($member['id']),
                    'id'         => 0, 
                    'is_checked' => 1,
                ];
                $this->ajaxReturn(['status' => 1 , 'msg'=>'授权成功！','data'=>$data]);     
            }
                                      
        }else{
         
             $insert = [
                 'openid'         => $user_info['openid'],
                 'wx_nickname'    => $user_info['nickname'],
                 'sex'            => $user_info['sex'],
                 'wx_headimgurl'  => $user_info['head_pic'],
                 'province'       => $user_info['province'],
                 'city'           => $user_info['city'],
                 'create_time'    => time(),
             ];
             
            $wxid  = Db::name('user')->insertGetId($insert);
            $data = [
                'token'      => '',  
                'id'         => $wxid,   
                'is_checked' => 0,
            ]; 
            if($wxid){
                 $this->ajaxReturn(['status' => 1 , 'msg'=>'授权成功！','data' => $data]);
            }
            $this->ajaxReturn(['status' => -2 , 'msg'=>'授权失败2！','data' => '']);            
        }
    }


     /**
     *
     * 通过access_token openid 从工作平台获取UserInfo      
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    { 
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res  = curl_exec($ch);//运行curl，结果以jason形式返回 
        $data = json_decode($res,true);   
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        // //if(!isset($data['unionid'])){
        //     $wechat = new WechatUtil($this->weixin_config);
        //     $fan = $wechat->getFanInfo($openid);//获取基础支持的access_token
        //     if ($fan !== false) {
        //         $data['subscribe'] = $fan['subscribe'];
        //     }
        // //}
        return $data;
    }


    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址     
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"]       = $openid;
        $urlObj["lang"]         = 'zh_CN';        
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;                    
    }


    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    } 

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"]         = $this->weixin_config['app_id'];
        $urlObj["redirect_uri"]  = "$redirectUrl";
        $urlObj["response_type"] = "code";
        //$urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }


    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
    	//1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
    	//2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);       
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res  = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);         
        curl_close($ch);
        return $data;
    }


    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"]      = $this->weixin_config['app_id'];
        $urlObj["secret"]     = $this->weixin_config['app_secret'];
        $urlObj["code"]       = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }
    /*
     *  注册接口
     */
    public function register(){
        $mobile      = input('mobile');
        $email       = input('email');
        $password    = input('password');
        $code        = input('code');
        $uid         = input('uid',0);

        $member = Db::table('member')->where('mobile',$mobile)->value('id');
		
		if ( $member ) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'此手机号已注册，请直接登录！']);
        }
        if($uid){
            $uid = Db::table('member')->where('mobile',$mobile)->value('id');
            if(!$uid){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'邀请人账号不存在！']);
            }
        }

        $res = action('PhoneAuth/phoneAuth',[$mobile,$code]);
        if( $res === '-1' ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码已过期！','data'=>'']);
		}else if( !$res ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码错误！','data'=>'']);
		}

        if( strlen($password) < 6 ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'密码长度必须大于或6位！','data'=>'']);
        }
        
        $agenttime = 0;
        $agentid = 0;
        if($uid){
            $agentid = $uid;
            $agenttime = time();
        }
        $salt = create_salt();
        $password = md5( $salt . $password);
        
        $id = Db::table('member')->insertGetId(['mobile'=>$mobile,'uid'=>$uid,'agentid'=>$agentid,'agenttime'=>$agenttime,'isagent'=>1,'salt'=>$salt,'password'=>$password,'createtime'=>time()]);
        if(!$id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'注册失败，请重试！','data'=>'']);
        }
        // Db::table('mc_members')->insert(['uid'=>$id,'mobile'=>$mobile,'createtime'=>time(),'salt'=>$salt,'password'=>$password]);

        $data['token'] = $this->create_token($id);
        $data['mobile'] = $mobile;
        $data['id'] = $id;
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'注册成功！','data'=>$data]);
    }


    /*
     *  登录接口
     */
    public function login(){
        $type = input('type',1);
        if($type == 1){
           $user_info = $this->GetOpenid();//微信授权用户信息
        }else{
            $mobile   = input('mobile');
            $password = input('password');
            // $code     = input('code');
            
            // $res = action('PhoneAuth/phoneAuth',[$mobile,$code]);
            // if( $res === '-1' ){
            //     $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码已过期！','data'=>'']);
            // }else if( !$res ){
            //     $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码错误！','data'=>'']);
            // }
    
            $data = Db::table("member")->where('mobile',$mobile)
                ->field('id,password,mobile,salt')
                ->find();
    
            if(!$data){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'手机不存在或错误！']);
            }
           
    
            $password = md5( $data['salt'] . $password);
            
            if ($password != $data['password']) {
                $this->ajaxReturn(['status' => -2 , 'msg'=>'登录密码错误！']);
            }
    
            unset($data['password'],$data['salt']);
            //重写
            $data['token']    = $this->create_token($data['id']);
        
            $this->ajaxReturn(['status' => 1 , 'msg'=>'登录成功！','data'=>$data]);
        }
       
    }


    /*
     *  找回密码接口
     */
    public function zhaohuipwd(){
        $mobile    = input('mobile');
        $password1 = input('password1');
        $password2 = input('password2');
        $code      = input('code');
        
        $res = action('PhoneAuth/phoneAuth',[$mobile,$code]);
        if( $res === '-1' ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码已过期！','data'=>'']);
        }else if( !$res ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码错误！','data'=>'']);
        }

        $data = Db::table("member")->where('mobile',$mobile)
            ->field('id,password,mobile,salt')
            ->find();

        if(!$data){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'手机不存在或错误！']);
        }

        if($password1 != $password2){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'确认密码不相同！！']);
        }

        // if( strlen($password2) < 6 ){
        //     $this->ajaxReturn(['status' => -2 , 'msg'=>'密码长度必须大于或6位！','data'=>'']);
        // }
        $salt     = create_salt();
        $password = md5($salt . $password2);

        $update['salt']     = $salt;
        $update['password'] = $password;

        $res =  Db::name('member')->where(['mobile' => $mobile])->update($update);


 
        if ($res == false) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'修改密码失败']);
        }

        $member['token'] = $this->create_token($data['id']);
        $member['mobile'] = $mobile;
        $member['id'] = $data['id'];
    
        $this->ajaxReturn(['status' => 1 , 'msg'=>'修改密码成功！','data'=>$member]);
    }






    /**
     * 用户信息
     */
    public function userinfo(){
        $user_id = $this->get_user_id();
        if(!empty($user_id)){
            $data = Db::name("member")->alias('m')
                    ->join('user u','m.id=u.uid','LEFT')
                    ->field('m.id,m.mobile,m.realname,m.pwd,m.avatar,m.gender,m.birthyear,m.birthmonth,m.birthday,m.mailbox,u.wx_nickname,wx_headimgurl')
                    ->where(['m.id' => $user_id])
                    ->find();
            if(empty($data)){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'会员不存在！','data'=>'']);
            }    
            $data['is_pwd'] = !empty($data['pwd'])?1:0;

            $res = Db::table("user_address")->where(['user_id'=>$data['id']])
                    ->field('*')
                    ->find();
            $data['is_address'] = $res?1:0;
            unset($data['pwd'],$data['id']);
            if(empty($data['mobile'])){
                $this->ajaxReturn(['status' => -2 , 'msg'=>'未绑定手机！','data'=>$data]);
            }
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }
    
    public function reset_pwd(){//重置密码
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $password1   = input('password1');
        $password2   = input('password2');
        if($password1 != $password2){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'确认密码错误','data'=>'']);
        }
        $member = Db::name('member')->where(['id' => $user_id])->field('id,password,pwd,mobile,salt')->find();
        $type     = input('type');//1登录密码 2支付密码
        $code     = input('code');
        $mobile   = $member['mobile'];
        $res      = action('PhoneAuth/phoneAuth',[$mobile,$code]);
        if( $res === '-1' ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码已过期！','data'=>'']);
        }else if( !$res ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码错误！','data'=>'']);
        }
        if($type == 1 ){
            $stri = 'password';
        }else{
            $stri = 'pwd';
        }
            $password = md5($member['salt'] . $password2);
        if ($password == $member[$stri]){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'新密码和旧密码不能相同']);
        }else{
            $data = array($stri=>$password);
            $update = Db::name('member')->where('id',$user_id)->data($data)->update();
            if($update){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'修改成功']);
            }else{
                $this->ajaxReturn(['status' => -2 , 'msg'=>'修改失败']);
            }
        }
        
    }
    /***
     * 邮箱编辑
     */
    public function reset_mailbox(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        $mailbox   = input('mailbox');
        $data = [
            'mailbox' => $mailbox
        ];
        $update = Db::name('member')->where(['id' => $user_id])->data($data)->update();
        if($update){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'修改成功']);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'修改失败']);
        }


    }

    /**
     * 头像上传
     */
      public function update_head_pic(){

            $user_id  = $this->get_user_id();
            $head_img = input('head_img');
            if(empty($head_img)){
                $this->ajaxReturn(['code'=>0,'msg'=>'上传图片不能为空','data'=>'']);
            }
            $saveName       = request()->time().rand(0,99999) . '.png';
            $base64_string  = explode(',', $head_img);
            $imgs           = base64_decode($base64_string[1]);
            //生成文件夹
            $names = "head";
            $name  = "head/" .date('Ymd',time());
            if (!file_exists(ROOT_PATH .Config('c_pub.img').$names)){ 
                mkdir(ROOT_PATH .Config('c_pub.img').$names,0777,true);
            }
            //保存图片到本地
            $r   = file_put_contents(ROOT_PATH .Config('c_pub.img').$name.$saveName,$imgs);
            if(!$r){
                $this->ajaxReturn(['status'=>-2,'msg'=>'上传出错','data' =>'']);
            }
            Db::name('member')->where(['id' => $user_id])->update(['avatar' => SITE_URL.'/upload/images/'.$name.$saveName]);

            $this->ajaxReturn(['status'=>1,'msg'=>'修改成功','data'=>SITE_URL.'/upload/images/'.$name.$saveName]);
           
    }

    /**
     * +---------------------------------
     * 地址组件原数据
     * +---------------------------------
    */
    public function get_address(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在','data'=>'']);
        }
        //第一种方法
        //$province_list  =  Db::name('region')->field('*')->where(['area_type' => 1])->column('area_id,area_name');
        // $city_list      =  Db::name('region')->field('*')->where(['area_type' => 2])->column('area_id,area_name');
        // $county_list    =  Db::name('region')->field('*')->where(['area_type' => 3])->column('area_id,area_name');
        // $data = [
        //     'province_list' => $province_list,
        //     'city_list'     => $city_list,
        //     'county_list'   => $county_list,
        // ];
        //第二种方法
        $list  = Db::name('region')->field('*')->select();
        foreach($list as $v){
           if($v['area_type'] == 1){
              $address_list['province_list'][$v['code'] * 10000]=  $v['area_name'];
           }
           if($v['area_type'] == 2){
              $address_list['city_list'][$v['code'] *100]=  $v['area_name'];
           }
           if($v['area_type'] == 3){
              $address_list['county_list'][$v['code']]=  $v['area_name'];
           }
        }
        $this->ajaxReturn(['status'=>1,'msg'=>'获取地址成功','data'=>$address_list]);
    }




    /**
     * +---------------------------------
     * 地址管理列表
     * +---------------------------------
    */
    public function address_list(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2, 'msg'=>'用户不存在','data'=>'']);
        }
        $data        =  Db::name('user_address')->where('user_id', $user_id)->select();
        $region_list =  Db::name('region')->field('*')->column('area_id,area_name');
        foreach ($data as &$v) {
            $v['province'] = $region_list[$v['province']];
            $v['city']     = $region_list[$v['city']];
            $district      = Db::name('region')->where(['area_id' => $v['district']])->value('code');
            $v['code']     = $district;
            $v['district'] = $region_list[$v['district']];
        
            if($v['twon'] == 0){
                $v['twon']     = '';
            }else{
                $v['twon'] = $region_list[$v['twon']];
            }
            
        }
        unset($v);
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }

    /**
     * +---------------------------------
     * 添加地址
     * +---------------------------------
    */
    public function add_address()
    {
            $user_id   = $this->get_user_id();
            if(!$user_id){
                $this->ajaxReturn(['status' => -2, 'msg'=>'用户不存在','data'=>'']);
            }

            $consignee = input('consignee');
            $longitude = input('lng');
            $latitude = input('lat');
            $address_district = input('address_district');
            $address_twon = input('address_twon');
            $address = input('address');
            $mobile = input('mobile');
            $is_default = input('is_default');

            $address = $address_twon . $address;

            $post_data['consignee'] = $consignee;
            $post_data['longitude'] = $longitude;
            $post_data['latitude'] = $latitude;
            $post_data['mobile'] = $mobile;
            $post_data['is_default'] = $is_default;
            
            if($latitude && $longitude){
                $url = "http://api.map.baidu.com/geocoder/v2/?ak=gOuAqF169G6cDdxGnMmB7kBgYGLj3G1j&callback=renderReverse&location={$latitude},{$longitude}&output=json";
                $res = request_curl($url);
                if($res){
                    $res = explode('Reverse(',$res)[1];
                    $res = substr($res,0,strlen($res)-1);
                    $res = json_decode($res,true)['result']['addressComponent'];

                    $post_data['province'] = Db::table('region')->where('area_name',$res['province'])->value('area_id');
                    $post_data['city'] = Db::table('region')->where('area_name',$res['city'])->value('area_id');
                    $post_data['district'] = Db::table('region')->where('area_name',$res['district'])->value('area_id');
                    if($res['town']){
                        $post_data['town'] = Db::table('region')->where('area_name',$res['town'])->value('area_id');
                    }
                }
            }
            $post_data['address'] = $address;
            
            $addressM  = Model('UserAddr');
            $return    = $addressM->add_address($user_id, 0, $post_data);
            $this->ajaxReturn($return);
    }

    

    /**
     * +---------------------------------
     * 地址编辑
     * +---------------------------------
    */
    public function edit_address()
    {
        $user_id = $this->get_user_id();
        $id      = input('address_id');
        $address = Db::name('user_address')->where(array('address_id' => $id, 'user_id' => $user_id))->find();
        if(!$address){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'地址id不存在！','data'=>'']);
        }
        
        $consignee = input('consignee');
        $longitude = input('lng');
        $latitude = input('lat');
        $address_district = input('address_district');
        $address_twon = input('address_twon');
        $address = input('address');
        $mobile = input('mobile');
        $is_default = input('is_default');

        $address = $address_twon . $address;

        $post_data['consignee'] = $consignee;
        $post_data['longitude'] = $longitude;
        $post_data['latitude'] = $latitude;
        $post_data['mobile'] = $mobile;
        $post_data['is_default'] = $is_default;
        
        if($latitude && $longitude){
            $url = "http://api.map.baidu.com/geocoder/v2/?ak=gOuAqF169G6cDdxGnMmB7kBgYGLj3G1j&callback=renderReverse&location={$latitude},{$longitude}&output=json";
            $res = request_curl($url);
            if($res){
                $res = explode('Reverse(',$res)[1];
                $res = substr($res,0,strlen($res)-1);
                $res = json_decode($res,true)['result']['addressComponent'];

                $post_data['province'] = Db::table('region')->where('area_name',$res['province'])->value('area_id');
                $post_data['city'] = Db::table('region')->where('area_name',$res['city'])->value('area_id');
                $post_data['district'] = Db::table('region')->where('area_name',$res['district'])->value('area_id');
                if($res['town']){
                    $post_data['town'] = Db::table('region')->where('area_name',$res['town'])->value('area_id');
                }
            }
        }

        $post_data['address'] = $address;



        $addressM  = Model('UserAddr');
        $return    = $addressM->add_address($user_id, $id, $post_data);
        $this->ajaxReturn($return);
    }



    /**
     * +---------------------------------
     * 删除地址
     * +---------------------------------
    */
    public function del_address()
    {
        $user_id = $this->get_user_id();
        $id      = input('address_id/d',86);
        $address = Db::name('user_address')->where(["address_id" => $id])->find();
        if(!$address){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'地址id不存在！','data'=>'']);
        }
        $row =  Db::name('user_address')->where(array('user_id' => $user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = Db::name('user_address')->where(["user_id" => $user_id])->find();
            $address2 && Db::name('user_address')->where(["address_id" => $address2['address_id']])->update(array('is_default' => 1));
        }
        if ($row !== false)
            $this->ajaxReturn(['status' => 1 , 'msg'=>'删除地址成功','data'=>$row]);
        else
            $this->ajaxReturn(['status' => -2 , 'msg'=>'删除失败','data'=>'']);
    }


   /**
     * +---------------------------------
     * 验证支付密码
     * +---------------------------------
    */
    public function check_pwd()
    {
        $user_id    = $this->get_user_id();
        $pwd        = input('pwd/d');
        $member     = Db::name('member')->where(["id" => $user_id])->find();
        if(!$member){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'用户不存在！','data'=>'']);
        }
        $password = md5($member['salt'] . $pwd);
        if($member['pwd'] !== $password){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付密码错误！','data'=>'']);
        }
        $this->ajaxReturn(['status' => 1 , 'msg'=>'密码正确！','data'=>'']);
    }

    /**
     * +---------------------------------
     * 修改生日||昵称||性别
     * +---------------------------------
    */

    public function set_reabir()
    {
        $user_id    = $this->get_user_id();
        $birthyear  = input('birthyear');
        $birthmonth = input('birthmonth');
        $birthday   = input('birthday');
        $realname   = input('realname');
        $gender     = input('gender',0);
        $type       = input('type',1);
        if($type == 1){
            if(empty($realname)){
                $this->ajaxReturn(['code'=>0,'msg'=>'昵称不能为空','data'=>'']);
            }
            $update['realname'] = $realname;
        }else if($type == 2){
            $update['birthyear']  = $birthyear;
            $update['birthmonth'] = $birthmonth;
            $update['birthday']   = $birthday;
        }else{
            $update['gender']     = $gender;
        }
        $member     = Db::name('member')->where(["id" => $user_id])->update($update);
        if($member !== false){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'修改成功','data'=>'']);
        }
        $this->ajaxReturn(['status' => -2 , 'msg'=>'修改失败','data'=>'']);
    }


    /***
     * 手机号换绑
     */

    public function change_mobile(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2, 'msg'=>'用户不存在','data'=>'']);
        } 
        $new_mobile = input('mobile');
        $code       = input('code');

        $member = Db::table('member')->where(['id' => $user_id])->find();

        if($member['mobile'] == $new_mobile){
             $this->ajaxReturn(['status' => -2 , 'msg'=>'手机号不能相同！','data'=>'']);
        }
       
        $res        = action('PhoneAuth/phoneAuth',[$new_mobile,$code]);
        if( $res === '-1'){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码已过期！','data'=>'']);
        }else if( !$res ){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码错误！','data'=>'']);
        }
      
        $res = Db::table('member')->where(['id' => $user_id])->update(['mobile' => $new_mobile]);

        if($res !== false){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'换绑成功','data'=>'']);
        }else{
            $this->ajaxReturn(['status' => -2 , 'msg'=>'换绑失败','data'=>'']);
        }

    }

   
   /**
     * +---------------------------------
     * 设置支付宝账户
     * +---------------------------------
    */
    public function set_alipay(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2, 'msg'=>'用户不存在','data'=>'']);
        } 
        $alipay_name   = input('alipay_name','');
        $alipay_number = input('alipay_number','');
        if(empty($alipay_name) || strlen($alipay_name) > 20){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付宝真实姓名有误！','data'=>'']);
        }

        if(empty($alipay_number) || strlen($alipay_number) > 20){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'支付宝账号！','data'=>'']);
        }

        $res = Db::table('member')->where(['id' => $user_id])->update(['alipay' => $alipay_number,'alipay_name' => $alipay_name]);

        if($res !== false){
            $this->ajaxReturn(['status' => 1 , 'msg'=>'修改成功','data'=>'']);
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'修改失败','data'=>'']);
    }


    



}
