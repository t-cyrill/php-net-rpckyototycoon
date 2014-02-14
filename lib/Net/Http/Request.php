<?php
namespace Net\Http;

class Request {
    private $host    = null;
    private $path    = '/';
    private $method  = 'GET';
    private $headers = array();
    private $params  = array();

    const GET    = 0;
    const POST   = 1;

    protected function __construct($host, $path) {
        $this->host = $host;
        $this->path = $path;
    }

    public static function factory($host, $path)
    {
        return new self($host, $path);
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function setMethod($method)
    {
        if ($method === self::GET) {
            $this->method = 'GET';
        } else if ($method === self::POST) {
            $this->method = 'POST';
        }
        return $this;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return string リクエスト文字列
     */
    public function getRequestString()
    {
        $headerString  = "{$this->method} {$this->path} HTTP/1.0\r\n";
        $headerString .= "Host: {$this->host}\r\n";
        $headers = $this->headers;
        foreach ($headers as $key => $value) {
            $headerString .= "{$key}: {$value}\r\n";
        }
        $headerString .= "\r\n";
        $headerString .= http_build_query($this->params);
        return $headerString;
    }
}

