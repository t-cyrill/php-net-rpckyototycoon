<?php
namespace Net\Http;

class HttpClient
{
    private $request;
    private $socket;
    private $info;
    private $response;
    private $host, $port, $errno, $errstr, $timeout;
    private $floatTimeout;
    private $ignoreWarning;

    const READ_BUFFER_SIZE = 1024;

    /**
     * @param string $host 接続先ホスト名
     * @param string $port ポート番号
     * @oaram array  $timeout タイムアウト(second, microsecond)
     */
    public function __construct($host, $port, array $timeout, $ignoreWarning = true)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->timeout  = isset($timeout[0], $timeout[1]) ? $timeout : array(0, 0);
        $this->ignoreWarning = $ignoreWarning;
    }

    public function getTimeoutAsFloat()
    {
        if ($this->floatTimeout === null) {
            $this->floatTimeout = $this->timeout[0] + ($this->timeout[1] / (1000 * 1000));
        }
        return $this->floatTimeout;
    }

    /**
     * socketを開き接続を確立させる
     *
     * @return bool ソケットを開けた場合true, 開けなかった場合false
     * ホスト名が無効の場合、warningが発生します。(fsockopen依存)
     */
    public function connect()
    {
        $socket = $this->ignoreWarning ?
            @fsockopen($this->host, $this->port, $this->errno, $this->errstr, $this->getTimeoutAsFloat())
            : fsockopen($this->host, $this->port, $this->errno, $this->errstr, $this->getTimeoutAsFloat());

        if ($socket === false) {
            return false;
        }
        $this->socket = $socket;
        return true;
    }

    public function isTimeout()
    {
        if (isset($info['timed_out'])) {
            return true;
        }
        return false;
    }

    /**
     * コネクションを開きっぱなしにされ接続タイムアウトしている場合に例外を投げる安全なfeof
     */
    public function isEOF($fp)
    {
        $start = microtime(true);

        // feofのタイムアウト時間をこのクラスに設定されたtimeout時間に一時的に設定する
        $defaultSocketTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', $this->timeout[0]);

        // default_socket_timeoutが経過するとfeofは強制的にreturnする
        // timeoutしているなら、microtimeの差分がtimeoutを越えるのでtimeoutと判断できる
        // http://php.net/manual/ja/function.feof.php
        $isEof = feof($fp);
        ini_set('default_socket_timeout', $defaultSocketTimeout);

        $isTimeout = ((microtime(true) - $start) >= $this->getTimeoutAsFloat());

        if ($isTimeout) {
            throw new TimeoutException('feof hung up. May be server did not close connection.');
        }
        return $isEof;
    }

    public function request($request)
    {
        if ($request === null) $request = $this->request;
        fwrite($this->socket, $request->getRequestString());
        return $this;
    }

    private function responseLoop()
    {
        if (!is_resource($this->socket) || (get_resource_type($this->socket) === 'Unknown')) {
            throw new \RuntimeException('socketが有効ではありません。');
        }
        stream_set_timeout($this->socket, (int) $this->timeout[0], (int) $this->timeout[1]);

        $buffer = '';
        while (!$this->isEOF($this->socket)) {
            $recieved = fread($this->socket, self::READ_BUFFER_SIZE);
            if ($recieved === '') {
                break;
            }
            $buffer .= $recieved;
            usleep(1);
        }
        $this->info = stream_get_meta_data($this->socket);
        $this->response = self::splitHttpResponse($buffer);
    }

    /**
     * レスポンスをヘッダとボディーに分解する
     * @param string HTTPレスポンス文字列
     */
    private static function splitHttpResponse($responseString)
    {
        // 改行コードの違いを補正する
        $responseString = str_replace("\r\n", "\n", $responseString);

        // 最初に改行が二回連続する箇所を見つける
        $splitPosition = strpos($responseString, "\n\n");
        $header = substr($responseString, 0, $splitPosition);
        $body   = substr($responseString, $splitPosition);
        return array('header' => $header, 'body' => $body);
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function getResponse()
    {
        if ($this->response === null) {
            $this->responseLoop();
        }
        return $this->response['body'];
    }

    public function close()
    {
        if ($this->socket === null) {
            return false;
        }
        fclose($this->socket);
        $this->socket = null;
        return true;
    }

    public function __destruct()
    {
        $this->close();
    }
}

