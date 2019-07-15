<?php


namespace app\common\logic;

use think\Db;
use think\Cache;
use think\Image;
use think\Validate;
use app\common\model\WxNews;
use app\common\model\WxReply;
use app\common\model\WxTplMsg;
use app\common\model\WxKeyword;
use app\common\model\WxMaterial;
use app\common\logic\wechat\WechatUtil;

/**
 * 微信公众号的业务逻辑类
 */
class WechatLogic
{
    static private $wx_user = null;
    static private $wechat_obj;

    public function __construct($config = null)
    {
        if (!self::$wx_user) {
            if ($config === null) {
                $config = Db::name('wx_user')->find();
            } 
            self::$wx_user = $config;
            self::$wechat_obj = new WechatUtil(self::$wx_user);
        }
    }

    /**
     * 处理接收推送消息
     */
    public function handleMessage()
    {
        self::$wechat_obj->registerMsgEvent(WechatUtil::EVENT_TEXT, function ($msg) {
            $this->handleTextMsg($msg);
        });

        self::$wechat_obj->registerMsgEvent(WechatUtil::EVENT_CLICK, function ($msg) {
            $this->handleClickEvent($msg);
        });

        self::$wechat_obj->registerMsgEvent(WechatUtil::EVENT_SUBSCRIBE, function ($msg) {
            $this->handleSubscribeEvent($msg);
        });

        self::$wechat_obj->handleMsgEvent();
    }
    public function write_log($content)
    {
        $content = "[".date('Y-m-d H:i:s')."]".$content."\r\n";
        $dir = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']),'/').'/logs';
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        $path = $dir.'/'.date('Ymd').'.txt';
        file_put_contents($path,$content,FILE_APPEND);
    }

