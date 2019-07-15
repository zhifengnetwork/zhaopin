<?php
namespace app\admin\model;

use think\Model;

class MgUser extends Model
{
    protected $autoWriteTimestamp = true;
    // 关闭自动写入update_time字段
    protected $updateTime = false;
}
