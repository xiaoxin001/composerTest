<?php
/**
 * Created by PhpStorm.
 * User: ouyangxiaoxin
 * Date: 2018/4/8
 * Time: 下午10:07
 */
namespace OYXX\Tools;

class CommonUtils {

    // 获取访问IP
    public static function getRealIP()
    {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realip = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $realip = $_SERVER["REMOTE_ADDR"];
        }

        return $realip;
    }

    /**
     * 判断是否是https
     * @return bool
     */
    public static function isHTTPS(){
        $is_https_1 = false;
        if (array_key_exists('HTTPS', $_SERVER)){
            $is_https_1 = $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || $_SERVER['HTTPS'] === 443;
        }
        $is_https_2 = false;
        if (array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER)){
            $is_https_2 = $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
        }
        return $is_https_1 || $is_https_2;
    }

    //对象转数组
    public static function objectToArray($object){
        $result = array();
        $object = is_object($object) ? get_object_vars($object) : $object;
        foreach ($object as $key => $val) {
            $val = (is_object($val) || is_array($val)) ? self::objectToArray($val) : $val;
            $result[$key] = $val;
        }
        return $result;
    }


    /**
     * 创建随机字符串(包含字母、数字)
     * @param $length
     * @return string
     */
    public static function createRandomWord($length) {
        $data_src = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $len = strlen($data_src);

        $key = '';
        for($i=0; $i<$length; $i++) {
            $key .= $data_src[mt_rand(0, $len-1)];    //生成php随机数
        }

        return $key;
    }

    /**
     * 创建随机数字串(不包含字母)
     * @param $length
     * @return string
     */
    public static function createRandomNumber($length) {
        $data_src = '1234567890';
        $len = strlen($data_src);

        $key = '';
        for($i=0; $i<$length; $i++) {
            $key .= $data_src[mt_rand(0, $len-1)];    //生成php随机数
        }

        return $key;
    }

    /**
     * 签名重要接口
     *
     * $params 参数数组
     * $timestamp 时间戳
     * $noncestr 随机字符串
     **/
    public static function signatureMethod($params)
    {
        if(!array_key_exists('timestamp', $params) || !array_key_exists('noncestr', $params)) {
            return '';
        }

        $params['author'] = 'magina'; //加盐. 固定字符

        // 按字典排序
        ksort($params);
        $query_str = '';
        foreach($params as $key => $value) {
            // 注意, 键和值都需要自定义编码
            $query_str .= $key.'='.$value.'&';
        }

        $signature = substr($query_str, 0, strlen($query_str)-1);
        // md5 加密
        $signature = md5($signature);
        // 转大写, 加入签名键值对
        return strtoupper($signature);
    }

    /**
     * 验证是否是手机号码
     *
     * @param string $phone 待验证的号码
     * @return boolean 如果验证失败返回false,验证成功返回true
     */
    public static function isTelNumber($phone) {
        $rule = '/^1\d{10}$/';
        return preg_match($rule, $phone);
    }


    /**
     * 验证是否是https合法url
     *
     * @url $url 待验证链接
     * @return boolean 如果验证失败返回false,验证成功返回true
     */
    public static function isHttpsURL($url){
        $pattern = '/^(https):\/\//';
        return preg_match($pattern, $url);
    }


    /**
     * 将xml字符串转换为对象，如果字符串不是xml格式则返回false
     * @param $str
     * @return bool|\SimpleXMLElement
     */
    public static function xmlParser($str){
        $xml_parser = xml_parser_create();
        if(!xml_parse($xml_parser,$str,true)){
            xml_parser_free($xml_parser);
            return false;
        }
        return simplexml_load_string($str, 'SimpleXMLElement', LIBXML_NOCDATA);
    }
}
