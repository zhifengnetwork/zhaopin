<?php

namespace app\admin\controller;

use think\Db;
use think\Config;
use think\Loader;
use think\Request;
use app\common\model\Message as MsgModel;

class Message extends Common
{
    public function index()
    {
        $list = MsgModel::where([])->order('id desc')
            ->paginate(10, false);
        return $this->fetch('', [
            'meta_title' => '系统消息列表',
            'list' => $list,
        ]);
    }

    public function edit()
    {
        $id = input('param.id', 0);
        $this->assign('id', $id);

        if (Request::instance()->isPost()) {
            $data = input('post.');
            if (!$data['title'] || !$data['content']) {
                return $this->error('标题或内容不能为空！');
            }
            if ($data['id'] > 0) {
                if (Db::table('message')->where('id', $data['id'])->update($data) !== false) {
                    return $this->success('编辑成功！', url('message/edit', array('id' => $id)));
                } else {
                    return $this->error('编辑失败！');
                }
            } else {
                $data['create_time'] = time();
                if ($id = Db::table('message')->insertGetId($data)) {
                    return $this->success('添加成功', url('message/edit', array('id' => $id)));
                } else {
                    return $this->error('添加失败');
                }
            }
        }
        $info = $id > 0 ? Db::table('message')->where('id', $id)->find() : [];
        $this->assign('info', $info);
        $this->assign('meta_title', ($id > 0 ? '编辑' : '新增') . '系统消息');
        return $this->fetch('edit');

    }

    public function show()
    {
        $id = input('id/d');
        $value = input('value/d');
        if (!$id || !in_array($value, [1, 0]) || !Db::name('message')->where(['id' => $id])->find()) {
            return json(['code' => 0, 'msg' => '数据错误！']);
        }
        if (Db::name('message')->where(['id' => $id])->update(['show' => $value])) {
            return json(['code' => 1, 'msg' => '修改成功！']);
        }
        return json(['code' => 0, 'msg' => '修改失败！']);

    }

}
