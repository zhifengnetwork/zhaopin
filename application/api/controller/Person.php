<?php

namespace app\api\controller;

use app\common\model\Category;
use app\common\model\Person as PersonModel;
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
        $this->_id = $this->get_user_id();
        if (!$this->_id || !($this->_person = PersonModel::get(['user_id'=>$this->_id]))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        }
    }

    public function index()
    {

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
        $validate = $this->validate(input('get.'), 'User.person_edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }
        $data = input('get.');
        $data['daogang_time'] = implode('-',[$data['daogang_year'],$data['daogang_month'],$data['daogang_day']]);
        $data['birth'] = implode('-',[$data['birth_year'],$data['birth_month'],$data['birth_day']]);
        unset($data['daogang_year'],$data['daogang_month'],$data['daogang_day'],$data['birth_year'],$data['birth_month'],$data['birth_day']);
        if (!$this->_person->save($data)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功！']);
    }

    // todo 隐藏部分信息，预定？
    public function detail(){
        $id = input('id/d');
        if (!$id||!($recruit = Db::name('person')->where(['id' => $id])->find())) {
            $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
        }
        $detail = Db::name('person')
            ->alias('p')
            ->field('p.name,p.gender,p.avatar,p.school_type,m.mobile,p.age,p.work_age,p.images,p.job_type,p.desc,p.experience')
            ->join('member m','m.id=p.user_id','LEFT')
            ->where(['p.id' => $id])
            ->find();
        $detail['gender'] = $detail['gender']=='female'?'女':'男';
        $detail['images'] = $detail['images']?1:0;
        $detail['job_type'] = Category::getNameById($detail['job_type']) ?: '';
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功','data'=>$detail]);
    }

    // 资料显示
    public function get_images()
    {
        $this->getPerson();
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => ['image' => json_decode($this->_person->images)]]);
    }

    // 资料管理
    public function edit_images()
    {
        $this->getPerson();
        if (Db::name('person')->where(['id' => $this->_id])->update(['images' => input('images')])) {
            $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '保存失败']);
    }


}
