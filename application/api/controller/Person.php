<?php

namespace app\api\controller;

use app\common\model\Category;
use app\common\model\Company as CompanyModel;
use app\common\model\Person as PersonModel;
use app\common\model\Reserve;
use app\common\model\Sysset;
use think\Db;

/**
 * 应聘者
 * Class Person
 * @package app\api\controller
 */
class Person extends ApiBase
{
    private $_id;

    /**
     * @var PersonModel
     */
    private $_person;

    public function getPerson()
    {
        if (!$this->get_user_id() || !($this->_person = PersonModel::get(['user_id' => $this->get_user_id()]))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        }
        $this->_id = $this->_person->id;
    }

    public function index()
    {

    }
    //隐私设置操作
    public function secret(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $type=input('type');//1 允许预定  2 上架简历 3是否推送简历
        $is_show=input('is_show');
        $person_one=Db::name('person')->where(['user_id'=>$user_id])->find();
        if(!$person_one){
            $this->ajaxReturn(['status' => -2, 'msg' => '用户类型不对，请重新操作']);
        }
        $person=[];
        switch ($type){
            case 1:
                $person['reserve']=$is_show;
                break;
            case 2:
                $person['shelf']=$is_show;
                break;
            case 3:
                $person['pull']=$is_show;
                break;
            default:
                $this->ajaxReturn(['status' => -2, 'msg' => '类型不对，请重新设置']);
                break;
        }
        $where['user_id']=$user_id;
        $res=Db::name('person')->where($where)->update($person);
        if($res){
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功！']);
        }else{
            $this->ajaxReturn(['status' => -2, 'msg' => '修改失败！']);
        }
    }
    //省市区获取
    public function get_address(){
        $user_id = $this->get_user_id();
        $parent_id = input('parent_id/d',0);
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $where = $parent_id ? ['parent_id'=>$parent_id] : ['area_type'=>1];
        $list  = Db::name('region')->field('area_id,code,parent_id,area_name')->where($where)->select();
        $this->ajaxReturn(['status'=>1,'msg'=>'获取地址成功','data'=>$list]);
    }
    //隐私列表
    public function secret_list(){
        $user_id=$this->get_user_id();
        $person_one=Db::name('person')->field('reserve,shelf,pull')->where(['user_id'=>$user_id])->find();
        if(!$person_one){
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在或者用户类型不对，请重新操作']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功！','data'=>$person_one]);
    }
    // 信息
    public function info()
    {
        $data = Db::name('person')->where(['user_id' => $this->get_user_id()])
            ->field('id,name,gender,avatar,age,nation,work_age,daogang_time,salary,job_type,desc,experience,education')->find();
        if(!$data){
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在或者用户类型不对，请重新操作']);
        }
        $data['gender'] = $data['gender'] == 'male' ? 1 : 2;
        $data['avatar'] = SITE_URL . $data['avatar'];
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
    }

    // 编辑信息
    public function edit()
    {
        $this->getPerson();
        $data = input();
        $validate = $this->validate($data, 'User.person_edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }
        $data['desc'] = $data['person_desc'];
        unset($data['token'], $data['person_desc']);

        Db::startTrans();
        if (!$this->_person->daogang_time && !$this->_person->save($data)) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }
        $res = Db::name('audit')->insert([
            'type' => 3,
            'content_id' => $this->_person->user_id,
            'data' => json_encode($data,JSON_UNESCAPED_UNICODE),
            'create_time'=>time()
        ]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }

        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功！']);
    }
    //我的钱包
    public function my_wallet(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $sysset = Db::table('sysset')->field('*')->find();
        $set =json_decode($sysset['vip'], true);

        $member=Db::name('member')->where(['id'=>$user_id])->field('id,balance,regtype')->find();
        if(!$member){
            $this->ajaxReturn(['status' => -2, 'msg' => '获取失败','data'=>[]]);
        }
        $member['month_money']=$set['month_money'];
        $member['quarter_money']=$set['quarter_money'];
        $member['year_money']=$set['year_money'];
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功','data'=>$member]);
    }
    // 个人信息
    public function detail()
    {
        $id = input('id/d');
        if (!$id || !($recruit = Db::name('person')->where(['id' => $id])->find())) {
            $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
        }
        $this->get_user_id();
        if (!$this->get_user_id() || !($this->_com = CompanyModel::get(['user_id' => $this->get_user_id()]))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        }

        $detail = Db::name('person')
            ->alias('p')
            ->field('p.id,p.name,p.gender,p.avatar,p.school_type,m.mobile,p.age,p.work_age,p.images,p.job_type,p.desc,p.experience')
            ->join('member m', 'm.id=p.user_id', 'LEFT')
            ->where(['p.id' => $id])
            ->find();
        if(!(Reserve::getBy($this->_com->id,$id))){
            $detail['name'] =shadow($detail['name']);
            $detail['mobile'] =shadow($detail['mobile']);
        }
        $detail['gender'] = $detail['gender'] == 'female' ? '女' : '男';
        $detail['images'] = $detail['images']!='[]' ? 1 : 0;
        $detail['job_type'] = Category::getNameById($detail['job_type']) ?: '';

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $detail]);
    }
    //个人列表
    public function person_list(){
        $type=input('type');//工种
        $kw=input('kw');
        $where = [];
        $pageParam = ['query' => []];
        if($type){
            $where['p.job_type']=$type;
            $pageParam['query']['job_type'] = $type;
        }
        if($kw){
            $where['p.name|ca.cat_name'] = ['like', '%' . $kw . '%'];
            $pageParam['query']['kw'] = $kw;
        }
        $list=Db::name('person')->alias('p')
            ->join('category ca','ca.cat_id=p.job_type','LEFT')
            ->where($where)
            ->field('p.id,p.work_age,p.name,p.avatar,p.gender,p.images,ca.cat_name')
            ->paginate(10,false,$pageParam);
        if(!$list){
            $this->ajaxReturn(['status' => -2, 'msg' => '获取失败','data'=>$list]);
        }
        $list=$list->toArray();
        foreach ($list['data'] as $key=>&$value){
            $value['images'] = $value['images']?1:0;
            $na = $value['gender']=='female'?'女士':'先生';
            $value['name']=mb_substr($value['name'], 0, 1, 'utf-8').$na;
            unset($value['gender']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功','data'=>$list['data']]);

    }
    //分类列表
    public function category_list(){
        $pid=input('pid',0);
        $where['pid']=$pid;
        $where['is_show']=1;
        $category_list=Db::name('category')->where($where)->field('cat_id,cat_name')->select();
        if(!$category_list){
            $this->ajaxReturn(['status' => -2, 'msg' => '获取失败','data'=>$category_list]);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功','data'=>$category_list]);
    }
    //去提现页面
    public function go_withdrawal(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $member=Db::name('member')->where(['id'=>$user_id])->field('balance,openid,alipay_name,alipay')->find();
        if(!$member){
            $this->ajaxReturn(['status' => -2, 'msg' => '获取失败','data'=>[]]);
        }
        $member['max_money']=Sysset::getWDMax();
        $member['percent']=Sysset::getWDRate();

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功','data'=>$member]);
    }
    //提现
    public function withdrawal(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pay_tpye=input('pay_tpye');
        $money=input('money');
        if(!$pay_tpye||!$money){
            $this->ajaxReturn(['status' => -2, 'msg' => '参数错误','data'=>[]]);
        }
        $max_money=Sysset::getWDMax();
        $percent=Sysset::getWDRate();
        if($money>$max_money){
            $this->ajaxReturn(['status' => -2, 'msg' => '提现金额不能大于最大金额'.$max_money,'data'=>[]]);
        }
        $member=Db::name('member')->where(['id'=>$user_id])->find();
        $poundage=sprintf("%.2f",$money*$percent/100);;//手续费
        $order_money=$money+$poundage;
        if($order_money>$member['balance']){
            $this->ajaxReturn(['status' => -2, 'msg' => '提现失败，余额不足'.$order_money,'data'=>[]]);
        }
        if($pay_tpye==2){//微信

        }elseif($pay_tpye==4){//支付宝   后台审核
            $alipay=input('alipay');
            $alipay_name=input('alipay_name');
            if(!$alipay||!$alipay_name){
                $this->ajaxReturn(['status' => -2, 'msg' => '支付宝账户和名称不能为空','data'=>[]]);
            }
            $data['alipay']=$alipay;
            $data['alipay_name']=$alipay_name;
            Db::table('member')->where('id',$user_id)->update($data);
            $data=[];
            Db::startTrans();
            $data['user_id']=$user_id;
            $data['money']=$order_money;
            $data['rate']=$percent;
            $data['taxfee']=$poundage;
            $data['account']=$money;
            $data['type']=$pay_tpye;
            $data['status']=0;
            $data['createtime']=time();
            $wi_id=Db::name('member_withdrawal')->insertGetId($data);
            if($wi_id){
                Db::table('member')->where('id',$user_id)->setDec('balance',$order_money);
                $data=[];
                $data['user_id']=$user_id;
                $data['money']=$order_money;
                $data['old_balance']=$member['balance'];
                $data['balance']=sprintf("%.2f",$member['balance']-$order_money);
                $data['balance_type']='支付宝提现';
                $data['source_type']=4;
                $data['log_type']=0;
                $data['source_id']=$wi_id;
                $data['create_time']=time();
                Db::name('member_balance_log')->insertGetId($data);
                Db::commit();
                $this->ajaxReturn(['status' => 1, 'msg' => '已提交后台审核！','data'=>$wi_id]);
            }else{
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '提现失败','data'=>[]]);
            }
        }
    }
    public function recharge(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $money=input('moeny');
        $recharge['recharge_sn'] = 'R'.date('YmdHis',time()) . mt_rand(1000,9999);
        $recharge['money'] = $money;
        $recharge['user_id'] = $user_id;
        $recharge['type'] = 2;//预约支付
        $recharge['c_time'] = time();
        $recharge_id=Db::name('recharge')->insertGetId($recharge);
        if($recharge_id){
            $this->ajaxReturn(['status' => 5, 'msg' => '请支付','data'=>$recharge_id]);
        }else{
            $this->ajaxReturn(['status' => -2, 'msg' => '充值失败!','data'=>[]]);
        }
    }
    public function buy_vip(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $vip_type=input('vip_type');
        $pay_type=input('pay_type');
        if(!$vip_type||!$pay_type) {
            $this->ajaxReturn(['status' => -2, 'msg' => '参数错误','data'=>[]]);
        }
        $sysset = Db::table('sysset')->field('*')->find();
        $set =json_decode($sysset['vip'], true);
        $member['month_money']=$set['month_money'];
        $member['quarter_money']=$set['quarter_money'];
        $member['year_money']=$set['year_money'];
        $money=0;
        $company=Db::name('company')->where(['user_id'=>$user_id])->find();
        if(!$company){
            $this->ajaxReturn(['status' => -2, 'msg' => '该用户不存在','data'=>[]]);
        }
        $vip_time=$company['vip_time'];
        if($vip_time<time()){
            $vip_time=time();
        }
        switch ($vip_type){
            case 1:
                $money=$set['month_money'];
                $vip_time=strtotime("+1 month",$vip_time);
                break;
            case 2:
                $money=$set['quarter_money'];
                $vip_time=strtotime("+3 month",$vip_time);
                break;
            case 3:
                $money=$set['year_money'];
                $vip_time=strtotime("+12 month",$vip_time);
                break;
            default:
                $this->ajaxReturn(['status' => -2, 'msg' => '会员类型不存在','data'=>[]]);
                break;
        }
        if($money==0){
            $this->ajaxReturn(['status' => -2, 'msg' => '金额错误，开通失败','data'=>[]]);
        }
        $member=Db::name('member')->where(['id'=>$user_id])->find();

        if($pay_type==1){//余额支付
            if($money>$member){
                $this->ajaxReturn(['status' => -2, 'msg' => '余额不足，开通失败','data'=>[]]);
            }
            Db::startTrans();
            Db::table('member')->where('id',$user_id)->setDec('balance',$money);
            $data['is_vip']=1;
            $data['vip_type']=$vip_type;
            $data['vip_time']=$vip_time;
            $res=Db::name('company')->where(['user_id'=>$user_id])->update($data);
            if(!$res){
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '开通失败!','data'=>[]]);
            }
            $data=[];
            $data['user_id']=$user_id;
            $data['money']=$money;
            $data['old_balance']=$member['balance'];
            $data['balance']=sprintf("%.2f",$member['balance']-$money);
            $data['balance_type']='VIP购买';
            $data['source_type']=4;
            $data['log_type']=0;
            $data['source_id']=$user_id;
            $data['create_time']=time();
            Db::name('member_balance_log')->insertGetId($data);
            Db::commit();
        }elseif ($pay_type==2){//微信支付
            $recharge['recharge_sn'] = 'V'.date('YmdHis',time()) . mt_rand(1000,9999);
            $recharge['money'] = $money;
            $recharge['user_id'] = $user_id;
            $recharge['for_id'] = $vip_type;//vip类型
            $recharge['type'] = 2;//预约支付
            $recharge['c_time'] = time();
            $recharge_id=Db::name('recharge')->insertGetId($recharge);
            if($recharge_id){
                $this->ajaxReturn(['status' => 5, 'msg' => '请支付','data'=>$recharge_id]);
            }else{
                $this->ajaxReturn(['status' => -2, 'msg' => '充值失败!','data'=>[]]);
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '开通VIP成功','data'=>[]]);
    }
}
