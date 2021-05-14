<?php



declare (strict_types=1);

namespace think\admin\extend;

/**
 * 随机数码管理扩展
 * Class CodeExtend
 * @package think\admin\extend
 */
class CodeExtend
{
    /**
     * 获取随机字符串编码
     * @param integer $size 编码长度
     * @param integer $type 编码类型(1纯数字,2纯字母,3数字字母)
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function random(int $size = 10, int $type = 1, string $prefix = ''): string
    {
        $numbs = '0123456789';
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        if (intval($type) === 1) $chars = $numbs;
        if (intval($type) === 3) $chars = "{$numbs}{$chars}";
        $code = $prefix . $chars[rand(1, strlen($chars) - 1)];
        while (strlen($code) < $size) $code .= $chars[rand(0, strlen($chars) - 1)];
        return $code;
    }

    /**
     * 唯一日期编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqidDate(int $size = 16, string $prefix = ''): string
    {
        if ($size < 14) $size = 14;
        $code = $prefix . date('Ymd') . (date('H') + date('i')) . date('s');
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 唯一数字编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqidNumber(int $size = 12, string $prefix = ''): string
    {
        $time = time() . '';
        if ($size < 10) $size = 10;
        $code = $prefix . (intval($time[0]) + intval($time[1])) . substr($time, 2) . rand(0, 9);
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 抽奖
     * @Author Cindy
     * @E-main cindyli@topichina.com.cn
     * @param  [type]                   $proArr [description]
     * @param  [type]                   $key    [概率字段]
     * @return [type]                           [description]
     */
     public static function get_rand($proArr,string $key) :array
     {    
        $num = count($proArr);
        for($i = 0; $i < $num; $i++) { 
            $arr[$i] = $i == 0 ? $proArr[$i][$key] : $proArr[$i][$key] + $arr[$i-1]; 
        } 
        $proSum = $arr[$num-1] * 100; //为更公平，扩大一下范围       
        $randNum = mt_rand(1, $proSum) % $arr[$num-1] + 1;  //$randNum 一定不大于 $arr[$num-1] 抽奖仅需一次即可
        // 概率数组循环   
        foreach ($arr as $k => $v) {   
            if ($randNum <= $v) {   
                $result = $proArr[$k];   
                break;   
            }        
        }     
        return $result;   
    }
}