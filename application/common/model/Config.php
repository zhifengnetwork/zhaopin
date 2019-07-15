<?php
namespace app\common\model;

use think\Model;

class Config extends Model
{
    protected $autoWriteTimestamp = true;

    /**
     * 获取配置
     */
    public function getConfig()
    {
        $data = cache('config');
        if (!$data) {
            $data = $this->where('status', 1)->column('value', 'name');
            cache('config', $data);
        }
        return $data;
    }

    /**
     * 重载配置
     * 每次修改完或者新增都要调用这个方法
     */
    public function resetConfig()
    {
        cache('config', $this->where('status', 1)->column('value', 'name'));
    }

    /**
     * 快捷保存
     */
    public function _save($name, $value, $module = 1, $title = false)
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        $res  = false;
        $data = $this->where('module', $module)->where('name', $name)->find();
        if ($data) {
            $res = $this->where('id', $data->getAttr('id'))->update(['value' => $value]);
        } else {
            $data = [
                'module' => $module,
                'name'   => $name,
                'title'  => $title,
                'value'  => $value,
            ];
            $res = $this->save($data);
        }

        if ($res) {
            $this->resetConfig();
        }

        return $res;
    }

    /**
     * 修改配置
     */
    public function updateConfig($data)
    {
        $list = $this->getConfig();
        foreach ($data as $k => $v) {
            if (array_key_exists($k, $list)) {
                if (Config::where('name', $k)->update(['value' => $v]) === false) {
                    return false;
                }
            }
        }
        $this->resetConfig();
        return true;
    }
}
