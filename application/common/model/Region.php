<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;

class Region extends Model
{
    public static function getParentId($id)
    {
        return self::where(['code' => $id])->value('parent_id');
    }

    public static function getName($id)
    {
        return self::where(['code' => $id])->value('area_name') ?: '';
    }
}