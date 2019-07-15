<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 菜单管理控制器
 */
class Menu extends Common
{
    /**
     * 菜单列表
     */
    public function index()
    {
        $menu_list      = Db::table('menu')->order('sort ASC')->select();
        $menu_list_tree = list_to_tree($menu_list);
        $this->assign('menu_list_tree', $menu_list_tree);
        $this->assign('meta_title', '菜单列表');
        return $this->fetch();
    }

    /**
     * 新增菜单
     */
    public function edit()
    {
        $id = input('id', 0);
        $this->assign('id', $id);

        if (Request::instance()->isPost()){
            $id     = input('id', 0);
            $title  = input('title', '');
            $pid    = input('pid', 0);
            $sort   = input('sort', 0);
            $url    = input('url', '');
            $hide   = input('hide', 0);
            $icon   = input('icon', '');
            $status = input('status', 1);
            $data = [
                'title'  => $title,
                'pid'    => $pid,
                'sort'   => $sort,
                'url'    => $url,
                'hide'   => $hide,
                'icon'   => $icon,
                'status' => $status,
            ];
            if ($id) {
                if (Db::table('menu')->where('id', $id)->update($data) !== false) {
                    session('ALL_MENU_LIST', null);
                    $this->success('编辑成功', url('admin/menu/index'));
                }
                $this->error('编辑失败');
            } else {
                $id = Db::table('menu')->insert($data, false, true);
                if ($id) {
                    session('ALL_MENU_LIST', null);
                    $this->success('添加成功', url('admin/menu/edit', ['id' => $id]));
                }
                $this->error('添加失败');
            }
        }
        $info        = [];
        $id && $info = Db::table('menu')->where('id', $id)->find();
        $this->assign('info', $info);
        $this->assign('meta_title', $id ? '编辑菜单' : '新增菜单');
        return $this->fetch();
    }

    /**
     * 导入菜单
     */
    public function import_menu()
    {
        Db::execute('TRUNCATE TABLE `menu`;'); // 清空所有菜单

        //现有菜单
        $menus = Db::table('menu')->where('status', 1)->order('sort ASC')->column('title,pid,sort,url,hide,status', 'id');

        //菜单配置文件
        $new_menus = config('menu_config');
        $res       = $this->importMenu($new_menus, []);
        session('ALL_MENU_LIST', null);
        $this->get_leftmenu();

        $res ? $this->success('菜单更新成功！') : $this->error('菜单更新失败！');
    }

    /**
     * 导入菜单
     * @param  array  $menus 菜单数组
     */
    public function importMenu($menus = array(), $data = array(), $pid = 0)
    {
        if (empty($menus)) {
            return false;
        }

        foreach ($menus as $val) {
            $menu          = [];
            $menu['id']    = $val['id'];
            $menu['title'] = $val['title'];
            $menu['url']   = strtolower($val['url']);
            $menu['pid']   = $pid;
            $menu['sort']  = $val['sort'];
            $menu['hide']  = $val['hide'];
            $menu['icon']  = isset($val['icon']) ? $val['icon'] : '';

            $id = $val['id'];
            if (isset($data[$val['id']])) {
                $res = Db::table('menu')->where('id', $id)->update($menu);
            } else {
                $res = Db::table('menu')->insert($menu);
            }
            if (isset($val['child']) && !empty($val['child'])) {
                $this->importMenu($val['child'], $data, $id);
            }
        }
        return true;
    }

}
