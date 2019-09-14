<?php

namespace BunnyPHP\WeChat\Pay\Service;

use BunnyPHP\Service;
use BunnyPHP\View;

class WechatPayService extends Service
{
    protected $appId;
    protected $mchId;
    protected $key;
    protected $openid;
    protected $outTradeNo;
    protected $body;
    protected $totalFee;
    protected $notifyUrl;

    public function init($appId, $openid, $mchId, $key, $notifyUrl)
    {
        $this->appId = $appId;
        $this->openid = $openid;
        $this->mchId = $mchId;
        $this->key = $key;
        $this->notifyUrl = $notifyUrl;
        return $this;
    }

    public function prepare($outTradeNo, $body, $totalFee)
    {
        $this->outTradeNo = $outTradeNo;
        $this->body = $body;
        $this->totalFee = $totalFee;
        return $this;
    }

    public function pay()
    {
        $unifiedOrder = $this->unifiedOrder();
        $data = [
            'appId' => $this->appId,
            'timeStamp' => '' . time() . '',
            'nonceStr' => $this->createNonceStr(),
            'package' => 'prepay_id=' . $unifiedOrder['prepay_id'],
            'signType' => 'MD5',
        ];
        $data['paySign'] = $this->getSign($data);
        return $data;
    }

    private function unifiedOrder()
    {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $data = [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
            'nonce_str' => $this->createNonceStr(),
            'body' => $this->body,
            'out_trade_no' => $this->outTradeNo,
            'total_fee' => $this->totalFee,
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
            'notify_url' => $this->notifyUrl,
            'openid' => $this->openid,
            'trade_type' => 'JSAPI'
        ];
        $data['sign'] = $this->getSign($data);
        $xmlData = $this->arrayToXml($data);
        return $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
    }

    private static function postXmlCurl($xml, $url, $second = 40)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        set_time_limit(0);
        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            View::error(['tp_error_msg' => 'CURL Errror,Code: ' . $error]);
            return null;
        }
    }

    private function arrayToXml($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= '<' . $key . '>' . $this->arrayToXml($val) . '</' . $key . '>';
            } else {
                $xml .= '<' . $key . '>' . $val . '</' . $key . '>';
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    private function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $xmlString = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlString), true);
        return $val;
    }


    private function createNonceStr($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getSign($obj)
    {
        $data = [];
        foreach ($obj as $k => $v) {
            $data[$k] = $v;
        }
        ksort($data);
        $tmp = $this->formatBizQueryParaMap($data, false);
        $tmp = $tmp . '&key=' . $this->key;
        $tmp = md5($tmp);
        return strtoupper($tmp);
    }

    private function formatBizQueryParaMap($paramMap, $urlEncode)
    {
        $buff = '';
        ksort($paramMap);
        foreach ($paramMap as $k => $v) {
            if ($urlEncode) {
                $v = urlencode($v);
            }
            $buff .= $k . '=' . $v . '&';
        }
        $res = '';
        if (strlen($buff) > 0) {
            $res = substr($buff, 0, strlen($buff) - 1);
        }
        return $res;
    }
}