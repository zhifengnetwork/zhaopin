<?php

namespace app\common\model;

use Overtrue\Wechat\Broadcast;
use Overtrue\Wechat\Exception;
use Overtrue\Wechat\Group;
use Overtrue\Wechat\Media;
use Overtrue\Wechat\Menu; //用户
use Overtrue\Wechat\MenuItem; //用户分组
use Overtrue\Wechat\Message; //服务
use Overtrue\Wechat\Server; //消息
use Overtrue\Wechat\Staff; //客服
use Overtrue\Wechat\User; //多媒体



use think\Db; //群发
use think\Model; //菜单
use \think\Cache; //菜单项
use \think\Config;

//异常

/**
 * 微信模型
 */
class Wx extends Model
{
    const LIMIT = 10;

    public function __construct()
    {

        //将微信配置放至后台管理
        $weixin_pay_arr = Config::get('wx_config');

        $this->appId  = $weixin_pay_arr['appid'];
        $this->secret = $weixin_pay_arr['appsecret'];
    }

    public function getlimit()
    {
        //内部访问常量
        return self::LIMIT;
    }

    public function errormsg($code)
    {
        $Exception = new Exception('', $code);
        return $Exception->getMessage();
    }

    /**
     * 上传图片永久素材
     */
    public function upload_image($path)
    {
        $media = new Media($this->appId, $this->secret);
        try {
            $image = $media->forever()->image(SITE_PATH . $path);
        } catch (Exception $e) {
            return false;
        }
        if ($image) {
            $data = array(
                'addtime'  => time(),
                'url'      => $image['url'],
                'path'     => $path,
                'media_id' => $image['media_id'],
            );
            Db::table('wx_media')->insert($data);
        }

        return $image;
    }
    /**
     * 获取图片素材微信地址
     */
    public function get_img_media($data, $field = 'path, url')
    {
        return Db::table('wx_media')->where(array('path' => array('in', $data)))->getField($field);
    }

    /**
     * 发送文本消息
     */
    public function send_text()
    {
    }

    /**
     * 删除永久素材
     */
    public function del_media($media_id)
    {
        try {
            $media = new Media($this->appId, $this->secret);
            $media->delete($media_id);
            Db::table('wx_media')->where(array('media_id' => $media_id))->delete();
        } catch (Exception $e) {
            return array('ret' => $e->getCode());
        }
        return array('ret' => 0);
    }

    /**
     * 生成或修改图文素材
     */
    public function make_news($data, $media_id, $art_ids)
    {
        $article = array();
        foreach ($data as $k => $v) {
            $article[] = array(
                'title'              => $v['title'],
                'thumb_media_id'     => $v['thumb_media_id'],
                'author'             => $v['author'],
                'digest'             => $v['intro'],
                'content'            => $v['wx_content'],
                'show_cover_pic'     => 0, //内容不显示封面
                'content_source_url' => '',
            );
        }

        if ($media_id && count($article) == count($art_ids)) {
            //存在媒体ID 并且 图文数量一致则编辑
            foreach ($article as $key => $val) {
                $media = new Media($this->appId, $this->secret);
                try { $media->updateNews($media_id, $val, $key);} catch (Exception $e) {return array('ret' => $e->getCode());}
            }
        } else {
            $media = new Media($this->appId, $this->secret);

            try {
                $media    = new Media($this->appId, $this->secret);
                $media_id = $media->news($article);
                //新增一条
                $add = array(
                    'type'     => 1,
                    'addtime'  => time(),
                    'media_id' => $media_id,
                );
                if (!Db::table('wx_media')->insert($add)) {

                }
            } catch (Exception $e) {
                return array('ret' => $e->getCode());
            }
        }
        return array('ret' => 0, 'data' => $media_id);
    }

    public function makemenu_1()
    {
        $menuService = new Menu($this->appId, $this->secret);
        $menus       = array(
            new MenuItem("商家后台", 'view', 'http://' . $_SERVER['HTTP_HOST'] . '/index.php/admin/'),
        );

        try {
            $menuService->set($menus); // 请求微信服务器
            echo '设置成功！';
        } catch (\Exception $e) {
            echo '设置失败：' . $e->getMessage();
        }
    }

