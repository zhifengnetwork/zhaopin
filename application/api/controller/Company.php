<?php

namespace app\api\controller;

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

    public function __construct()
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
        $data = $this->_com->toArray();
        $open = $data['open_time'] ? explode('-', $data['open_time']) : [];
        $data['open_year'] = $open ? $open[0] : '';
        $data['open_month'] = $open ? $open[1] : '';
        $data['open_day'] = $open ? $open[2] : '';

        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $data]);
    }

    // 编辑信息
    public function edit()
    {
        $validate = $this->validate(input('get.'), 'User.company_edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }
        $data = input('get.');
        $data['open_time'] = implode('-', [$data['open_year'], $data['open_month'], $data['open_day']]);
        unset($data['open_year'], $data['open_month'], $data['open_day']);
        if (!$this->_com->save($data)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '保存失败！']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功！']);
    }

    // 公司招聘
    public function recruit()
    {
        $where = ['company_id' => $this->_id];
        $pageParam['query']['company_id'] = $this->_id;
        $list = Db::name('recruit')->where($where)->order('id desc')->paginate(3, false, $pageParam);
        $this->ajaxReturn(['status' => 1, 'msg' => '请求成功', 'data' => $list]);
    }

    // 编辑招聘
    public function edit_recruit()
    {
        $id = input('id');
        if ($id > 0) {
            if (!($recruit = Db::name('recruit')->where(array('company_id' => $this->_id, 'id' => $id))->find())) {
                $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
            }
        }
        $data = input('get.');
        $validate = $this->validate(input('get.'), 'Recruit.edit');
        if (true !== $validate) {
            return $this->ajaxReturn(['status' => -2, 'msg' => $validate]);
        }

        if ($id > 0) {
            Db::name('recruit')->where(['company_id' => $this->_id, 'id' => $id])->update($data);
        } else {
            $data['company_id'] = $this->_id;
            $data['create_time'] = time();
            Db::name('recruit')->insert($data);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '保存成功']);
    }

    // 职位详情
    public function detail(){
        $id = input('id/d');
        if (!$id||!($recruit = Db::name('recruit')->where(['id' => $id])->find())) {
            $this->ajaxReturn(['status' => -2, 'msg' => '信息不存在！']);
        }
        $detail = Db::name('recruit')
            ->alias('r')
            ->field('r.title,r.salary,r.work_age,c.province,c.logo,c.company_name,c.city,c.district,r.detail')
            ->join('company c','c.id=r.company_id','LEFT')
            ->where(['r.id' => $id])
            ->find();
        $detail['province'] = Region::getName($detail['province']);
        $detail['city'] =Region::getName($detail['city']);
        $detail['district'] =Region::getName($detail['district']);
            $this->ajaxReturn(['status' => 1, 'msg' => '保存成功','data'=>$detail]);

    }

}