    /**
     * 处理关注事件
     * @param array $msg
     * @return array
     */
    private function handleSubscribeEvent($msg)
    {
        $this->write_log(json_encode($msg));
        $openid = $msg['FromUserName'];
        if (!$openid) {
            exit("openid无效");
        }

        if ($msg['MsgType'] != 'event' || $msg['Event'] != 'subscribe') {
            $this->replyError($msg , "不是关注事件");   
        }

        $userinfo = Db::name('oauth_users')->where('openid', $openid)->find();
        if (!$userinfo) {
            $wxdata = self::$wechat_obj->getFanInfo($openid);
            if (false === $wxdata ){
                $this->replyError($msg , self::$wechat_obj->getError());
            }

            $userData = [
                'head_pic'  => $wxdata['headimgurl'],
                'nickname'  => $wxdata['nickname'] ?: '微信用户',
                'sex'       => $wxdata['sex'] ?: 0,
                'reg_time'  => time(),
                'password'  => '',
                'is_distribut' => 1,
            ];

            // 由场景值获取分销一级id
            if (!empty($msg['EventKey'])) {
                $userData['first_leader'] = substr($msg['EventKey'], strlen('qrscene_'));
                if ($userData['first_leader']) {
                    $first_leader = Db::name('users')->where('user_id', $userData['first_leader'])->find();
                    if ($first_leader) {
                        $userData['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
                        $userData['third_leader'] = $first_leader['second_leader']; // 第二级推荐人
                        //他上线分销的下线人数要加1
                        Db::name('users')->where('user_id', $userData['first_leader'])->setInc('underling_number');
                        Db::name('users')->where('user_id', $userData['second_leader'])->setInc('underling_number');
                        Db::name('users')->where('user_id', $userData['third_leader'])->setInc('underling_number');
                    }
                } else {
                    $userData['first_leader'] = 0;
                }
            }
            //$is_bind_account = tpCache('basic.is_bind_account');
            //if($is_bind_account && $userData['first_leader']){
                //如果是绑定账号, 把first_leader保存到cookie
                //setcookie('user_id', $userData['first_leader'] ,null,'/');
                //缓存关注时候传过来的上级id，User/bind_guide用到
                //Cache::set($openid,$userData['first_leader'],86400);
            //}else if(!$is_bind_account){//非绑定账号才给注册
                //此处注册也送积分

               
                $is_cunzai = Db::name('users')->where(['openid'=>$openid])->find();
                if(!$is_cunzai ){
                    $user_id = Db::name('users')->insertGetId($userData);
                }

                $isRegIntegral = tpCache('integral.is_reg_integral');
                $pay_points = 0;
                if($isRegIntegral==1){
                    $pay_points = tpCache('integral.reg_integral');
                }
                if($pay_points > 0){
                    //accountLog($user_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
                }
                
                $is_cunzai2 = Db::name('oauth_users')->where(['openid'=>$openid])->find();
                if(!$is_cunzai2 ){
                    Db::name('oauth_users')->insert([
                    'user_id' => $user_id,
                    'openid' => $openid,
                    'unionid' => isset($wxdata['unionid']) ? $wxdata['unionid'] : '',
                    'oauth' => 'weixin',
                    'oauth_child' => 'mp',
                    ]);
                }
                

           // } 
        }

        $this->replySubscribe($msg['ToUserName'], $openid,$msg);
    }

    /**
     * 关注时回复消息
     */
    private function replySubscribe($from, $to ,$msg)
    {
        $store_name = tpCache("shop_info.store_name");
        $first_leader = substr($msg['EventKey'], strlen('qrscene_'));

        if($first_leader > 0){
            //有分享
            //有上级
            //$to1 =  Db::name('users')->where('user_id', $leader_user_id)->value('nickname');
            //$to1 =  Db::name('users')->where('user_id', $leader_user_id)->value('openid');
            //$result_str = self::$wechat_obj->createReplyMsgOfText($from, $to1, "您的一级创客 [ $nickname ] 成功关注了本公众号 \n：".$msg);

            //有分享
            $xiaji = Db::name('users')->where('openid', $to)->value('user_id');
            $this->write_log('to:'.$to);
            $this->write_log($xiaji);
            share_deal_after($xiaji,$first_leader);

            $leader_nickname =  Db::name('users')->where('user_id', $first_leader)->value('nickname');
            $result_str = self::$wechat_obj->createReplyMsgOfText($from, $to, "您扫了[ $leader_nickname ]的分享，成功关注 $store_name !");
//
        }else{
            $result_str = self::$wechat_obj->createReplyMsgOfText($from, $to, "成功关注了 $store_name !");
       }

       
        // $result_str = $this->createReplyMsg($from, $to, WxReply::TYPE_FOLLOW);
        //if ( ! $result_str) {
            //没有设置关注回复，则默认回复如下：
           
        // }

        exit($result_str);
    }

    // private function deal_after($op){

    // }

    /**
     * 创建回复消息
     * @param $from string 发送方
     * @param $to string 被发送方
     * @param $type string WxReply的类型
     * @param array $data 附加数据
     * @return string
     */
    private function createReplyMsg($from, $to, $type, $data = [])
    {
        if ($type != WxReply::TYPE_KEYWORD) {
            $reply = WxReply::get(['type' => $type]);
        } else {
            $wx_keyword = WxKeyword::get(['keyword' => $data['keyword'], 'type' => WxKeyword::TYPE_AUTO_REPLY], 'wxReply');
            $wx_keyword && $reply = $wx_keyword->wx_reply;
        }

        if (empty($reply)) {
            return '';
        }

        $resultStr = '';
        if ($reply->msg_type == WxReply::MSG_TEXT && $reply['data']) {
            $resultStr = self::$wechat_obj->createReplyMsgOfText($from, $to, $reply['data']);
        } elseif ($reply->msg_type == WxReply::MSG_NEWS) {
            $resultStr = $this->createNewsReplyMsg($from, $to, $reply->material_id);
        } else {
            //扩展其他类型，如image，voice等
        }

        return $resultStr;
    }

    /**
     * 处理点击事件
     * @param array $msg
     */
    private function handleClickEvent($msg)
    {
        $from = $msg['ToUserName'];
        $to = $msg['FromUserName'];
        $eventKey = $msg['EventKey'];
        $distribut = tpCache('distribut');

        // 分销二维码图片
        if ($eventKey === $distribut['qrcode_menu_word']) {
            $this->replyMyQrcode($msg);
        }

        // 关键字自动回复
        $this->replyKeyword($from, $to, $eventKey);
    }

    /**
     * 回复我的二维码
     */
    private function replyMyQrcode($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = self::$wechat_obj;

        $user = Db::name('oauth_users')->alias('o')->join('__USERS__ u', 'u.user_id=o.user_id')
            ->field('u.*')->where('o.openid', $fromUsername)->find();
        if (!$user) {
            $content = '请进入商城: '.SITE_URL.' , 再获取二维码哦';
            $reply = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
            exit($reply);
        }

        //获取缓存的图片id
        $distribut = tpCache('distribut');
        $mediaId = $this->getCacheQrcodeMedia($user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        if (!$mediaId) {
            $mediaId = $this->createQrcodeMedia($msg, $user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        }

        //回复图片消息
        $reply = $wechatObj->createReplyMsgOfImage($toUsername, $fromUsername, $mediaId);
        exit($reply);
    }

    private function createQrcodeMedia($msg, $userId, $headPic, $qrBackImg)
    {
        $wechatObj = self::$wechat_obj;

        //创建二维码关注url
        $qrCode = $wechatObj->createTempQrcode(2592000, $userId);
        if (!(is_array($qrCode) && $qrCode['url'])) {
            $this->replyError($msg, '创建二维码失败');
        }

        //创建分销二维码图片
        empty($headPic) && $headPic = '/public/images/icon_goods_thumb_empty_300.png'; //没有头像用默认图片
        $shareImg = $this->createShareQrCode('.'.$qrBackImg, $qrCode['url'], $headPic);
        if (!$shareImg) {
            $this->replyError($msg, '生成图片失败');
        }

        //上传二维码图片
        if (!($mediaInfo = $wechatObj->uploadTempMaterial($shareImg, 'image'))) {
            @unlink($shareImg);
            $this->replyError($msg, '上传图片失败');
        }
        @unlink($shareImg);

        $this->setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo);

        return $mediaInfo['media_id'];
    }

    private function getCacheQrcodeMedia($userId, $headPic, $qrBackImg)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        $config = cache($mediaIdCache);
        if (!$config) {
            return false;
        }

        //$config = json_decode($config);
        //有效期3天（259200s）,提前5小时(18000s)过期
        if (!(is_array($config) && $config['media_id'] && ($config['created_at'] + 259200 - 18000) > time())) {
            return false;
        }

        return $config['media_id'];
    }

    private function setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        cache($mediaIdCache, $mediaInfo);
    }

