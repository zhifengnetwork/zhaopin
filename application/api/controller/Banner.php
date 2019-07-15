<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/5/29
 * Time: 18:43
 */

namespace app\api\controller;


use think\Db;

class Banner extends ApiBase
{
    public function banner () {
        $only_logo = request()->param('only_logo');
        if (empty($only_logo)){
            return json(['code'=>-2,'msg'=>'标识不能为空！','data'=>[]]);
        }else{
            $data['banner'] = $this->getAllData($only_logo);
            $data['advertising'] = $this->getAllData($only_logo,1);
            return json($data);
        }
    }

    public function getAllData ($only_logo,$type=0){
        return Db::table('page_advertisement')->alias('a')->join('advertisement b','a.id = b.page_id','left')
            ->where(['a.only_logo'=>$only_logo,'a.status'=>1,'b.state'=>1,'b.type'=>$type])->field('b.*')->select();
    }
}