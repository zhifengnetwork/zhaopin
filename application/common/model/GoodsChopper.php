<?php
namespace app\common\model;

use think\Model;
use think\Db;



class GoodsChopper extends Model
{
    protected $updateTime = false;
    protected $autoWriteTimestamp = true;

    public function update_status(){
         $endTime   = time();//当前时间
         $startTime = strtotime('-7 days');
         $where['start_time'][] = ['egt', $startTime];
         $where['start_time'][] = ['elt', $endTime];
         $where['end_time'][]   = ['egt', $endTime];
         $where['status'][]     = ['eq', 1];
         $this->where($where)->update(['status' => 2]);
    } 
    
}
