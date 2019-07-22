<?php

namespace app\api\controller;

use app\common\model\Category;
use app\common\model\Company as CompanyModel;
use app\common\model\Region;
use app\common\model\Reserve;
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
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
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
        $data = Db::name('company')->field('id,logo,open_time,type,company_name,contacts_scale,desc,introduction,achievement')
            ->where(['id'=>$this->_id])->find();
        $open = $data['open_time'] ? explode('-', $data['open_time']) : [];
        $data['open_year'] = $open ? $open[0] : '';
        $data['open_month'] = $open ? $open[1] : '';
        $data['open_day'] = $open ? $open[2] : '';

        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
    }

    // 编辑信息
    public function edit()
    {
        $this->getCompany();
        $data = input();
        $validate = $this->validate($data, 'User.company_edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }

        $data['open_time'] = implode('-', [$data['open_year'], $data['open_month'], $data['open_day']]);
        unset($data['open_year'], $data['open_month'], $data['open_day']);
        $data['status'] = 0;
        $data['remark'] = '';
        if (!$this->_com->save($data)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }
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
            ->where($where)->order('id desc')->paginate(3, false, $pageParam);
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $list]);
    }

    // 编辑招聘 审核失败可编辑
    public function edit_recruit()
    {
        $this->getCompany();
        $id = input('id/d');
        if ($id > 0) {
            $recruit = Db::name('recruit')->where(array('company_id' => $this->_id, 'id' => $id))->find();
            if (!($recruit) || $recruit['status'] != 2) {
                $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
            }
        }
        $data = input();
        $validate = $this->validate($data, 'Recruit.edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }
        unset($data['token']);
        $data['status'] = 0;
        if ($id > 0) {
            if (Db::name('recruit')->where(['company_id' => $this->_id, 'id' => $id])->update($data)) {
                $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);
            }
        } else {
            $data['company_id'] = $this->_id;
            $data['create_time'] = time();
            if (Db::name('recruit')->insert($data)) {
                $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);
            }
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
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
            $where['r.province']=$province;
            $pageParam['query']['province']=$province;
        }
        $city=input('city');
        if($city){
            $where['r.city']=$city;
            $pageParam['query']['city']=$city;
        }
        $district=input('district');
        if($district){
            $where['r.district']=$district;
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
            ->field('co.logo,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate(3,false,$pageParam);
        $recruit_hot=$recruit_hot->toArray();
        $data['recruit_hot']=$recruit_hot['data'];
        unset($where['r.is_rcmd']);

        $where['r.is_better']=1;
        $recruit_better=Db::name('recruit')->alias('r')
            ->join('company co','co.id=r.company_id','LEFT')
            ->join('member m','m.id=co.user_id','LEFT')
            ->where($where)
            ->field('co.logo,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate(3,false,$pageParam);
        $recruit_better=$recruit_better->toArray();
        $data['recruit_better']=$recruit_better['data'];
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);

    }
    public function recruit_better(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam = ['query' => []];
        $province=input('province');
        if($province){
            $where['r.province']=$province;
            $pageParam['query']['province']=$province;
        }
        $city=input('city');
        if($city){
            $where['r.city']=$city;
            $pageParam['query']['city']=$city;
        }
        $district=input('district');
        if($district){
            $where['r.district']=$district;
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
            ->field('co.logo,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate(3,false,$pageParam);
        $recruit_better=$recruit_better->toArray();
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $recruit_better['data']]);
    }
    public function recruit_hot(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $pageParam = ['query' => []];
        $province=input('province');
        if($province){
            $where['r.province']=$province;
            $pageParam['query']['province']=$province;
        }
        $city=input('city');
        if($city){
            $where['r.city']=$city;
            $pageParam['query']['city']=$city;
        }
        $district=input('district');
        if($district){
            $where['r.district']=$district;
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
            ->field('co.logo,r.title,r.require_cert,r.salary,r.work_age')
            ->paginate(3,false,$pageParam);
        $recruit_hot=$recruit_hot->toArray();
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
        if (Db::name('company')->where(['id' => $this->_id])->update(['images' => json_encode($images)])) {
            $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
    }

    // 职位详情
    public function recruit_detail()
    {
        $id = input('id/d');
        if (!$id || !($recruit = Db::name('recruit')->where(['id' => $id])->find())) {
            $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
        }
        $detail = Db::name('recruit')
            ->alias('r')
            ->field('r.id,r.title,r.salary,r.work_age,c.province,c.logo,c.company_name,c.city,c.district,r.detail')
            ->join('company c', 'c.id=r.company_id', 'LEFT')
            ->where(['r.id' => $id])
            ->find();
        $detail['province'] = Region::getName($detail['province']);
        $detail['city'] = Region::getName($detail['city']);
        $detail['district'] = Region::getName($detail['district']);
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功', 'data' => $detail]);

    }

    // 预约列表
    public function reserve_list()
    {
        $this->getCompany();
        $where = ['r.company_id' => $this->_id];
        $param['query']['r.company_id'] = $this->_id;
        $list = Db::name('reserve')->alias('r')
            ->field('r.id,r.person_id,p.name,p.avatar,p.work_age,p.images,p.job_type')
            ->join('person p', 'p.id = r.person_id', 'LEFT')
            ->where($where)
            ->paginate(10, false, $param);

        $list=$list->toArray();
        foreach ($list['data'] as &$v) {
            $v['images'] = $v['images'] ? 1 : 0;
            $v['job_type'] = Category::getNameById($v['job_type']) ?: '';
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $list['data']]);

    }

    // 预约操作
    public function reserve()
    {
        $this->getCompany();
        $id = input('id/d');
        if(!Db::name('person')->where(['id'=>$id])->find()){
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        }
        $num=$this->look_num($this->_id);//可预约人数
        if($num>0){
            if(!Reserve::getBy($this->_id,$id)){
                if(Db::name('reserve')->insert(['company_id'=>$this->_id,'person_id'=>$id,'create_time'=>time()])){
                    $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
                }
            }
        }else{
            //TODO   预约支付
            $this->ajaxReturn(['status' => -2, 'msg' => '可预约人数不足，请充值或者购买VIP']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '操作失败']);
    }
    //查询该公司还有多少可查看（预约）人数
    public function look_num($company_id){
        $vip_type=Db::name('company')->where(['id'=>$company_id])->value('vip_type');
        $sysset = Db::table('sysset')->field('*')->find();
        $set =json_decode($sysset['vip'], true);
        $re_num=Db::name('reserve')->where(['company_id'=>$company_id])->count();
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
