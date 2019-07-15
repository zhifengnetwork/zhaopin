<?php

namespace app\common\logic;

use think\Model;

/**
 *活动抽象类
 * Class CatsLogic
 * @package common\Logic
 */
abstract class Prom extends Model
{
    abstract protected function getPromModel();//获取活动模型
    abstract protected function checkActivityIsAble();//活动是否正在进行
    abstract protected function checkActivityIsEnd();//检查活动是否结束
    abstract protected function getGoodsInfo();//获取商品详细
    abstract protected function IsAble();//活动是否已经失效
    abstract protected function getActivityGoodsInfo();//获取商品转换活动商品的数据
}