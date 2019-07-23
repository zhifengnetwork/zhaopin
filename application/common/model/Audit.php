<?php
namespace app\common\model;

use think\Db;
use think\helper\Time;
use think\Model;

class Audit extends Model
{
    public function getDataArrayAttr($value, $data){
        return json_decode($data['data'],true);
    }

    public function getCompanyDataAttr($value, $data){
        return Db::name('company')->where(['user_id'=>$data['content_id']])->find();
    }

    public function getPersonDataAttr($value, $data){
        return Db::name('person')->where(['user_id'=>$data['content_id']])->find();
    }

    public function getRecruitDataAttr($value, $data){
        return Db::name('recruit')->where(['id'=>$data['content_id']])->find();
    }


    public function getMemberDataAttr($value, $data){
        return Db::name('member')->where(['id'=>$data['content_id']])->find();
    }

}