<?php

namespace app\admin\controller;

use \think\Db;
use \think\Request;
use app\common\model\Wx;

/**
 * 微信菜单管理
 */
class Wxmenu extends Common
{
    /**
     * 菜单列表
     */
    public function index()
    {
        $weixin = new Wx();

        $list = $weixin->getTree();

        $this->assign('list', $list);
        $this->meta_title = '微信菜单设置';
        return view();
    }

    /**
     * 新增菜单
     */
    public function addmenu()
    {
        if (Request::instance()->isPost()) {
            $pid = input('pid/i', 0);
            if (!$pid) {
                $count = Db::table('wx_menu')->where(['pid'=>0])->count();
                if ($count >= 3) {
                    $this->error('顶级菜单最多只能添加3个');
                }
            } else {
                $count = Db::table('wx_menu')->where(['pid'=>$pid])->count();
                if ($count >= 7) {
                    $this->error('二级菜单最多只能添加7个');
                }
            }

            $weixin = new Wx();
            if (!$weixin->updatemenu()) {
                $this->error($weixin->getError());
            } else {
                $this->success('添加成功');
            }
        }
    }

    /**
     * 编辑菜单
     */
    public function editmenu($id = 0)
    {
        if (!$id) {
            $this->error('参数错误');
        }
        if (Request::instance()->isPost()) {
            $weixin = new Wx();
            if (!$weixin->updatemenu()) {
                $this->error($weixin->getError());
            } else {
                $this->success('编辑成功');
            }
        }

        $menuinfo = Db::table('wx_menu')->where(['id'=>$id])->find();
        // $this->ajaxReturn($menuinfo);
        return $menuinfo;
    }

    /**
     * 删除菜单
     */
    public function delmenu()
    {
        $id = input('id/i', 0);
        $info = Db::table('wx_menu')->where(['id'=>$id])->find();
        $where['id'] = $id;
        if ($info['pid'] == 0) {
            $where['pid'] = $id;
            $where['_logic'] = 'OR';
        }
        if (Db::table('wx_menu')->where($where)->delete()) {
            $this->success('操作成功');
        }

        $this->error('操作失败');
    }

    /**
     * 生成(同步)菜单
     */
    public function makemenu()
    {
        $weixin = new Wx();

        /**微信接口配置*/
        if ($weixin->makemenu()) {
            $this->success('操作成功');
        }

        $this->error('操作失败');
    }
}