    public function make_msg($info)
    {
        if ($info['msgtype'] == 1) {
            try {
                $message = Message::make('mp_news')->media_id($info['media_id']); //图文
            } catch (Exception $e) {
                return array('ret' => $e->getCode());
            }
        } else {
            try {
                $message = Message::make('text')->content($info['content']);
            } catch (Exception $e) {
                return array('ret' => $e->getCode());
            }
        }
        return array('ret' => 0, 'data' => $message);
    }

    /**
     * 通过客服接口发送
     *
     */
    public function send_by_staff($info)
    {
        $ret = $this->make_msg($info);

        if ($ret['ret']) {
            return $ret;
        }

        $message = $ret['data'];

        $staff = new Staff($this->appId, $this->secret);

        //发送对象处理
        $where = array();
        if ($info['send_item'] == 2) {
            $opendids = explode(',', $info['send_openids']);
        } else {
            if ($info['send_item'] == 1) {
                $where = array('groupid' => $info['group_id']);
            }

            $where['event_time'] = array('gt', time() - (86400 * 2));
            $opendids            = Db::table('wx_fans')->where($where)->getField('openid', true);
        }
        $all   = count($opendids);
        $error = 0;
        foreach ($opendids as $key => $openId) {
            try {
                $staff->send($message)->to($openId);
            } catch (Exception $e) {
                $error++;
            }
        }
        $success = $all - $error;
        Db::table('wx_message')->where(array('id' => $info['id']))->save(array('send_success_num' => $success, 'send_error_num' => $error));

        return array('ret' => 0);
    }
    /**
     * 通过群发接口发送
     */
    public function send_by_broadcast($info)
    {
        $ret = $this->make_msg($info);
        if ($ret['ret']) {
            return $ret;
        }

        $message = $ret['data'];

        $broadcast = new Broadcast($this->appId, $this->secret);
        $send_item = null;
        if ($info['send_item'] == 2) {
            $send_item = explode(',', $info['send_openids']);
        } elseif ($info['send_item'] == 1) {
            $send_item = $info['group_id'];
        }
        try {
            $broadcast->send($message)->to($send_item);
        } catch (Exception $e) {
            return array('ret' => $e->getCode());
        }

        return array('ret' => 0);
    }

    /**
     * 发送指定消息
     */
    public function send_msg($id)
    {
        $info = Db::table('wx_message')->where(array('id' => $id))->find();
        if ($info['send_type'] == 0) {
            $ret = $this->send_by_staff($info);
        } else {
            return $this->send_by_broadcast($info);
        }
    }

    /**
     * 发送图文消息
     */
    public function send_news_item()
    {
        //$media->image($path);
        $media = new Media($this->appId, $this->secret);
        //$image = $media->forever()->image('D:\Visual-NMP-x64\www\mooga_erp_trunk\Public\static\images\shop-avatar-64.png');
        //var_dump($image);die;

        try {
            $message = Message::make('mp_news')->media_id('-nikJtJ3FfCS-qtEWvxEE-jg8imXpFc5RSu3fFkaksE6'); //图文
        } catch (Exception $e) {
            $this->error('545');
            //echo $e->getMessage();
        }

        // $news_item = $media->news($article);
        // var_dump($news_item);die;
        // $news = Message::make('news')->items(function(){
        //     return array(
        //         Message::make('news_item')->media('-nikJtJ3FfCS-qtEWvxEE77OYJKLhXFRWYLwzB-IHKA'),
        //     );
        // });

        // $news = Message::make('news')->items(function(){
        //     return array(
        //             Message::make('news_item')->title('水电费水电费水电费水电费谁的')->description('好不好？')->url('http://shop.mooga.cn')->picUrl('https://mmbiz.qlogo.cn/mmbiz/V5RTXylhppALPptK23AoF6LyMwfqtMTibtweY5bLFfnKfqiblbVZq3XVattJsQpB5kqHJ4e3MRSY9Vkle75tibwVA/0?wx_fmt=png'),
        //             Message::make('news_item')->title('大佛山市的鬼地方个地方官回复的回复的挂号费过')->url('http://shop.mooga.cn')->picUrl('https://mmbiz.qlogo.cn/mmbiz/V5RTXylhppALPptK23AoF6LyMwfqtMTibtweY5bLFfnKfqiblbVZq3XVattJsQpB5kqHJ4e3MRSY9Vkle75tibwVA/0?wx_fmt=png'),
        //             Message::make('news_item')->title('水电费水电费水电费水电费是电风扇等')->description('好不好？水电费水电费水电费的谁发是电风扇等')->url('http://shop.mooga.cn')->picUrl('https://mmbiz.qlogo.cn/mmbiz/V5RTXylhppALPptK23AoF6LyMwfqtMTibtweY5bLFfnKfqiblbVZq3XVattJsQpB5kqHJ4e3MRSY9Vkle75tibwVA/0?wx_fmt=png'),

        //             );
        // });
        $staff = new Broadcast($this->appId, $this->secret);
        //array('oha86vyoD99E6vXyYh7yOHCIjjZw','oha86v1gVNODvPyvSUskQVjz8j9A')
        try {
            $msgId = $staff->send($message)->preview('oha86vyoD99E6vXyYh7yOHCIjjZw');
        } catch (Exception $e) {
            return $e->getCode();
        }
        //var_dump($msgId);
        //echo print_r($staff->status(2722413722));
    }

