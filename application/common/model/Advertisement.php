<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Advertisement extends Model
{
    protected $autoWriteTimestamp = true;
    // 关闭自动写入update_time字段
    protected $updateTime = false;


    /**
     * 图片上传
     */
    public static function pictureUpload($name, $compress = 1, $field = 'file')
    {
        if ($file = request()->file($field)) {

            if (!$file->check('img')) {
                return [1, $file->error()];
            }
            // 大小验证，暂时不处理
            $image = \think\Image::open($file);
            $saveName = DS . 'uploads' . DS . $name . DS . date('Ymd') . DS . md5(microtime(true)) . '.' . $image->type();

            $fileName = ROOT_PATH . 'public' . $saveName;

            // 创建目录
            $path = dirname($fileName);
            if (is_dir($path) || mkdir($path, 0755, true)) {
                $compress ? $image->thumb(30000, 30000, \think\Image::THUMB_SCALING)->save($fileName) : $image->save($fileName);
                return [0, $saveName];
            }
        }
        return [0, ''];
    }

    public static function getList()
    {
        $page = input('page', 1);
        $list = Db::table('advertisement')->field('picture,url')->where(['state' => 1, 'page_id' => $page])->limit(5)->order('type asc sort asc')->select();
        for ($i = 0; $i < count($list); $i++) {
            $list[$i]['picture'] = SITE_URL . '/public' . $list[$i]['picture'];
        }
        return $list;
    }
}
