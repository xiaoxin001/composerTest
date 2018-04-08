<?php
/**
 * Created by PhpStorm.
 * User: ouyangxiaoxin
 * Date: 2018/4/8
 * Time: 下午10:07
 */
namespace OYXX\Tools;

class DesUtils {

    const KEY = "oyxx";

    /**
     * 可加密一个或多个半角逗号分隔的字符串
     * @param $content
     * @return string
     */
    public static function encryptContent($content)
    {
        $content = explode(",",$content);
        foreach($content as &$c){
            $c = trim($c);
            if($c != null && $c != ""){
                $c = strtoupper(self::encrypt($c));
            }
        }
        return implode(",",$content);

    }
    /**
     * 可解密一个或多个半角逗号分隔的字符串
     * @param $content
     * @return string
     */
    public static function decryptContent($content)
    {
        $content = explode(",",$content);
        foreach($content as &$c){
            $c = trim($c);
            if($c != null && $c != ""){
                $c = self::decrypt($c);
            }
        }
        return implode(",",$content);
    }

    /**
     * 可加密单个字符串
     * @param $content
     * @return string
     */
    public static function encrypt($encrypt) {
        try{
            $key = substr(openssl_digest(openssl_digest(self::KEY, 'sha1', true), 'sha1', true), 0, 16);
            $iv = openssl_random_pseudo_bytes(0);
            $encrypted = openssl_encrypt($encrypt, 'AES-128-ECB',$key,OPENSSL_RAW_DATA,$iv);
            return bin2hex($encrypted);
        }catch(\Exception $e){
            LogUtils::info("执行出现异常：：".$encrypt);
            return $encrypt;
        }
    }

    /**
     * 可解密单个字符串
     * @param $content
     * @return string
     */
    public static function decrypt($decrypt) {
        try{
            $key = substr(openssl_digest(openssl_digest(self::KEY, 'sha1', true), 'sha1', true), 0, 16);
            $decoded = hex2bin($decrypt);
            $iv = openssl_random_pseudo_bytes(0);
            return openssl_decrypt($decoded, 'AES-128-ECB',$key,OPENSSL_RAW_DATA,$iv);
        }catch(\Exception $e){
            LogUtils::info("执行出现异常：：".$decrypt);
            return $decrypt;
        }
    }
}
