<?php
/**
 * 用户API
 */

namespace app\api\controller;

use app\common\model\Advertisement;
use app\common\model\Category;
use app\common\model\Member;
use app\common\model\Region;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use think\Config;
use think\Db;

class User extends ApiBase
{
    public function __construct()
    {
        $this->weixin_config = Config::get('pay_weixin');//取微获信配置
    }

    // 网页授权登录获取 OpendId
    public function GetOpenid()
    {
        //触发微信返回code码
        //$baseUrl = urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
        //
        $baseUrl = urlencode('http://zfshop.zhifengwangluo.com'); //做成配置
        $url = $this->__CreateOauthUrlForCode($baseUrl); // 获取 code地址 // 跳转到微信授权页面 需要用户确认登录的页面
        // Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
        // exit();
        $this->ajaxReturn(['status' => 1, 'msg' => '微信授权登录地址', 'data' => $url]);
    }

    /**
     * 获取code 进行用户信息获取
     */
    public function get_code()
    {
        //上面获取到code后这里跳转回来
        $code = input('code');
        if (!isset($code)) {
            $this->ajaxReturn(['status' => -2, 'msg' => 'code不能为空！', 'data' => '']);
        }
        $data = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid

        if (!isset($data['access_token'])) {
            $this->ajaxReturn(['status' => -2, 'msg' => 'code必须要刷新！', 'data' => '']);
        }

        $data2 = $this->GetUserInfo($data['access_token'], $data['openid']);//获取微信用户信息

        $data['city'] = $data2['city'];
        $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
        $data['sex'] = $data2['sex'];
        $data['province'] = $data2['province'];
        $data['head_pic'] = $data2['headimgurl'];
        // $data['subscribe']   = $data2['subscribe'];
        // $data['oauth_child'] = 'mp';
        // session('openid',$data['openid']);
        $data['oauth'] = 'weixin';
        if (isset($data2['unionid'])) {
            $data['unionid'] = $data2['unionid'];
        }

        $this->wx_user($data);
    }

    /***
     * 绑定手机号
     */
    public function binding_mob()
    {
        $id = input('id/d', 0);
        $mobile = input('mobile', '');

        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号有问题！', 'data' => '']);
        }