    /**
     * 处理点击推送事件
     * @param array $msg
     */
    private function handleTextMsg($msg)
    {
        $from = $msg['ToUserName'];
        $to = $msg['FromUserName'];
        $keyword = trim($msg['Content']);

        //分销二维码图片
        $distribut = tpCache('distribut');
        if ($distribut['qrcode_input_word'] === $keyword) {
            $this->replyMyQrcode($msg);
        }

        // 关键字自动回复
        $this->replyKeyword($from, $to, $keyword);
    }

    /**
     * 关键字自动回复
     * @param $from
     * @param $to
     * @param $keyword
     */
    private function replyKeyword($from, $to, $keyword)
    {
        if (!$keyword) {
            $this->replyDefault($from, $to);
        }

        $resultStr = $this->createReplyMsg($from, $to, WxReply::TYPE_KEYWORD, ['keyword' => $keyword]);
        if ($resultStr) {
            exit($resultStr);
        } else {
            $this->replyDefault($from, $to);
        }
    }

    /**
     * 创建图文回复消息
     */
    private function createNewsReplyMsg($fromUser, $toUser, $material_id)
    {
        $material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews');
        if (!$material || !$material->wx_news) {
            return '';
        }

        $articles = [];
        foreach ($material->wx_news as $news) {
            $articles[] = [
                'title'       => $news->title,
                'description' => $news->digest ?: $news->content_digest,
                'picurl'      => SITE_URL . $news->thumb_url,
                'url'         => SITE_URL . url('/mobile/article/news', ['id' => $news->id])
            ];
        }

        return self::$wechat_obj->createReplyMsgOfNews($fromUser, $toUser, $articles);
    }

    /**
     * 默认回复
     * @param array $msg
     */
    private function replyDefault($from, $to)
    {
        $resultStr = $this->createReplyMsg($from, $to, WxReply::TYPE_DEFAULT);
        if ( ! $resultStr) {
            //没有设置默认回复，则默认回复如下：
            $store_name = tpCache("shop_info.store_name");
            $resultStr = self::$wechat_obj->createReplyMsgOfText($from, $to, "欢迎来到 $store_name !");
        }

        exit($resultStr);
    }

    /**
     * 错误回复
     */
    private function replyError($msg, $extraMsg = '')
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = self::$wechat_obj;

        if ($wechatObj->isDedug()) {
            $content = '错误信息：';
            $content .= $wechatObj->getError() ?: '';
            $content .= $extraMsg ?: '';
        } elseif ($extraMsg) {
            $content = '系统信息：'.$extraMsg;
        } else {
            $content = '系统正在处理...';
        }

