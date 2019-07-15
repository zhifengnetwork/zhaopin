<?php


namespace app\common\logic;

use app\common\util\TpshopException;
use think\Model;
use think\Db;

/**
 * 门店类
 */
class Shop
{
    /**
     * 过滤远距离，
     * @param $shop_list
     * @param $lng
     * @param $lat
     * @param $distance | 单位Km
     * @return mixed
     */
    public function filterDistance($shop_list, $lng, $lat, $distance){
        if(empty($shop_list)) return [];
        if(is_array($shop_list)){
            foreach($shop_list as $k => $arr){
                $dis = $this->getDistance($lat, $lng, $arr['latitude'], $arr['longitude']);
                $shop_list[$k]['distance'] = round($dis,2);
                $shop_list[$k]['ngt_dis'] = $distance;
                if($dis > floatval($distance)){
                    unset($shop_list[$k]);
                }else{
                    $shop_list[$k]['distance'] = round($dis, 1);
                    $shop_list[$k]['shop_img'] = $this->getShopImg($arr['shop_id']);
                }
            }
            $shop_list = array_sort($shop_list,'distance','asc');
        }else{
            return [];
        }
        return array_values($shop_list);
    }
    /**
     * 获取2点之间的距离
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @return float|int
     */
    public function getDistance($lat1, $lng1, $lat2, $lng2)
    {
        $p = 3.1415926535898;
        $r = 6378.137;

        $radLat1 = $lat1 * ($p / 180);
        $radLat2 = $lat2 * ($p / 180);
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * ($p / 180)) - ($lng2 * ($p / 180));
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
        $s = $s * $r;
        $s = round($s * 10000) / 10000;
        return $s;
    }

    /**
     * @param $shop_id
     * @return mixed
     */
    function getShopImg($shop_id){
        $image_url = Db::name('shop_images')->where('shop_id',$shop_id)->value('image_url');
        $image_url = $image_url ?:'/public/static/images/haidilao.jpg';
        return $image_url;
    }
}