        if (!$id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '参数错误！', 'data' => '']);
        }

        $wxuser = Db::name('user')->where(['id' => $id])->find();

        if (!$wxuser) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在请重新授权！', 'data' => '']);
        }

        $member = Db::name('member')->where(['openid' => $wxuser['openid']])->find();
        // 启动事务
        Db::startTrans();
        if ($member) {

            $res = Db::name('member')->where(['openid' => $wxuser['openid']])->update(['mobile' => $mobile]);
            if ($res === false) {
                $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在请重新授权！', 'data' => '']);
                Db::rollback();
            }
            $res2 = Db::name('user')->where(['openid' => $wxuser['openid']])->update(['uid' => $member['id'], 'is_checked' => 1]);
            if ($res2 === false) {
                $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在请重新授权！', 'data' => '']);
                Db::rollback();
            }
            $data['token'] = $this->create_token($member['id']);
        } else {
            $insert = [
                'mobile' => $mobile,
                'openid' => $wxuser['openid'],
                'weixin' => $wxuser['wx_nickname'],
                'createtime' => time(),
            ];
            $memberid = Db::name('member')->insertGetId($insert);
            if (!$memberid) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '输入的手机号有误，请重新输入！', 'data' => '']);
            }
            $res1 = Db::name('user')->where(['openid' => $wxuser['openid']])->update(['uid' => $memberid, 'is_checked' => 1]);

            if ($res1 === false) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '输入的手机号有误，请重新输入！', 'data' => '']);
            }
            $data['token'] = $this->create_token($memberid);
        }
        // 提交事务
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功！', 'data' => $data]);
    }


    public function wx_user($user_info)
    {
        $wxres = Db::name('user')->where(['openid' => $user_info['openid']])->find();

        if ($wxres) {

            if ($wxres['is_checked'] == 0) {
                $data = [
                    'id' => $wxres['id'],
                    'token' => '',
                    'is_checked' => 0,
                ];
                $this->ajaxReturn(['status' => 1, 'msg' => '授权成功！', 'data' => $data]);
            } else {
                //重写
                $member = Db::table("member")->where('id', $wxres['uid'])
                    ->field('id,mobile')
                    ->find();
                $data = [

                    'token' => $this->create_token($member['id']),
                    'id' => 0,
                    'is_checked' => 1,
                ];
                $this->ajaxReturn(['status' => 1, 'msg' => '授权成功！', 'data' => $data]);
            }

        } else {

            $insert = [
                'openid' => $user_info['openid'],
                'wx_nickname' => $user_info['nickname'],
                'sex' => $user_info['sex'],
                'wx_headimgurl' => $user_info['head_pic'],
                'province' => $user_info['province'],
                'city' => $user_info['city'],
                'create_time' => time(),
            ];

            $wxid = Db::name('user')->insertGetId($insert);
            $data = [
                'token' => '',
                'id' => $wxid,
                'is_checked' => 0,
            ];
            if ($wxid) {
                $this->ajaxReturn(['status' => 1, 'msg' => '授权成功！', 'data' => $data]);
            }
            $this->ajaxReturn(['status' => -2, 'msg' => '授权失败2！', 'data' => '']);
        }
    }


    /**
     *
     * 通过access_token openid 从工作平台获取UserInfo
     * @return openid
     */
    public function GetUserInfo($access_token, $openid)
    {
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token, $openid);
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回
        $data = json_decode($res, true);
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
    private function __CreateOauthUrlForUserinfo($access_token, $openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?" . $bizString;
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
        foreach ($urlObj as $k => $v) {
            if ($k != "sign") {
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
    private function get_url()
    {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
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
        $urlObj["appid"] = $this->weixin_config['app_id'];
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        //$urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE" . "#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?" . $bizString;
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回
        $data = json_decode($res, true);
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
        $urlObj["appid"] = $this->weixin_config['app_id'];
        $urlObj["secret"] = $this->weixin_config['app_secret'];
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?" . $bizString;
    }


    // 发送注册验证码
    public function register_code()
    {
        $mobile = input('mobile');
        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        if (Member::get(['mobile' => $mobile])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '此手机号已注册，请直接登录！']);
        }
        $res = Db::name('phone_auth')->field('exprie_time')->where('mobile', '=', $mobile)->order('id DESC')->find();
        if ($res['exprie_time'] > time()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请求频繁请稍后重试！']);
        }

        $code = mt_rand(111111, 999999);

        $data['mobile'] = $mobile;
        $data['auth_code'] = $code;
        $data['start_time'] = time();
        $data['exprie_time'] = time() + 60;

        $res = Db::table('phone_auth')->insert($data);
        if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '发送失败，请重试！']);
        }

        $ret = send_zhangjun($mobile, $code);
        if ($ret['message'] == 'ok') {
            $this->ajaxReturn(['status' => 1, 'msg' => '发送成功！']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '发送失败，请重试！']);
    }

    /*
     *  注册接口
     */
    public function register()
    {
        $register=input('register',0);

        $type = input('type');
        if (!key_exists($type, Member::$_registerType)) {// 1公司，2第三方,3个人
            $this->ajaxReturn(['status' => -2, 'msg' => '类型选择错误']);
        }
//        if ($member->mobile) {
//            $this->ajaxReturn(['status' => -2, 'msg' => '当前账号已注册，请直接登录！']);
//        }
        $mobile = input('mobile');
        $pwd = input('pwd');
        $pwd2 = input('pwd2');
        $code = input('code');
        if ($pwd != $pwd2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '两次密码输入不一样！请重新输入！']);
        }
        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        if (Member::get(['mobile' => $mobile])) {
            $this->ajaxReturn(['status' => -1, 'msg' => '此手机号已注册，请直接登录！']);
        }

        $res = action('PhoneAuth/phoneAuth', [$mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }
        if($register){//微信登陆绑定手机
            $user_id = $this->get_user_id();
            if (!$user_id || !($member = Member::get($user_id))) {
                $this->ajaxReturn(['status' => -2, 'msg' => '用户错误']);
            }
            $data['salt'] = create_salt();
            $data['password'] = md5($data['salt'] . $pwd);
            $data['regtype'] = $type;
            $data['mobile'] = $mobile;
            $data['createtime'] = time();
            $res=Db::name('member')->where(['id'=>$user_id])->update($data);
            if(!$res){
                $this->ajaxReturn(['status' => -2, 'msg' => '注册失败，请重试！', 'data' => '']);
            }
            $data_user['token'] = $this->create_token($user_id);
            $data_user['mobile'] = $mobile;
            $data_user['id'] = $user_id;
            $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！', 'data' => $data_user]);
        }else{
            $data['salt'] = create_salt();
            $data['password'] = md5($data['salt'] . $pwd);
            $data['regtype'] = $type;
            $data['mobile'] = $mobile;
            $data['createtime'] = time();
            $id = Db::name('member')->insertGetId($data);
            if (!$id) {
                $this->ajaxReturn(['status' => -2, 'msg' => '注册失败，请重试！', 'data' => '']);
            }
            $data_user['token'] = $this->create_token($id);
            $data_user['mobile'] = $mobile;
            $data_user['id'] = $id;
            $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！', 'data' => $data_user]);
        }

    }
    /*
     *  微信注册开始
     */
    public function weixin_register()
    {
        $user_id = $this->get_user_id();
        if (!$user_id || !($member = Member::get($user_id))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户错误']);
        }
        $type = input('type');
        if (!key_exists($type, Member::$_registerType)) {// 1公司，2第三方,3个人
            $this->ajaxReturn(['status' => -2, 'msg' => '类型选择错误']);
        }
        $mobile = input('mobile');
        $pwd = input('pwd');
        $pwd2 = input('pwd2');
        $code = input('code');
        if ($pwd != $pwd2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '两次密码输入不一样！请重新输入！']);
        }
        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        if (Member::get(['mobile' => $mobile])) {
            $this->ajaxReturn(['status' => -1, 'msg' => '此手机号已注册，请直接登录！']);
        }

        $res = action('PhoneAuth/phoneAuth', [$mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }
        $data['salt'] = create_salt();
        $data['password'] = md5($data['salt'] . $pwd);
        $data['regtype'] = $type;
        $data['mobile'] = $mobile;
        $data['createtime'] = time();
        $res=Db::name('member')->where(['id'=>$user_id])->update($data);
        if(!$res){
            $this->ajaxReturn(['status' => -2, 'msg' => '注册失败，请重试！', 'data' => '']);
        }
        $data_user['token'] = $this->create_token($user_id);
        $data_user['mobile'] = $mobile;
        $data_user['id'] = $user_id;
        $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！', 'data' => $data_user]);
    }
    // 下一步
    public function next()
    {
        $user_id = $this->get_user_id();
        if (!$user_id || !($member = Db::name('member')->where(['id' => $user_id])->find())||!in_array($member['regtype'],[1,2,3])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户错误']);
        }
        if (!$member['mobile']) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请先注册手机号']);
        }
        $data = input();
        Db::startTrans();
        if ($member['regtype'] == 1 || $member['regtype'] == 2) {// 公司，第三方
            $validate = $this->validate($data, 'User.company');
            $co=Db::name('company')->where(['user_id'=>$user_id])->find();
            if($co){
                $this->ajaxReturn(['status' => -1, 'msg' => '该账户已注册，请登陆']);
            }
            if (true !== $validate) {
                return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
            }

            $data['c_img'] = str_replace(SITE_URL, '', $data['c_img']);
            $images = [];
            if(isset($data['image'])&&!$data['image']){
                foreach ($data['image'] as $k => $image) {
                    $images[] = [
                        'path' => str_replace(SITE_URL, '', $image),
                        'title' => isset($data['title'][$k]) ? $data['title'][$k] : ''
                    ];
                }
            }
            $data['images'] = json_encode($images,JSON_UNESCAPED_UNICODE);

            unset($data['token'], $data['title'], $data['image']);
            $data['city'] = Region::getParentId($data['district']) ?: 0;
            $data['city'] > 0 && $data['province'] = Region::getParentId($data['city']) ?: 0;
            $data['user_id'] = $user_id;
            $data['add_time'] = time();
            if (!Db::name('company')->insert($data)) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '注册失败！']);
            }
        } elseif ($member['regtype'] == 3) {// 个人

            $validate = $this->validate($data, 'User.person');
            if (true !== $validate) {
                return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
            }
            $pe=Db::name('person')->where(['user_id'=>$user_id])->find();
            if($pe){
                $this->ajaxReturn(['status' => -1, 'msg' => '该账户已注册，请登陆']);
            }
            $data['idcard_back'] = str_replace(SITE_URL, '', $data['idcard_back']);
            $data['idcard_front'] = str_replace(SITE_URL, '', $data['idcard_front']);
            $images = [];
            if(isset($data['image'])&&!$data['image']){
                foreach ($data['image'] as $k => $image) {
                    $images[] = [
                        'path' => str_replace(SITE_URL, '', $image),
                        'title' => isset($data['title'][$k]) ? $data['title'][$k] : ''
                    ];
                }
            }

            $data['images'] = json_encode($images,JSON_UNESCAPED_UNICODE);

            $data['gender'] = $data['gender'] == 2 ? 'female' : 'male';
            $data['birth'] = implode('-', [$data['birth_year'], $data['birth_month'], $data['birth_day']]);
            $data['graduate_time'] = implode('-', [$data['graduate_year'], $data['graduate_month'], $data['graduate_day']]);
            $data['work_age'] = date('Y') - $data['graduate_year'];
            $data['user_id'] = $user_id;
            $data['create_time'] = time();
            unset($data['token'], $data['title'], $data['image']);
            unset($data['birth_year']);
            unset($data['birth_month']);
            unset($data['birth_day']);
            unset($data['graduate_year']);
            unset($data['graduate_month']);
            unset($data['graduate_day']);
            if (!Db::name('person')->insert($data)) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '注册失败！']);
            }
        }
        $res = Db::name('audit')->insert([
            'type'=>$member['regtype'],
            'content_id'=>$user_id,
            'data'=>json_encode($data,JSON_UNESCAPED_UNICODE),
            'create_time'=>time()
        ]);
        if(!$res){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '注册失败！']);
        }
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '注册成功！']);
    }

    public function upload_file()
    {
        if ($file = request()->file('file')) {
            $dir = UPLOAD_PATH . DS;
            if (!file_exists(ROOT_PATH . $dir)) mkdir(ROOT_PATH . $dir, 0777);
            if ($info = $file->validate(['size' => 2000000, 'ext' => 'jpg,png,jpeg'])->move(ROOT_PATH . $dir)) {
                $this->ajaxReturn([
                    'status' => 1,
                    'msg' => '上传成功',
                    'data' => SITE_URL . DS . $dir . $info->getSaveName()
                ]);
            } else {
                $this->ajaxReturn(['status' => -2, 'msg' => $file->getError(), 'data' => $file->getInfo()]);
            }
        }
        $this->ajaxReturn(['status' => 2, 'msg' => '上传文件不存在']);
    }

    // 游客首页
    public function visit()
    {
        $adList = Advertisement::getList();
        // 热招
        $list = Db::name('recruit')
            ->field('r.id,c.logo,r.title,r.salary,r.work_age,r.require_cert,m.regtype')
            ->alias('r')
            ->join('company c', 'c.id=r.company_id', 'LEFT')
            ->join('member m', 'c.user_id=m.id', 'LEFT')
            ->limit(6)
            ->where(['r.is_hot' => 1, 'r.status' => 1])->select();
        foreach ($list as $key=>$value){
            if($list[$key]['logo']){
                $list[$key]['logo']=SITE_URL.$list[$key]['logo'];
            }
        }
        // 找活
        $person = Db::name('person')
            ->alias('p')
            ->field('p.id,p.avatar,p.name,p.job_type,p.work_age,p.images')
            ->where(['p.reserve_c' => 0,'status'=>1])
            ->limit(6)
            ->select();
        foreach ($person as &$v) {
            $v['job_type'] = Category::getNameById($v['job_type']) ?: '';
            $v['images'] = $v['images']!='[]' ? 1 : 0;
            if($v['avatar']){
                $v['avatar']=SITE_URL.$v['avatar'];
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功！',
            'data' => ['ad' => $adList, 'recruit' => $list, 'person' => $person]
        ]);
    }

    // 首页
    public function user_visit()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $adList = Advertisement::getList();
        $member=Db::name('member')->where(['id'=>$user_id])->find();
        $regtype=$member['regtype'];
        $kw=input('kw');
        if($regtype==1||$regtype==2){
            // 热招

            if($regtype==1){
                $rt=2;
            }else{
                $rt=1;
            }
            $where=[];
            if($kw){
                $where['r.title'] = ['like', '%' . $kw . '%'];
            }
            $list = Db::name('recruit')
                ->field('r.id,c.logo,r.title,r.salary,r.work_age,r.require_cert,m.regtype')
                ->alias('r')
                ->join('company c', 'c.id=r.company_id', 'LEFT')
                ->join('member m', 'c.user_id=m.id', 'LEFT')
                ->limit(6)
                ->where($where)
                ->where(['r.is_hot' => 1, 'r.status' => 1,'m.regtype'=>$rt])->select();
            foreach ($list as $key=>$value){
                if($list[$key]['logo']){
                    $list[$key]['logo']=SITE_URL.$list[$key]['logo'];
                }
            }
            $company_id = Db::name('company')->where(['user_id'=>$user_id])->value('id');
            $job_type=input('job_type');
            $where=['p.status'=>1,'p.reserve_c' => [['=', 0], ['=', $company_id], 'or']];
            if($job_type){
                $where['p.job_type']=$job_type;
            }
            $where['p.reserve_c']=0;
            $where['p.status']=1;
            // 找活
            $person = Db::name('person')
                ->alias('p')
                ->field('p.id,p.avatar,p.name,p.job_type,p.work_age,p.images')
                ->where($where)
                ->limit(6)
                ->select();
            foreach ($person as &$v) {
                $v['job_type'] = Category::getNameById($v['job_type']) ?: '';
                $v['images'] = $v['images']!='[]' ? 1 : 0;
                if($v['avatar']){
                    $v['avatar']=SITE_URL.$v['avatar'];
                }
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '请求成功！',
                'data' => ['ad' => $adList, 'recruit' => $list, 'person' => $person]
            ]);
        }elseif ($regtype==3){
            $where=[];
            $province=input('province');
            if($province){
                $where['c.province']=$province;
            }
            $city=input('city');
            if($city){
                $where['c.city']=$city;
            }
            $district=input('district');
            if($district){
                $where['c.district']=$district;
            }
            if($kw){
                $where['r.title'] = ['like', '%' . $kw . '%'];
            }
            $list = Db::name('recruit')
                ->field('r.id,c.logo,r.title,r.salary,r.work_age,r.require_cert,m.regtype')
                ->alias('r')
                ->join('company c', 'c.id=r.company_id', 'LEFT')
                ->join('member m', 'c.user_id=m.id', 'LEFT')
                ->where($where)
                ->limit(6)
                ->where(['r.is_hot' => 1, 'r.status' => 1,'m.regtype'=>1])->select();
            foreach ($list as $key=>$value){
                if($list[$key]['logo']){
                    $list[$key]['logo']=SITE_URL.$list[$key]['logo'];
                }
            }
            $person = Db::name('recruit')
                ->field('r.id,c.logo,r.title,r.salary,r.work_age,r.require_cert,m.regtype')
                ->alias('r')
                ->join('company c', 'c.id=r.company_id', 'LEFT')
                ->join('member m', 'c.user_id=m.id', 'LEFT')
                ->where($where)
                ->limit(6)
                ->where(['r.is_hot' => 1, 'r.status' => 1,'m.regtype'=>2])->select();
            foreach ($person as $k=>$v){
                if($person[$k]['logo']){
                    $person[$k]['logo']=SITE_URL.$person[$k]['logo'];
                }
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '请求成功！',
                'data' => ['ad' => $adList, 'recruit' => $list, 'person' => $person]
            ]);
        }

    }
    public function search(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $member=Db::name('member')->where(['id'=>$user_id])->find();
        $regtype=$member['regtype'];
        $kw=input('kw');
        $page=input('page',1);
        $rows=input('rows',10);
        $start=($page-1)*$rows;
        if($regtype==1||$regtype==2){
            // 热招
            if($regtype==1){
                $rt=2;
            }else{
                $rt=1;
            }
            $where=[];
            if($kw){
                $where['r.title'] = ['like', '%' . $kw . '%'];
            }
            $list = Db::name('recruit')
                ->field('r.id,c.logo,r.title,r.salary,r.work_age,r.require_cert,m.regtype')
                ->alias('r')
                ->join('company c', 'c.id=r.company_id', 'LEFT')
                ->join('member m', 'c.user_id=m.id', 'LEFT')
                ->limit($start,$rows)
                ->where($where)
                ->where(['r.is_hot' => 1, 'r.status' => 1,'m.regtype'=>$rt])->select();
            foreach ($list as $key=>$value){
                if($list[$key]['logo']){
                    $list[$key]['logo']=SITE_URL.$list[$key]['logo'];
                }
            }
            $company_id = Db::name('company')->where(['user_id'=>$user_id])->value('id');
            $where=['p.status'=>1,'p.reserve_c' => [['=', 0], ['=', $company_id], 'or']];
            $where['p.name']=['like', '%' . $kw . '%'];
            // 找活
            $where['p.reserve_c']=0;
            $where['p.status']=1;
            $person = Db::name('person')
                ->alias('p')
                ->field('p.id,p.avatar,p.name,p.job_type,p.work_age,p.images')
                ->where($where)
                ->limit($start,$rows)
                ->select();
            foreach ($person as &$v) {
                $v['job_type'] = Category::getNameById($v['job_type']) ?: '';
                $v['images'] = $v['images']!='[]' ? 1 : 0;
                if($v['avatar']){
                    $v['avatar']=SITE_URL.$v['avatar'];
                }
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '请求成功！',
                'data' => ['recruit' => $list, 'person' => $person]
            ]);
        }elseif ($regtype==3){
            $where=[];
            if($kw){
                $where['r.title'] = ['like', '%' . $kw . '%'];
            }
            $list = Db::name('recruit')
                ->field('r.id,c.logo,r.title,r.salary,r.work_age,r.require_cert,m.regtype')
                ->alias('r')
                ->join('company c', 'c.id=r.company_id', 'LEFT')
                ->join('member m', 'c.user_id=m.id', 'LEFT')
                ->where($where)
                ->limit($start,$rows)
                ->where([ 'r.status' => 1,'m.regtype'=>1])->select();
            foreach ($list as $key=>$value){
                if($list[$key]['logo']){
                    $list[$key]['logo']=SITE_URL.$list[$key]['logo'];
                }
            }
            $person = Db::name('recruit')
                ->field('r.id,c.logo,r.title,r.salary,r.work_age,r.require_cert,m.regtype')
                ->alias('r')
                ->join('company c', 'c.id=r.company_id', 'LEFT')
                ->join('member m', 'c.user_id=m.id', 'LEFT')
                ->where($where)
                ->limit($start,$rows)
                ->where([ 'r.status' => 1,'m.regtype'=>2])->select();
            foreach ($person as $k=>$v){
                if($person[$k]['logo']){
                    $person[$k]['logo']=SITE_URL.$person[$k]['logo'];
                }
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '请求成功！',
                'data' => [ 'recruit' => $list, 'person' => $person]
            ]);
        }
    }
    public function index()
    {
        $user_id = $this->get_user_id();
        $member = Member::get($user_id);
        if ($member['regtype'] == 3) {
            $data = Db::name('person')->alias('p')
                ->field('p.id,p.name,p.avatar,m.mobile,m.openid,p.status,p.reserve,p.shelf,p.pull')
                ->join('member m', 'm.id = p.user_id', 'LEFT')
                ->where(['p.user_id' => $user_id])->find();
            if($data['avatar']){
                $data['avatar']=SITE_URL.$data['avatar'];
            }else{
                $data['avatar']=SITE_URL.'/public/images/default.jpg';
            }

            $data['user_id']=$user_id;
        } else {
            $data = Db::name('company')->alias('c')
                ->field('c.id,c.contacts,c.logo,m.openid,c.vip_time,m.mobile,c.vip_type,c.company_name,c.status,c.is_vip')
                ->join('member m', 'm.id = c.user_id', 'LEFT')
                ->where(['c.user_id' => $user_id])->find();
            if($data['logo']){
                $data['logo']=SITE_URL.$data['logo'];
            }else{
                $data['logo']=SITE_URL.'/public/images/default.jpg';
            }
            $num=0;
            if($data['is_vip']){
                $num=Db::name('company')->where(['id'=>$data['id']])->value('reserve_num');
//                $num=look_num($data['id']);
            }
            $data['number']=$num;
            $data['user_id']=$user_id;
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！', 'data' => $data]);
    }
    //查询该公司还有多少可查看（预约）人数
    public function look_num($company_id){
        $vip_type=Db::name('company')->where(['id'=>$company_id])->value('vip_type');
        $sysset = Db::table('sysset')->field('*')->find();
        $set =json_decode($sysset['vip'], true);
        $re_num=Db::name('reserve')->where(['company_id'=>$company_id])->count();
        switch ($vip_type){
            case 1:
                $num=$set['month']-$re_num;
                break;
            case 2:
                $num=$set['quarter']-$re_num;
                break;
            case 3:
                $num=$set['year']-$re_num;
                break;
            default:
                $num=0;
                break;
        }
        return $num;
    }
    /**
     * 上传头像
     */
    public function upload_headpic()
    {
        $pic = input('head_pic');
        if (!$pic) $this->ajaxReturn(['status' => -2, 'msg' => '路径不能为空']);
        $pic = str_replace(SITE_URL, '', $pic);
        $user_id = $this->get_user_id();
        $regtype = Db::name('member')->where(['id' => $user_id])->value('regtype');
        if (!$user_id || !$regtype) $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        if ($regtype == 3) {
            $res = Db::name('person')->where(['user_id' => $user_id])->update(['avatar' => $pic]);
        } else {
            $res = Db::name('company')->where(['user_id' => $user_id])->update(['logo' => $pic]);
        }
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功！']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '修改失败！']);
    }

    /*
     *  登录接口
     */
    public function login()
    {
//        $type = input('type',1);
//        if($type == 1){
//           $user_info = $this->GetOpenid();//微信授权用户信息
//        }else{
        $mobile = input('mobile');
        $password = input('password');
        // $code     = input('code');

        // $res = action('PhoneAuth/phoneAuth',[$mobile,$code]);
        // if( $res === '-1' ){
        //     $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码已过期！','data'=>'']);
        // }else if( !$res ){
        //     $this->ajaxReturn(['status' => -2 , 'msg'=>'验证码错误！','data'=>'']);
        // }

        $data = Db::table("member")->where('mobile', $mobile)
            ->field('id,password,mobile,salt,regtype')
            ->find();


        if (!$data) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机不存在或错误！']);
        }


        $password = md5($data['salt'] . $password);
        if ($password != $data['password']) {
            $this->ajaxReturn(['status' => -2, 'msg' => '登录密码错误！']);
        }

        unset($data['password'], $data['salt']);

        //重写
        $data['token'] = $this->create_token($data['id']);
        if($data['regtype']==1||$data['regtype']==2){
            $company=Db::name('company')->where(['user_id'=>$data['id']])->find();
            if(!$company){
                $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
            }elseif($company['status']==-1){
                Db::name('company')->where(['user_id'=>$data['id']])->delete();
                $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
            }
        }elseif ($data['regtype']==3){
            $person=Db::name('person')->where(['user_id'=>$data['id']])->find();
            if(!$person){
                $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
            }elseif($person['status']==-1){
                Db::name('person')->where(['user_id'=>$data['id']])->delete();
                $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
            }
        }

        $this->ajaxReturn(['status' => 1, 'msg' => '登录成功！', 'data' => $data]);
//        }

    }


    /*
     *  找回密码接口
     */
    public function zhaohuipwd()
    {
        $mobile = input('mobile');
        $password1 = input('password1');
        $password2 = input('password2');
        $code = input('code');

        $res = action('PhoneAuth/phoneAuth', [$mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }

        $data = Db::table("member")->where('mobile', $mobile)
            ->field('id,password,mobile,salt')
            ->find();

        if (!$data) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机不存在或错误！']);
        }

        if ($password1 != $password2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码不相同！！']);
        }

        // if( strlen($password2) < 6 ){
        //     $this->ajaxReturn(['status' => -2 , 'msg'=>'密码长度必须大于或6位！','data'=>'']);
        // }
        $salt = create_salt();
        $password = md5($salt . $password2);

        $update['salt'] = $salt;
        $update['password'] = $password;

        $res = Db::name('member')->where(['mobile' => $mobile])->update($update);


        if ($res == false) {
            $this->ajaxReturn(['status' => -2, 'msg' => '修改密码失败']);
        }

        $member['token'] = $this->create_token($data['id']);
        $member['mobile'] = $mobile;
        $member['id'] = $data['id'];

        $this->ajaxReturn(['status' => 1, 'msg' => '修改密码成功！', 'data' => $member]);
    }

    /**
     * 用户信息
     */
    public function userinfo()
    {
        $user_id = $this->get_user_id();
        if (!empty($user_id)) {
            $data = Db::name("member")->alias('m')
                ->join('user u', 'm.id=u.uid', 'LEFT')
                ->field('m.id,m.mobile,m.realname,m.pwd,m.avatar,m.gender,m.birthyear,m.birthmonth,m.birthday,m.mailbox,u.wx_nickname,wx_headimgurl')
                ->where(['m.id' => $user_id])
                ->find();
            if (empty($data)) {
                $this->ajaxReturn(['status' => -2, 'msg' => '会员不存在！', 'data' => '']);
            }
            $data['is_pwd'] = !empty($data['pwd']) ? 1 : 0;

            $res = Db::table("user_address")->where(['user_id' => $data['id']])
                ->field('*')
                ->find();
            $data['is_address'] = $res ? 1 : 0;
            unset($data['pwd'], $data['id']);
            if (empty($data['mobile'])) {
                $this->ajaxReturn(['status' => -2, 'msg' => '未绑定手机！', 'data' => $data]);
            }
        } else {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
    }

    public function reset_pwd()
    {//重置密码
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $password1 = input('password1');
        $password2 = input('password2');
        if ($password1 != $password2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码错误', 'data' => '']);
        }
        $member = Db::name('member')->where(['id' => $user_id])->field('id,password,pwd,mobile,salt')->find();
        $type = input('type',1);//1登录密码 2支付密码
        $code = input('code');
        $mobile = $member['mobile'];
        $res = action('PhoneAuth/phoneAuth', [$mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }
        if ($type == 1) {
            $stri = 'password';
        } else {
            $stri = 'pwd';
        }
        $password = md5($member['salt'] . $password2);
        if ($password == $member[$stri]) {
            $this->ajaxReturn(['status' => -2, 'msg' => '新密码和旧密码不能相同']);
        } else {
            $data = array($stri => $password);
            $update = Db::name('member')->where('id', $user_id)->data($data)->update();
            if ($update) {
                $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
            } else {
                $this->ajaxReturn(['status' => -2, 'msg' => '修改失败']);
            }
        }

    }

    /***
     * 邮箱编辑
     */
    public function reset_mailbox()
    {
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $mailbox = input('mailbox');
        $data = [
            'mailbox' => $mailbox
        ];
        $update = Db::name('member')->where(['id' => $user_id])->data($data)->update();
        if ($update) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        } else {
            $this->ajaxReturn(['status' => -2, 'msg' => '修改失败']);
        }


    }

    /**
     * 头像上传
     */
    public function update_head_pic()
    {

        $user_id = $this->get_user_id();
        $head_img = input('head_img');
        if (empty($head_img)) {
            $this->ajaxReturn(['code' => 0, 'msg' => '上传图片不能为空', 'data' => '']);
        }
        $saveName = request()->time() . rand(0, 99999) . '.png';
        $base64_string = explode(',', $head_img);
        $imgs = base64_decode($base64_string[1]);
        //生成文件夹
        $names = "head";
        $name = "head/" . date('Ymd', time());
        if (!file_exists(ROOT_PATH . Config('c_pub.img') . $names)) {
            mkdir(ROOT_PATH . Config('c_pub.img') . $names, 0777, true);
        }
        //保存图片到本地
        $r = file_put_contents(ROOT_PATH . Config('c_pub.img') . $name . $saveName, $imgs);
        if (!$r) {
            $this->ajaxReturn(['status' => -2, 'msg' => '上传出错', 'data' => '']);
        }
        Db::name('member')->where(['id' => $user_id])->update(['avatar' => SITE_URL . '/upload/images/' . $name . $saveName]);

        $this->ajaxReturn(['status' => 1, 'msg' => '修改成功', 'data' => SITE_URL . '/upload/images/' . $name . $saveName]);

    }

    /**
     * +---------------------------------
     * 地址组件原数据
     * +---------------------------------
     */
    public function get_address()
    {

        $data = Db::name('region')->select();
        $result = [];
        foreach ($data as $item) {
            $result[(int)$item['parent_id']][(int)$item['code']] = $item['area_name'];
        }

//        $province = Db::name('region')->field('code,area_name as name')->where('area_type',1)->select();
//        foreach($province as $k=>&$v){
//            $province[$k]['child']=Db::name('region')->field('code,area_name as name')->where('parent_id',$v['code'])->select();
//            foreach($province[$k]['child'] as $kk=>&$vv) {
//                $province[$k]['child'][$kk]['child'] = Db::name('region')->field('code,area_name as name')->where('parent_id',$vv['code'])->select();
//            }
//        }
        $this->ajaxReturn($result);
    }

    /**
     * +---------------------------------
     * 验证支付密码
     * +---------------------------------
     */
    public function check_pwd()
    {
        $user_id = $this->get_user_id();
        $pwd = input('pwd/d');
        $member = Db::name('member')->where(["id" => $user_id])->find();
        if (!$member) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在！', 'data' => '']);
        }
        $password = md5($member['salt'] . $pwd);
        if ($member['pwd'] !== $password) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付密码错误！', 'data' => '']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '密码正确！', 'data' => '']);
    }

    /**
     * +---------------------------------
     * 修改生日||昵称||性别
     * +---------------------------------
     */

    public function set_reabir()
    {
        $user_id = $this->get_user_id();
        $birthyear = input('birthyear');
        $birthmonth = input('birthmonth');
        $birthday = input('birthday');
        $realname = input('realname');
        $gender = input('gender', 0);
        $type = input('type', 1);
        if ($type == 1) {
            if (empty($realname)) {
                $this->ajaxReturn(['code' => 0, 'msg' => '昵称不能为空', 'data' => '']);
            }
            $update['realname'] = $realname;
        } else if ($type == 2) {
            $update['birthyear'] = $birthyear;
            $update['birthmonth'] = $birthmonth;
            $update['birthday'] = $birthday;
        } else {
            $update['gender'] = $gender;
        }
        $member = Db::name('member')->where(["id" => $user_id])->update($update);
        if ($member !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功', 'data' => '']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '修改失败', 'data' => '']);
    }

    //获取用户类型
    public function get_user_type(){
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $member=Db::name('member')->field('regtype')->where(['id'=>$user_id])->find();
        if(!$member){
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' =>$member]);
    }
    /***
     * 手机号换绑
     */

    public function change_mobile()
    {

        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $new_mobile = input('mobile');
        if (!checkMobile($new_mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号格式错误！']);
        }
        $code = input('code');
        $user = Db::table('member')->where(['mobile' => $new_mobile])->find();
        if($user){
            $this->ajaxReturn(['status' => -2, 'msg' => '该手机号已有用户！', 'data' => '']);
        }
        $member = Db::table('member')->where(['id' => $user_id])->find();
        if ($member['mobile'] == $new_mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不能相同！', 'data' => '']);
        }

        $res = action('PhoneAuth/phoneAuth', [$new_mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }

        $res = Db::table('member')->where(['id' => $user_id])->update(['mobile' => $new_mobile]);

        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功', 'data' => '']);
        } else {
            $this->ajaxReturn(['status' => -2, 'msg' => '修改失败', 'data' => '']);
        }

    }
    // 发送验证码
    public function send_code()
    {
        $mobile = input('mobile');
        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        $res = Db::name('phone_auth')->field('exprie_time')->where('mobile', '=', $mobile)->order('id DESC')->find();
        if ($res['exprie_time'] > time()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请求频繁请稍后重试！']);
        }

        $code = mt_rand(111111, 999999);

        $data['mobile'] = $mobile;
        $data['auth_code'] = $code;
        $data['start_time'] = time();
        $data['exprie_time'] = time() + 180;

        $res = Db::table('phone_auth')->insert($data);
        if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '发送失败，请重试！']);
        }

        $ret = send_zhangjun($mobile, $code);
        if ($ret['message'] == 'ok') {
            $this->ajaxReturn(['status' => 1, 'msg' => '发送成功！']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '发送失败，请重试！']);
    }

    public function get_images()
    {
        $user_id = $this->get_user_id();
        $type = Db::name('member')->where(['id' => $user_id])->value('regtype');
        if (!$type) $this->ajaxReturn(['status' => -2, 'msg' => '请求失败']);
        if ($type == 3) {
            $images = Db::name('person')->where(['user_id' => $user_id])->value('images');
        } else {
            $images= Db::name('company')->where(['user_id' => $user_id])->value('images');
        }
        if (!$images) $this->ajaxReturn(['status' => -2, 'msg' => '请求失败']);
        $images=json_decode($images,true);
        foreach ($images as $key=>$value){
            $images[$key]['path']=SITE_URL.$images[$key]['path'];
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => ['image' =>$images ]]);
    }

    // 资料管理
    public function edit_images()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $type = Db::name('member')->where(['id' => $user_id])->value('regtype');
        if (!$type) $this->ajaxReturn(['status' => -2, 'msg' => '请求失败']);
        $table = $type == 3 ? 'person' : 'company';
        $a_type=$type == 3 ? 6 : 5;
        if (!Db::name($table)->where(['user_id' => $user_id])->find()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
        }


        $data = input();
        $images = [];
        foreach ($data['image'] as $k => $image) {
            $images[] = [
                'path' => str_replace(SITE_URL, '', $image),
                'title' => isset($data['title'][$k]) ? $data['title'][$k] : ''
            ];
        }
        $res = Db::name('audit')->insert([
            'type'=>$a_type,
            'content_id'=>$user_id,
            'data'=>json_encode($images,JSON_UNESCAPED_UNICODE),
            'create_time'=>time()
        ]);
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
    }


}
