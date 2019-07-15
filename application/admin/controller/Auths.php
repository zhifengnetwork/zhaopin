<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/*
 * 权限管理
 */
class Auths extends Common
{
    /*
     * 权限分组
     */
    public function auth_group()
    {
        $where = [];
        $list  = Db::table('auth_group')->where($where)->select();
        $this->assign('list', $list);

        $this->assign('meta_title', '权限分组');
        return $this->fetch();
    }

    /*
     * 编辑分组
     */
    public function edit()
    {
        $id = input('param.id', 0);
        $this->assign('id', $id);

        if (Request::instance()->isPost()) {
            $id     = input('post.id', 0);
            $title  = input('post.title', '');
            $desc   = input('post.desc', '');
            $status = input('post.status', 1);

            !$title && $this->error('分组名称不能为空！');
            $data = [
                'title'  => $title,
                'desc'   => $desc,
                'status' => $status,
            ];

            if ($id) {
                if (Db::table('auth_group')->where('id', $id)->update($data) !== false) {
                    return $this->success('编辑成功', url('auths/auth_group'));
                } else {
                    return $this->error('编辑失败');
                }
            } else {
                $data['create_time'] = time();
                $id                  = Db::table('auth_group')->insert($data, false, true);
                if ($id) {
                    return $this->success('添加成功', url('auths/auth_group'));
                } else {
                    return $this->error('添加失败');
                }
            }
        } else {
            $info = [];
            if ($id) {
                $info = Db::table('auth_group')->where('id', $id)->find();
            }
            $this->assign('info', $info);
        }
        $this->assign('meta_title', $id ? '编辑菜单' : '新增菜单');
        return $this->fetch();
    }

    /*
     * 分组授权
     */
    public function manage_auths()
    {
        $id = input('param.id');
        $this->assign('id', $id);

        if (Request::instance()->isPost()) {
            $rules     = input('post.rules/a');
            $rules_str = $rules ? implode(',', $rules) : '';

            $id = input('post.id');
            if (Db::table('auth_group')->where('id', $id)->update(['rules' => $rules_str]) !== false) {
                return $this->success('授权成功！');
            } else {
                return $this->error('授权失败！');
            }
        }

        $menu_list = Db::table('menu')->select();
        $menu_tree = list_to_tree($menu_list);

        $rules      = Db::table('auth_group')->where('id', $id)->value('rules');
        $rules_list = explode(',', $rules);

        $this->assign('rules_list', $rules_list);
        $this->assign('menu_tree', $menu_tree);

        $this->assign('meta_title', '分组授权');
        return $this->fetch();
    }

    /*
     * 授权用户
     */
    public function auth_user()
    {
        $group_id = input('param.group_id', 0);
        $this->assign('group_id', $group_id);

        $where_a['aga.group_id'] = $group_id;
        $where_a['u.status']     = 1;
        $auth_user               = Db::table('auth_group_access')->alias('aga')
            ->join('mg_user u', 'aga.mgid=u.mgid', 'LEFT')
            ->where($where_a)
            ->column('aga.mgid');
        $this->assign('auth_user', $auth_user ? $auth_user : []);

        if (Request::instance()->isPost()) {
            $group_id = input('post.group_id', 0);
            $users    = input('post.users/a');
            if ($users) {
                $data_list = [];
                foreach ($users as $val) {
                    if ($auth_user && in_array($val, $auth_user)) {
                        continue;
                    }
                    $data_list[] = [
                        'mgid'     => $val,
                        'group_id' => $group_id,
                    ];
                }
                if ($data_list && Db::table('auth_group_access')->insertAll($data_list) === false) {
                    return $this->error('设置失败！');
                }
            } else {
                $users = [];
            }

            if ($auth_user) {
                $del_arr = [];
                foreach ($auth_user as $val) {
                    !in_array($val, $users) && $del_arr[] = $val;
                }
                if ($del_arr && Db::table('auth_group_access')->where('group_id', $group_id)->where('mgid', 'in', $del_arr)->delete() === false) {
                    return $this->error('设置失败！');
                }
            }
            return $this->success('设置成功！');
        }

        $where['status'] = 1;
        $user_list       = Db::table('mg_user')->field('mgid,name')->where($where)->select();
        $this->assign('user_list', $user_list);

        $this->assign('meta_title', '用户授权');
        return $this->fetch();
    }

}
