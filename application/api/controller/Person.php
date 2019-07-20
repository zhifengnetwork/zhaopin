<?php

namespace app\api\controller;

use app\common\model\Category;
use app\common\model\Company as CompanyModel;
use app\common\model\Person as PersonModel;
use app\common\model\Reserve;
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
        if (!$this->_id || !($this->_person = PersonModel::get(['user_id' => $this->get_user_id()]))) {
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
        $this->getPerson();
        $data = $this->_person->toArray();
        $birth = $data['birth'] ? explode('-', $data['birth']) : [];
        $data['birth_year'] = $birth ? $birth[0] : '';
        $data['birth_month'] = $birth ? $birth[1] : '';
        $data['birth_day'] = $birth ? $birth[2] : '';

        $daogang = $data['daogang_time'] ? explode('-', $data['daogang_time']) : [];
        $data['daogang_year'] = $daogang ? $daogang[0] : '';
        $data['daogang_month'] = $daogang ? $daogang[1] : '';
        $data['daogang_day'] = $daogang ? $daogang[2] : '';

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

        $data['daogang_time'] = implode('-',[$data['daogang_year'],$data['daogang_month'],$data['daogang_day']]);
        $data['birth'] = implode('-',[$data['birth_year'],$data['birth_month'],$data['birth_day']]);
        unset($data['daogang_year'],$data['daogang_month'],$data['daogang_day'],$data['birth_year'],$data['birth_month'],$data['birth_day']);
        if (!$this->_person->save($data)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功！']);
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
        // 非预定，隐藏信息
        if(!(Reserve::getBy($this->_com->id,$id))){
            $detail['name'] =shadow($detail['name']);
            $detail['mobile'] =shadow($detail['mobile']);
        }
        $detail['gender'] = $detail['gender'] == 'female' ? '女' : '男';
        $detail['images'] = $detail['images']!='[]' ? 1 : 0;
        $detail['job_type'] = Category::getNameById($detail['job_type']) ?: '';
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $detail]);
    }
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

}
