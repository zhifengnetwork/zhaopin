<?php

namespace app\api\controller;

use app\common\model\Category;
use app\common\model\Company as CompanyModel;
use app\common\model\Region;
use think\Db;

/**
 * 公司，第三方
 * Class Company
 * @package app\api\controller
 */
class Company extends ApiBase
{
    private $_id;

    /**
     * @var CompanyModel
     */
    private $_com;

    public function getCompany()
    {
        if (!$this->get_user_id() || !($this->_com = CompanyModel::get(['user_id' => $this->get_user_id()]))) {
            $this->ajaxReturn(['status' => -1, 'msg' => '用户不存在']);
        }
        $this->_id = $this->_com->id;
    }

    public function index()
    {

    }

    // 信息
    public function info()
    {
        $this->getCompany();
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $member=Db::name('member')->where(['id'=>$user_id])->find();
        $regtype=$member['regtype'];
        $audit=Db::name('audit')->where(['content_id'=>$user_id])->where(['type'=>$regtype,'edit'=>1])->order('id DESC')->find();
        $company = Db::name('company')->field('id,logo,open_time,type,company_name,contacts_scale,desc,introduction,achievement,status')
            ->where(['user_id' => $this->get_user_id()])->find();
        if($company['status']==0){
            $this->ajaxReturn(['status' => -3, 'msg' => '审核中，暂不可编辑']);
        }
        if (!$audit) {
            $data = Db::name('company')->field('id,logo,open_time,type,company_name,contacts_scale,desc,introduction,achievement')
                ->where(['user_id' => $user_id])->find();
            if (!$data) {
                return $this->ajaxReturn(['status' => -2, 'msg' => '不存在的信息']);
            }
            $audit=Db::name('audit')->where(['content_id'=>$user_id])->where(['type'=>$regtype])->order('id DESC')->find();
            if($data['logo']){
                $data['logo'] = SITE_URL . $data['logo'];
            }
            if($data['open_time']){
                $open = $data['open_time'] ? explode('-', $data['open_time']) : [];
                $data['open_year'] = $open ? $open[0] : '';
                $data['open_month'] = $open ? $open[1] : '';
                $data['open_day'] = $open ? $open[2] : '';
            }else{
                $data['open_year'] =  '';
                $data['open_month'] = '';
                $data['open_day'] =  '';
            }
            $data['is_edit']=$audit['status'];
            $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
        }else{
            if($audit['status']==1){
                $data = Db::name('company')->field('id,logo,open_time,type,company_name,contacts_scale,desc,introduction,achievement')
                    ->where(['user_id' => $user_id])->find();
                if (!$data) {
                    return $this->ajaxReturn(['status' => -2, 'msg' => '不存在的信息']);
                }
                if($data['logo']){
                    $data['logo'] = SITE_URL . $data['logo'];
                }
                if($data['open_time']){
                    $open = $data['open_time'] ? explode('-', $data['open_time']) : [];
                    $data['open_year'] = $open ? $open[0] : '';
                    $data['open_month'] = $open ? $open[1] : '';
                    $data['open_day'] = $open ? $open[2] : '';
                }else{
                    $data['open_year'] =  '';
                    $data['open_month'] = '';
                    $data['open_day'] =  '';
                }
                $data['is_edit']=$audit['status'];
                $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
            }elseif($audit['status']==0){
                $data=json_decode($audit['data'],true);
                if(empty($company['logo'])&&$company['logo']){
                    $data['logo'] = SITE_URL . $company['logo'];
                }else{
                    $data['logo']='';
                }
                if(isset($data['open_time'])&&$data['open_time']){
                    $open = $data['open_time'] ? explode('-', $data['open_time']) : [];
                    $data['open_year'] = $open ? $open[0] : '';
                    $data['open_month'] = $open ? $open[1] : '';
                    $data['open_day'] = $open ? $open[2] : '';
                }else{
                    $data['open_year'] = '';
                    $data['open_month'] =  '';
                    $data['open_day'] = '';
                }
                $data['is_edit']=$audit['status'];//是否可编辑
                $data['remark']=$audit['remark'];
                $this->ajaxReturn(['status' => 1, 'msg' => '请1求成功', 'data' => $data]);
            }elseif ($audit['status']==-1){
                $data=json_decode($audit['data'],true);
                if(empty($company['logo'])&&$company['logo']){
                    $data['logo'] = SITE_URL . $company['logo'];
                }else{
                    $data['logo']='';
                }
                if(isset($data['open_time'])&&$data['open_time']){
                    $open = $data['open_time'] ? explode('-', $data['open_time']) : [];
                    $data['open_year'] = $open ? $open[0] : '';
                    $data['open_month'] = $open ? $open[1] : '';
                    $data['open_day'] = $open ? $open[2] : '';
                }else{
                    $data['open_year'] = '';
                    $data['open_month'] =  '';
                    $data['open_day'] = '';
                }
                $data['is_edit']=1;//是否可编辑
                $data['remark']=$audit['remark'];
                $this->ajaxReturn(['status' => 1, 'msg' => '请1求成功', 'data' => $data]);
            }else{
                $this->ajaxReturn(['status' => -2, 'msg' => '编辑信息状态异常！']);
            }
        }
    }
// 信息
    public function look_company()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $company_id=input('company_id');
        $data = Db::name('company')->field('id,logo,open_time,type,company_name,contacts_scale,desc,introduction,achievement,status')
            ->where(['id' => $company_id])->find();
        if (!$data) {
            return $this->ajaxReturn(['status' => -2, 'msg' => '不存在的信息']);
        }
        if($data['status']==0){
            $this->ajaxReturn(['status' => -3, 'msg' => '该公司信息审核中，暂不可查看']);
        }
        if($data['logo']){
            $data['logo'] = SITE_URL . $data['logo'];
        }
        if($data['open_time']){
            $open = $data['open_time'] ? explode('-', $data['open_time']) : [];
            $data['open_year'] = $open ? $open[0] : '';
            $data['open_month'] = $open ? $open[1] : '';
            $data['open_day'] = $open ? $open[2] : '';
        }else{
            $data['open_year'] =  '';
            $data['open_month'] = '';
            $data['open_day'] =  '';
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
    }
    // 编辑信息
    public function edit()
    {
        $this->getCompany();
        $regtype = Db::name('member')->where(['id'=>$this->get_user_id()])->value('regtype');
        if(Db::name('audit')->where(['type'=>$regtype,'content_id'=>$this->get_user_id(),'status'=>0])->find()){
            return $this->ajaxReturn(['status' => -2, 'msg' => '信息还在审核中，不可再次编辑']);
        }

        $data = input();
        $validate = $this->validate($data, 'User.company_edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }

        $data['open_time'] = implode('-', [$data['open_year'], $data['open_month'], $data['open_day']]);
        unset($data['token'],$data['open_year'], $data['open_month'], $data['open_day']);
        $data['edit'] = 1;
        Db::startTrans();
        if (!$this->_com->company_name && !$this->_com->type && !$this->_com->save($data)) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }
        $res = Db::name('audit')->insert([
            'type'=>$regtype,
            'content_id'=>$this->_com->user_id,
            'data'=>json_encode($data,JSON_UNESCAPED_UNICODE),
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

    // 公司招聘
    public function recruit()
    {
        $this->getCompany();
        $where = ['company_id' => $this->_id];
        $pageParam['query']['company_id'] = $this->_id;
        $list = Db::name('recruit')
            ->field('id,title,salary,work_age,type,require_cert,detail,status,remark')
            ->where($where)->order('id desc')
            ->select();
//            ->paginate(3, false, $pageParam);
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $list]);
    }
    // 查看公司招聘
    public function get_recruit_list()
    {
        $user_id = $this->get_user_id();
        $company_id = input('company_id',0);
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $where = ['company_id' => $company_id,'status' => 1];
        $pageParam['query']['company_id'] = $this->_id;
        $list = Db::name('recruit')
            ->field('id,title,salary,work_age,type,require_cert,detail,status,remark')
            ->where($where)->order('id desc')
            ->select();
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $list]);
    }
    //公司、第三方列表
    public function company_list(){
        $user_id = $this->get_user_id();
        $this->getCompany();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam=[];
        $rows=input('rows',10);
        $page=input('page',10);
        $regtype = Db::name('member')->where(['id'=>$this->get_user_id()])->value('regtype');
        if($regtype==1){
            $list=Db::name('company')->alias('c')
                ->join('member m','m.id=c.user_id','left')
                ->field('c.id,c.logo,c.open_time,c.type,c.company_name,c.contacts_scale,c.desc,c.introduction,c.achievement,c.status')
                ->where(['c.status'=>1,'m.regtype'=>2])
                ->paginate($rows, false, $pageParam);
//                ->limit(3)->select();
        }else{
            $list=Db::name('company')->alias('c')
                ->join('member m','m.id=c.user_id','left')
                ->field('c.id,c.logo,c.open_time,c.type,c.company_name,c.contacts_scale,c.desc,c.introduction,c.achievement,c.status')
                ->where(['c.status'=>1,'m.regtype'=>1])
                ->paginate($rows, false, $pageParam);
//                ->limit(3)->select();
        }
        $list=$list->toArray();
        $list=$list['data'];
        foreach ($list as $key=>$value){
            if($list[$key]['logo']){
                $list[$key]['logo'] = SITE_URL . $list[$key]['logo'];
            }
            if($list[$key]['open_time']){
                $open = $list[$key]['open_time'] ? explode('-', $list[$key]['open_time']) : [];
                $list[$key]['open_year'] = $open ? $open[0] : '';
                $list[$key]['open_month'] = $open ? $open[1] : '';
                $list[$key]['open_day'] = $open ? $open[2] : '';
            }else{
                $list[$key]['open_year'] =  '';
                $list[$key]['open_month'] = '';
                $list[$key]['open_day'] =  '';
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $list]);
    }
    // 编辑招聘 审核失败可编辑
    public function edit_recruit()
    {
        $this->getCompany();
        $id = input('id/d');
        if ($id > 0) {
            $recruit = Db::name('recruit')->where(array('company_id' => $this->_id, 'id' => $id))->find();
            if (!($recruit) || $recruit['status'] == 0) {
                $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
            }
            if(Db::name('audit')->where(['type'=>4,'content_id'=>$id,'status'=>0])->find()){
                return $this->ajaxReturn(['status' => -2, 'msg' => '信息还在审核中，不可再次编辑']);
            }
        }

        $data = input();
        $validate = $this->validate($data, 'Recruit.edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }
        unset($data['token']);
        $data['status'] = 0;

        Db::startTrans();
        if ($id > 0) {
            if ($recruit['edit'] == 0 && !Db::name('recruit')->where(['company_id' => $this->_id, 'id' => $id])->update(['edit' => 1])) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
            }
        } else {
            $data['company_id'] = $this->_id;
            $data['create_time'] = time();
            if (!($id = Db::name('recruit')->insertGetId($data))) {
                Db::rollback();
                $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
            }
        }
        $res = Db::name('audit')->insert([
            'type' => 4,
            'content_id' => $id,
            'data' => json_encode($data,JSON_UNESCAPED_UNICODE),
            'create_time'=>time()
        ]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }

        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);

    }

    // 删除招聘
    public function del_recruit()
    {
        $ids = input('ids');
        $ids = rtrim($ids, ',');
        if (empty($ids)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '职位不存在']);
        }

        if (!Db::name('recruit')->where("id in ($ids)")->delete()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '删除失败']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }
    public function recruit_list(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam = ['query' => []];
        $province=input('province');
        if($province){
            $where['co.province']=$province;
            $pageParam['query']['province']=$province;
        }
        $city=input('city');
        if($city){
            $where['co.city']=$city;
            $pageParam['query']['city']=$city;
        }
        $district=input('district');
        if($district){
            $where['co.district']=$district;
            $pageParam['query']['district']=$district;
        }
        $regtype=input('regtype',1);
        $where['r.is_rcmd']=1;
        $where['r.status']=1;
        $where['m.regtype']=$regtype;
        $recruit_hot=Db::name('recruit')->alias('r')
            ->join('company co','co.id=r.company_id','LEFT')
            ->join('member m','m.id=co.user_id','LEFT')
            ->where($where)
            ->field('co.logo,r.id,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate(3,false,$pageParam);
        $recruit_hot=$recruit_hot->toArray();
        foreach ($recruit_hot['data'] as $key=>&$value){
            if($value['logo']){
                $value['logo']=SITE_URL.$value['logo'];
            }
        }
        $data['recruit_hot']=$recruit_hot['data'];
        unset($where['r.is_rcmd']);

        $where['r.is_better']=1;
        $recruit_better=Db::name('recruit')->alias('r')
            ->join('company co','co.id=r.company_id','LEFT')
            ->join('member m','m.id=co.user_id','LEFT')
            ->where($where)
            ->field('co.logo,r.id,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate(3,false,$pageParam);
        $recruit_better=$recruit_better->toArray();
        foreach ($recruit_better['data'] as $k=>&$v){
            if($v['logo']){
                $v['logo']=SITE_URL.$v['logo'];
            }
        }
        $data['recruit_better']=$recruit_better['data'];
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);

    }
    public function recruit_better(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam = ['query' => []];
        $rows=input('rows',10);
        $province=input('province');
        if($province){
            $where['co.province']=$province;
            $pageParam['query']['province']=$province;
        }
        $city=input('city');
        if($city){
            $where['co.city']=$city;
            $pageParam['query']['city']=$city;
        }
        $district=input('district');
        if($district){
            $where['co.district']=$district;
            $pageParam['query']['district']=$district;
        }
        $regtype=input('regtype',1);
        $where['r.is_better']=1;
        $where['r.status']=1;
        $where['m.regtype']=$regtype;
        $recruit_better=Db::name('recruit')->alias('r')
            ->join('company co','co.id=r.company_id','LEFT')
            ->join('member m','m.id=co.user_id','LEFT')
            ->where($where)
            ->field('co.logo,r.id,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate($rows,false,$pageParam);
        $recruit_better=$recruit_better->toArray();
        foreach ($recruit_better['data'] as $k=>&$v){
            if($v['logo']){
                $v['logo']=SITE_URL.$v['logo'];
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $recruit_better['data']]);
    }
    public function recruit_hot(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam = ['query' => []];
        $rows=input('rows',10);
        $province=input('province');
        if($province){
            $where['co.province']=$province;
            $pageParam['query']['province']=$province;
        }
        $city=input('city');
        if($city){
            $where['co.city']=$city;
            $pageParam['query']['city']=$city;
        }
        $district=input('district');
        if($district){
            $where['co.district']=$district;
            $pageParam['query']['district']=$district;
        }
        $regtype=input('regtype',1);
        $where['r.is_rcmd']=1;
        $where['r.status']=1;
        $where['m.regtype']=$regtype;
        $recruit_hot=Db::name('recruit')->alias('r')
            ->join('company co','co.id=r.company_id','LEFT')
            ->join('member m','m.id=co.user_id','LEFT')
            ->where($where)
            ->field('co.logo,r.id,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate($rows,false,$pageParam);
        $recruit_hot=$recruit_hot->toArray();
        foreach ($recruit_hot['data'] as $key=>&$value){
            if($value['logo']){
                $value['logo']=SITE_URL.$value['logo'];
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $recruit_hot['data']]);
    }
    // 资料显示
    public function get_images()
    {
        $this->getCompany();
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => ['image' => json_decode($this->_com->images)]]);
    }

    // 资料管理
    public function edit_images()
    {
        $this->getCompany();
        $images = json_decode(input('images'), true);
        foreach ($images as &$v) {
            $v['image'] = $this->base64_to_img($v['image'],UPLOAD_PATH . 'company/');
            if(!$v['image']){
                $this->ajaxReturn(['status' => -2, 'msg' => '文件格式错误！']);
            }
        }
        if (Db::name('company')->where(['id' => $this->_id])->update(['images' => json_encode($images,JSON_UNESCAPED_UNICODE)])) {
            $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
    }

    // 职位详情
    public function recruit_detail()
    {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $id = input('id/d');
        if (!$id || !($recruit = Db::name('recruit')->where(['id' => $id])->find())) {
            $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
        }

        $detail = Db::name('recruit')
            ->alias('r')
            ->field('r.id,r.title,r.salary,r.work_age,c.province,c.logo,c.id company_id,c.company_name,c.city,c.district,r.detail')
            ->join('company c', 'c.id=r.company_id', 'LEFT')
            ->where(['r.id' => $id])
            ->find();
        $detail['is_collection']=0;
        $res=Db::name('collection')->where(['user_id'=>$user_id,'to_id'=>$id])->find();
        if($res){
            $detail['is_collection']=1;
        }
        $detail['province'] = Region::getName($detail['province']);
        $detail['city'] = Region::getName($detail['city']);
        $detail['district'] = Region::getName($detail['district']);
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功', 'data' => $detail]);

    }

    // 预约列表
    public function reserve_list()
    {
        $this->getCompany();
        $where = ['reserve_c' => $this->_id];
        $param['query']['reserve_c'] = $this->_id;
        $list = Db::name('person')
            ->field('id as person_id,name,avatar,work_age,images,job_type')
            ->where($where)
            ->paginate(10, false, $param);

        $list=$list->toArray();
        foreach ($list['data'] as &$v) {
            $v['images'] = $v['images']!='[]' ? 1 : 0;
            $v['job_type'] = Category::getNameById($v['job_type']) ?: '';
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $list['data']]);

    }

    // 预约操作
    public function reserve()
    {
        $this->getCompany();
        $id = input('id/d');
        if(!($person = Db::name('person')->where(['id'=>$id])->field('id,reserve_c')->find())){
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        }
        if ($person['reserve_c'] == $this->_id)
            $this->ajaxReturn(['status' => -2, 'msg' => '您已预约该应聘者']);
        if ($person['reserve_c'] != $this->_id && $person['reserve_c'] > 0)
            $this->ajaxReturn(['status' => -2, 'msg' => '已被预约']);
        $reserve_num=Db::name('company')->where(['id'=>$this->_id])->value('reserve_num');
//        $num=$this->look_num($this->_id);//可预约人数
        if($reserve_num>0){
            if (Db::name('person')->where(['id' => $id])->update(['reserve_c' =>$this->_id])) {
                //删除收藏该应聘者的数据，除了预约的第三方或公司
                Db::name('collection')->where(['type' => 2, 'to_id' => $id, 'user_id' => ['neq', $this->get_user_id()]])->delete();
                Db::name('company')->where(['id'=>$this->_id])->setDec('reserve_num',1);
                $this->ajaxReturn(['status' => 1, 'msg' => '预约成功']);
            }
        }else{
            $money=Db::name('config')->where(['name'=>'reserve_money'])->value('value');
            $recharge['recharge_sn'] = 'Y'.date('YmdHis',time()) . mt_rand(1000,9999);
            $recharge['money'] = $money;
            $recharge['user_id'] = $this->get_user_id();
            $recharge['type'] = 3;//预约支付
            $recharge['c_time'] = time();
            $recharge['for_id']=$this->_id;
            $recharge['to_id']=$id;
            $recharge_id=Db::name('recharge')->insertGetId($recharge);
            if($recharge_id){
                $this->ajaxReturn(['status' => 5, 'msg' => '请支付','data'=>$recharge_id]);
            }
            $this->ajaxReturn(['status' => -2, 'msg' => '可预约人数不足，请充值或者购买VIP']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '预约失败']);
    }
    //查询该公司还有多少可查看（预约）人数
    public function look_num($company_id){
        $vip_type=Db::name('company')->where(['id'=>$company_id])->value('vip_type');
        $sysset = Db::table('sysset')->field('*')->find();
        $set =json_decode($sysset['vip'], true);
        $re_num=Db::name('person')->where(['reserve_c'=>$company_id])->count();
        switch ($vip_type){
            case 1:
                $num=$set['month']-$re_num;
                break;
            case 2:
                $num=$set['quarter']-$re_num;
                break;
            case 3:
                $num=$set['year']-$re_num;
                break;
            default:
                $num=0;
                break;
        }
        return $num;
    }

}
