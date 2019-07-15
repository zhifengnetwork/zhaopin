<?php

namespace app\admin\controller;

use \think\Db;
use \think\Request;
use app\common\model\Wx;

/**
 * 公众号粉条管理控制器
 */
class Wxfans extends Common
{
    /**
     * 粉丝列表
     */
    public function index()
    {
        //关键字
        $kw = input('kw', '');
        $state = input('state', '1');
        $start = input('start', '');
        $end = input('end', '');

        $where = [];
        if ($kw) {
            $where['wx_nickname'] = ['LIKE', '%'.$kw.'%'];
        }
        if ($state >= 0) {
            $where['state'] = $state;
        } else if ($state == -2) { //已取消
            $where['state'] = 0;
            $where['subscribe_time'] = ['GT', 0];
        }
        $this->assign('kw', $kw);
        $this->assign('state', $state);
        $this->assign('start', $start);
        $this->assign('end', $end);
        //关注时间
        $subscribe_stime = strtotime($start);
        $subscribe_etime = strtotime($end) + 86400 - 1;
        $start && $where['subscribe_time'][] = array('egt', $subscribe_stime);
        $end && $where['subscribe_time'][] = array('elt', $subscribe_etime);
        //分组
        $group_id = input('group_id', -1, 'intval');
        if ($group_id > -1) {
            $where['groupid'] = $group_id;
        }
        $this->assign('group_id', $group_id);
        $list = Db::table('user')->where($where)->order('subscribe_time DESC')->paginate(20,false,['query'=>[
                'start'     => $start,
                'end'     => $end,
                'kw'     => $kw,
                'state'     => $state,
            ]]);

        // 获取分页显示
        $page = $list->render();
        $this->assign('_list', $list);
        $this->assign('groups', get_fans_group());
        $this->assign('page', $page);
        $this->assign('meta_title', '微信粉丝');
        return view();
    }

    /**
     * 生成菜单
     */
    public function make_menu()
    {
        $weixin = new Wx();
        $weixin->makemenu_1();
    }

    /**
     * 分组列表
     */
    public function group_list()
    {
    }

    /**
     * 添加分组
     */
    public function add_group()
    {
        if (Request::instance()->isPost()) {
            $name = input('name');
            $data = array(
                'name' => $name
            );
            $weixin = new Wx();
            if ($weixin->add_group($data)) {
                $this->success('添加成功');
            } else {
                $this->error('添加失败');
            }
        }
        $this->display();
    }

    /**
     * 添加分组
     */
    public function edit_group()
    {
        if (Request::instance()->isPost()) {
            $g_id = input('id');
            $name = input('name');
            $weixin = new Wx();
            if ($weixin->edit_group($g_id, $name)) {
                $this->success('编辑成功');
            } else {
                $this->error('编辑失败');
            }
        }
        $this->display();
    }

    /**
     * 删除分组
     */
    public function del_group()
    {
        $g_id = input('g_id');
        $weixin = new Wx();
        $res = $weixin->del_group($g_id);
        if ($res['ret'] == 0) {
            $this->success('删除成功');
        }
        $this->error($weixin->errormsg($res['ret']));
    }

    /**
     * 同步微信分组
     */
    public function sync_group()
    {
        $weixin = new Wx();
        if (!$weixin->sync_group()) {
            $this->error('同步失败');
        }

        $this->success('同步成功');
    }

    /**
     * 同步粉丝
     */
    public function sync_fans()
    {
        $pg = input('pg', 1); 
        $weixin = new Wx();
        $res = $weixin->sync_fans();
        if (!$res) {
            $this->error('同步完成');
        } else {
            $offset = $weixin->offset();
            $count = $weixin->get_fans_coount();

            $this->success(num_fmt((($res+$offset)/$count*100)).'%');
        }
    }

    /**
     * 移动粉丝到指定分组
     */
    public function move_fans()
    {
        $openids = input('openid');
        $g_id = input('g_id');
        $weixin = new Wx();
        if ($weixin->move_fans($openids, $g_id)) {
            $this->success('移动成功');
        }
        $this->error('移动失败');
    }

    /**
     * 编辑粉丝备注
     */
    public function edit_remark()
    {
        $uid = input('uid/i', 0);
        $remark = input('remark', '');

        if ( Db::table('user')->where( array('uid'=>$uid) )->save( array('remark'=>$remark) ) !== false ) {
            $this->success('编辑成功！');
        } else {
            $this->error('编辑失败！');
        }
    }
}