    public function get_fans_coount()
    {
        $all_fans_opendid = Cache::get('weixin_fans_openid');
        if (!$all_fans_opendid) {
            $userService      = new User($this->appId, $this->secret);
            $fans             = $userService->lists(null);
            $all_fans_opendid = $fans->data['openid'];

            Cache::set('weixin_fans_openid', $all_fans_opendid, 300);
        }

        return count($all_fans_opendid);
    }

    public function offset()
    {
        $pg     = input('pg', 1);
        $offset = ($pg - 1) * self::LIMIT;
        return $offset;
    }

    /**
     * 获取所有微信粉丝
     */
    public function get_fans()
    {
        $all_fans_opendid = Cache::get('weixin_fans_openid');
        $userService      = new User($this->appId, $this->secret);

        if (!$all_fans_opendid) {
            $fans             = $userService->lists(null);
            $all_fans_opendid = $fans->data['openid'];
            Cache::set('weixin_fans_openid', $all_fans_opendid, 300);
        }
        $fans_list = array();

        //print_r($all_fans_opendid);die;

        $offset = $this->offset();

        $openids = array_slice($all_fans_opendid, $offset, self::LIMIT);

        if (!empty($openids)) {
            $fans_list = $userService->batchGet($openids);
        }

        return $fans_list;
    }

    public function save_one_fans($openid)
    {
        $userinfo     = Db::table('user')->where(['wx_openid' => $openid])->find();
        $userService  = new User($this->appId, $this->secret);
        $wx_user_info = $userService->get($openid);
        $data = [
            'wx_openid'      => $wx_user_info['openid'],
            'wx_nickname'    => $wx_user_info['nickname'],
            'wx_headimgurl'  => $wx_user_info['headimgurl'],
            'province'       => $wx_user_info['province'],
            'city'           => $wx_user_info['city'],
            'sex'            => $wx_user_info['sex'],
            'subscribe_time' => $wx_user_info['subscribe_time'],
            'groupid'        => $wx_user_info['groupid'],
            'state'          => 1,
            'update_time'    => time(),
        ];
        if ($userinfo) {
            if (empty($userinfo['first_subscribe_time'])) {
                $data['first_subscribe_time'] = time();
            }
            Db::table('user')->where(array('wx_openid' => $openid))->update($data);
        } else {
            $data['first_subscribe_time'] = time();
            $data['create_time']          = time();
            Db::table('user')->insert($data);
        }
    }

    public function update_visit_time($openid)
    {
        $where['openid'] = $openid;

        $data               = array();
        $data['event_time'] = time(); //更新活跃时间

        if (Db::table('wx_fans')->where($where)->find()) {
            Db::table('wx_fans')->where($where)->save($data);
        }
    }

