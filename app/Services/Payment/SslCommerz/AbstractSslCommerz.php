<?php

namespace App\Services\Payment\SslCommerz;

abstract class AbstractSslCommerz implements SslCommerzInterface
{
    protected $apiUrl;
    protected $storeId;
    protected $storePassword;
    protected $data = [];

    public function setApiUrl($url)
    {
        $this->apiUrl = $url;
    }

    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
    }

    public function getStoreId()
    {
        return $this->storeId;
    }

    public function setStorePassword($storePassword)
    {
        $this->storePassword = $storePassword;
    }

    public function getStorePassword()
    {
        return $this->storePassword;
    }

    public function callToApi($data, $header = [], $setLocalhost = false)
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $this->apiUrl);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);

        if ($setLocalhost) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
        } else {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        }

        if (!empty($header)) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $header);
        }

        $response = curl_exec($handle);
        curl_close($handle);

        return $response;
    }

    public function formatResponse($response, $type = 'checkout', $pattern = 'json')
    {
        if ($pattern == 'json') {
            $result = json_decode($response, true);
            return $result;
        }
        return $response;
    }

    protected function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}
