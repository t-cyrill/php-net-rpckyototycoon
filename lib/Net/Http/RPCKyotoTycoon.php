<?php
namespace Net\Http;

/**
 * RPC用のKyotoTyrantクラス
 *
 * @author cyrill
 */
class RPCKyotoTycoon {
    private $host, $port, $db, $timeout;
    private $lastError = null;
    private $errno = 0, $errstr = '';

    /**
     * レスポンス取得に使うリードバッファのサイズ
     */
    const READ_BUFFER_SIZE = 4096;

    public function __construct(array $config)
    {
        $expectedKeys = array('db', 'timeout');
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw new \InvalidArgumentException("config:key {$key} must be set.");
            }
        }

        $this->host          = isset($config['host']) ? $config['host'] : 'localhost';
        $this->port          = isset($config['port']) ? $config['port'] : 1978;
        $this->db            = $config['db'];
        $this->timeout       = $config['timeout'];
        $this->ignoreWarning = isset($config['ignoreWarning']) ? $config['ignoreWarning'] : true;
    }

    /**
     * HTTP RPCアクセス用HTTP命令を作成する
     *
     * @param string $proc
     * @param array $params
     */
    protected function getRPCHeader($proc, $params = array())
    {
        $path = "/rpc/${proc}?DB={$this->db}&".http_build_query($params, '', '&');
        $header  = "GET ${path} HTTP/1.0\r\n";
        $header .= "Connection: Close\r\n";
        $header .= "\r\n";
        return $header;
    }

    protected function buildRPCRequest($procedure, $params)
    {
        $path = "/rpc/${procedure}?DB={$this->db}&".http_build_query($params, '', '&');
        $request = Request::factory($this->host, $path);
        $request->setHeaders(array('Connection', 'close'));
        return $request;
    }

    /**
     * エラーメッセージを記録する
     * @param string $message 記録するエラーメッセージ
     */
    private function reportError($message)
    {
        $this->lastError = $message;
    }

    /**
     * 最後に発生したエラーを取得する
     * @return string エラーメッセージ
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * 指定したキーで値を取得する
     *
     * @param string $key 取得するキー
     * @return mixed 成功した場合stringの値, 失敗した場合false
     */
    public function get($key) {
        $this->synchronousRequest('get', array('key' => $key), $results);
        $result = false;
        foreach ($results as $value) {
            if ($value[0] === 'ERROR') {
                $this->reportError($value[1]);
            } elseif ($value[0] === 'value') {
                $result = $value[1];
            }
        }
        return $result;
    }

    /**
     * 指定したキーで値をセットする
     *
     * @param string $key    設定するキー
     * @param string $value  設定する値
     * @param string $expire キャッシュの有効期限(現在からの秒数)
     * @return bool 成功した場合true, 失敗した場合false
     */
    public function set($key, $value, $expire = false) {
        $params = array('key' => $key, 'value' => $value);
        if ($expire !== false) {
            $params['xt'] = $expire;
        }
        return $this->asynchronousRequest('set', $params);
    }

    /**
     * 複数の値を設定する
     *
     * @param array $keyValueMap キーと値が格納された連想配列
     * @return bool 成功した場合true, 失敗した場合false
     */
    public function setBulk(array $keyValueMap, $expire = false)
    {
        $params = array();
        if ($expire !== false) {
            $params['xt'] = $expire;
        }

        foreach ($keyValueMap as $key => $value) {
            $params["_$key"] = $value;
        }
        return $this->asynchronousRequest('set_bulk', $params);
    }

    /**
     * 指定したキーで削除する
     * 
     * @param string $key 削除するキー
     * @return bool 成功した場合true, 失敗した場合false
     */
    public function remove($key) {
        $params = array('key' => $key);

        return $this->asynchronousRequest('remove', $params);
    }

    /**
     * 前方一致したキーを取得する
     *
     * @param string $prefix 取得するキーのプレフィクス
     * test_ と指定すると test_*** に当てはまるキーリストを取得できる
     * @param int $max 最大取得件数
     * 0を指定すると、最大取得件数の上限がなくなります。
     * 取得するキーが多いとtimeoutを起こすので
     * timeout値も長い時間に設定する必要がある。
     */
    private function match_prefix($prefix, $max = 10) {
        $max = (int) $max;
        $params = array('prefix' => $prefix);
        if ($max > 0) {
            $params['max'] = $max;
        }

        $this->synchronousRequest('match_prefix', $params, $results);
        return $results;
    }

    /**
     * 前方一致したキーを取得する
     *
     * @param string $prefix 前方一致検索をするキー名
     * @param int 最大取得件数
     * @return array KTのマッチしたキーの配列
     */
    public function getPrefixMatchKeys($prefix, $limit)
    {
        return self::formatForKeys($this->match_prefix($prefix, $limit));
    }

    /**
     * 正規表現で取得する
     *
     * @param string $regex 正規表現にマッチしたキーリストを取得できる
     * @param int $max 最大取得件数
     * @return array リクエストの結果が格納された連想配列
     */
    private function match_regex($regex, $max = 10) {
        $max = (int) $max;
        $params = array('regex' => $regex);
        if ($max > 0) {
            $params['max'] = $max;
        }

        $this->synchronousRequest('match_regex', $params, $results);
        return $results;
    }

    /**
     * 正規表現でマッチしたキー一覧を取得する
     * 正規表現での検索は比較的重くタイムアウトしやすいため注意してください。
     *
     * @param string $prefix 検索をするキーの正規表現
     * @param int 最大取得件数
     * @return array KTのマッチしたキーの配列
     */
    public function getRegexMatchKeys($regex, $limit)
    {
        return self::formatForKeys($this->match_regex($regex, $limit));
    }

    /**
     * キー名一覧取得メソッドのためのフォーマッター
     *
     * @param mixed match_prefixなどが返す結果セット
     * @return array キー一覧配列
     */
    private static function formatForKeys($results)
    {
        if (!is_array($results)) {
            return false;
        }

        // KTの出力ルールでキー名の先頭に_がついているので外した値を入れる
        // キー数は飛ばす
        $keys = array();
        foreach ($results as $value) {
            if ($value[0] === 'num') {
                continue;
            }
            $keys[] = substr($value[0], 1);
        }
        return $keys;
    }

    /**
     * リポートを取得する
     * @return array リポート情報が格納された連想配列
     */
    public function report() {
        $this->synchronousRequest('report', array(), $result);
        $ret = array();
        foreach ($result as $line) {
            $ret[$line[0]] = $line[1];
        }

        return $ret;
    }

    /**
     * レスポンスが不要なHTTPリクエストを発行する
     * @param string $procedure 実行するプロシージャ
     * @param array ポストする配列
     * @return bool 成功した場合true/失敗した場合false
     */
    private function asynchronousRequest($procedure, array $params = array())
    {
        $client = new HttpClient($this->host, $this->port, $this->timeout, $this->ignoreWarning);
        if (!$client->connect()) {
            return false;
        }
        $client->request($this->buildRPCRequest($procedure, $params))->close();
        return true;
    }

    /**
     * レスポンスが必要なHTTPリクエストを発行する
     * @param string $procedure 実行するプロシージャ
     * @param array $params ポストする配列
     * @param array $response レスポンスが格納される配列
     * @return mixed 成功した場合true, 失敗した場合 or 接続タイムアウトになった場合false
     */
    private function synchronousRequest($procedure, array $params = array(), &$response = null)
    {
        $client = new HttpClient($this->host, $this->port, $this->timeout, $this->ignoreWarning);
        if (!$client->connect()) {
            return false;
        }

        $response = $client->request($this->buildRPCRequest($procedure, $params))
                           ->getResponse();
        $client->close();

        if ($client->isTimeout()) {
            return false;
        }

        $response = self::tsvToArray($client->getResponse());
        return true;
    }

    /**
     * Tab-separate-valueを連想配列に格納する
     * @param string data KTが返すデータ
     * @return array タブ分割した配列の配列
     */
    private static function tsvToArray($data)
    {
        $result = array();
        $data = explode("\n", $data);

        foreach ($data as $row) {
            $row = trim($row);
            if (!$row) {
                continue;
            }
            $result[] = explode("\t", $row);
        }

        return $result;
    }
}

