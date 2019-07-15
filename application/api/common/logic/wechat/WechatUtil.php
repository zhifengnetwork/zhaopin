<?php


namespace app\common\logic\wechat;

use think\Db;
use think\Cache;

/**
 * 说明：此类只进行微信公众号的接口封装，不实现业务逻辑！
 * 接口统一错误返回false，错误信息由getError()获取
 * 业务逻辑请前往 WechatLogic
 */
class WechatUtil extends WxCommon
{
    private $config = [];    //微信公众号配置
    private $tagsMap = null; //粉丝标签映射
    private $events = [];

    //事件类型
    const EVENT_ALL = 0; //有事件就处理
    const EVENT_TEXT = 1; //文本输入事件
    const EVENT_SUBSCRIBE = 2; //关注事件
    const EVENT_UNSUBSCRIBE = 3; //取消关注事件
    const EVENT_SCAN = 4; //已关注的扫描二维码事件
    const EVENT_LOCATION = 5; //上报二维码时间
    const EVENT_CLICK = 6; //点击菜单事件
    const EVENT_VIEW = 7; //点击菜单跳转链接事件


    public function __construct($config = null)
    {
        if ($config === null) {
            $config = Db::name('wx_user')->find();
        }
        $this->config = $config;
    }

    /**
     * 获取access_token
     * @return string
     */
    public function getAccessToken()
    {
        $wechat = $this->config;
        if (empty($wechat)) {
            $this->setError("公众号不存在！");
            return false;
        }

        //判断是否过了缓存期
        $expire_time = $wechat['web_expires'];
        if ($expire_time > time()) {
            return $wechat['web_access_token'];
        }

        $appid = $wechat['appid'];
        $appsecret = $wechat['appsecret'];
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
        $return = $this->requestAndCheck($url, 'GET');
        if (!isset($return['access_token'])) {
            $this->config['web_expires'] = 0;
            Db::name('wx_user')->where('id', $wechat['id'])->save(['web_expires' => 0]);
            return false;
        }

        $web_expires = time() + 7000; // 提前200秒过期
        Db::name('wx_user')->where('id', $wechat['id'])->save(['web_access_token'=>$return['access_token'], 'web_expires'=>$web_expires]);
        $this->config['web_access_token'] = $return['access_token'];
        $this->config['web_expires'] = $web_expires;

        return $return['access_token'];
    }

    /**
     * 获取粉丝详细信息
     * @param string $openid
     * @param string $access_token 如果为null，自动获取
     * @return array|bool
     */
    public function getFanInfo($openid, $access_token = null)
    {
        if (null === $access_token) {
            if (!$access_token = $this->getAccessToken()) {
                return false;
            }
        }

        $url ="https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$openid}&lang=zh_CN";
        $return = $this->requestAndCheck($url, 'GET');
        if ($return === false) {
            return false;
        }

        /* $wxdata[]元素：
         * subscribe	用户是否订阅该公众号标识，值为0时，代表此用户没有关注该公众号，拉取不到其余信息。
         * openid	用户的标识，对当前公众号唯一
         * nickname	用户的昵称
         * sex	用户的性别，值为1时是男性，值为2时是女性，值为0时是未知
         * city	用户所在城市
         * country	用户所在国家
         * province	用户所在省份
         * language	用户的语言，简体中文为zh_CN
         * headimgurl	用户头像，最后一个数值代表正方形头像大小（有0、46、64、96、132数值可选，0代表640*640正方形头像），用户没有头像时该项为空。若用户更换头像，原有头像URL将失效。
         * subscribe_time	用户关注时间，为时间戳。如果用户曾多次关注，则取最后关注时间
         * unionid	只有在用户将公众号绑定到微信开放平台帐号后，才会出现该字段。
         * remark	公众号运营者对粉丝的备注，公众号运营者可在微信公众平台用户管理界面对粉丝添加备注
         * groupid	用户所在的分组ID（兼容旧的用户分组接口）
         * tagid_list	用户被打上的标签ID列表
         */
        $return['sex_name'] = $this->sexName($return['sex']);
        return $return;
    }

    /**
     * sex_id 用户的性别，值为1时是男性，值为2时是女性，值为0时是未知
     */
    public function sexName($sex_id)
    {
        if ($sex_id == 1) {
            return '男';
        } else if ($sex_id == 2) {
            return '女';
        }
        return '未知';
    }

