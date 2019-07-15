<?php
namespace app\admin\controller;

use app\common\model\Config as ConfigModel;
use think\Request;

/**
 * 基本配置管理控制器
 */
class Config extends Common
{
    /**
     * 基本配置
     */
    public function index()
    {
        // 是否可见
        $where['status'] = 1;

        $module = input('module/d', 0);
        if ($module) {
            $where['module'] = $module;
        }

        $list = ConfigModel::where($where)
            ->order('sort')
            ->paginate(100, false, [
                'query' => [
                    'module' => $module,
                ],
            ]);

        $this->assign('list', $list);
        $this->assign('meta_title', '配置管理');
        return $this->fetch();
    }

    /**
     * 基本配置
     */
    public function operate_config()
    {
        if (request()->isPost()) {

            $agc_list              = input('agc_list', '');
            $main_title            = input('main_title', '');
            $login_title           = input('login_title', '');
            $customer_tel          = input('customer_tel', '');
            $customer_qq           = input('customer_qq', '');
            $customer_wx           = input('customer_wx', '');
            $ali_tx_explain        = input('ali_tx_explain', '');
            $agent_share_url       = input('agent_share_url', '');
            $up_nickname_num       = input('up_nickname_num', '');
            $bank_tx_explain       = input('bank_tx_explain', '');
            $bind_tel_send_gold    = input('bind_tel_send_gold', '');
            $ali_withdraw_percent  = input('ali_withdraw_percent', '');
            $bank_withdraw_percent = input('bank_withdraw_percent', '');

            $agent_ali_withdraw_percent  = input('agent_ali_withdraw_percent', '');
            $agent_bank_withdraw_percent = input('agent_bank_withdraw_percent', '');
            $registered_nickname_mode    = input('registered_nickname_mode', '');

            $withdraw_min = input('withdraw_min', '');
            $withdraw_max = input('withdraw_max', '');

            $data = [
                'agc_list'                    => $agc_list,
                'main_title'                  => $main_title,
                'login_title'                 => $login_title,
                'customer_qq'                 => $customer_qq,
                'customer_wx'                 => $customer_wx,
                'customer_tel'                => $customer_tel,
                'withdraw_min'                => $withdraw_min,
                'withdraw_max'                => $withdraw_max,
                'agent_share_url'             => $agent_share_url,
                'bank_tx_explain'             => $bank_tx_explain,
                'up_nickname_num'             => $up_nickname_num,
                'bind_tel_send_gold'          => $bind_tel_send_gold,
                'ali_withdraw_percent'        => $ali_withdraw_percent,
                'bank_withdraw_percent'       => $bank_withdraw_percent,
                'registered_nickname_mode'    => $registered_nickname_mode,
                'agent_ali_withdraw_percent'  => $agent_ali_withdraw_percent,
                'agent_bank_withdraw_percent' => $agent_bank_withdraw_percent,
            ];
            //验证器
            $result = $this->validate($data, 'Config.edit');
            true !== $result && $this->error($result);

            if (!(new ConfigModel)->updateConfig($data)) {
                $this->error('编辑失败');
            }
            $this->admin_log(2, '编辑游戏通用配置', json_encode($data));
            $this->success('编辑成功', url('config/operate_config'));
        }
        $this->assign('meta_title', '配置管理');
        return $this->fetch();
    }

    /**
     * 系统配置
     */
    public function edit()
    {
        // $this->sort();die();
        $id     = input('id/d');
        $name   = input('name');
        $title  = input('title');
        $value  = input('value');
        $remark = input('remark');

        if ($name == 'Bucket_url') {
            $value = rtrim($value, '/');
        }
        $data = [
            'module'      => input('module/d', 1),
            'name'        => $name,
            'title'       => $title,
            'value'       => $value,
            'remark'      => $remark,
            'sort'        => input('sort/d', 0),
            'update_time' => time(),
        ];

        if ($id) {
            $result = (new ConfigModel)->save($data, ['id' => $id]);
        } else {
            $result = (new ConfigModel($data))->save();
        }

        if ($result) {
            // 重载配置
            (new ConfigModel)->resetConfig();
            $this->success('保存成功');
        }

        $this->error('保存失败');
    }

    /**
     * 获取配置
     * @return [type] [description]
     */
    public function get_config()
    {
        $id  = input('id/d');
        $res = ConfigModel::get($id);
        $this->result($res, 1, '');
    }

    /**
     * 排序
     * @return [type] [description]
     */
    public function sort()
    {
        $res = ConfigModel::order('sort')->select();
        foreach ($res as $key => $value) {
            ConfigModel::where('id', $value['id'])->update(['id' => $key + 1]);
        }
        echo 'SUCCESS';
    }
}
