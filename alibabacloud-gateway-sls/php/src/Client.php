<?php

// This file is auto-generated, don't edit it. Thanks.
namespace Darabonba\GatewaySls;

use Darabonba\GatewaySpi\Client as DarabonbaGatewaySpiClient;
use AlibabaCloud\Tea\Utils\Utils;
use AlibabaCloud\Darabonba\String\StringUtil;
use AlibabaCloud\Darabonba\SignatureUtil\Signer;
use AlibabaCloud\Darabonba\EncodeUtil\Encoder;
use AlibabaCloud\Tea\Tea;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Darabonba\Map\MapUtil;
use AlibabaCloud\Darabonba\Array_\ArrayUtil;

use Darabonba\GatewaySpi\Models\InterceptorContext;
use Darabonba\GatewaySpi\Models\AttributeMap;

class Client extends DarabonbaGatewaySpiClient {
    public function __construct(){
        parent::__construct();
    }

    /**
     * @param InterceptorContext $context
     * @param AttributeMap $attributeMap
     * @return void
     */
    public function modifyConfiguration($context, $attributeMap){
        $config = $context->configuration;
        $config->endpoint = $this->getEndpoint($config->regionId, $config->network, $config->endpoint);
    }

    /**
     * @param InterceptorContext $context
     * @param AttributeMap $attributeMap
     * @return void
     */
    public function modifyRequest($context, $attributeMap){
        $request = $context->request;
        $hostMap = [];
        if (!Utils::isUnset($request->hostMap)) {
            $hostMap = $request->hostMap;
        }
        $project = @$hostMap["project"];
        $config = $context->configuration;
        $credential = $request->credential;
        $accessKeyId = $credential->getAccessKeyId();
        $accessKeySecret = $credential->getAccessKeySecret();
        $securityToken = $credential->getSecurityToken();
        if (!Utils::empty_($accessKeyId)) {
            $request->headers["x-log-signaturemethod"] = "hmac-sha1";
        }
        if (!Utils::empty_($securityToken)) {
            $request->headers["x-acs-security-token"] = $securityToken;
        }
        if (!Utils::isUnset($request->body)) {
            if (StringUtil::equals($request->reqBodyType, "protobuf")) {
                // var bodyMap = Util.assertAsMap(request.body);
                // 缺少body的Content-MD5计算，以及protobuf处理
                $request->headers["content-type"] = "application/x-protobuf";
            }
            else if (StringUtil::equals($request->reqBodyType, "json")) {
                $bodyStr = Utils::toJSONString($request->body);
                $request->headers["content-md5"] = StringUtil::toUpper(Encoder::hexEncode(Signer::MD5Sign($bodyStr)));
                $request->stream = $bodyStr;
                $request->headers["content-type"] = "application/json";
            }
            else if (StringUtil::equals($request->reqBodyType, "formData")) {
                $str = Utils::toJSONString($request->body);
                $request->headers["content-md5"] = StringUtil::toUpper(Encoder::hexEncode(Signer::MD5Sign($str)));
                $request->stream = $str;
                $request->headers["content-type"] = "application/json";
            }
        }
        $request->headers = Tea::merge([
            "accept" => "application/json",
            "host" => $this->getHost($config->network, $project, $config->endpoint),
            "date" => Utils::getDateUTCString(),
            "user-agent" => $request->userAgent,
            "x-log-apiversion" => "0.6.0",
            "x-log-bodyrawsize" => "0"
        ], $request->headers);
        $request->headers["authorization"] = $this->getAuthorization($request->pathname, $request->method, $request->query, $request->headers, $accessKeyId, $accessKeySecret);
    }

    /**
     * @param InterceptorContext $context
     * @param AttributeMap $attributeMap
     * @return void
     * @throws TeaError
     */
    public function modifyResponse($context, $attributeMap){
        $request = $context->request;
        $response = $context->response;
        if (Utils::is4xx($response->statusCode) || Utils::is5xx($response->statusCode)) {
            $error = Utils::readAsJSON($response->body);
            $resMap = Utils::assertAsMap($error);
            throw new TeaError([
                "code" => @$resMap["errorCode"],
                "message" => @$resMap["errorMessage"],
                "data" => [
                    "httpCode" => $response->statusCode,
                    "requestId" => @$response->headers["x-log-requestid"]
                ]
            ]);
        }
        if (!Utils::isUnset($response->body)) {
            if (Utils::equalString($request->bodyType, "binary")) {
                $response->deserializedBody = $response->body;
            }
            else if (Utils::equalString($request->bodyType, "byte")) {
                $byt = Utils::readAsBytes($response->body);
                $response->deserializedBody = $byt;
            }
            else if (Utils::equalString($request->bodyType, "string")) {
                $response->deserializedBody = Utils::readAsString($response->body);
            }
            else if (Utils::equalString($request->bodyType, "json")) {
                $obj = Utils::readAsJSON($response->body);
                $res = Utils::assertAsMap($obj);
                $response->deserializedBody = $res;
            }
            else if (Utils::equalString($request->bodyType, "array")) {
                $response->deserializedBody = Utils::readAsJSON($response->body);
            }
            else {
                $response->deserializedBody = Utils::readAsString($response->body);
            }
        }
    }

