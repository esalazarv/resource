<?php

namespace Esalazarv\Resource;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

trait ResourceTrait
{

    private function endpoint()
    {
        $endpoint = $this->config['endpoint'];
        if (!isset($this->endpoint)) {
            $endpoint = is_array($endpoint) ? array_first($endpoint) : $endpoint;
        } else {
            $endpoint = $endpoint[$this->endpoint];
        }
        return new Client([
            'base_uri' => $endpoint
        ]);
        $this->apiHeaders = [];
    }

    public function headers($headers = [])
    {
        $this->apiHeaders = array_merge($this->apiHeaders, $headers);
        return $this;
    }

    public function __call($name, $params = [])
    {
        return $this->request($name, $params[0], $params[1]);
    }

    public function request($method, $uri, $params = [], $cache = null)
    {
        $this->config = config('resource');
        $name = str_slug($method.$uri.serialize($this->apiHeaders).serialize($params));

        if ($this->config['enable_cache'] && Cache::has($name)) {
            $data = Cache::get($name);
        } else {
            $serverResponse = $this->endpoint()->request($method, $uri ,$this->apiHeaders ,$params);
            $data = $this->makeData($serverResponse);
        }

        $this->setInCache($name, $data);

        $response = $this->prepareResponse($data);

        return new ApiResponse($response);
    }

    private function makeData(Response $serverResponse)
    {
        return [
            'headers' => $serverResponse->getHeaders(),
            'status' => [
                'code' => $serverResponse->getStatusCode(),
                'message' => $serverResponse->getReasonPhrase(),
            ],
            'body'  => json_decode($serverResponse->getBody()),
            'cache' => [
                'enabled' => false,
                'created_at' => null,
                'end_at' => null,
            ]
        ];
    }

    private function setInCache($name, $data, $cache = null)
    {
        if ($this->config['enable_cache']) {
            $lifeTime = is_null($cache) ? $this->config['cache_live_time'] : $cache;
            $created_at = Carbon::now();
            $data['cache']['enabled'] = true;
            $data['cache']['created_at'] = $created_at->timestamp;
            $data['cache']['end_at'] = $created_at->addMinutes($lifeTime)->timestamp;
            Cache::add($name, $data, $lifeTime);
        }
    }

    private function prepareResponse($data)
    {
        $body = collect($data['body']);
        $body = collect(json_decode($body))->map(function ($item) {
            $class = get_class($this);
            return new $class((array) $item);
        });
        $data['body'] = ($body->count() > 1) ? $body : $body->first();
        $data['cache']['created_at'] = is_null($data['cache']['created_at'])
            ? null
            : Carbon::createFromTimestamp($data['cache']['created_at']);
        $data['cache']['end_at'] = is_null($data['cache']['end_at'])
            ? null
            : Carbon::createFromTimestamp($data['cache']['end_at']);
        return $data;
    }
}
