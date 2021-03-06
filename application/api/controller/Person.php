<?php

namespace app\api\controller;

use app\common\model\Category;
use app\common\model\Company as CompanyModel;
use app\common\model\MemberWithdrawal;
use app\common\model\Person as PersonModel;
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
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在']);
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
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $audit=Db::name('audit')->where(['content_id'=>$user_id])->where(['type'=>3])->where(['edit'=>1])->order('id DESC')->find();
        $status = Db::name('person')->where(['user_id' => $this->get_user_id()])->value('status');
        if($status==0){
            $this->ajaxReturn(['status' => -3, 'msg' => '审核中，暂不可编辑']);
        }
        if (!$audit){
            $data = Db::name('person')->where(['user_id' => $this->get_user_id()])
                ->field('id,name,gender,avatar,age,nation,work_age,daogang_time,salary,job_type,desc,experience,education,province,city,district,school_type')->find();
            if(!$data){
                $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在或者用户类型不对，请重新操作']);
            }
            $audit_person=Db::name('audit')->where(['content_id'=>$user_id])->where(['type'=>3])->order('id DESC')->find();
            $data['is_edit']=$audit_person['status'];
            $data['gender'] = $data['gender'] == 'male' ? 1 : 2;
            $data['province_str']=$this->address($data['province']);
            $data['city_str']=$this->address($data['city']);
            $data['district_str']=$this->address($data['district']);
            $data['avatar'] = SITE_URL . ($data['avatar']?:'/public/images/default.jpg');
            $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
        }else{
            if($audit['status']==1){
                $data = Db::name('person')->where(['user_id' => $this->get_user_id()])
                    ->field('id,name,gender,avatar,age,nation,work_age,daogang_time,salary,job_type,desc,experience,education,province,city,district,school_type')->find();
                if(!$data){
                    $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在或者用户类型不对，请重新操作']);
                }
                $data['is_edit']=$audit['status'];
                $data['gender'] = $data['gender'] == 'male' ? 1 : 2;
                $data['province_str']=$this->address($data['province']);
                $data['city_str']=$this->address($data['city']);
                $data['district_str']=$this->address($data['district']);
                $data['avatar'] = SITE_URL . ($data['avatar']?:'/public/images/default.jpg');
                $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
            }elseif($audit['status']==0){
                $data=json_decode($audit['data'],true);
                $data['gender'] = $data['gender'] == 'male' ? 1 : 2;
                if(isset($data['avatar'])&&$data['avatar']){
                    $data['avatar'] = SITE_URL . $data['avatar'];
                }else{
                    $data['avatar']= SITE_URL . '/public/images/default.jpg';
                }
                $data['province_str']=$this->address($data['province']);
                $data['city_str']=$this->address($data['city']);
                $data['district_str']=$this->address($data['district']);
                $data['is_edit']=$audit['status'];//是否可编辑
                $data['remark']=$audit['remark'];
                $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
            }elseif($audit['status']==-1){
                $data=json_decode($audit['data'],true);
                $data['gender'] = $data['gender'] == 'male' ? 1 : 2;
                if(isset($data['avatar'])&&$data['avatar']){
                    $data['avatar'] = SITE_URL . $data['avatar'];
                }else{
                    $data['avatar']= SITE_URL . '/public/images/default.jpg';
                }
                $data['province_str']=$this->address($data['province']);
                $data['city_str']=$this->address($data['city']);
                $data['district_str']=$this->address($data['district']);
                $data['is_edit']=1;//是否可编辑
                $data['remark']=$audit['remark'];
                $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
            }else{
                $this->ajaxReturn(['status' => -2, 'msg' => '用户编辑信息状态异常！']);
            }
        }


    }

    // 编辑信息
    public function edit()
    {
        $this->getPerson();
        if(Db::name('audit')->where(['type'=>3,'content_id'=>$this->get_user_id(),'status'=>0])->find()){
            return $this->ajaxReturn(['status' => -2, 'msg' => '信息还在审核中，不可再次编辑']);
        }

        $data = input();
        $validate = $this->validate($data, 'User.person_edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }
        $data['desc'] = $data['person_desc'];
        unset($data['token'], $data['person_desc']);

        Db::startTrans();
//        if (!$this->_person->daogang_time && !$this->_person->save($data)) {
//            Db::rollback();
//            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
//        }
        $res = Db::name('audit')->insert([
            'type' => 3,
            'content_id' => $this->_person->user_id,
            'data' => json_encode($data,JSON_UNESCAPED_UNICODE),
            'edit'=>1,
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
        $member['month_num']=$set['month'];
        $member['quarter_num']=$set['quarter'];
        $member['year_num']=$set['year'];
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功','data'=>$member]);
    }
    // 个人信息:没被预约都隐藏，被预约其他人看不到，本公司看全部
    public function detail()
    {
        $id = input('id/d');
        if (!$id) $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
        $detail = Db::name('person')
            ->field('id,user_id,name,gender,avatar,school_type,age,work_age,images,job_type,desc,experience,reserve_c,province,city,district,degree,reserve')
            ->where(['id' => $id,'status'=>1])->find();


        if (!$detail) $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);

        $user_id=$this->get_user_id();

        $member=Db::name('member')->where(['id'=>$user_id])->find();
        $detail['is_collection']=0;
        if($member['regtype']==1||$member['regtype']==2){
            $res=Db::name('collection')->where(['user_id'=>$user_id,'to_id'=>$id])->find();
            if($res){
                $detail['is_collection']=1;
            }
        }

        if (!$this->get_user_id() || !($company = CompanyModel::get(['user_id' => $this->get_user_id()]))) {
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在']);
        }
        $detail['mobile'] = Db::name('member')->where(['id'=>$detail['user_id']])->value('mobile');
        if ($detail['reserve_c'] > 0 && $detail['reserve_c'] != $company->id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '该用户已被预约，无法显示']);

        } elseif ($detail['reserve_c'] == 0) {// 不是当前公司第三方的预约，隐藏信息
//            $detail['name'] = shadow($detail['name']);
            $detail['mobile'] = shadow($detail['mobile']);
//            $detail['school_type'] = '***';
//            $detail['work_age'] = '***';
//            $detail['job_type'] = '***';
//            $detail['desc'] = '***';
            $detail['address']=$this->address($detail['province']).$this->address($detail['city']).$this->address($detail['district']);
            $detail['education'] = '***';
            $detail['experience'] = '***';
            $detail['avatar']=SITE_URL.($detail['avatar']?:'/public/images/default.jpg');
            unset($detail['province']);
            unset($detail['city']);
            unset($detail['district']);
        }

        $detail['gender'] = $detail['gender'] == 'female' ? '女' : '男';
        $detail['images'] = $detail['images']!='[]' ? 1 : 0;
        $detail['job_type'] = Category::getNameById($detail['job_type']) ?: '';
        $detail['reserve'] = $detail['reserve'] == 1 ? 1 : 0;

        // 预约所需金额
        $detail['reserve_money'] = Db::name('config')->where(['name'=>'reserve_money'])->value('value');

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $detail]);
    }
    public function address($code){
        if($code){
            return Db::name('region')->where(['code'=>$code])->value('area_name');
        }else{
            return '';
        }
    }
    // 个人列表
    public function person_list(){
        if (!$this->get_user_id() || !($this->_com = CompanyModel::get(['user_id' => $this->get_user_id()]))) {
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在']);
        }
        $type=input('type');//工种
        $rows=input('rows',10);
        $kw=input('kw');
        $where = ['p.status'=>1,'p.reserve_c' => [['=', 0], ['=', $this->_com->id], 'or']];
        $pageParam = ['query' => ['p.status'=>1,'p.reserve_c' => [['=', 0], ['=', $this->_com->id], 'or']]];
        if($type){
            $where['p.job_type']=$type;
            $pageParam['query']['job_type'] = $type;
        }
        if($kw){
            $where['p.name|ca.cat_name'] = ['like', '%' . $kw . '%'];
            $pageParam['query']['kw'] = $kw;
        }
        $province=input('province');
        if($province){
            $where['p.province']=$province;
            $pageParam['query']['province']=$province;
        }
        $city=input('city');
        if($city){
            $where['p.city']=$city;
            $pageParam['query']['city']=$city;
        }
        $district=input('district');
        if($district){
            $where['p.district']=$district;
            $pageParam['query']['district']=$district;
        }
        $where['p.reserve_c']=0;
        $where['p.status']=1;
        $list=Db::name('person')->alias('p')
            ->join('category ca','ca.cat_id=p.job_type','LEFT')
            ->where($where)
            ->field('p.id,p.work_age,p.name,p.avatar,p.gender,p.images,ca.cat_name,p.status,p.reserve_c,p.school_type,p.salary')
            ->paginate($rows,false,$pageParam);
        if(!$list){
            $this->ajaxReturn(['status' => -2, 'msg' => '获取失败','data'=>$list]);
        }
        $list=$list->toArray();
        foreach ($list['data'] as $key=>&$value){
            $value['images'] = $value['images']!='[]'?1:0;
            $na = $value['gender']=='female'?'女士':'先生';
            $value['name']=mb_substr($value['name'], 0, 1, 'utf-8').$na;
            if($value['avatar']){
                $value['avatar']=SITE_URL.$value['avatar'];
            }else{
                $value['avatar']=SITE_URL.'/public/images/default.jpg';
            }
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
        if($money>$member['balance']){
            $this->ajaxReturn(['status' => -2, 'msg' => '提现失败，余额不足'.$money,'data'=>[]]);
        }
        $poundage=sprintf("%.2f",$money*$percent/100);;//手续费
        $order_money=$money-$poundage;
        if($pay_tpye==2){//微信
            if(!$member['openid']){
                $this->ajaxReturn(['status' => 8, 'msg' => '授权微信登录','data'=>[]]);
            }
            $account['openid'] = $member['openid'];
        }elseif($pay_tpye==4) {//支付宝   后台审核
            $alipay = input('alipay');
            $alipay_name = input('alipay_name');
            if (!$alipay || !$alipay_name) {
                $this->ajaxReturn(['status' => -2, 'msg' => '支付宝账户和名称不能为空', 'data' => []]);
            }
            $data['alipay'] = $alipay;
            $data['alipay_name'] = $alipay_name;
            if (Db::table('member')->where('id', $user_id)->update($data)===false) {
                $this->ajaxReturn(['status' => -2, 'msg' => '更新支付宝信息失败', 'data' => []]);
            }
            $account = $data;
        }
        $data=[];
        Db::startTrans();
        $data['user_id']=$user_id;
        $data['money']=$money;
        $data['rate']=$percent;
        $data['taxfee']=$poundage;
        $data['account']=$order_money;
        $data['type']=$pay_tpye;
        $data['status']=0;
        $data['data']=json_encode($account);
        $data['createtime']=time();
        $wi_id=Db::name('member_withdrawal')->insertGetId($data);
        if(!$wi_id||!Db::table('member')->where('id',$user_id)->setDec('balance',$money)){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '提现失败','data'=>[]]);
        }
        $data=[];
        $data['user_id']=$user_id;
        $data['money']=$money;
        $data['old_balance']=$member['balance'];
        $data['balance']=sprintf("%.2f",$member['balance']-$money);
        $data['balance_type']=($pay_tpye==2?'微信':'支付宝').'提现';
        $data['source_type']=4;
        $data['log_type']=0;
        $data['source_id']=$wi_id;
        $data['create_time']=time();
        if(!Db::name('member_balance_log')->insertGetId($data)){
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '提现失败','data'=>[]]);
        }
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '已提交后台审核！','data'=>$wi_id]);
    }

    public function withdrawal_list(){
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $where = ['user_id'=>$user_id];
        $list=Db::name('member_withdrawal')
            ->where($where)
            ->field('id,money,createtime,status')
            ->order('id desc')
            ->paginate(20,false,['query' => $where]);
        if(!$list){
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功','data'=>[]]);
        }
        $list=$list->toArray();
        foreach ($list['data'] as &$value){
            $value['createtime']=date('Y-m-d', $value['createtime']);
            $value['status']=MemberWithdrawal::getStatusTextBy($value['status']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功','data'=>$list['data']]);
    }

    //提现
    public function withdrawal222(){
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
        $money = input('money');
        $money = bcadd($money,0,2);
        if($money<0.01||$money>100000){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'金额不能小于0.01,大于100000','data'=>$money]);
        }
        $recharge['recharge_sn'] = 'R'.date('YmdHis',time()) . mt_rand(1000,9999);
        $recharge['money'] = $money;
        $recharge['user_id'] = $user_id;
        $recharge['type'] = 1;//充值
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
        $num=0;
        switch ($vip_type){
            case 1:
                $money=$set['month_money'];
                $num=$set['month'];
                $vip_time=strtotime("+1 month",$vip_time);
                break;
            case 2:
                $money=$set['quarter_money'];
                $num=$set['quarter'];
                $vip_time=strtotime("+3 month",$vip_time);
                break;
            case 3:
                $money=$set['year_money'];
                $num=$set['year'];
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
            if($money>$member['balance']){
                $this->ajaxReturn(['status' => -2, 'msg' => '余额不足，开通失败','data'=>[]]);
            }
            Db::startTrans();
            Db::table('member')->where('id',$user_id)->setDec('balance',$money);
            $data['is_vip']=1;
            $data['vip_type']=$vip_type;
            $data['vip_time']=$vip_time;
            $data['reserve_num']=$company['reserve_num']+$num;
            $data['reserve_num_all']=$company['reserve_num_all']+$num;
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
            $data['source_type']=3;
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
            $recharge['type'] = 2;//VIP
            $recharge['c_time'] = time();
            $recharge_id=Db::name('recharge')->insertGetId($recharge);
            if($recharge_id){
                $this->ajaxReturn(['status' => 5, 'msg' => '请支付','data'=>$recharge_id]);
            }else{
                $this->ajaxReturn(['status' => -2, 'msg' => '开通失败!','data'=>[]]);
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '开通VIP成功','data'=>[]]);
    }
}
