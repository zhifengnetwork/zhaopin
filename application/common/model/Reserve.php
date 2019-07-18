<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Reserve extends Model
{
    static function getBy($c_id, $p_id)
    {
        return $c_id > 0 && $p_id > 0 ? Db::name('reserve')->where(['company_id' => $c_id, 'person_id' => $p_id])->find() : null;
    }
}