<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13 0013
 * Time: 14:58
 */

namespace app\common\util\safe;


class Validation
{
    private $error = true, $module, $controller, $data = [], $scene;

    /**
     * Validation constructor.
     * @param $module
     * @param $controller
     * @param array $data
     * @param string $scene
     */
    public function __construct($module, $controller, $data = [], $scene = '')
    {
        $this->module       = $module;
        $this->controller   = $controller;
        $this->data         = $data;
        $this->scene        = $scene;
    }

    /**
     * @return bool
     */
    public function check()
    {
        if ($this->hasFile()) {
            if (!empty($this->data)) {
                $vali = $this->getNameSpace();
                $oVali = new $vali();
                if ($this->scene && $oVali->hasScene($this->scene)) {
                    $oVali = $oVali->scene($this->scene);
                }
                if (true !== $oVali->check($this->data)) {
                    $this->error = $oVali->getError();
                }
            }
        }
        return $this->error !== false ? $this->error : true;
    }

    /**
     * @return string
     */
    protected function getNameSpace()
    {
        $module     = lcfirst($this->module);
        $controller = ucfirst($this->controller);
        return sprintf('app\\%s\\validate\\%s', $module, $controller);
    }

    /**
     * @return string
     */
    protected function getFilePath()
    {
        return sprintf('%s%s.php', ROOT_PATH, str_replace([
            'app\\',
            '\\'
        ], [
            'application/',
            '/'
        ], $this->getNameSpace()));
    }

    /**
     * @return bool
     */
    protected function hasFile()
    {
        return is_file($this->getFilePath());
    }

    /**
     * @return bool
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param $scene
     * @return $this
     */
    public function setScene($scene)
    {
        $this->scene    = $scene;
        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param $module
     * @param $controller
     * @param array $data
     * @param string $scene
     * @return Validation
     */
    public static function instance($module, $controller, array $data = [], $scene = '')
    {
        return new self($module, $controller, $data, $scene);
    }
}