        $resultStr = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
        exit($resultStr);
    }

    /**
     * 创建分享二维码图片
     * @param string $backImg 背景大图片
     * @param string $qrText  二维码文本:关注入口
     * @param string $headPic 头像路径
     * @return string 图片路径
     */
    private function createShareQrCode($backImg, $qrText, $headPic)
    {
        if (!is_file($backImg) || !$headPic || !$qrText) {
            return false;
        }

        vendor('phpqrcode.phpqrcode');
        vendor('topthink.think-image.src.Image');

        $qr_code_path = UPLOAD_PATH.'qr_code/';
        !file_exists($qr_code_path) && mkdir($qr_code_path, 0777, true);

        /* 生成二维码 */
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        \QRcode::png($qrText, $qr_code_file, QR_ECLEVEL_M);

        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        //$QR_height = $QR->height();

        /* 添加背景图 */
        if ($backImg && is_file($backImg)) {
            $back =Image::open($backImg);
            $backWidth = $back->width();
            $backHeight = $back->height();

            //生成的图片大小以540*960为准
            if ($backWidth <= $backHeight) {
                $refWidth = 540;
                $refHeight = 960;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                } else {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                }
            } else {
                $refWidth = 960;
                $refHeight = 540;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                } else {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                }
            }

            $shortSize = $backWidth > $backHeight ? $backHeight : $backWidth;
            $QR_width = $shortSize / 2;
            $QR_height = $QR_width;
            $QR->thumb($QR_width, $QR_height, \think\Image::THUMB_CENTER)->save($qr_code_file, null, 100);
            $back->thumb($backWidth, $backHeight, \think\Image::THUMB_CENTER)
                ->water($qr_code_file, \think\Image::WATER_CENTER, 90)->save($qr_code_file, null, 100);
            $QR = $back;
        }

        /* 添加头像 */
        if ($headPic) {
            //如果是网络头像
            if (strpos($headPic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $headPic);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path.time().rand(1, 10000).'.png';
                    file_put_contents($head_pic_path, $file_content);
                    $headPic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($headPic)) {
                $logo = Image::open($headPic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 5;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path.time().rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->water($logo_file, \think\Image::WATER_CENTER);
                unlink($logo_file);
            }
            if (!empty($head_pic_path)) {
                unlink($head_pic_path);
            }
        }

        //加上有效时间
        $valid_date = date('Y.m.d', strtotime('+30 days'));
        $QR->text('有效时间 '.$valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 16, '#FFFFFF', Image::WATER_SOUTH)->save($qr_code_file);

        return $qr_code_file;
    }

    /**
     * 获取粉丝列表
     */
    public function getFanList($p, $num = 10)
    {
        $wechatObj = self::$wechat_obj;
        if (!$access_token = $wechatObj->getAccessToken()) {
            return ['status' => -1, 'msg' => $wechatObj->getError()];
        }

        $p = intval($p) > 0 ? intval($p) : 1;
        $offset = ($p - 1) * $num;
        $max = 10000; //粉丝列表每次只能拉取的数量

        /* 获取所有粉丝列表openid并缓存 */
        $fans_key = 'wechat.fan_list';
        if (!$fans = Cache::get($fans_key)) {
            $next_openid = '';
            $fans = [];
            do {
                $ids = $wechatObj->getFanIdList($next_openid);
                if ($ids === false) {
                    return ['status' => -1, 'msg' => $wechatObj->getError()];
                }
                if($ids['data']['openid']){
                    $fans = array_merge($fans, $ids['data']['openid']);
                }
                $next_openid = $ids['next_openid'];
            } while ($ids['total'] > $max && $ids['count'] == $max);
            Cache::set($fans_key, $fans, 3600); //缓存列表一个小时
        }

        /* 获取指定粉丝，并获取详细信息 */
        $part_fans = array_slice($fans, $offset, $num);
        $user_list = [];
        $fan_key = 'wechat.fan_info';
        foreach ($part_fans as $openid) {
            if (!$fan = Cache::get($fan_key.'.'.$openid)) {
                $fan = $wechatObj->getFanInfo($openid, $access_token);
                if ($fan === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                $fan['tags'] = $wechatObj->getFanTagNames($fan['tagid_list']);
                if ($fan['tags'] === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                Cache::set($fan_key.'.'.$openid, $fan, 3600); //缓存粉丝一个小时
            }
            //查user_id
            $user_id = db('oauth_users')->where(['openid'=>$openid])->value('user_id');
            $fan['user_id'] = $user_id?$user_id:0;
            $user_list[$openid] = $fan;
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => [
            'total' => count($fans),
            'list' => $user_list
        ]];
    }

    /**
     * 商城用户里的粉丝列表
     */
    public function getUserFanList($p, $num = 10, $keyword= '')
    {
        $wechatObj = self::$wechat_obj;
        if (!$access_token = $wechatObj->getAccessToken()) {
            return ['status' => -1, 'msg' => $wechatObj->getError()];
        }

        $p = intval($p) > 0 ? intval($p) : 1;
        $condition = ['o.openid' => ['<>', ''], 'o.oauth' => 'weixin', 'o.oauth_child' => 'mp'];
        $keyword = trim($keyword);
        $keyword && $condition['o.openid|u.nickname'] = ['like', "%$keyword%"];

        $query = Db::name('oauth_users')->field('o.*')->alias('o')->join('__USERS__ u', 'u.user_id = o.user_id')->where($condition);
        $copyQuery = clone $query;
        $users = $query->page($p, $num)->select();
        $user_num = $copyQuery->count();

        $fan_key = 'wechat.user_fan_info';
        foreach ($users as &$user) {
            if (!$fan = Cache::get($fan_key.'.'.$user['openid'])) {
                $fan = $wechatObj->getFanInfo($user['openid'], $access_token);
                if ($fan === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                Cache::set($fan_key.'.'.$user['openid'], $fan, 3600); //缓存粉丝一个小时
            }
            $user['weixin'] = $fan;
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => [
            'total' => $user_num,
            'list' => $users
        ]];
    }

    /**
     * 新建和更新文本素材
     * （图文素材只需保存在本地，微信不存储文本素材）
     */
    public function createOrUpdateText($material_id, $data)
    {
        $validate = new Validate([
            ['title','require|max:64','标题必填|标题最多64字'],
            ['content','require|max:600','内容必填|内容最多600字'],
        ]);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        $text = [
            'type' => 'text',
            'update_time' => time(),
            'data' => [
                'title' => $data['title'],
                'content' => $data['content'],
            ]
        ];

        if ($material_id) {
            if (!$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_TEXT])) {
                return ['status' => -1, 'msg' => '文本素材不存在'];
            }
            $material->save($text);
        } else {
            $material = WxMaterial::create($text);
        }

        return ['status' => 1, 'msg' => '素材提交成功！', 'result' => $material->id];
    }

    /**
     * 删除文本素材
     */
    public function deleteText($material_id)
    {
        if (!$material_id || !$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_TEXT])) {
            return ['status' => -1, 'msg' => '文本素材不存在'];
        }

        $material->delete();

        return ['status' => 1, 'msg' => '删除文本素材成功'];
    }


    /**
     * 新建和更新图文素材
     */
    public function createOrUpdateNews($material_id, $news_id, $data)
    {
        $article = [
            "title"             => $data['title'],
            //"thumb_media_id"    => $data['thumb_media_id'],
            "thumb_url"         => $data['thumb_url'],
            "author"            => $data['author'],
            "digest"            => $data['digest'],
            "show_cover_pic"    => $data['show_cover_pic'] ? 1 : 0,
            "content"           => $data['content'],
            "content_source_url" => $data['content_source_url'],
            "material_id"       => $material_id,
            "update_time"       => time(),
        ];

        if ($material_id) {
            if (!$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS])) {
                return ['status' => -1, 'msg' => '图文素材不存在'];
            }

            if ($news_id) {
                //更新单图文
                if (!$news = WxNews::get(['id' => $news_id, 'material_id' => $material_id])) {
                    return ['status' => -1, 'msg' => '单图文素材不存在'];
                }
                $news->save($article);
                if ($material->media_id) {
                    $material->save(['media_id' => 0]); // 需要重新上传
                }

            } else {
                //新增单图文
                $all_news = WxNews::all(['material_id' => $material_id]);
                $max_news_per_material = 8;
                if (count($all_news) >= $max_news_per_material) {
                    return ['status' => -1, 'msg' => "一个图文素材中的文章最多 $max_news_per_material 篇"];
                }
                WxNews::create($article);
            }
            $material->save([
                'update_time' => time(),
                'media_id' => 0 // 需要重新上传
            ]);

        } else {
            //新增多图文
            $material = WxMaterial::create([
                'type' => WxMaterial::TYPE_NEWS,
                'update_time' => time(),
            ]);
            $article['material_id'] = $material->id;
            WxNews::create($article);
        }

        //先不用上传到微信服务器，等实际使用的时候再上传

        return ['status' => 1, 'msg' => '素材提交成功！'];
    }

    /**
     * 删除图文素材
     * @param $material_id int 素材id
     * @return array
     */
    public function deleteNews($material_id)
    {
        if (!$material_id || !$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews')) {
            return ['status' => -1, 'msg' => '素材不存在'];
        }

        if (WxReply::get(['material_id' => $material_id, 'msg_type' => WxReply::MSG_NEWS])) {
            return ['status' => -1, 'msg' => '该素材正被自动回复使用，无法删除'];
        }

        if ($material->media_id) {
            self::$wechat_obj->delMaterial($material->media_id);
        }

        if (is_array($material->wx_news)) {
            foreach ($material->wx_news as $news) {
                $news->delete();
            }
        }
        $material->delete();

        return ['status' => 1, 'msg' => '删除图文成功'];
    }

    /**
     * 删除单图文
     * @param $news_id int 单图文的id
     * @return array
     */
    public function deleteSingleNews($news_id)
    {
        if (!$news_id || !$news = WxNews::get($news_id, 'wxMaterial')) {
            return ['status' => -1, 'msg' => '单图文素材不存在'];
        }

        if (!$news->wx_material) {
            return ['status' => -1, 'msg' => '该单图文所属素材不存在'];
        }

        if (count($news->wx_material->wx_news) == 1) {
            return $this->deleteNews($news->material_id);
        } else {
            if ($news->wx_material->media_id) {
                $news->wx_material->save(['media_id' => 0]); // 需要重新上传
            }
            $news->delete();
        }

        return ['status' => 1, 'msg' => '删除单图文成功'];
    }

    /**
     * 上传图文
     * @param $material WxMaterial
     * @return array
     */
    private function uploadNews($material)
    {
        $articles = [];
        foreach ($material->wx_news as $news) {
            // 1.获取或上传封面
            if ($thumb = WxMaterial::get(['type' => WxMaterial::TYPE_IMAGE, 'key' => md5($news['thumb_url'])])) {
                $thumb_media_id = $thumb->media_id;
            } else {
                $thumb = self::$wechat_obj->uploadMaterial('.'.$news['thumb_url'], 'image');
                if ($thumb ===  false) {
                    return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
                }
                $thumb_media_id = $thumb['media_id'];
                WxMaterial::create([
                    'type' => WxMaterial::TYPE_IMAGE,
                    'key'  => md5($news['thumb_url']),
                    'media_id' => $thumb_media_id,
                    'update_time' => time(),
                    'data' => [
                        'url' => $news['thumb_url'],
                        'mp_url' => $thumb['url'],
                    ]
                ]);
            }

            // 2.将文章中的图片上传
            $news['content'] = htmlspecialchars_decode($news['content']);
            if (preg_match_all('#<img .*?src="(.*?)".*?/>#i', $news['content'], $matches)) {
                $imgs = array_unique($matches[1]);
                foreach ($imgs as $img) {
                    if (stripos($img, 'http') === 0) {
                        continue;
                    }

                    // 3.获取或上传文章中图片
                    if ($news_image = WxMaterial::get(['type' => WxMaterial::TYPE_NEWS_IMAGE, 'key' => md5($img)])) {
                        $news_image_url = $news_image->data['mp_url'];
                    } else {
                        $news_image_url = self::$wechat_obj->uploadNewsImage('.'.$img);
                        if ($news_image_url ===  false) {
                            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
                        }
                        WxMaterial::create([
                            'type' => WxMaterial::TYPE_NEWS_IMAGE,
                            'key'  => md5($img),
                            'update_time' => time(),
                            'data' => [
                                'url' => $news['thumb_url'],
                                'mp_url' => $news_image_url,
                            ]
                        ]);
                    }

                    $news['content'] = str_replace($img, $news_image_url, $news['content']);
                }
            }

            $articles[] = [
                "title"             => $news['title'],
                "thumb_media_id"    => $thumb_media_id,
                "author"            => $news['author'] ?: '',
                "digest"            => $news['digest'] ?: '',
                "show_cover_pic"    => $news['show_cover_pic'] ? 1 : 0,
                "content"           => $news['content'],
                "content_source_url" => $news['content_source_url'],
            ];
        }

        $news_media_id = self::$wechat_obj->uploadNews($articles);
        if ($news_media_id ===  false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }
        $material->save(['media_id' => $news_media_id]);

        return ['status' => 1, 'msg' => '上传成功', 'result' => $news_media_id];
    }

    /**
     * 发送图文消息
     * @param $material_id int 素材id
     * @param $openids array|string 可多个用户openid
     * @param int $to_all 0由openids决定，1所有粉丝
     * @return array
     */
    public function sendNewsMsg($material_id, $openids, $to_all = 0)
    {
        $material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews');
        if (!$material || !$material->wx_news) {
            return ['status' => -1, 'msg' => '该素材不存在'];
        }

        if ($material->media_id) {
            $news_media_id = $material->media_id;
            if (false === self::$wechat_obj->getMaterial($material->media_id)) {
                $news_media_id = 0; //获取失败，可能被手动删了，需要重新上传
            }
        }
        if (empty($news_media_id)) {
            $return = $this->uploadNews($material);
            if ($return['status'] != 1) {
                return $return;
            }
            $news_media_id = $return['result'];
        }

        // 5.发送消息
        if ($to_all) {
            $result = self::$wechat_obj->sendMsgToAll(0, 'mpnews', $news_media_id);
        } else {
            $result = self::$wechat_obj->sendMsg($openids, 'mpnews', $news_media_id);
        }
        if ($result === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送成功'];
    }

    /**
     * 删除图片
     * @param $url string 存储在本地的url
     */
    public function deleteImage($url)
    {
        if (strpos($url, 'weixin_mp_image/') === false) {
            return;
        }
        if (!$image = WxMaterial::get(['type' => WxMaterial::TYPE_IMAGE, 'key' => md5($url)])) {
            return;
        }
        if ($image->media_id) {
            self::$wechat_obj->delMaterial($image->media_id);
        }
    }

    /**
     * 系统默认模板消息
     * @return array
     */
    public function getDefaultTemplateMsg($template_sn = null)
    {
        $templates = [
            [
                "template_sn" => "TM00016",
                "title" => "订单提交成功",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."订单号：{{orderID.DATA}}\n"
                    ."待付金额：{{orderMoneySum.DATA}}\n"
                    ."{{backupFieldName.DATA}}{{backupFieldData.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM204987032",
                "title" => "订单支付成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单：{{keyword1.DATA}}\n"
                    ."支付状态：{{keyword2.DATA}}\n"
                    ."支付日期：{{keyword3.DATA}}\n"
                    ."商户：{{keyword4.DATA}}\n"
                    ."金额：{{keyword5.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM202243318",
                "title" => "订单发货通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单内容：{{keyword1.DATA}}\n"
                    ."物流服务：{{keyword2.DATA}}\n"
                    ."快递单号：{{keyword3.DATA}}\n"
                    ."收货信息：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM401833445",
                "title" => "余额变动提示",
                "content" =>
                    "{{first.DATA}}\n"
                    ."变动时间：{{keyword1.DATA}}\n"
                    ."变动类型：{{keyword2.DATA}}\n"
                    ."变动金额：{{keyword3.DATA}}\n"
                    ."当前余额：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM207126233",
                "title" => "分销商申请成功",
                "content" =>
                    "{{first.DATA}}\n"
                    ."分销商名称：{{keyword1.DATA}}\n"
                    ."分销商电话：{{keyword2.DATA}}\n"
                    ."申请时间：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM201812627",
                "title" => "佣金提醒",
                "content" =>
                    "{{first.DATA}}\n"
                    ."佣金金额：{{keyword1.DATA}}\n"
                    ."时间：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM407307456",
                "title" => "开团成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."商品名称：{{keyword1.DATA}}\n"
                    ."商品价格：{{keyword2.DATA}}\n"
                    ."组团人数：{{keyword3.DATA}}\n"
                    ."拼团类型：{{keyword4.DATA}}\n"
                    ."组团时间：{{keyword5.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM400048581",
                "title" => "参团成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."拼团名：{{keyword1.DATA}}\n"
                    ."拼团价：{{keyword2.DATA}}\n"
                    ."有效期：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM407456411",
                "title" => "拼团成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."团购商品：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM400940587",
                "title" => "拼团退款提醒",
                "content" =>
                    "{{first.DATA}}\n"
                    ."单号：{{keyword1.DATA}}\n"
                    ."商品：{{keyword2.DATA}}\n"
                    ."原因：{{keyword3.DATA}}\n"
                    ."退款：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ]
        ];

        $templates = convert_arr_key($templates, 'template_sn');

        $valid_sns = ['OPENTM204987032', 'OPENTM202243318']; //目前支持的模板
        $valid_templates = [];
        foreach ($valid_sns as $sn) {
            if (isset($templates[$sn])) {
                $valid_templates[$sn] = $templates[$sn];
            }
        }

        if ($template_sn) {
            return $valid_templates[$template_sn];
        }
        return $valid_templates;
    }

    /**
     * 配置模板
     * @param $data array 配置
     */
    public function setTemplateMsg($template_sn, $data)
    {
        if (!isset($data['is_use']) && !isset($data['remark'])) {
            return ['status' => -1, 'msg' => '参数为空'];
        }

        $tpls = $this->getDefaultTemplateMsg();
        if (!key_exists($template_sn, $tpls)) {
            return ['status' => -1, 'msg' => "模板消息的编号[$template_sn]不存在"];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            $tpl_msg->save($data);
        } else {
            if (!$template_id = self::$wechat_obj->addTemplateMsg($template_sn)) {
                return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
            }
            WxTplMsg::create([
                'template_id' => $template_id,
                'template_sn' => $template_sn,
                'title' => $tpls[$template_sn]['title'],
                'is_use' => isset($data['is_use']) ? $data['is_use'] : 0,
                'remark' => isset($data['remark']) ? $data['remark'] : '',
                'add_time' => time(),
            ]);
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }

    /**
     * 重置模板消息
     */
    public function resetTemplateMsg($template_sn)
    {
        if (!$template_sn) {
            return ['status' => -1, 'msg' => '参数不为空'];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            if ($tpl_msg->template_id) {
                self::$wechat_obj->delTemplateMsg($tpl_msg->template_id);
            }
            $tpl_msg->delete();
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }

    /**
     * 发送模板消息（订单支付成功通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnPaySuccess($order)
    {
        if ( ! $order) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }

        $template_sn = 'OPENTM204987032';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }
    
        $store_name = tpCache('shop_info.store_name');
        $data = [
            'first' => ['value' => '订单支付成功！'],
            'keyword1' => ['value' => $order['order_sn']],
            'keyword2' => ['value' => '已支付'],
            'keyword3' => ['value' => date('Y-m-d H:i', $order['pay_time'])],
            'keyword4' => ['value' => $store_name],
            'keyword5' => ['value' => $order['order_amount']],
            'remark' => ['value' => $tpl_msg->remark ?: ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（订单发货通知）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnDeliver($deliver)
    {
        if ( ! $deliver) {
            return ['status' => -1, 'msg' => '订单物流不存在'];
        }

        $template_sn = 'OPENTM202243318';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $deliver['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        // 收货地址
        $province = getRegionName($deliver['province']);
        $city = getRegionName($deliver['city']);
        $district = getRegionName($deliver['district']);
        $full_address = $province.' '.$city.' '.$district.' '. $deliver['address'];

        $order_goods = Db::name('order_goods')->where('order_id', $deliver['order_id'])->find();
        $data = [
            'first' => ['value' => "订单{$deliver['order_sn']}发货成功！"],
            'keyword1' => ['value' => $order_goods['goods_name']],
            'keyword2' => ['value' => $deliver['shipping_name']],
            'keyword3' => ['value' => $deliver['delivery_sn']],
            'keyword4' => ['value' => $full_address],
            'remark' => ['value' => $tpl_msg->remark ?: ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$deliver['order_id']);
        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（订单发货通知）新
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnDeliverNew($deliver){
        
        if ( ! $deliver) {
            return ['status' => -1, 'msg' => '订单物流不存在'];
        }

        $province = getRegionName($deliver['province']);
        $city = getRegionName($deliver['city']);
        $district = getRegionName($deliver['district']);
        $full_address = $province.' '.$city.' '.$district.' '. $deliver['address'];

        $order_goods = Db::name('order_goods')->where('order_id', $deliver['order_id'])->find();

        $user = Db::name('OauthUsers')->where(['user_id'=>$deliver['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
        if ($user) {
            $wechat = new \app\common\logic\wechat\WechatUtil();
            $wechat->sendMsg($user['openid'], 'text', $wx_content);
            $wx_content = "订单{$deliver['order_sn']}发货成功！\n订单内容：{$order_goods['goods_name']}\n"."物流服务：{$deliver['shipping_name']}\n"."快递单号：{$deliver['delivery_sn']}\n"."收货信息：{$full_address}\n";
        }
    }

    /**
     * 图片插件中展示的列表
     * @param $size int 拉取多少
     * @param $start int 开始位置
     * @return string
     */
    public function getPluginImages($size, $start = 0)
    {
        $data = self::$wechat_obj->getMaterialList('image', $size * $start, $size);
        if ($data === false) {
            return json_encode([
                "state" => self::$wechat_obj->getError(),
                "list" => [],
                "start" => $start,
                "total" => 0
            ]);
        }

        $list = [];
        foreach ($data['item'] as $item) {
            $list[] = [
                'url' => $item['url'],
                'mtime' => $item['update_time'],
                'name' => $item['name'],
            ];
        }

        return json_encode([
            "state" => "no match file",
            "list" => $list,
            "start" => $start,
            "total" => $data['total_count']
        ]);
    }

    /**
     * 修正关键字
     * @param $keywords
     * @return array
     */
    private function trimKeywords($keywords)
    {
        $keywords = explode(',', $keywords);
        $keywords = array_map('trim', $keywords);
        $keywords = array_unique($keywords);
        foreach ($keywords as $k => $keyword) {
            if (!$keyword) {
                unset($keywords[$k]);
            }
        }

        return array_values($keywords);
    }

    /**
     * 更新关键字
     * @param $reply_id int 回复id
     * @param $wx_keywords WxKeyword[]
     * @param $keywords array 关键字数组
     */
    private function updateKeywords($reply_id, $wx_keywords, $keywords)
    {
        $wx_keywords = convert_arr_key($wx_keywords, 'keyword');

        //先删除不存在的keyword
        foreach ($wx_keywords as $key => $word) {
            if (!in_array($key, $keywords)) {
                $word->delete();
                unset($wx_keywords[$key]);
            }
        }
        //创建要设置的keyword
        foreach ($keywords as $keyword) {
            if (!isset($wx_keywords[$keyword])) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply_id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }
    }

    /**
     * 检查文本自动回复表单
     */
    private function checkTextAutoReplyForm(&$data)
    {
        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['keywords','require','关键词必填'],
                ['rule','require|max:32','规则名必填|规则名最多32字'],
                ['content','require|max:600','文本内容必填|文本内容最多600字'],
            ];
        } else {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['content','max:600','文本内容最多600字'],
            ];
        }
        $validate = new Validate($rules);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        if ( ! key_exists($data['type'], WxReply::getAllType())) {
            return ['status' => -1, 'msg' => '回复类型不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (!$data['keywords'] = $this->trimKeywords($data['keywords'])) {
                return ['status' => -1, 'msg' => '关键字不存在'];
            }
        }

        return ['status' => 1, 'msg' => '检查成功'];
    }

    /**
     * 添加文本自动回复
     */
    public function addTextAutoReply($data)
    {
        $return = $this->checkTextAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (WxKeyword::get(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }
        }

        $reply = WxReply::create([
            'rule' => $data['rule'],
            'update_time' => time(),
            'type' => $data['type'],
            'msg_type' => WxReply::MSG_TEXT,
            'data' => $data['content'],
        ]);

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            foreach ($data['keywords'] as $keyword) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply->id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }

        return ['status' => 1, 'msg' => '添加成功'];
    }

    /**
     * 更新文本自动回复
     * @param $reply_id int 回复id
     * @param $data array
     * @return array
     */
    public function updateTextAutoReply($reply_id, $data)
    {
        $return = $this->checkTextAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        $with = ($data['type'] == WxReply::TYPE_KEYWORD) ? 'wxKeywords' : [];
        if (!$reply = WxReply::get(['id' => $reply_id], $with)) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $keyword_ids = get_arr_column($reply->wx_keywords, 'id');
            if (WxKeyword::all(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY, 'id' => ['not in', $keyword_ids]])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }

            $this->updateKeywords($reply_id, $reply->wx_keywords, $data['keywords']);
        }

        $reply->save([
            'rule' => $data['rule'],
            'update_time' => time(),
            'data' => $data['content'],
            'material_id' => 0,
            'msg_type' => WxReply::MSG_TEXT
        ]);

        return ['status' => 1, 'msg' => '更新成功'];
    }

    /**
     * 检查文本自动回复表单
     */
    private function checkNewsAutoReplyForm(&$data)
    {
        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $rules = [
                ['keywords','require','关键词必填'],
                ['rule','require|max:32','规则名必填|规则名最多32字'],
                ['type', 'require', '回复类型必需'],
                ['material_id','require','关联素材id必需'],
            ];
        } else {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['material_id','require','关联素材id必需'],
            ];
        }
        $validate = new Validate($rules);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (!$data['keywords'] = $this->trimKeywords($data['keywords'])) {
                return ['status' => -1, 'msg' => '关键字不存在'];
            }
        }

        if (!WxMaterial::get(['id' => $data['material_id'], 'type' => WxMaterial::TYPE_NEWS])) {
            return ['status' => -1, 'msg' => '关联图文素材不存在'];
        }

        return ['status' => 1, 'msg' => '检查成功'];
    }

    /**
     * 新增图文自动回复
     */
    public function addNewsAutoReply($data)
    {
        $return = $this->checkNewsAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (WxKeyword::get(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }
        }

        $reply = WxReply::create([
            'rule' => $data['rule'],
            'update_time' => time(),
            'type' => $data['type'],
            'msg_type' => WxReply::MSG_NEWS,
            'material_id' => $data['material_id'],
        ]);

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            foreach ($data['keywords'] as $keyword) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply->id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }

        return ['status' => 1, 'msg' => '添加成功'];
    }

    /**
     * 更新图文自动回复
     * @param $reply_id int 回复id
     * @param $data array
     * @return array
     */
    public function updateNewsAutoReply($reply_id, $data)
    {
        $return = $this->checkNewsAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        $with = ($data['type'] == WxReply::TYPE_KEYWORD) ? 'wxKeywords' : [];
        if (!$reply = WxReply::get(['id' => $reply_id], $with)) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $keyword_ids = get_arr_column($reply->wx_keywords, 'id');
            if (WxKeyword::all(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY, 'id' => ['not in', $keyword_ids]])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }

            $this->updateKeywords($reply_id, $reply->wx_keywords, $data['keywords']);
        }

        $reply->save([
            'rule' => $data['rule'],
            'update_time' => time(),
            'material_id' => $data['material_id'],
            'msg_type' => WxReply::MSG_NEWS,
            'data' => '',
        ]);

        return ['status' => 1, 'msg' => '更新成功'];
    }

    /**
     * 添加自动回复
     */
    public function addAutoReply($type, $data)
    {
        if ($type == 'text') {
            return $this->addTextAutoReply($data);
        } elseif ($type == 'news') {
            return $this->addNewsAutoReply($data);
        } else {
            return ['status' => -1, 'msg' => '自动回复类型不存在'];
        }
    }

    /**
     * 更新自动回复
     */
    public function updateAutoReply($type, $reply_id, $data)
    {
        if ($type == 'text') {
            return $this->updateTextAutoReply($reply_id, $data);
        } elseif ($type == 'news') {
            return $this->updateNewsAutoReply($reply_id, $data);
        } else {
            return ['status' => -1, 'msg' => '自动回复类型不存在'];
        }
    }

    /**
     * 删除自动回复
     */
    public function deleteAutoReply($reply_id)
    {
        if (!$reply = WxReply::get(['id' => $reply_id])) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($reply->type == WxReply::TYPE_KEYWORD) {
            WxKeyword::where(['pid' => $reply_id])->delete();
        }

        $reply->delete();

        return ['status' => 1, 'msg' => '删除成功'];
    }
}