<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Category extends Model
{
    static function getNameById($id)
    {
        return $id > 0 ? Db::name('category')->where(['cat_id' => $id])->value('cat_name') : '';
    }
}