    /**
     * 同步粉丝
     */
    public function sync_fans()
    {
        $fans_list = $this->get_fans();
        if (!empty($fans_list)) {
            foreach ($fans_list as $k => $v) {
                $f_info = Db::table('user')->where(array('wx_openid' => $v['openid']))->find();
                //print_r($f_info);die;
                if ($f_info) {
                    $data = [
                        'wx_nickname'    => $v['nickname'],
                        'wx_headimgurl'  => $v['headimgurl'],
                        'province'       => $v['province'],
                        'city'           => $v['city'],
                        'sex'            => $v['sex'],
                        'subscribe_time' => $v['subscribe_time'],
                        'groupid'        => $v['groupid'],
                        'state'          => 1,
                        'update_time'    => time(),
                    ];
                    Db::table('user')->where(array('wx_openid' => $v['openid']))->update($data);
                } else {
                    $data = [
                        'wx_openid'      => $v['openid'],
                        'wx_nickname'    => $v['nickname'],
                        'wx_headimgurl'  => $v['headimgurl'],
                        'province'       => $v['province'],
                        'city'           => $v['city'],
                        'sex'            => $v['sex'],
                        'subscribe_time' => $v['subscribe_time'],
                        'groupid'        => $v['groupid'],
                        'state'          => 1,
                        'create_time'    => time(),
                        'update_time'    => time(),
                    ];
                    Db::table('user')->insert($data);
                }
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * 添加分组
     */
    public function add_group($data)
    {
        $group = new Group($this->appId, $this->secret);
        $res   = $group->create($data['name']);
        if (!$res) {
            return false;
        }
        $data['wechatgroupid'] = $res['id'];

        return Db::table('user_group')->insert($data);
    }

    /**
     * 编辑分组
     */
    public function edit_group($g_id, $name)
    {
        $group = new Group($this->appId, $this->secret);
        $res   = $group->update($g_id, $name);
        if (!$res) {
            return false;
        }
        $data['name'] = $name;
        return (Db::table('user_group')->where(array('wechatgroupid' => $g_id))->update($data) !== false);
    }

    /**
     * 删除分组
     */
    public function del_group($g_id)
    {
        $group = new Group($this->appId, $this->secret);

        try {
            $group->delete($g_id); //删除分组
        } catch (Exception $e) {
            if ($e->getCode() != 40152) {
                return array('ret' => $e->getCode());
            }
        }

        Db::table('user_group')->where(array('wechatgroupid' => $g_id))->delete();

        Db::table('user')->where(array('groupid' => $g_id))->update(array('groupid' => 0));

        return array('ret' => 0);
    }

    /**
     * 移动粉丝
     */
    public function move_fans($openids, $g_id)
    {
        $group = new Group($this->appId, $this->secret);
        $res   = $group->moveUsers($openids, $g_id);
        if (!$res) {
            return false;
        }
        $this->sync_group();
        return (Db::table('wx_fans')->where(array('openid' => array('in', $openids)))->save(array('groupid' => $g_id)) !== false);
    }

    /**
     * 同步微信粉丝分组
     */
    public function sync_group()
    {
        $mj_groups = Db::table('user_group')->select();

        $g_1 = $g_2 = array(); //g_1已提交微信组,g_2未提交微信组
        if (!empty($mj_groups)) {
            foreach ($mj_groups as $k => $v) {
                if ($v['wechatgroupid'] === '') {
                    $g_2[$v['id']] = $v['name'];
                } else {
                    $g_1[$v['wechatgroupid']] = $v['name'];
                }
            }
        }
        $group     = new Group($this->appId, $this->secret);
        $wx_groups = $group->lists();

        //微信分组名与本地已有分组名不同，更新本地至微信
        foreach ($wx_groups as $k => $v) {
            if (!empty($g_1[$v['id']])) {
                if ($v['name'] != $g_1[$v['id']]) {
                    $group->update($v['id'], $g_1[$v['id']]);
                }

                Db::table('user_group')->where(array('wechatgroupid' => $v['id']))->update(array('fanscount' => $v['count']));
            } else {
                $data = array(
                    'wechatgroupid' => $v['id'],
                    'name'          => $v['name'],
                    'fanscount'     => $v['count'],
                );
                Db::table('user_group')->insert($data);
            }
        }
        if (!empty($g_2)) {
            foreach ($g_2 as $k => $v) {
                //创建微信分组
                $res = $group->create($v);
                if ($res) {
                    Db::table('user_group')->where(array('id' => $k))->update(array('wechatgroupid' => $res['id']));
                }
            }
        }

        return true;
    }

    /**
     * 获取微信菜单
     * @return array          分类树
     */
    public function getTree()
    {
        /* 获取所有分类 */
        $map  = array('state' => 0);
        $list = Db::table('wx_menu')->where($map)->order('msort')->select();
        $list = list_to_tree($list);
        /* 获取返回数据 */
        if (isset($info)) {
            //指定分类则返回当前分类极其子分类
            $info['child'] = $list;
        } else {
            //否则返回所有分类
            $info = $list;
        }

        return $info;
    }

    /**
     * 读取菜单
     */
    public function getmenu()
    {
        $menuService = new Menu($this->appId, $this->secret);
        $list        = $menuService->get();

        print_r($list);
    }

    /**
     * 更新分类信息
     */
    public function updatemenu()
    {
        $data = input('post.');
        if (!$data) {
            //数据对象创建错误
            return false;
        }

        /* 添加或更新数据 */
        if (empty($data['id'])) {
            $res = Db::table('wx_menu')->insert($data); //添加
        } else {
            $res = Db::table('wx_menu')->update($data) !== false;
        }

        return $res;
    }

    /**
     * 更新菜单
     */
    public function makemenu()
    {
        /**微信接口配置*/
        $menuService = new Menu($this->appId, $this->secret);
        $list        = Db::table('wx_menu')->where(array('state' => 0))->order('msort ASC')->select();
        if (empty($list)) {
            return;
        }

        $list  = list_to_tree($list);
        $menus = array();
        foreach ($list as $key => $val) {
            if (empty($val['_child'])) {
                $menus[] = new MenuItem($val['title'], 'view', $val['url']);
                continue;
            }
            $button = new MenuItem($val['title']);

            $m_child = array();

            foreach ($val['_child'] as $k => $v) {
                $m_child[] = new MenuItem($v['title'], 'view', $v['url']);
            }
            $menus[] = $button->buttons($m_child);
        }
        try {
            $menuService->set($menus); // 请求微信服务器
            return true;
        } catch (\Exception $e) {
            echo '设置失败：' . $e->getMessage();die;
            return false;
        }
    }

    /**
     * 获取关注公众号的粉丝列表
     * 当公众号关注者数量超过10000时，可通过填写next_openid的值，从而多次拉取列表的方式来满足需求
     *
     * @author dwer
     * @date   2017-10-13
     *
     * @param  string $nextOpenid
     * @return array
     */
    public function getOpenIdList($nextOpenid = null)
    {
        $userService = new User($this->appId, $this->secret);
        $fans        = $userService->lists($nextOpenid);

        $all_fans_opendid = $fans->data['openid'];

        $total         = $fans->total;
        $count         = $fans->count;
        $resNextOpenid = $fans->next_openid;
        $list          = $fans->data['openid'];

        return ['total' => $total, 'count' => $count, 'next_openid' => $resNextOpenid, 'list' => $list];
    }

    /**
     * 同步粉丝，同时将数据写入库中
     * @author dwer
     * @date   2017-10-13
     *
     * @param  array $openIdList
     * @return bool
     */
    public function syncFansData($openIdList)
    {
        if (!$openIdList || !is_array($openIdList)) {
            return false;
        }

        $userService = new User($this->appId, $this->secret);
        $fansList    = $userService->batchGet($openIdList);

        if ($fansList) {
            foreach ($fansList as $k => $v) {
                $userInfo = Db::table('user')->where(array('wx_openid' => $v['openid']))->find();
                if ($userInfo) {
                    if ($v['subscribe'] == 1) {
                        $data = [
                            'wx_nickname'    => $v['nickname'],
                            'wx_headimgurl'  => $v['headimgurl'],
                            'province'       => $v['province'],
                            'city'           => $v['city'],
                            'sex'            => $v['sex'],
                            'subscribe_time' => $v['subscribe_time'],
                            'groupid'        => $v['groupid'],
                            'state'          => 1,
                            'update_time'    => time(),
                        ];
                    } else {
                        $data = [
                            'state'       => 0,
                            'update_time' => time(),
                        ];
                    }

                    Db::table('user')->where(array('wx_openid' => $v['openid']))->update($data);
                } else {
                    //用户没有关注，直接跳过
                    if ($v['subscribe'] == 0) {
                        continue;
                    }

                    $data = [
                        'wx_openid'      => $v['openid'],
                        'wx_nickname'    => $v['nickname'],
                        'wx_headimgurl'  => $v['headimgurl'],
                        'province'       => $v['province'],
                        'city'           => $v['city'],
                        'sex'            => $v['sex'],
                        'subscribe_time' => $v['subscribe_time'],
                        'groupid'        => $v['groupid'],
                        'state'          => 1,
                        'create_time'    => time(),
                        'update_time'    => time(),
                    ];
                    Db::table('user')->insert($data);
                }
            }
        }

        return count($fansList);
    }

}
