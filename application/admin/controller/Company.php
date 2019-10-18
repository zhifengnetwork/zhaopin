<?php
namespace app\admin\controller;

use app\common\model\Audit;
use think\Db;
use think\Loader;
use think\Request;
/*
 * 商品管理
 */
class Company extends Common
{
    /*
     * 商品列表
     */
    public function index()
    {
        $list = $this->company_list(1);

        return $this->fetch('company/index',[
            'list'         =>$list,
            'meta_title'   => '公司审核列表',
        ]);

    }

    function company_list($regtype){
        $where =  ['type'=>$regtype];
        $pageParam['query']=['type'=>$regtype];
        return Audit::where($where)
            ->paginate(10,false,$pageParam);
    }

    public function third()
    {
        $list = $this->company_list(2);

        return $this->fetch('company/index',[
            'list'         =>$list,
            'meta_title'   => '第三方审核列表',
        ]);

    }
    public function audit(){

        $status = input('status/d');
        if ($status != -1 && $status != 1) $this->error('状态错误');

        $id = input('id/d');//audit表的id

        $audit=Db::name('audit')->where(['id'=>$id])->find();
        if (!$audit || $audit['status'] != 0) $this->error('数据没有找到或不能操作');

        $content = input('content');
        if ($status == -1 && !$content) $this->error('内容不能为空');

        Db::startTrans();
        if ($audit['edit'] == 0) {
            // 注册审核
            $data['status'] = $status;
            $data['remark'] = $content;
            $data['auditor'] = UID;
            $data['examination'] = time();
            $data['edit'] = 1;
            if ($status == 1) {
                // 普通会员可免费预约查看人数设置
                $sysset = Db::table('sysset')->field('*')->find();
                $set = json_decode($sysset['vip'], true);
                isset($set['members']) && $set['members'] > 0 && ($data['reserve_num_all'] = $set['members']);
            }
            $res = Db::name('company')->where(['user_id' => $audit['content_id']])->update($data);
            if ($res===false) {
                Db::rollback();
                $this->error('审核失败!');
            }
        } elseif ($audit['edit'] != 0 && $status == 1) {
            // 编辑信息审核成功，替换数据
            $data = json_decode($audit['data'], true);
            isset($data['images']) && $data['images'] = json_encode($data['images']);
            $res = Db::name('company')->where(['user_id' => $audit['content_id']])->update($data);
            if ($res===false) {
                Db::rollback();
                $this->error('审核失败!');
            }
        }
        $res=Db::name('audit')->where(['id'=>$id])->update([
            'remark' => $content,
            'status'=>$status
        ]);
        if($res===false){
            Db::rollback();
            $this->error('审核失败！');
        }
        Db::commit();
        $this->success('操作成功', url('company/index'));
    }
    public function person_list(){
        $where=['type'=>3];
        $pageParam = ['query' => ['type'=>3]];
        $list=Audit::where($where)->order('id desc')
//            ->field('p.*,c.cat_name')
            ->paginate(10,false,$pageParam);
        return $this->fetch('company/person_list',[
            'list'         => $list,
            'meta_title'   => '个人审核列表',
        ]);
    }
    public function person_audit(){
        $status = input('status/d');
        if ($status != -1 && $status != 1) $this->error('状态错误');

        $id = input('id/d');
        $audit = Db::name('audit')->where(['id' => $id,'type'=>3])->find();
        if (!$audit || $audit['status'] != 0) $this->error('数据没有找到或不能操作');

        $content = input('content');
        if ($status == -1 && !$content) $this->error('内容不能为空');

        Db::startTrans();
        if ($audit['edit'] == 0) {
            // 注册审核
            $data['status'] = $status;
            $data['remark'] = $content;
            $data['check_user'] = UID;
            $data['check_time'] = time();
            $data['edit'] = 1;
            $res = Db::name('person')->where(['user_id' => $audit['content_id']])->update($data);
            if ($res===false) {
                Db::rollback();
                $this->error('审核失败!');
            }
        } elseif ($audit['edit'] != 0 && $status == 1) {
            // 编辑信息审核成功，替换数据
            $data = json_decode($audit['data'], true);
            isset($data['images']) && $data['images'] = json_encode($data['images']);
            $res = Db::name('person')->where(['user_id' => $audit['content_id']])->update($data);
            if ($res===false) {
                Db::rollback();
                $this->error('审核失败!');
            }
        }
        $person = Db::name('person')->where(['user_id' => $audit['content_id']])->field('user_id,job_type')->find();
        if ($status == 1 && $audit['edit'] == 0) {
            // 注册审核成功，根据求职类型返佣
            $balance = Db::name('member')->where(['id' => $person['user_id']])->value('balance');
            $money = Db::name('category')->where(['cat_id' => $person['job_type']])->value('money');
            $member_balance = bcadd($balance, $money);
            $res = Db::name('member')->where(['id' => $person['user_id']])->update(['balance' => $member_balance]);
            if ($money > 0 && $res===false) {
                Db::rollback();
                $this->error('审核失败!');
            }

            // 余额记录
            $res = Db::name('member_balance_log')->insert([
                'user_id' => $person['user_id'], 'money' => $money, 'old_balance' => $balance, 'balance' => $member_balance,
                'source_type' => 8, 'log_type' => 1, 'source_id' => $person['user_id'], 'note' => '注册成功', 'create_time' => time()
            ]);
            if (!$res) {
                Db::rollback();
                $this->error('审核失败！');
            }
        }
        $res = Db::name('audit')->where(['id' => $id])->update([
            'remark' => $content,
            'status' => $status
        ]);
        if ($res===false) {
            Db::rollback();
            $this->error('审核失败！');
        }
        Db::commit();
        $this->success('操作成功', url('company/person_list'));

    }

