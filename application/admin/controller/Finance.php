<?php

namespace app\admin\controller;

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
        $level = input('level', '');
        $groupid = input('groupid', '');
        $where = [];
        if (!empty($source_type)) {
            $where['log.source_type'] = $source_type;
        }


        if (!empty($kw)) {
            $where['m.mobile'] = ['like', "%{$kw}%"];
        }
        if ($begin_time && $end_time) {
            $where['m.createtime'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['m.createtime'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['m.createtime'] = ['LT', strtotime($end_time)];
        }

        // 携带参数
        $carryParameter = [
            'kw' => $kw,
            'level' => $level,
            'source_type' => $source_type,
            'groupid' => $groupid,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
        ];

        $list = Db::name('menber_balance_log')->alias('log')
            ->field('log.id,m.id as mid, log.user_id,m.avatar,m.weixin,log.note,log.source_type,m.mobile,log.old_balance,log.balance,log.create_time')
            ->join("member m", 'm.id=log.user_id', 'LEFT')
            ->where($where)
            ->where(['log.balance_type' => 1])
            ->order('m.createtime DESC')
            ->paginate(10, false, ['query' => $carryParameter]);
        // 导出
        $exportParam = $carryParameter;
        $exportParam['tplType'] = 'export';
        $tplType = input('tplType', '');
        if ($tplType == 'export') {
            $list = OrderModel::alias('uo')->field('uo.*,d.order_id as order_idd,d.invoice_no,a.realname')
                ->join("delivery_doc d", 'uo.order_id=d.order_id', 'LEFT')
                ->join("member a", 'a.id=uo.user_id', 'LEFT')
                ->where($where)
                ->order('uo.order_id DESC')
                ->select();
            $str = "订单ID,用户id,订单金额\n";

            foreach ($list as $key => $val) {
                $str .= $val['order_id'] . ',' . $val['user_id'] . ',' . $val['order_amount'] . ',';
                $str .= "\n";
            }
            export_to_csv($str, '余额记录', $exportParam);
        }
        // 模板变量赋值
        return $this->fetch('', [
            'list' => $list,
            'exportParam' => $exportParam,
            'kw' => $kw,
            'level' => $level,
            'source_type' => $source_type,
            'groups' => MemberModel::getGroups(),
            'levels' => MemberModel::getLevels(),
            'groupid' => $groupid,
            'begin_time' => empty($begin_time) ? date('Y-m-d') : $begin_time,
            'end_time' => empty($end_time) ? date('Y-m-d') : $end_time,
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
        $uid = input('id/d', 27);
        $profile = MemberModel::get($uid);
        $balance_info = get_balance($uid, 0);
        if (Request::instance()->isPost()) {
            $num = input('num/f');
            if ($num <= 0) {
                $this->error('输入的金额有误');
            }

            MemberModel::setBalance($uid, 0, $num, array(UID, '余额充值'));
            $this->success('充值成功', url('member/member_edit', ['id' => $profile['id']]));
        }
        $profile['balance'] = $balance_info['balance'];
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

            $max_preday = input('max_preday/d', 0);
            if ($max_preday > 0) {
                $set['withdrawal']['max_preday'] = $max_preday;//最大提现金额
            } else {
                $this->error('每个用户每天最高提现金额不能少于0');
            }

            $rate = bcadd(input('rate'), 0, 2);
            if ($rate < 0.01 || $rate > 100) {
                $this->error('提现手续费0.01-100');
            }
            $set['withdrawal']['rate'] = $rate;
            $set['withdrawal']['times'] = input('times/d', 0);
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
        //提现方式
        $type_list = [
            0 => '默认全部',
            1 => '余额',
            2 => '微信',
            3 => '银行',
            4 => '支付宝',
        ];;
        $where = array();
        $type = input('type/d', 0);
        $status = input('status');
        $ordersn = input('ordersn');
        $kw = input('kw');
        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');

        $ckbegin_time = input('ckbegin_time', '');
        $ckend_time = input('ckend_time', '');

        if ($type > 0) {
            $where['w.type'] = $type;
        }
        if ($status != 0) {
            $where['w.status'] = $status;
        }

        if (!empty($ordersn)) {
            $where['w.ordersn'] = $ordersn;
        }

        if (!empty($kw)) {
            $where['m.mobile'] = ['like', "%{$kw}%"];
        }

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
            ->field('w.data, w.id, m.id as mid , m.groupid , m.level , m.avatar , w.money , w.rate , w.account , w.content ,w.ordersn , m.nickname , m.realname , m.mobile ,m.weixin ,w.createtime ,w.checktime ,w.type,w.status')
            ->join("member m", 'm.openid = w.openid', 'LEFT')
            ->where($where)
            ->order('m.createtime DESC')
            ->paginate(10, false, ['query' => $where]);

        $this->assign('type_list', $type_list);
        $this->assign('list', $list);
        $this->assign('meta_title', '提现列表');
        return $this->fetch('finance/withdrawal_list', [
            'type' => $type,
            'status' => $status,
            'ordersn' => $ordersn,
            'kw' => $kw,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
            'ckbegin_time' => $ckbegin_time,
            'ckend_time' => $ckend_time,
            'type_list' => $type_list,
            'list' => $list,
            'meta_title' => '提现列表',
        ]);
    }


}