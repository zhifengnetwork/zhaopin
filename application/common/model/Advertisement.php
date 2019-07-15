<?php
namespace app\common\model;

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
            $image    = \think\Image::open($file);
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
}