    /**
     * 获取粉丝标签
     * @return mixed
     */
    public function getAllFanTags()
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $url = "https://api.weixin.qq.com/cgi-bin/tags/get?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'GET');
        if ($return === false) {
            return false;
        }

        //$wxdata数据样例：{"tags":[{"id":1,"name":"每天一罐可乐星人","count":0/*此标签下粉丝数*/}, ...]}
        return $return['tags'];
    }

    /**
     * 获取所有用户标签
     * @return array|bool
     */
    public function getAllFanTagsMap()
    {
        if ($this->tagsMap !== null) {
            return $this->tagsMap;
        }

        $user_tags = $this->getAllFanTags();
        if ($user_tags === false) {
            return false;
        }

        $this->tagsMap = [];
        foreach ($user_tags as $tag) {
            $this->tagsMap[$tag['id']] = $this->tagsMap[$tag['name']];
        }
        return $this->tagsMap;
    }

    /**
     * 获取粉丝标签名
     * @param array $tagid_list
     * @param array $tagsMap
     * @return array|bool
     */
    public function getFanTagNames($tagid_list)
    {
        if ($this->tagsMap === null) {
            $tagsMap = $this->getAllFanTagsMap();
            if ($tagsMap === false) {
                return false;
            }
            $this->tagsMap = $tagsMap;
        }

        $tag_names = [];
        foreach ($tagid_list as $tag) {
            $tag_names[] = $this->tagsMap[$tag];
        }
        return $tag_names;
    }

    /**
     * 获取粉丝id列表
     * @param string $next_openid 下一次拉取的起始id的前一个id
     * @return array|bool
     */
    public function getFanIdList($next_openid='')
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $url ="https://api.weixin.qq.com/cgi-bin/user/get?access_token={$access_token}&next_openid={$next_openid}";//重头开始拉取，一次最多拉取10000个
        $return = $this->requestAndCheck($url, 'GET');
        if ($return === false) {
            return false;
        }

        //$list[]元素：
        //total	关注该公众账号的总用户数
        //count	拉取的OPENID个数，最大值为10000
        //data	列表数据，OPENID的列表
        //next_openid	拉取列表的最后一个用户的OPENID
        //样本数据：{"total":2,"count":2,"data":{"openid":["OPENID1","OPENID2"]},"next_openid":"NEXT_OPENID"}
        return $return;
    }

    /**
     * 设置粉丝备注
     */
    public function setFanRemark($openid, $remark)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(['openid '=> $openid, 'remark' => $remark]);
        $url ="https://api.weixin.qq.com/cgi-bin/user/info/updateremark?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        return true;
    }

    /*
     * 向一个粉丝发送消息
     * 文档：https://mp.weixin.qq.com/wiki?action=doc&id=mp1421140547#2
     * @param $type string (text,news,image,voice,video,music,mpnews,wxcard)
     */
    public function sendMsgToOne($openid, $type, $content)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $data = [
            'touser' => $openid,
            'msgtype' => $type,
        ];

        if ($type == 'text') {
            $data[$type]['content'] = $content; //text
        } elseif (in_array($type, ['image', 'voice', 'mpnews'])) {
            $data[$type]['media_id'] = $content; //media_id
        } elseif ($type == 'wxcard') {
            $data[$type]['card_id'] = $content; //card_id
        } elseif ($type == 'news') {
            //$content = [{
            //     "title":"Happy Day",
            //     "description":"Is Really A Happy Day",
            //     "url":"URL",
            //     "picurl":"PIC_URL"
            //}, ...]
            $data[$type]['articles'] = $content;
        } elseif ($type == 'video') {
            //$content = {
            //    "media_id":"MEDIA_ID",
            //    "thumb_media_id":"MEDIA_ID",
            //    "title":"TITLE",
            //    "description":"DESCRIPTION"
            //}
            $data[$type] = $content;
        } elseif ($type == 'music') {
            //$content = {
            //    "title":"MUSIC_TITLE",
            //    "description":"MUSIC_DESCRIPTION",
            //    "musicurl":"MUSIC_URL",
            //    "hqmusicurl":"HQ_MUSIC_URL",
            //    "thumb_media_id":"THUMB_MEDIA_ID"
            //}
            $data[$type] = $content;
        }

        $post = $this->toJson($data);
        $url ="https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        return true;
    }

    /**
     * 指定一部分人群发消息，只有服务号可用
     * @param array|string $openids
     * @param $type string (text,image,voice,mpvideo,mpnews,wxcard)
     * @return boolean|array
     */
    public function sendMsgToMass($openids, $type, $content)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        if (is_string($openids)) {
            $openids = explode(',', $openids);
        }
        $data = [
            'touser' => $openids,
            'msgtype' => $type,
        ];

        if ($type == 'text') {
            $data[$type]['content'] = $content; //text
        } elseif (in_array($type, ['image', 'voice'])) {
            $data[$type]['media_id'] = $content; //media_id
        } elseif ($type == 'mpnews') {
            $data[$type]['media_id'] = $content; //media_id
            $data[$type]['send_ignore_reprint'] = 1;//图文消息被判定为转载时，是否继续群发。 1为继续群发（转载），0为停止群发
        } elseif ($type == 'wxcard') {
            $data[$type]['card_id'] = $content; //card_id
        } elseif ($type == 'mpvideo') {
            //$content = {
            //    "media_id":"MEDIA_ID",
            //    "title":"TITLE",
            //    "thumb_media_id":"MEDIA_ID",
            //    "description":"DESCRIPTION"
            //}
            $data[$type] = $content;
        }

        $post = $this->toJson($data);
        $url ="https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        return [
            'type' => $return['type'],//媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb），次数为news，即图文消息
            'msg_id' => $return['type'], //消息发送任务的ID
            'msg_data_id' => $return['msg_data_id'],//消息的数据ID,可以用于在图文分析数据接口中，获取到对应的图文消息的数据
        ];
    }

    /**
     * 给同一标签的所有粉丝发消息
     * @param int $tag_id 群发到的标签的tag_id, 0则表示发送给所有粉丝
     * @param $type string (text,image,voice,mpvideo,mpnews,wxcard)
     * @param mixed $content
     * @return boolean|array
     */
    public function sendMsgToAll($tag_id, $type, $content)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $data = [
            'filter' => ['is_to_all' => !boolval($tag_id), 'tag_id' => $tag_id],
            'msgtype' => $type,
        ];

        if ($type == 'text') {
            $data[$type]['content'] = $content; //text
        } elseif (in_array($type, ['image', 'voice'])) {
            $data[$type]['media_id'] = $content; //media_id
        } elseif ($type == 'mpnews') {
            $data[$type]['media_id'] = $content; //media_id
            $data[$type]['send_ignore_reprint'] = 1;//图文消息被判定为转载时，是否继续群发。 1为继续群发（转载），0为停止群发
        } elseif ($type == 'wxcard') {
            $data[$type]['card_id'] = $content; //card_id
        } elseif ($type == 'mpvideo') {
            //$content = {
            //    "media_id":"MEDIA_ID",
            //    "title":"TITLE",
            //    "thumb_media_id":"MEDIA_ID",
            //    "description":"DESCRIPTION"
            //}
            $data[$type] = $content;
        }

        $post = $this->toJson($data);
        $url ="https://api.weixin.qq.com/cgi-bin/message/mass/sendall?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        return [
            'type' => $return['type'],//媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb），次数为news，即图文消息
            'msg_id' => $return['type'], //消息发送任务的ID
            'msg_data_id' => $return['msg_data_id'],//消息的数据ID,可以用于在图文分析数据接口中，获取到对应的图文消息的数据
        ];
    }

    /**
     * 发送消息，自动识别id数
     * @param string|array $openids
     * @param $type string (text,image,voice,mpvideo,mpnews,wxcard)
     * @param mixed $content
     * @return boolean
     */
    public function sendMsg($openids, $type, $content)
    {
        if (empty($openids)) {
            return true;
        }
        if (is_string($openids)) {
            $openids = explode(',', $openids);
        }

        if (count($openids) > 1) {
            $result = $this->sendMsgToMass($openids, $type, $content);
        } else {
            $result = $this->sendMsgToOne($openids[0], $type, $content);
        }
        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * 新增媒质永久素材
     * 文档：https://mp.weixin.qq.com/wiki?action=doc&id=mp1444738729
     * @parem type $path 素材地址
     * @param string $type 类型有image,voice,video,thumb
     * @param array $param 目前是video类型需要
     * @return {"media_id":MEDIA_ID,"url":URL}
     */
    public function uploadMaterial($path, $type, $param=[])
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post_arr = ['media' => '@'.$path];
        if ($type == 'video') {
            $post_arr['description'] = $this->toJson([
                'title' => $param['title'],
                'introduction' => $param['introduction'],
            ]);
        }

        $url ="https://api.weixin.qq.com/cgi-bin/material/add_material?access_token={$access_token}&type={$type}";
        $return = $this->requestAndCheck($url, 'POST', $post_arr);
        if ($return === false) {
            return false;
        }

        return $return;
    }

    /**
     * 上传图文素材。 说明：news里面的图片只能用news_image，封面用image
     * 文档：https://mp.weixin.qq.com/wiki?action=doc&id=mp1444738729
     * @param array $articles
     *  [
     *      [
     *          "title"=> TITLE,
     *          "thumb_media_id"=> THUMB_MEDIA_ID, //封面图片素材id
     *          "author"=> AUTHOR,
     *          "digest"=> DIGEST, //图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空。如果本字段为没有填写，则默认抓取正文前64个字。
     *          "show_cover_pic"=> SHOW_COVER_PIC(0 / 1), //是否显示封面，0为false，即不显示，1为true，即显示
     *          "content"=> CONTENT, //图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS,
     *                                  涉及图片url必须来源"上传图文消息内的图片获取URL"接口获取。外部图片url将被过滤。
     *          "content_source_url"=> CONTENT_SOURCE_URL //图文消息的原文地址，即点击“阅读原文”后的URL
     *      ],
     *      //若新增的是多图文素材，则此处应还有几段articles结构(最多8段)
     *  ]
     * @return string|bool MEDIA_ID
     */
    public function uploadNews($articles)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(["articles" => $articles]);
        $url ="https://api.weixin.qq.com/cgi-bin/material/add_news?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        return $return['media_id'];
    }

    /**
     * 上传图文消息中的图片
     * 文档：https://mp.weixin.qq.com/wiki?action=doc&id=mp1444738729
     * @param string $path 图片地址
     * @return string|bool 图片的url
     */
    public function uploadNewsImage($path)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post_arr = ["media"=>'@'.$path];
        $url ="https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post_arr);
        if ($return === false) {
            return false;
        }

        return $return['url'];
    }

    /**
     * 上传临时材料（3天内有效）
     * 文档：https://mp.weixin.qq.com/wiki?action=doc&id=mp1444738726
     * @parem type $path 素材地址
     * @param string $type 类型有image,voice,video,thumb
     * @return array|bool {"type":"TYPE","media_id":"MEDIA_ID","created_at":123456789}
     */
    public function uploadTempMaterial($path, $type = 'image')
    {
        if (!($access_token = $this->getAccessToken())) {
            return false;
        }

        $post_arr = ['media' => '@'.$path];
        $url ="https://api.weixin.qq.com/cgi-bin/media/upload?access_token={$access_token}&type={$type}";
        $return = $this->requestAndCheck($url, 'POST', $post_arr);
        if ($return === false) {
            return false;
        }

        return $return;
    }

    /**
     * 更新一篇图文
     * 文档：https://mp.weixin.qq.com/wiki?action=doc&id=mp1444738732&t=0.5904919423628598
     * @param string $mediaId MEDIA_ID
     * @param array $article INDEX
    {
    "title": TITLE,
    "thumb_media_id": THUMB_MEDIA_ID,
    "author": AUTHOR,
    "digest": DIGEST,
    "show_cover_pic": SHOW_COVER_PIC(0 / 1),
    "content": CONTENT,
    "content_source_url": CONTENT_SOURCE_URL
    }
     * @param number $index 要更新的文章在图文消息中的位置（多图文消息时，此字段才有意义），第一篇为0
     * @return boolean
     */
    public function updateNews($mediaId, $article, $index = 0)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson([
            'media_id' => $mediaId,
            'index' => $index,
            'articles' => $article
        ]);

        $url ="https://api.weixin.qq.com/cgi-bin/material/update_news?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        return true;
    }

    /**
     * 获取图文素材
     * @param string $mediaId
     * @return boolean|array
     */
    public function getNews($mediaId)
    {
        $wxdata = $this->getMaterial($mediaId);
        if ($wxdata === false) {
            return false;
        }

//    [
//        [
//        title 图文消息的标题
//        thumb_media_id	图文消息的封面图片素材id（必须是永久mediaID）
//        show_cover_pic	是否显示封面，0为false，即不显示，1为true，即显示
//        author	作者
//        digest	图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空
//        content	图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS
//        url	图文页的URL
//        content_source_url	图文消息的原文地址，即点击“阅读原文”后的URL
//        ],
//        //多图文消息有多篇文章
//     ]
        return $wxdata['news_item'];
    }

    /**
     * 获取媒质素材
     * @param string $mediaId
     * @return boolean
    array video返回{
    "title":TITLE,
    "description":DESCRIPTION,
    "down_url":DOWN_URL,
    }
     */
    public function getMaterial($mediaId)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(['media_id' => $mediaId]);
        $url ="https://api.weixin.qq.com/cgi-bin/material/get_material?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return true;
    }

    /**
     * 删除素材，包括图文
     * @param string $mediaId
     * @return boolean
     */
    public function delMaterial($mediaId)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(['media_id' => $mediaId]);
        $url ="https://api.weixin.qq.com/cgi-bin/material/del_material?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        return true;
    }

    /**
     * 获取素材总数
     * @return array|bool
    //voice_count	语音总数量
    //video_count	视频总数量
    //image_count	图片总数量
    //news_count	图文总数量
     */
    public function getMaterialCount()
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $url ="https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'GET');
        if ($return === false) {
            return false;
        }

        return $return;
    }

    /**
     * 获取素材列表
     * @param string $type 素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
     * @param int $offset 从全部素材的该偏移位置开始返回，0表示从第一个素材 返回
     * @param int $count 返回素材的数量，取值在1到20之间
     * @return array|bool
     */
    public function getMaterialList($type, $offset, $count)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson([
            'type' => $type,
            'offset' => $offset,
            'count' => $count
        ]);

        $url ="https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

        /* 返回图文消息结构 */
        //{
        //  "total_count": TOTAL_COUNT,
        //  "item_count": ITEM_COUNT,
        //  "item": [{
        //      "media_id": MEDIA_ID,
        //      "content": {
        //          "news_item": [{
        //              "title": TITLE,
        //              "thumb_media_id": THUMB_MEDIA_ID,
        //              "show_cover_pic": SHOW_COVER_PIC(0 / 1),
        //              "author": AUTHOR,
        //              "digest": DIGEST,
        //              "content": CONTENT,
        //              "url": URL,
        //              "content_source_url": CONTETN_SOURCE_URL
        //          },
        //          //多图文消息会在此处有多篇文章
        //          ]
        //       },
        //       "update_time": UPDATE_TIME
        //   },
        //   //可能有多个图文消息item结构
        // ]
        //}

        /*其他类型*/
        //{
        //  "total_count": TOTAL_COUNT,
        //  "item_count": ITEM_COUNT,
        //  "item": [{
        //      "media_id": MEDIA_ID,
        //      "name": NAME,
        //      "update_time": UPDATE_TIME,
        //      "url":URL
        //  },
        //  //可能会有多个素材
        //  ]
        //}
        return $return;
    }

    /**
     * 创建临时二维码
     * @param int $expire 过期时间，单位秒，最大30天，即2592000秒
     * @param int $scene_id 场景id，用户自定义，目前支持1-100000
     * @return boolean
     */
    public function createTempQrcode($expire, $scene_id)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson([
            'expire_seconds' => $expire,
            'action_name'    => 'QR_SCENE',
            'action_info'    => [
                'scene' => [
                    'scene_id' => $scene_id
                ]
            ]
        ]);

        $url ="https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }

