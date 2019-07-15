<?php
namespace app\api\model;

use app\common\model\Advertisement;
use app\common\model\Broadcast;
use think\Db;
use think\Model;

/**
 * 通知信息管理
 */
class Notice extends Model
{
    /**
     * 获取广播信息
     */
    public static function getBroadcast()
    {
        $where = [
            'status' => 1,
            'type'   => 1,
        ];

        $data = Broadcast::where($where)
            ->order('id DESC')
            ->field('content')
            ->find();

        if ($data) {
            return [$data, 0, ''];
        }

        return [[], 1, '广播信息获取失败！'];
    }

    /*
     * 获取公告列表
     */
    public static function getNoticeList()
    {
        $uid = think_decrypt(input('token'));
        if (!$uid) {
            return ['', 1, 'token已失效'];
        }
        $notice_id = input('notice_id/d', 0);
        if (!$uid && !$notice_id) {
            return ['', 1, '数据传输为空'];
        }

        if ($notice_id) {
            $data = Db::table('notice')
                ->master()
                ->where('notice_id', $notice_id)
                ->where('status', 1)
                ->field('title, content')
                ->find();
            if (!$data) {
                return ['', 1, '公告内容为空！'];
            }

        } else {
            $data = Db::table('notice')
                ->master()
                ->field('notice_id,title,content,create_time,popup_num')
                ->where('status', 1)
                ->order('create_time DESC')
                ->select();

            $notice_user = Db::table('notice_user')->where('uid', $uid)->master()->column('read_num', 'notice_id');
            if (!$data) {
                return ['', 1, '公告列表为空！'];
            }

            foreach ($data as &$val) {
                $val['is_popup']    = ($val['popup_num'] != 0 && (!isset($notice_user[$val['notice_id']]) || $val['popup_num'] > $notice_user[$val['notice_id']])) ? 1 : 0;
                $val['is_read']     = isset($notice_user[$val['notice_id']]) || $val['is_popup'] ? 1 : 0;
                $val['create_time'] = date('m-d H:i', $val['create_time']);

                if ($val['popup_num'] != 0 && !isset($notice_user[$val['notice_id']])) {
                    $n_user_data = [
                        'notice_id' => $val['notice_id'],
                        'uid'       => $uid,
                        'last_time' => time(),
                    ];
                    $result = Db::table('notice_user')->insert($n_user_data);
                } else if ($val['popup_num'] != 0 && $val['popup_num'] > $notice_user[$val['notice_id']]) {
                    $result = Db::table('notice_user')
                        ->where('notice_id', $val['notice_id'])
                        ->where('uid', $uid)
                        ->update(['last_time' => time(), 'read_num' => Db::raw('read_num+1')]);
                }
                unset($val['popup_num']);
            }
        }
        return [$data, 0, ''];
    }

    /**
     * 公告阅读标记
     */
    public static function notice_read()
    {
        $notice_id = input('notice_id/d', 0);

        $uid = think_decrypt(input('token'));
        if (!$uid) {
            return ['', 1, 'token已失效'];
        }
        if (!$uid || !$notice_id) {
            return ['', 1, '数据传输为空'];
        }

        $id = Db::table('notice_user')->where('notice_id', $notice_id)->master()->where('uid', $uid)->value('id');
        if ($id) {
            $result = Db::table('notice_user')->where('id', $id)->update(['last_time' => time(), 'read_num' => Db::raw('read_num+1')]);
        } else {
            $data = [
                'notice_id' => $notice_id,
                'uid'       => $uid,
                'last_time' => time(),
            ];
            $result = Db::table('notice_user')->insert($data);
        }

        if ($result > 0) {
            return ['', 0, '已读标记成功！'];
        }

        return ['', 1, '已读标记失败！'];
    }

    /**
     * 轮播公告
     */
    public static function fixedPicture()
    {
        $data = Advertisement::order('sort desc')->column('picture');
        foreach ($data as &$val) {
            $val = 'http://' . $_SERVER['HTTP_HOST'] . $val;
        }

        return [$data, 0, ''];
    }
}
