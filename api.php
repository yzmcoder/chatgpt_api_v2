<?php
error_reporting(0);
ini_set('max_execution_time', 300);

$myKey = ''; // 接口密钥，需与系统配置的一致

try {

    $post = file_get_contents("php://input");
    $post = base64_decode($post);
    $post = json_decode($post, true);

    $apiKey = $post['apiKey'];
    $diyKey = $post['diyKey'];
    $api = $post['api'];
    $model = $post['model'] ?? '';
    $temperature = $post['temperature'] ?? '';
    $max_tokens = $post['max_tokens'] ?? '';

    if (md5($myKey) != md5($diyKey)) {
        echo json_encode([
            'errno' => 1,
            'message' => '接口密钥不正确'
        ]);
        exit;
    }

    $SDK = new sdk($apiKey, $model, $temperature, $max_tokens);
    if ($api == 'sendText30') {
        $result = $SDK->sendText30($post['message']);
        echo json_encode($result);
        exit;
    }
    if ($api == 'sendText35') {
        $result = $SDK->sendText35($post['message']);
        echo json_encode($result);
        exit;
    }

    if ($api == 'getBalance') {
        $result = $SDK->getBalance();
        echo json_encode($result);
        exit;
    }
} catch (Exception $e) {
    echo json_encode([
        'errno' => 1,
        'message' => $e->getMessage()
    ]);
    exit;
}



class sdk
{
    protected static $model = 'gpt-3.5-turbo';
    protected static $apiKey = '';
    protected static $temperature = 1;
    protected static $max_tokens = 1000;

    /**
     * sdk constructor.
     * @param string $apiKey
     * @param string $model
     * @param string $temperature
     * @param string $max_tokens
     */
    public function __construct($apiKey = '', $model = '', $temperature = '', $max_tokens = '')
    {
        if ($model) {
            self::$model = $model;
        }
        if ($temperature) {
            self::$temperature = $temperature;
        }
        if ($max_tokens) {
            self::$max_tokens = $max_tokens;
        }
        self::$apiKey = $apiKey;
    }

    /**
     * @param string $message
     * @return array
     * GPT3模型
     */
    public function sendText30($message = '')
    {
        $url = 'https://api.openai.com/v1/completions';
        $post = [
            'prompt' => $message,
            'max_tokens' => self::$max_tokens,
            'temperature' => self::$temperature,
            'model' => self::$model,
            'frequency_penalty' => 0,
            'presence_penalty' => 0.6,
            'stop' => [" Human:", " AI:"]
        ];
        $result = $this->httpRequest($url, json_encode($post));
        if (isset($result['error'])) {
            return [
                'errno' => 1,
                'message' => $result['error']['message']
            ];
        } else {
            return [
                'errno' => 0,
                'data' => [
                    'text' => $result['choices']['0']['text'],
                    'total_tokens' => $result['usage']['total_tokens']
                ]
            ];
        }
    }

    /**
     * @param string $message
     * @return array
     * GPT3.5模型
     */
    public function sendText35($message = [])
    {

        $url = 'https://api.openai.com/v1/chat/completions';
        $post = [
            'messages' => $message,
            'max_tokens' => self::$max_tokens,
            'temperature' => self::$temperature,
            'model' => self::$model,
            'frequency_penalty' => 0,
            'presence_penalty' => 0.6
        ];
        $result = $this->httpRequest($url, json_encode($post));
        if (isset($result['error'])) {
            return [
                'errno' => 1,
                'message' => $result['error']['message']
            ];
        } else {
            return [
                'errno' => 0,
                'data' => [
                    'text' => $result['choices']['0']['message']['content'],
                    'total_tokens' => $result['usage']['total_tokens']
                ]
            ];
        }
    }


    public function getModelList()
    {
        $url = 'https://api.openai.com/v1/models';
        return $this->httpRequest($url);
    }

    /**
     * @return array|mixed
     * 查询账户余额
     */
    public function getBalance()
    {
        $url = 'https://api.openai.com/dashboard/billing/credit_grants';
        return $this->httpRequest($url);
    }

    /**
     * http请求
     */
    protected function httpRequest($url, $post = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . self::$apiKey
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return [
                'errno' => 1,
                'message' => 'curl 错误信息: ' . curl_error($ch)
            ];
        }
        curl_close($ch);
        return json_decode($result, true);
    }
}
