<?php
/**
 * Created by PhpStorm.
 * User: ouyangxiaoxin
 * Date: 2018/4/7
 * Time: 下午11:28
 */
namespace OYXX\Tools;

class WXUtils {

    /**
     * 获取微信AppId和AppSecret
     * @return stdClass
     */
    public static function init() {
        $wi = new stdClass();
        $wi->app_id = "";
        $wi->app_secret = "";
        return $wi;
    }

    /**
     * 用户同意授权，获取code
     * @param $scope
     * 应用授权作用域，snsapi_base
     * （不弹出授权页面，直接跳转，只能获取用户openid），
     * snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、
     * 性别、所在地。并且，即使在未关注的情况下，只要用户授权，
     * 也能获取其信息）
     */
    public static function get_oauth2_code($redirect_uri, $scope){
        $wi = WXUtils::init();
        $code_url = "https://open.weixin.qq.com/connect/oauth2/authorize"
            ."?appid=".$wi->app_id
            ."&redirect_uri=".urlencode($redirect_uri)
            ."&response_type=code"
            ."&scope=".$scope
            ."&state=STATE"
            ."#wechat_redirect";
        return $code_url;
    }


    /**
     * 通过code换取网页授权access_token
     * @param $code
     * @return mixed|stdClass
     */
    public static function get_oauth2_token($code){
        $wi = WXUtils::init();
        $TOKEN_URL="https://api.weixin.qq.com/sns/oauth2/access_token"
            ."?appid=".$wi->app_id
            ."&secret=".$wi->app_secret
            ."&code=".$code
            ."&grant_type=authorization_code";

        $data = HttpUtils::curlGet($TOKEN_URL);

        $result = json_decode($data);

        if(!$result || isset($result->errcode)){
            return null;
        }

        return $result->access_token;

    }

    /**
     * 生成相关JS相关签名
     * @return string
     */
    public static function make_nonceStr(){
        $codeSet = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for ($i = 0; $i<16; $i++) {
            $codes[$i] = $codeSet[mt_rand(0, strlen($codeSet)-1)];
        }
        return implode($codes);
    }


    /**
     * 生成签名之前必须先了解一下jsapi_ticket，jsapi_ticket是公众号用于调用微信JS接口的临时票据。
     * 正常情况下，jsapi_ticket的有效期为7200秒，通过access_token来获取。
     * 由于获取jsapi_ticket的api调用次数非常有限，频繁刷新jsapi_ticket会导致api调用受限，
     * 影响自身业务，开发者必须在自己的服务全局缓存jsapi_ticket 。
     */
    public static function get_make_ticket() {

        $result = new stdClass();
        $ticket = LRedis::get("AccessTicket-sunpie-10229");

        LogUtils::debug("获取ticket::".$ticket);

        if($ticket == null){
            $url =  env("EJY_HOSTS")."/goserver/sunpie/jsapi_ticket/get?oil_station=10229";

            $data = json_decode(HttpUtils::curlGet($url));

            LogUtils::debug("返回的data::".json_encode($data));

            if(!$data){
                $data = new stdClass();
                $data->Result = 1;
            }

            if($data->Result == 0){
                $ticket = $data->Ticket;
                $result->ret = 0;
                $result->ticket = $ticket;
            }else{
                $result->ret = 1;
                $result->msg = "获取微信签名凭证jsapi_ticket失败";
            }
        }else{
            $result->ret = 0;
            $result->ticket = $ticket;
        }
        return $result;
    }

    /**
     * 微信参数信息签名
     * @param $nonceStr
     * @param $timestamp
     * @param $jsapi_ticket
     * @param $url
     * @return string
     */
    public static function make_signature($nonceStr, $timestamp, $jsapi_ticket, $url) {
        $tmpArr = array(
            'noncestr' => $nonceStr,
            'timestamp' => $timestamp,
            'jsapi_ticket' => $jsapi_ticket,
            'url' => $url
        );
        ksort($tmpArr, SORT_STRING);
        $string1 = http_build_query( $tmpArr );
        $string1 = urldecode( $string1 );

        return sha1( $string1 );
    }

