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
        return Db::name('audit')->alias('a')
            ->field('c.id as c_id,c.company_name,r.id as r_id')
            ->join('recruit r','r.id = a.content_id','LEFT')
            ->join('company c','r.company_id = c.id','LEFT')
            ->where(['a.id'=>$data['content_id']])->find();
    }

    public function getMemberDataAttr($value, $data){
        return Db::name('member')->where(['id'=>$data['content_id']])->find();
    }

}