    /**
     * @param string $regionId
     * @param string $network
     * @param string $endpoint
     * @return string
     */
    public function getEndpoint($regionId, $network, $endpoint){
        if (!Utils::empty_($endpoint)) {
            return $endpoint;
        }
        if (Utils::empty_($regionId)) {
            $regionId = "cn-hangzhou";
        }
        if (!Utils::empty_($network)) {
            if (StringUtil::equals($network, "intranet")) {
                return "" . $regionId . "-intranet.log.aliyuncs.com";
            }
            else if (StringUtil::equals($network, "accelerate")) {
                return "log-global.aliyuncs.com";
            }
            else if (StringUtil::equals($network, "share")) {
                if (StringUtil::equals($regionId, "cn-hangzhou-corp") || StringUtil::equals($regionId, "cn-shanghai-corp")) {
                    return "" . $regionId . ".sls.aliyuncs.com";
                }
                else if (StringUtil::equals($regionId, "cn-zhangbei-corp")) {
                    return "zhangbei-corp-share.log.aliyuncs.com";
                }
                return "" . $regionId . "-share.log.aliyuncs.com";
            }
        }
        return "" . $regionId . ".log.aliyuncs.com";
    }

    /**
     * @param string $network
     * @param string $project
     * @param string $endpoint
     * @return string
     */
    public function getHost($network, $project, $endpoint){
        if (Utils::isUnset($project)) {
            return $endpoint;
        }
        return "" . $project . "." . $endpoint . "";
    }

    /**
     * @param string $pathname
     * @param string $method
     * @param string[] $query
     * @param string[] $headers
     * @param string $ak
     * @param string $secret
     * @return string
     */
    public function getAuthorization($pathname, $method, $query, $headers, $ak, $secret){
        return "LOG " . $ak . ":" . $this->getSignature($pathname, $method, $query, $headers, $secret) . "";
    }

    /**
     * @param string $pathname
     * @param string $method
     * @param string[] $query
     * @param string[] $headers
     * @param string $secret
     * @return string
     */
    public function getSignature($pathname, $method, $query, $headers, $secret){
        $resource = $pathname;
        $stringToSign = "";
        $canonicalizedResource = $this->buildCanonicalizedResource($resource, $query);
        $canonicalizedHeaders = $this->buildCanonicalizedHeaders($headers);
        $stringToSign = "" . $method . "\n" . $canonicalizedHeaders . "" . $canonicalizedResource . "";
        return Encoder::base64EncodeToString(Signer::HmacSHA1Sign($stringToSign, $secret));
    }

    /**
     * @param string $pathname
     * @param string[] $query
     * @return string
     */
    public function buildCanonicalizedResource($pathname, $query){
        $canonicalizedResource = $pathname;
        if (!Utils::isUnset($query)) {
            $queryList = MapUtil::keySet($query);
            $sortedParams = ArrayUtil::ascSort($queryList);
            $separator = "?";
            foreach($sortedParams as $paramName){
                $canonicalizedResource = "" . $canonicalizedResource . "" . $separator . "" . $paramName . "";
                if (!Utils::isUnset(@$query[$paramName])) {
                    $canonicalizedResource = "" . $canonicalizedResource . "=" . @$query[$paramName] . "";
                }
                $separator = "&";
            }
        }
        return $canonicalizedResource;
    }

    /**
     * @param string[] $headers
     * @return string
     */
    public function buildCanonicalizedHeaders($headers){
        $canonicalizedHeaders = "";
        $contentType = @$headers["content-type"];
        if (Utils::isUnset($contentType)) {
            $contentType = "";
        }
        $contentMd5 = @$headers["content-md5"];
        if (Utils::isUnset($contentMd5)) {
            $contentMd5 = "";
        }
        $canonicalizedHeaders = "" . $canonicalizedHeaders . "" . $contentMd5 . "\n" . $contentType . "\n" . @$headers["date"] . "\n";
        $keys = MapUtil::keySet($headers);
        $sortedHeaders = ArrayUtil::ascSort($keys);
        foreach($sortedHeaders as $header){
            if (StringUtil::contains(StringUtil::toLower($header), "x-log-") || StringUtil::contains(StringUtil::toLower($header), "x-acs-")) {
                $canonicalizedHeaders = "" . $canonicalizedHeaders . "" . $header . ":" . @$headers[$header] . "\n";
            }
        }
        return $canonicalizedHeaders;
    }
}