    /**
     * 获取网页JS的相关参数（new）
     */
    public static function get_wx_params($domain) {

        $result = new stdClass();

        $wi = WXUtils::init();
        $nonceStr = self::make_nonceStr();
        $timestamp = time();

        $data = self::get_make_ticket();

        LogUtils::debug('WXUtils->get_make_ticket,result='.json_encode($data));
        if($data->ret == 0){
            $result->ret = 0;
            $jsapi_ticket = $data->ticket;
        }else{
            $result->ret = 1;
            $result->msg = $data->msg;
            return $result;
        }

        $url = $domain.$_SERVER["REQUEST_URI"];
        $signature = self::make_signature($nonceStr, $timestamp, $jsapi_ticket, $url);

        $weixin_params = array();
        $weixin_params['appId'] = $wi->app_id;
        $weixin_params['timestamp'] = $timestamp;
        $weixin_params['nonceStr'] = $nonceStr;
        $weixin_params['signature'] = $signature;

        $result->weixin_params = $weixin_params;
        return $result;
    }

    /**
     * 开发者可通过OpenID来获取用户基本信息。请使用https协议。
     * getone  true  $openid  单个
     * getone  false $openid  多个openid以 , 连接的字符串
     */
    public static function official_accounts_get_user_info($openid, $getone=True){

        $access_token = self::get_access_token();

        if($getone){
            // get方式  可获取但个openid的信息
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$openid}&lang=zh_CN";
            $data = HttpUtils::curlGet($url);
        }else{
            // post方式  可获取多个openid的信息
            $url = "https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token={$access_token}";

            $open_arr = array_filter(explode(',',$openid));
            $arr = [];
            foreach ($open_arr as $k=>$v){
                $arr[$k]['openid'] = $v;
                $arr[$k]['lang'] = 'zh-CN';
            }
            $post_data = json_encode(array('user_list'=>$arr));

            $data = HttpUtils::curlPost($url,$post_data);
        }

        $result = json_decode($data);

        if(!$result){
            $result = new stdClass();
            $result->result = 1;
            return $result;
        }

        if(isset($result->errcode) && $result->errcode > 0){
            $result->result = 1;
        }else{
            $result->result = 0;
        }

