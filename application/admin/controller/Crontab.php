<?php
namespace app\admin\controller;
use app\common\model\GoodsChopper;
use think\Controller;

/**
 * 定时任务管理器
 */
class Crontab extends Controller
{
    /** 总执行任务数 */
    private $_total = 0;
    /** 有效任务计数器 */
    private $_vcounter = 0;
    /** 任务计数器 */
    private $_counter = 0;

    /**
     * 初始化
     */
    public function test()
    {
                
    }

    /*
     * 执行
     */
    public function run()
    {
        $tasks = [
            'crontab/updateChopper',
            // 'crontab/updateAgentMoney',
        ];
        echo '=== begin run tasks === ' . PHP_EOL;

        foreach ($tasks as $task) {
            $this->_run($task);
        }

        echo '=== end run tasks === ' . PHP_EOL . PHP_EOL;
        echo 'summary:' . PHP_EOL;
        echo "  tasks: " . $this->_vcounter . " (" . $this->_total . ")" . PHP_EOL;
        echo "  peak memory: " . number_format(memory_get_peak_usage() / 1024) . 'KB' . PHP_EOL;
    }

    /**
     * 真正执行
     */
    private function _run($task, $key = '')
    {
        $this->_counter++;

        if (!$task) {
            return;
        }

        $this->_vcounter++;

        echo "  # begin task: $key " . $task . PHP_EOL;
        $r = action(ucfirst($task));
        echo "progress: " . $this->_counter . "/" . $this->_total . PHP_EOL . PHP_EOL;
    }

    /**
     * 每天/每月/每周/0点
     */
    public function createGameStatistics()
    {
        if (date('H') != '00') {
            return;
        }
        $key = 'crontab-game-statistics';
        $d   = date('Y-m-d');
        if (cache($key) == $d) {
            return;
        }
        cache($key, $d);
            Statistics::setGameStatisticsEndTime(1);
        if (date('w', strtotime(date('Y-m-d'))) == 1) {
            Statistics::setGameStatisticsEndTime(2);
        }
        if (date('d', strtotime(date('Y-m-d'))) == 1) {
            Statistics::setGameStatisticsEndTime(3);
        }
    }

    //每天更新砍价商品状态
    public function updateChopper()
    {
        
        if (date('H') != '00'){
            return;
        }
        if (date('i') != '05') {
            return;
        }
        (new GoodsChopper())->update_status();
    }

}
