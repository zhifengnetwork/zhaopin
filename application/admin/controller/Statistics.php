<?php
namespace app\admin\controller;

use think\Db;

/**
 * 统计首页
 */
class Statistics extends Common
{
    public function index()
    { 
        $this->assign('meta_title', '统计首页');
        return $this->fetch();
    }

    public function sales()
    {
        $years        = array();
        $current_year = date('Y');
        $year         = empty(input('year')) ? $current_year : input('year');
        for ($i = $current_year - 10; $i <= $current_year; $i++) {
            $years[] = array(
                'data' => $i,
                'selected' => ($i == $year)
            );
        }
        $months        = array();
        $month         = input('month',0);
        for ($i = 1; $i <= 12; $i++) {
            $months[] = array(
                'data' => $i,
                'selected' => ($i == $month)
            );
        }
        $day = input('day',0);
        $type = input('type',0);

        $totalcount = 0;
        $maxcount = 0;
        $maxcount_date = '';
        $list = [];

        if (!empty($year) && !empty($month) && !empty($day)){

            for ($hour = 0; $hour < 24; $hour++) {
                $nexthour = $hour + 1;
                $where['pay_time'] = [
                    ['>=',strtotime("{$year}-{$month}-{$day} {$hour}:00:00")],
                    ['<',strtotime("{$year}-{$month}-{$day} {$hour}:59:59") + 1],
                    'and'
                ];
                if ($type == 0){
                    $count = model('Order')->where($where)->sum('total_amount');
                }else{
                    $count = model('Order')->where($where)->count('*');
                }
                $dr       = array(
                    'data' => $hour . '点 - ' . $nexthour . "点",

                    'count' =>$count
                );
                $totalcount += $dr['count'];
                if ($dr['count'] > $maxcount) {
                    $maxcount      = $dr['count'];
                    $maxcount_date = "{$year}年{$month}月{$day}日 {$hour}点 - {$nexthour}点";
                }
                $list[] = $dr;
            }
        }else if (!empty($year) && !empty($month)) {
            $lastday = date('t', strtotime("{$year}-{$month} -1"));

            for ($d = 1; $d <= $lastday; $d++) {
                $where['pay_time'] = [
                    ['>=',strtotime("{$year}-{$month}-{$d} 00:00:00")],
                    ['<',strtotime("{$year}-{$month}-{$d} 23:59:59") + 1],
                    'and'
                ];
                if ($type == 0){
                    $count = model('Order')->where($where)->sum('total_amount');
                }else{
                    $count = model('Order')->where($where)->count('*');
                }
                $dr       = array(
                    'data' => $d . "日",

                    'count' =>$count
                );
                $totalcount += $dr['count'];
                if ($dr['count'] > $maxcount) {
                    $maxcount      = $dr['count'];
                    $maxcount_date = "{$year}年{$month}月{$d}日";
                }
                $list[] = $dr;
            }
        } else if (!empty($year)) {
//            var_dump($months);die;
            foreach ($months as $m) {
                $lastday = date('t', strtotime("{$year}-{$m['data']} -1"));
                $where['pay_time'] = [
                    ['>=',strtotime("{$year}-{$m['data']}-1 00:00:00")],
                    ['<',strtotime("{$year}-{$m['data']}-{$lastday} 23:59:59") + 1],
                    'and'
                ];
                if ($type == 0){
                    $count = model('Order')->where($where)->sum('total_amount');
                }else{
                    $count = model('Order')->where($where)->count('*');
                }
                $dr       = array(
                    'data' => $m['data'] . "月",

                    'count' =>$count
                );
                $totalcount += $dr['count'];
                if ($dr['count'] > $maxcount) {
                    $maxcount      = $dr['count'];
                    $maxcount_date = "{$year}年{$m['data']}月";
                }
                $list[] = $dr;
            }
        }



        $this->assign('list',$list);
        $this->assign('maxcount',$maxcount);
        $this->assign('totalcount',$totalcount);
        $this->assign('maxcount_date',$maxcount_date);
        $this->assign('years',$years);
        $this->assign('months',$months);
        $this->assign('day',$day);
        $this->assign('type',$type);
        return $this->fetch();
    }

}