        return $result;
    }

    public static function sendCustomMsg($openid,$content,$msgtype = "text")
    {
        if($msgtype == "text"){                 //发送文本消息
            $content = str_replace("\"","'",$content);
            $content = "{\"content\":\"$content\"}";
        }else if($msgtype == "image" || $msgtype == "voice" || $msgtype == "mpnews"){          //发送图片、语音、图文
            $content = str_replace("\"","'",$content);
            $content = "{\"media_id\":\"$content\"}";
        }else if ($msgtype == "news"){
            $content = "{\"articles\":$content}";
        }else{
            $result = new stdClass();
            $result->ret = 1;
            $result->msg = "消息类型不正确";
            return $result;
        }

        $access_token = self::get_access_token();

        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=$access_token";

        $post_data = "{\"touser\":\"$openid\",\"msgtype\":\"$msgtype\","."\"$msgtype\":$content}";

        $result = json_decode(HttpUtils::curlPost($url,$post_data));

        if(!$result){
            $result = new stdClass();
            $result->ret = 1;
            return $result;
        }

        if(isset($result->errcode) && $result->errcode != 0){
            $result->ret = 1;
        }else{
            $result->ret = 0;
        }

        return $result;
    }

    /*
     * 发送客服消息
     */
    public static function sendCustomMessage($openid,$content,$msgtype = "text"){
        if($msgtype == "text"){                 //发送文本消息
            $content = array('content' => $content);
        }else if($msgtype == "image" || $msgtype == "voice" || $msgtype == "mpnews"){          //发送图片、语音、图文
            $content = array('media_id' => $content);
        }else if ($msgtype == "news"){
            $content = array('articles' => $content);
        }else{
            $result = new stdClass();
            $result->ret = 1;
            $result->msg = "消息类型不正确";
            return $result;
        }
        $access_token = self::get_access_token();

        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=$access_token";

        $post_data_arr = array(
            'touser' => $openid,
            'msgtype' => $msgtype,
            $msgtype => $content
        );
        $post_data = json_encode($post_data_arr,JSON_UNESCAPED_UNICODE);

        $result = json_decode(HttpUtils::curlPost($url,$post_data));

        if(!$result){
            $result = new stdClass();
            $result->ret = 1;
            return $result;
        }

        if(isset($result->errcode) && $result->errcode != 0){
            $result->ret = 1;
        }else{
            $result->ret = 0;
        }
        $result->msg = $result->errmsg;

        return $result;
    }


    /**
     * 发送微信模板消息
     * @param $template_id
     * @param $access_link
     * @param $openid
     * @param $content
     * @param int $url_type
     * @param string $miniprogram
     * @return mixed|stdClass
     */
    public static function sendTemplateMessage($template_id,$access_link,$openid,$content,$url_type = 1,$miniprogram = "")
    {
        $access_token = self::get_access_token();

        if($url_type == 1){
            $post_data_arr = array(
                'touser' => $openid,
                'template_id' => $template_id,
                'url' => $access_link,
                'data' => $content
            );
        }else{
            $post_data_arr = array(
                'touser' => $openid,
                'template_id' => $template_id,
                'url' => $access_link,
                'data' => $content,
                'miniprogram' => $miniprogram
            );
        }

        $post_data = json_encode($post_data_arr,JSON_UNESCAPED_UNICODE);

        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$access_token";

        $data = HttpUtils::curlPost($url,$post_data);

        $result = json_decode($data);

        if(!$result || (isset($result->errcode) && $result->errcode != 0)){
            return null;
        }

        return "success";
    }

    /*
     * 上传永久素材
     * $file  上传文件对象
     * $type  媒体文件类型
     */
    public static function addMaterial($type,$file)
    {
        $access_token = self::get_access_token();

        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$access_token.'&type='.$type;
        $post_data = curl_file_create($file['file']['tmp_name'],'image/jpeg','default.jpg');

        return HttpUtils::curlPost($url,array('media'=>$post_data));
    }


    /**
     * 上传图文素材
     * @param $data
     * @return mixed
     */
    public static function addNews($data){
        $access_token = WXUtils::get_access_token();

        $post_data_arr = array(
            'articles' => $data
        );

        $post_data = json_encode($post_data_arr,JSON_UNESCAPED_UNICODE);
        $url = "https://api.weixin.qq.com/cgi-bin/material/add_news?access_token=".$access_token;

        return HttpUtils::curlPost($url,$post_data);
    }


    /**
     * 修改图文素材
     * @param $media_id
     * @param $data
     * @return mixed
     */
    public static function updateNews($media_id,$data){
        $access_token = WXUtils::get_access_token();

        $post_data_arr = array(
            'media_id' => $media_id,
            'articles' => $data
        );

        $post_data = json_encode($post_data_arr,JSON_UNESCAPED_UNICODE);
        $url = "https://api.weixin.qq.com/cgi-bin/material/update_news?access_token=" . $access_token;

        return HttpUtils::curlPost($url, $post_data);
    }

    /**
     * 生成微信短连接
     * @param $long_url
     * @return mixed|stdClass
     */
    public static function get_short_url($long_url)
    {
        $access_token = self::get_access_token();

        $post_data_arr = array(
            'action' => 'long2short',
            'long_url' => $long_url
        );

        $post_data = json_encode($post_data_arr,JSON_UNESCAPED_UNICODE);

        $url = "https://api.weixin.qq.com/cgi-bin/shorturl?access_token=$access_token";

        $data = HttpUtils::curlPost($url,$post_data);

        $result = json_decode($data);

        if(!$result || (isset($result->errcode) && $result->errcode != 0)){
            return null;
        }

        return $result->short_url;
    }
}