//        返回数据格式：
//        {
//            "ticket":"gQH47joAAAAAAAAAASxodHRwOi8vd2VpeGluLnFxLmNvbS9xL2taZ2Z3TVRtNzJXV1Brb3ZhYmJJAAIEZ23sUwMEmm3sUw==",
//            "expire_seconds":60,
//            "url":"http:\/\/weixin.qq.com\/q\/kZgfwMTm72WWPkovabbI"
//        }

        return $return;
    }

    /**
     * 获取用户所有模板消息
     * @return bool|mixed|string
     */
    public function getAllTemplateMsg()
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $url ="https://api.weixin.qq.com/cgi-bin/material/get_all_private_template?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'GET');
        if ($return === false) {
            return false;
        }

        //返回数据格式：
        //{"template_list": [{
        //    "template_id": "iPk5sOIt5X_flOVKn5GrTFpncEYTojx6ddbt8WYoV5s",
        //    "title": "领取奖金提醒",
        //    "primary_industry": "IT科技",
        //    "deputy_industry": "互联网|电子商务",
        //    "content": "{ {result.DATA} }\n\n领奖金额:{ {withdrawMoney.DATA} }\n领奖  时间:{ {withdrawTime.DATA} }\n银行信息:{ {cardInfo.DATA} }\n到账时间:  { {arrivedTime.DATA} }\n{ {remark.DATA} }",
        //    "example": "您已提交领奖申请\n\n领奖金额：xxxx元\n领奖时间：2013-10-10 12:22:22\n银行信息：xx银行(尾号xxxx)\n到账时间：预计xxxxxxx\n\n预计将于xxxx到达您的银行卡"
        //}, ...]}
        return $return;
    }

    /**
     * 添加消息模板
     * @param $template_sn string 模板编号
     * @return bool|string 模板id
     */
    public function addTemplateMsg($template_sn)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(['template_id_short' => $template_sn]);
        $url ="https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return $return['template_id'];
    }

    /**
     * 删除模板消息
     * @param $template_id string 模板id
     * @return bool
     */
    public function delTemplateMsg($template_id)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson(['template_id' => $template_id]);
        $url ="https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return true;
    }

    public function sendTemplateMsg($openid, $template_id, $url, $data)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson([
            "touser" => $openid,
            "template_id" => $template_id,
            "url" => $url, //模板跳转链接
//            "miniprogram" => [ //小程序跳转配置
//                "appid" => "xiaochengxuappid12345",
//                "pagepath" => "index?foo=bar"
//            ],
            "data" => $data, //模板数据
//            [
//                "first" =>  [
//                    "value" => "恭喜你购买成功！",
//                    "color" => "#173177"
//                ],
//                "keynote1" => [
//                    "value" => "巧克力",
//                    "color" => "#173177"
//                ],
//                "remark" => [
//                    "value" => "欢迎再次购买！",
//                    "color" => "#173177"
//                ]
//            ]
        ]);
        //注：url和miniprogram都是非必填字段，若都不传则模板无跳转；若都传，会优先跳转至小程序。
        //开发者可根据实际需要选择其中一种跳转方式即可。当用户的微信客户端版本不支持跳小程序时，将会跳转至url

        $url ="https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return true;
    }

    /**
     * 创建自定义菜单
     * 文档：https://mp.weixin.qq.com/wiki?action=doc&id=mp1421141013
     * @param $data array
     * @return bool
     */
    public function createMenu($data)
    {
        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $post = $this->toJson($data);
        $url ="https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$access_token}";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            return false;
        }
        return true;
    }

    /**
     * 获取ticket。 jsapi_ticket是公众号用于调用微信JS接口的临时票据
     * 文档 https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141115
     * @param string $type ticket类型（jsapi,wx_card）
     * @return bool
     */
    public function getTicket($type = 'jsapi')
    {
        $key = 'weixin_ticket_'.$type;
        $ticket = Cache::get($key);
        if (!empty($ticket)) {
            return $ticket;
        }

        if (!$access_token = $this->getAccessToken()) {
            return false;
        }

        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$access_token}&type={$type}";
        $return = $this->requestAndCheck($url, 'GET');
        if ($return === false) {
            return false;
        }

        Cache::set($key, $return['ticket'], 7000);
        return $return['ticket'];
    }

    /**
     * 签名
     * @param string $url
     * @return array|bool
     */
    public function getSignPackage($url = '')
    {
        $ticket = $this->getTicket();
        if ($ticket === false) {
            return false;
        }

        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = $url ?: $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$ticket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);

        $signPackage = [
            "appId" => $this->config['appid'],
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "rawString" => $string,
            "signature" => $signature
        ];
        return $signPackage;
    }

    /**
     * 随机字符串
     * @param int $length
     * @return string
     */
    private function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 推送消息处理接口
     * @return array|bool
     */
    public function getPushMessage()
    {
        $content = $GLOBALS['HTTP_RAW_POST_DATA'] ?: file_get_contents('php://input');

        $this->logDebugFile($content);

        $message = \app\common\util\XML::parse($content);
        if (empty($message)) {
            $this->setError('推送消息为空！');
            return false;
        }

        $this->logDebugFile($message);

        return $message;
    }

    /**
     * 订阅消息事件
     * @param $event_type
     * @param $callback
     */
    public function registerMsgEvent($event_type, $callback)
    {
        $this->events[$event_type] = $callback;
    }

    /**
     * 处理消息事件
     */
    public function handleMsgEvent()
    {
        $msg = $this->getPushMessage();
        if (!$msg) {
            exit($this->getError());
        }

        // 先处理全局事件
        if (isset($this->events[self::EVENT_ALL]) && is_callable($this->events[self::EVENT_ALL])) {
            $this->events[self::EVENT_ALL]($msg);
        }

        static $event_parse = [
            self::EVENT_TEXT        => ['MsgType' => 'text'],
            self::EVENT_SUBSCRIBE   => ['MsgType' => 'event', 'Event' => 'subscribe'],
            self::EVENT_UNSUBSCRIBE => ['MsgType' => 'event', 'Event' => 'unsubscribe'],
            self::EVENT_SCAN        => ['MsgType' => 'event', 'Event' => 'SCAN'],
            self::EVENT_LOCATION    => ['MsgType' => 'event', 'Event' => 'LOCATION'],
            self::EVENT_CLICK       => ['MsgType' => 'event', 'Event' => 'CLICK'],
            self::EVENT_VIEW        => ['MsgType' => 'event', 'Event' => 'VIEW'],
        ];

        // 找出注册的事件并处理
        foreach ($this->events as $event => $callback) {
            if ( ! isset($event_parse[$event])) {
                continue;
            }

            $find_event = true;
            foreach ($event_parse[$event] as $key => $word) {
                if ($msg[$key] !== $word) {
                    $find_event = false;
                    break;
                }
            }
            if ( ! $find_event) {
                continue;
            }

            is_callable($callback) && $callback($msg);
            break;
        }
        
        if($msg=='1' || $msg[$key] == 'LOCATION') return;
        $return= $this->createReplyMsgOfText($msg['ToUserName'], $msg['FromUserName'], '你已关注公众号');
        exit($return);
    }

    /**
     * 创建文本回复消息
     * @param string $fromUser
     * @param string $toUser
     * @param string $text
     * @return string
     */
    public function createReplyMsgOfText($fromUser, $toUser, $text)
    {
        $time = time();
        $template =
            "<xml>
            <ToUserName><![CDATA[$toUser]]></ToUserName>
            <FromUserName><![CDATA[$fromUser]]></FromUserName>
            <CreateTime>$time</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[$text]]></Content>
            </xml>";
        return $template;
    }

    /**
     * 创建图片回复消息
     * @param string $fromUser
     * @param string $toUser
     * @param string $mediaId
     * @return string
     */
    public function createReplyMsgOfImage($fromUser, $toUser, $mediaId)
    {
        $time = time();
        $template =
            "<xml>
            <ToUserName><![CDATA[$toUser]]></ToUserName>
            <FromUserName><![CDATA[$fromUser]]></FromUserName>
            <CreateTime>$time</CreateTime>
            <MsgType><![CDATA[image]]></MsgType>
            <Image>
            <MediaId><![CDATA[$mediaId]]></MediaId>
            </Image>
            </xml>";
        return $template;
    }

    /**
     * 创建图文回复消息
     * @param string $fromUser
     * @param string $toUser
     * @param array $articles
     * @return string
     */
    public function createReplyMsgOfNews($fromUser, $toUser, $articles)
    {
        $articles = array_slice($articles, 0, 7);//最多支持7个
        $num = count($articles);
        if (!$num) {
            return '';
        }

        $itemTpl = '';
        foreach ($articles as $item) {
            $itemTpl .=
            "<item>
            <Title><![CDATA[{$item['title']}]]></Title> 
            <Description><![CDATA[{$item['description']}]]></Description>
            <PicUrl><![CDATA[{$item['picurl']}]]></PicUrl>
            <Url><![CDATA[{$item['url']}]]></Url>
            </item>";
        }

        $time = time();
        $template =
            "<xml>
            <ToUserName><![CDATA[$toUser]]></ToUserName>
            <FromUserName><![CDATA[$fromUser]]></FromUserName>
            <CreateTime>$time</CreateTime>
            <MsgType><![CDATA[news]]></MsgType>
            <ArticleCount>$num</ArticleCount>
            <Articles>$itemTpl</Articles>
            </xml>";
        return $template;
    }
}