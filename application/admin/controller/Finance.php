<?php

namespace app\admin\controller;

use app\common\model\MemberBalanceLog;
use think\Db;
use app\common\model\Order as OrderModel;
use app\common\model\Member as MemberModel;
use app\common\model\MemberWithdrawal;
use think\Request;

/**
 * 首页
 */
class Finance extends Common
{
    public function index()
    {
        $this->assign('meta_title', '财务首页');
        return $this->fetch();
        # code...
    }

    /**
     * 余额记录
     */
    public function balance_logs()
    {

        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');
        $kw = input('realname', '');
        $source_type = input('source_type', '');
        $type = input('type', '');
        $where = [];
        if (!empty($source_type)) {
            $where['log.source_type'] = $source_type;
        }
        if (!empty($type)) {
            $where['m.regtype'] = $type;
        }

        if (!empty($kw)) {
            is_numeric($kw) ? $where['m.mobile'] = ['like', "%{$kw}%"] : $where['m.realname'] = ['like', "%{$kw}%"];
        }
        if ($begin_time && $end_time) {
            $where['log.create_time'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['log.create_time'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['log.create_time'] = ['LT', strtotime($end_time)];
        }

        // 携带参数
        $carryParameter = [
            'kw' => $kw,
            'type' => $type,
            'source_type' => $source_type,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
        ];

        $list = Db::name('member_balance_log')->alias('log')
            ->field('m.id as mid,log.id,m.regtype,log.source_type,log.balance,log.create_time,log.money,log.log_type')
            ->join('member m','log.user_id = m.id','LEFT')
            ->where($where)
            ->where(['log.balance_type' => 0])
            ->order('log.create_time DESC')
            ->paginate(10, false, ['query' => $carryParameter]);

        // 模板变量赋值
        return $this->fetch('', [
            'list' => $list,
            'exportParam' => $carryParameter,
            'kw' => $kw,
            'type' => $type,
            'source_type' => $source_type,
            'type_list' => MemberBalanceLog::$type_list,
            'register_type' => MemberModel::$_registerType,
            'begin_time' => empty($begin_time) ? '' : $begin_time,
            'end_time' => empty($end_time) ? '' : $end_time,
            'meta_title' => '余额记录',
        ]);
    }


    /***
     * 财务数据
     */
    public function finance()
    {
        $this->assign('meta_title', '财务数据');
        return $this->fetch();
    }

    /***
     * 业务数据
     */
    public function business()
    {
        $this->assign('meta_title', '业务数据');
        return $this->fetch();
    }

    /***
     * 余额充值
     */
    public function balance_recharge()
    {
        $uid = input('id/d');
        $profile = MemberModel::get($uid);
        if(!$profile)$this->error('用户不存在');
        $balance_info = get_balance($uid, 0);
        if (Request::instance()->isPost()) {
            $num = input('num/f');
            if ($num <= 0) {
                $this->error('输入的金额有误');
            }

            MemberModel::setBalance($uid, 0, $num, array(UID, '余额充值'));
            $this->success('充值成功', url('member/index'));
        }
        $profile['balance'] = $balance_info['balance'];
        $this->assign('register_type', \app\common\model\Member::$_registerType);
        $this->assign('profile', $profile);
        $this->assign('meta_title', '余额充值');
        return $this->fetch();
    }

    /***
     * 金币设置
     */
    public function balance_set()
    {
        $sysset = Db::table('sysset')->field('*')->find();
        $set = json_decode($sysset['jinbi'], true);

        if (Request::instance()->isPost()) {
            $jinbi = Request::instance()->post('jinbi/a');
            $money = Request::instance()->post('money/a');
            foreach ($jinbi as $k => $v) {
                $v && $money[$k] && $data[] = ['jinbi' => intval($v), 'money' => intval($money[$k])];
            }
            $res = Db::name('sysset')->where(['id' => 1])->update(['jinbi' => json_encode($data)]);
            if ($res !== false) {
                $this->success('编辑成功', url('finance/balance_set'));
            }
            $this->error('编辑失败');

        }
        $this->assign('set', $set);
        $this->assign('meta_title', '金币设置');
        return $this->fetch();
    }

    // 提现设置
    public function withdrawalset()
    {
        $sysset = Db::table('sysset')->field('*')->find();
        $set = unserialize($sysset['sets']);

        if (Request::instance()->isPost()) {
            $max = input('max/d', 0);
            if ($max > 0) {
                $set['withdrawal']['max'] = $max;//最大提现金额
            } else {
                $this->error('每次最高提现金额不能少于0');
            }

            $rate = bcadd(input('rate'), 0, 2);
            if ($rate < 0.01 || $rate > 100) {
                $this->error('提现手续费0.01-100');
            }
            $set['withdrawal']['rate'] = $rate;
            $set['withdrawal']['show'] = input('show/d');
            $res = Db::name('sysset')->where(['id' => 1])->update(['sets' => serialize($set)]);
            if ($res !== false) {
                $this->success('编辑成功', url('finance/withdrawalset'));
            }
            $this->error('编辑失败');

        }
        $this->assign('set', $set);
        $this->assign('meta_title', '余额提现设置');
        return $this->fetch();
    }

    /***
     * 提现列表
     */

    public function withdrawal_list()
    {
        $where = array();
        $type = input('type/d', 0);
        $status = input('status');
        $kw = input('kw');
        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');
        $ckbegin_time = input('ckbegin_time', '');
        $ckend_time = input('ckend_time', '');

        if ($type > 0) $where['w.type'] = $type;
        if ($status != '') $where['w.status'] = $status;
        if (!empty($kw)) is_numeric($kw) ? $where['m.mobile'] = ['like', "%{$kw}%"] : $where['m.realname'] = ['like', "%{$kw}%"];

        if ($begin_time && $end_time) {
            $where['w.createtime'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['w.createtime'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['w.createtime'] = ['LT', strtotime($end_time)];
        }

        if ($ckbegin_time && $ckend_time) {
            $where['w.checktime'] = [['EGT', strtotime($ckbegin_time)], ['LT', strtotime($ckend_time)]];
        } elseif ($ckbegin_time) {
            $where['w.checktime'] = ['EGT', strtotime($ckbegin_time)];
        } elseif ($ckend_time) {
            $where['w.checktime'] = ['LT', strtotime($ckend_time)];
        }

        $list = MemberWithdrawal::alias('w')
            ->field('w.*, m.id as mid,m.mobile,m.regtype')
            ->join("member m", 'm.id = w.user_id', 'LEFT')
            ->where($where)
            ->order('w.id DESC')
            ->paginate(10, false, ['query' => $where]);
        foreach ($list as &$v){
            if ($v['regtype'] == 3) {
                $data = Db::name('person')->where(['user_id' => $v['mid']])->field('name,avatar')->find();
            } else {
                $data = Db::name('company')->where(['user_id' => $v['mid']])->field('company_name as name,logo as avatar')->find();
            }
            $v['pic'] = isset($data['avatar']) ? $data['avatar'] : '';
            $v['user_name'] = isset($data['name']) ? $data['name'] : '';
        }
        return $this->fetch('finance/withdrawal_list', [
            'type' => $type,
            'status' => $status,
            'kw' => $kw,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
            'ckbegin_time' => $ckbegin_time,
            'ckend_time' => $ckend_time,
            'type_list' => MemberWithdrawal::$type_list,
            'status_list' => MemberWithdrawal::$status_list,
            'list' => $list,
            'meta_title' => '余额提现列表',
        ]);
    }


    // 提现审核操作
    public function withdrawal()
    {
        $status = input('status/d');
        if ($status != -1 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');
        $withdrawal = MemberWithdrawal::get($id);
        if (!$withdrawal || $withdrawal->status != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $content = input('content');
        if ($status == -1 && !$content) {
            $this->error('内容不能为空');
        }
        Db::startTrans();
        $res = $withdrawal->save(['status' => $status, 'content' => $content, 'checktime' => time()]);
        if (!$res) {
            Db::rollback();
            $this->error('操作失败');
        }
        // 审核失败，退回余额
        if ($status == -1) {
            $member = Db::name('member')->where(['id' => $withdrawal->user_id])->find();
            $balance = bcadd($member['balance'], $withdrawal->money, 2);
            $res = Db::name('member')->where(['id' => $withdrawal->user_id])->update(['balance' => $balance]);
            $res && $res = Db::name('member_balance_log')->insert([
                'user_id' => $withdrawal->user_id,
                'balance_type' => 0,
                'log_type' => 1,
                'source_type' => 5,
                'source_id' => $withdrawal->id,
                'money' => $withdrawal->money,
                'old_balance' => $member['balance'],
                'balance' => $balance,
                'create_time' => time(),
                'note' => '提现审核失败返还'
            ]);
            if (!$res) {
                Db::rollback();
                $this->error('操作失败');
            }
        }

        Db::commit();
        $this->success('操作成功', url('finance/withdrawal_list'));
    }


}