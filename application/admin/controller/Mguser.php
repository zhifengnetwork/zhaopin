<?php
namespace app\admin\controller;

use app\admin\model\MgUser as MgUserModel;
use think\Db;
use think\Loader;
use think\Request;

/*
 * 后台用户管理
 */
class Mguser extends Common
{
    /*
     * 用户列表
     */
    public function index()
    {
        $where = [];
        $list  = Db::table('mg_user')->where($where)->paginate(20);
        $page  = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);

        $this->assign('meta_title', '用户列表');
        return $this->fetch();
    }

    /*
     * 编辑用户
     */
    public function edit()
    {
        $mgid = input('param.mgid', 0);
        $this->assign('mgid', $mgid);

        if (Request::instance()->isPost()) {
            $data = input('post.');
            $mgid = $data['mgid'];

            //实例化验证器
            $validate       = Loader::validate('Mguser');
            $validate_scene = $mgid ? 'edit' : 'add';
            if (!$validate->scene($validate_scene)->check($data)) {
                return $this->error($validate->getError());
            }
            if ($mgid) {
                unset($data['repassword']);
                unset($data['password']);
                unset($data['second_password']);
                unset($data['resecond_password']);
                if (Db::table('mg_user')->where('mgid', $mgid)->update($data) !== false) {
                    return $this->success('编辑成功！', url('mguser/index'));
                } else {
                    return $this->error('编辑失败！');
                }
            } else {
                unset($data['repassword']);
                unset($data['resecond_password']);
                $data['salt']            = create_salt();
                $data['password']        = minishop_md5($data['password'], $data['salt']);
                $data['second_password'] = minishop_md5($data['second_password'], $data['salt']);
                $data['create_time']     = time();
                if (Db::table('mg_user')->insert($data, false, true)) {
                    return $this->success('添加成功', url('mguser/index'));
                } else {
                    return $this->error('添加失败');
                }
            }
        } else {
            $info = [];
            if ($mgid) {
                $info = Db::table('mg_user')->where('mgid', $mgid)->find();
            }
            $this->assign('info', $info);

            $this->assign('meta_title', '新增用户');
            return $this->fetch('edit');
        }
    }

    /*
     * 用户授权
     */
    public function set_authgroup()
    {
        $mgid = input('param.mgid', 0);
        $this->assign('mgid', $mgid);

        $where_a['aga.mgid']  = $mgid;
        $where_a['ag.status'] = 1;
        $auth_access          = Db::table('auth_group_access')->alias('aga')
            ->join('auth_group ag', 'aga.group_id=ag.id', 'LEFT')
            ->where($where_a)
            ->column('ag.id');
        $this->assign('auth_access', $auth_access ? $auth_access : []);

        if (Request::instance()->isPost()) {
            $mgid   = input('post.mgid', 0);
            $groups = input('post.groups/a');
            if ($groups) {
                $data_list = [];
                foreach ($groups as $val) {
                    if ($auth_access && in_array($val, $auth_access)) {
                        continue;
                    }
                    $data_list[] = [
                        'mgid'     => $mgid,
                        'group_id' => $val,
                    ];
                }
                if ($data_list && Db::table('auth_group_access')->insertAll($data_list) === false) {
                    return $this->error('设置失败！');
                }
            } else {
                $groups = [];
            }

            if ($auth_access) {
                $del_arr = [];
                foreach ($auth_access as $val) {
                    !in_array($val, $groups) && $del_arr[] = $val;
                }
                if ($del_arr && Db::table('auth_group_access')->where('mgid', $mgid)->where('group_id', 'in', $del_arr)->delete() === false) {
                    return $this->error('设置失败！');
                }
            }
            return $this->success('设置成功！');
        }

        $where['status'] = 1;
        $auth_group_list = Db::table('auth_group')->field('id,title')->where($where)->select();
        $this->assign('auth_group_list', $auth_group_list);

        $this->assign('meta_title', '用户授权');
        return $this->fetch();
    }

    /*
     * 修改一级密码
     */
    public function update_pwsd()
    {
        $type = input('param.type', 0);
        $this->assign('type', $type);

        if (Request::instance()->isPost()) {
            //密码类型 0-一级密码,1-二级密码
            $type         = input('post.type', 0);
            $old_password = input('post.old_password', '');
            $password     = input('post.password', '');
            $repassword   = input('post.repassword', '');

            //实例化验证器
            $validate = Loader::validate('Mguser');
            $data     = [
                'password'   => $password,
                'repassword' => $repassword,
            ];
            if (!$validate->scene('editPass')->check($data)) {
                return $this->error($validate->getError());
            }

            $pwd_field = ($type == 1) ? 'second_password' : 'password';
            $mg_info   = Db::table('mg_user')->field('password,salt,second_password')->where('mgid', UID)->find();
            if (minishop_md5($old_password, $mg_info['salt']) !== $mg_info[$pwd_field]) {
                return $this->error('原密码不正确！');
            }

            $new_password = minishop_md5($password, $mg_info['salt']);
            if (Db::table('mg_user')->where('mgid', UID)->update([$pwd_field => $new_password]) !== false) {
                return $this->success('密码修改成功！', $type ? '' : url('login/login_out'));
            } else {
                return $this->error('密码修改失败！');
            }
        }

        $this->assign('meta_title', $type ? '修改二级密码' : '修改密码');
        return $this->fetch();
    }

    /**
     * 操作列表
     */
    public function operation_log()
    {
        //时间
        $startDate = input('startDate', '');
        $endDate   = input('endDate', '');
        $where     = time_condition($startDate, $endDate, 'l.add_time');
        //关键字
        $kw = input('kw', '');
        if ($kw) {
            if (is_numeric($kw)) {
                $where['l.add_uid'] = $kw;
            } else {
                $mgid = MgUserModel::where('name|username', $kw)->value('mgid');

                !empty($mgid) && $where['l.add_uid'] = $mgid;
            }
        }
        $this->assign('kw', $kw);
        $where['l.type'] = 1;
        $list            = Db::table('user_agent_log')
            ->alias('l')
            ->join('mg_user g', 'g.mgid=l.add_uid', 'LEFT')
            ->field('l.*,g.name,g.username')
            ->where($where)
            ->order('id DESC')
            ->paginate(15, false, ['query' => [
                'startDate' => $startDate,
                'endDate'   => $endDate,
                'kw'        => $kw,
            ]]);
        $this->assign('list', $list);
        $this->assign('meta_title', '操作日志列表');
        return $this->fetch();
    }

}
