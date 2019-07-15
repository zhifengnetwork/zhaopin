<?php
namespace app\admin\controller;

use app\common\model\Delivery as DeliveryModel;
use think\Db;
use think\Loader;
use think\Request;

class Delivery extends Common
{

    public function index(){

        $list    = DeliveryModel::field('*')
                    ->where([])
                    ->order('id DESC')
                    ->paginate(2);
        $this->assign('list', $list);
        $this->assign('meta_title', '配送方式');
        return $this->fetch();
    }

    public function add(){
        $this->assign('meta_title', '新增配送方式');
        return $this->fetch();
    }

    public function edit(){
        $this->assign('meta_title', '编辑配送方式');
        return $this->fetch();
    }

}