    public function vip_set()
    {
        $sysset = Db::table('sysset')->field('*')->find();
        $set =json_decode($sysset['vip'], true);
        if (Request::instance()->isPost()) {
            $set['members'] = trim(input('members'));
            $set['month'] = trim(input('month'));
            $set['quarter'] = trim(input('quarter'));
            $set['year'] = trim(input('year'));
            $set['month_money'] = trim(input('month_money'));
            $set['quarter_money'] = trim(input('quarter_money'));
            $set['year_money'] = trim(input('year_money'));

            if ($set['members'] < 0||$set['month']<0||$set['quarter']<0||$set['year']<0) {
                $this->error('人数设置不能少于0',url('company/vip_set'));
            }
            $res = Db::name('sysset')->where(['id' => 1])->update(['vip' => json_encode($set)]);
            if ($res !== false) {
                $this->success('编辑成功', url('company/vip_set'));
            }
            $this->error('编辑失败');

        }
        $this->assign('set', $set);
        $this->assign('meta_title', 'vip设置');
        return $this->fetch();
    }
    public function company_details(){
        $id=input('id');
        $where['user_id']=$id;
        $company=Db::name('company')->where($where)->find();
        if(!$company){
            $this->error('未填写注册信息');
        }
        $this->assign('company', $company);
        return $this->fetch();
    }
    public function person_details(){
        $id=input('id');
        $where['user_id']=$id;
        $person=Db::name('person')->where($where)->find();
        if(!$person){
            $this->error('未填写注册信息');
        }
        $person['job_type']=Db::name('category')->where(['cat_id'=>$person['job_type']])->value('cat_name');
        $this->assign('person', $person);
        return $this->fetch();
    }
    /*
     * 职位管理
     */
    public function recruit_list()
    {
        $where = ['r.status'=>['neq',0]];
        $pageParam = ['query' => $where];
        $list=Db::name('recruit')->alias('r')
            ->join('company c','c.id=r.company_id','LEFT')
            ->field('r.*,c.company_name')->where($where)->order('id desc')
            ->paginate(10,false,$pageParam);

        return $this->fetch('company/recruit_list',[
            'list'         =>$list,
            'meta_title'   => '职位列表',
        ]);

    }
    /*
     * 职位审核管理
     */
    public function audit_list()
    {
        $where =  ['a.type'=>4];
        $pageParam['query']=['a.type'=>4];
        $list=Audit::where($where)->field('a.*')->order('a.id desc')->alias('a')
            ->join('recruit r','a.content_id=r.id')
            ->paginate(10,false,$pageParam);
        
        return $this->fetch('',[
            'list'         =>$list,
            'meta_title'   => '职位审核列表',
        ]);

    }
    public function recruit_audit(){

        $status = input('status/d');
        if ($status != -1 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');

        $audit=Db::name('audit')->where(['id'=>$id,'type'=>4])->find();
        if (!$audit || $audit['status'] != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $recruit=Db::name('recruit')->where(['id'=>$audit['content_id']])->find();
        if (!$recruit) {
            $this->error('数据没有找到');
        }
        $content = input('content');
        if ($status == -1 && !$content) {
            $this->error('内容不能为空');
        }
        Db::startTrans();
        if ($recruit['edit'] == 1){
            $data = json_decode($audit['data'],true);
        }
        $data['status']=$status;
        $data['id']=$audit['content_id'];
        $data['remark']=$content;
        $data['check_time']=time();
        $res=Db::name('recruit')->update($data);
        if($res===false){
            Db::rollback();
            $this->error('审核失败！');
        }
        $res=Db::name('audit')->where(['id'=>$id])->update(['status'=>$status]);
        if($res===false){
            Db::rollback();
            $this->error('审核失败！');
        }
        Db::commit();
        $this->success('操作成功', url('company/recruit_list'));
    }
    public function recruit_exit(){
        $id=input('id');
        $key=input('key');
        $value=input('value');
        $res=Db::name('recruit')->where(['id'=>$id])->update(array($key=>$value));
        if($res!==false){
            return json(['code'=>1, 'msg'=>'修改成功！','data'=>[]]);
        }else{
            return json(['code'=>0, 'msg'=>'修改失败！','data'=>[]]);
        }

    }

    public function person_pic(){
        $where =  ['type'=>6];
        $pageParam['query']=['regtype'=>6];
        $list=Audit::where($where)->order('id desc')
            ->paginate(10,false,$pageParam);

        return $this->fetch('',[
            'list'         =>$list,
            'meta_title'   => '个人证书审核',
        ]);
    }

    public function pic_audit(){
        $status = input('status/d');
        if ($status != -1 && $status != 1) $this->error('状态错误');

        $id = input('id/d');
        $audit = Db::name('audit')->where(['id' => $id,'type'=>6])->find();
        if (!$audit || $audit['status'] != 0) $this->error('数据没有找到或不能操作');

        $content = input('content');
        if ($status == -1 && !$content) $this->error('内容不能为空');

        Db::startTrans();
        if ($status == 1) {
            // 编辑信息审核成功，替换数据
            $res = Db::name('person')->where(['user_id' => $audit['content_id']])->update([
                'images' => $audit['data']
            ]);
            if ($res===false) {
                Db::rollback();
                $this->error('审核失败!');
            }
        }
        $res = Db::name('audit')->where(['id' => $id])->update([
            'remark' => $content,
            'status' => $status
        ]);
        if ($res===false) {
            Db::rollback();
            $this->error('审核失败！');
        }
        Db::commit();
        $this->success('操作成功', url('company/person_pic'));
    }

    public function reserve_set()
    {
        $money = Db::name('config')->where(['name'=>'reserve_money'])->value('value');
        if (Request::instance()->isPost()) {
            $money = input('money');
            $money = bcadd($money,0,2);
            if ($money < 0) {
                $this->error('预定金额不能少于0',url('company/vip_set'));
            }
            $res = Db::name('config')->where(['name'=>'reserve_money'])->update(['value'=>$money]);
            if ($res !== false) {
                $this->success('编辑成功', url('company/reserve_set'));
            }
            $this->error('编辑失败');

        }
        $this->assign('money', $money);
        $this->assign('meta_title', '预定设置');
        return $this->fetch();
    }
}
