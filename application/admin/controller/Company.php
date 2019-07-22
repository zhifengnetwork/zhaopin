<?php
namespace app\admin\controller;

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
        $status  = input('status',2);
        $list = $this->company_list(1);

        return $this->fetch('company/index',[
            'status'       => $status,
            'list'         =>$list,
            'meta_title'   => '公司审核列表',
        ]);

    }

    function company_list($regtype){
        $status  = input('status',2);
        $where['m.regtype'] =  $regtype;
        $pageParam['query']['m.regtype'] = $regtype;
        if($status != 2){
            $where['c.status'] =  $status;
            $pageParam['query']['c.status'] = $status;
        }
        return Db::name('company')->alias('c')->field('c.*')
            ->join('member m','c.user_id=m.id','LEFT')
            ->where($where)
            ->paginate(10,false,$pageParam);
    }

    public function third()
    {
        $status  = input('status',2);
        $list = $this->company_list(2);

        return $this->fetch('company/index',[
            'status'       => $status,
            'list'         =>$list,
            'meta_title'   => '第三方审核列表',
        ]);

    }
    public function audit(){

        $status = input('status/d');
        if ($status != -1 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');

        $company=Db::name('company')->where(['id'=>$id])->find();
        if (!$company || $company['status'] != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $content = input('content');
        if ($status == -1 && !$content) {
            $this->error('内容不能为空');
        }
        $data['status']=$status;
        $data['id']=$id;
        $data['remark']=$content;
        $data['auditor']=UID;
        $data['examination']=time();
        $res=Db::name('company')->update($data);
        if(!$res){
            $this->error('审核失败！');
        }
        $this->success('操作成功', url('company/index'));
    }
    public function person_list(){
        $status  = input('status',2);
        $where=[];
        $pageParam = ['query' => []];
        if($status != 2){
            $where['p.status'] =  $status;
            $pageParam['query']['p.status'] = $status;
        }
        $list=Db::name('person')->alias('p')
            ->join('category c','c.cat_id=p.job_type','LEFT')
            ->where($where)
            ->field('p.*,c.cat_name')
            ->paginate(10,false,$pageParam);
        return $this->fetch('company/person_list',[
            'status'       => $status,
            'list'         => $list,
            'meta_title'   => '个人审核列表',
        ]);
    }
    public function person_audit(){

        $status = input('status/d');
        if ($status != -1 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');

        $person=Db::name('person')->where(['id'=>$id])->find();
        if (!$person || $person['status'] != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $content = input('content');
        if ($status == -1 && !$content) {
            $this->error('内容不能为空');
        }
        Db::startTrans();

        $data['status']=$status;
        $data['id']=$id;
        $data['remark']=$content;
        $data['check_user']=UID;
        $data['check_time']=time();
        if($person['edit']==0)$data['edit']=1;
        $res=Db::name('person')->update($data);

        if(!$res){
            Db::rollback();
            $this->error('审核失败！');
        }else{
            if($status==1&&$person['edit']==0){
                $balance=Db::name('member')->where(['id'=>$person['user_id']])->value('balance');
                $money= Db::name('category')->where(['cat_id'=>$person['job_type']])->value('money');
                $member_balance=bcadd($balance,$money);
                $res = Db::name('member')->update(['balance'=>$member_balance]);
                if(!$res){
                    Db::rollback();
                    $this->error('审核失败！');
                }

                // 余额记录
                $res = Db::name('member_balance_log')->insert([
                    'user_id'=>$person['user_id'],'money'=>$money,'old_balance'=>$balance,'balance'=>$member_balance,
                    'source_type'=>8,'log_type'=>1,'source_id'=>$person['user_id'],'note'=>'注册成功','create_time'=>time()
                ]);
                if(!$res){
                    Db::rollback();
                    $this->error('审核失败！');
                }
            }
            Db::commit();
            $this->success('操作成功', url('company/person_list'));
        }

    }
    public function recruit_audit(){

        $status = input('status/d');
        if ($status != -1 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');

        $company=Db::name('recruit')->where(['id'=>$id])->find();
        if (!$company || $company['status'] != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $content = input('content');
        if ($status == -1 && !$content) {
            $this->error('内容不能为空');
        }
        $data['status']=$status;
        $data['id']=$id;
        $data['remark']=$content;
        $data['check_time']=time();
        $res=Db::name('recruit')->update($data);
        if(!$res){
            $this->error('审核失败！');
        }
        $this->success('操作成功', url('company/recruit_list'));
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
        $this->assign('company', $company);
        return $this->fetch();
    }
    public function person_details(){
        $id=input('id');
        $where['user_id']=$id;
        $person=Db::name('person')->where($where)->find();
        $person['job_type']=Db::name('category')->where(['cat_id'=>$person['job_type']])->value('cat_name');
        $this->assign('person', $person);
        return $this->fetch();
    }
    /*
     * 职位管理
     */
    public function recruit_list()
    {

        $pageParam = ['query' => []];
        $list=Db::name('recruit')->alias('r')
            ->join('company c','c.id=r.company_id','LEFT')
            ->field('r.*,c.company_name')
            ->paginate(10,false,$pageParam);

        return $this->fetch('company/recruit_list',[
            'list'         =>$list,
            'meta_title'   => '职位列表',
        ]);

    }
    public function recruit_exit(){
        $id=input('id');
        $key=input('key');
        $value=input('value');
        $res=Db::name('recruit')->where(['id'=>$id])->update(array($key=>$value));
        if($res){
            return json(['code'=>1, 'msg'=>'修改成功！','data'=>[]]);
        }else{
            return json(['code'=>0, 'msg'=>'修改失败！','data'=>[]]);
        }

    }
}
