<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13 0013
 * Time: 11:25
 */

namespace app\common\util;

/**
 * Class Exception
 * @package app\common\util
 */
class Exception extends \Exception
{
    public function __construct($msg, $code = 0)
    {
        parent::__construct($msg, $code);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return [
            'code'  => $this->getCode(),
            'msg'   => $this->getMessage(),
            'status'=> $this->getCode(),
        ];
    }

    public function getFullData()
    {
        return [
            'code'  => $this->getCode(),
            'msg'   => $this->getMessage(),
            'line'  => $this->getLine(),
            'file'  => $this->getFile(),
            'status'=> $this->getCode(),
        ];
    }
    
    
    /**
     * @return false|string
     */
    public function getJson()
    {
        return json_encode($this->getData());
    }

    /**
     * @return false|string
     */
    public function getFullJson()
    {
        return json_encode($this->getFullData());
    }
}