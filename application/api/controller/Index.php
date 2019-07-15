<?php
/**
 * 用户API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use think\Db;

class Index extends ApiBase
{

   /**
    * 首页接口
    */
    public function index()
    {
        // $redis = $this->getRedis();
        // for($i=1;$i<=10;$i++){
        //     $redis->rpush('ss',1);
        // }

        // $n = 12;
        // for($i=0;$i<$n;$i++){
        //     if( $redis->lpop('ss') <= 0 ){
        //         for($j=1;$j<=$i;$j++){
        //             $redis->rpush('ss',1);
        //             continue;
        //         }
        //         echo "还有{$i}件可购买";die;
        //         continue;
        //     }
        // }


        // die;
        echo '这里真的是api模块的首页';
        exit;
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }


        $data = '首页数据';
        

        $this->ajaxReturn(['status' => 0 , 'msg'=>'获取成功','data'=>$data]);
    }

    /***
     * 首页ID
     */
    public function page(){
        // $user_id = $this->get_user_id();
        // if(!$user_id){
        //     $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        // }
        $ewei = Db::name('diy_ewei_shop')->where(['status' => 1])->find();

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取首页成功！','data'=>$ewei['id']]);
    }

    

    

    
}
