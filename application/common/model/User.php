<?php
namespace app\common\model;

use think\helper\Time;
use think\Model;

class User extends Model
{
    protected $autoWriteTimestamp = true;

    public static function followed($dephp_3){
        /**
         * 是否关注
         */
        $dephp_28 = !empty($dephp_3);
        if ($dephp_28){
            $dephp_29 = self::where(['openid' =>$dephp_28])->find();
            $dephp_28 = $dephp_29['state'] == 1;
        }
        return $dephp_28;
    }




}