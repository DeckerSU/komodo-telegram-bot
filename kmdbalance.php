<?php
/* 
komodod <-> telegram interaction script (c) DeckerSU

Requirements: 
                - php-curl php-openssl extensions intalled
                - host with ssl certificate
                - access to komodod via rpc
*/


/* Configuration */
$token = ""; // token from Bot Father
$admin_id = ""; // your Telegram user ID

$komodod = [
    "user" => "bitcoinrpc",
    "password" => "password",
    "ip" => "127.0.0.1", // ip of komomod
    "port" => "10999"
];

$script_domain = "example.org"; // your hostname, script must be available at https://example.org:443/kmdbalance.php
$script_curdir = dirname(__FILE__);

function KMD_listunspent($user,$password,$ip,$port) {
    
    $post = [
        "jsonrpc" => "1.0",
        "id" => "curltest",
        "method"   => "listunspent",
        "params" => [6]
    ];
    $data_string = json_encode($post);

    $ch = curl_init();
    $url = sprintf("http://%s:%s@%s:%s/",$user,$password,$ip,$port);
    // logger(var_export($url,true));
    
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($data_string)));
    $response = curl_exec($ch);
    curl_close($ch);
    
    $response = json_decode($response,true);
    if ($response) {
        if (!isset($response["error"])) {
            $input = [];
            foreach ($response["result"] as $res) {
                isset($input[$res["address"]]) ? $input[$res["address"]] += $res["amount"] : $input[$res["address"]] = $res["amount"];
            }
            
            foreach ($input as $address => $amount) { $message .= sprintf('<code>%15.8f</code> - <a href="kmd.explorer.supernet.org/address/%s">%s</a>'."\n",$amount,$address,$address); }
            // $message .= "<code>".sprintf("%15.8f",array_sum($input))."</code> - <b>Total balance</b>\n";
            $message .= "<code>---------------\n".sprintf("%15.8f",array_sum($input))."</code>\n";
            return $message;
            
        } else return "Error: [".$response["error"]["code"]."] ".$response["error"]["message"];
    } else return "Error: Couldn't connect to node ...";
}

function KMD_getblockchaininfo($user,$password,$ip,$port) {
    
    $post = [
        "jsonrpc" => "1.0",
        "id" => "curltest",
        "method"   => "getblockchaininfo",
        "params" => []
    ];
    $data_string = json_encode($post);

    $ch = curl_init();
    $url = sprintf("http://%s:%s@%s:%s/",$user,$password,$ip,$port);
    // logger(var_export($url,true));
    
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($data_string)));
    $response = curl_exec($ch);
    curl_close($ch);
    
    $response = json_decode($response,true);
    if ($response) {
        if (!isset($response["error"])) {
            $input = [];
            logger(var_export($response["result"],true));
            $message = "<b>Status:</b>\n";
            $message .= isset($response["result"]["chain"]) ? "Chain: ".$response["result"]["chain"]."\n" : "";
            $message .= isset($response["result"]["blocks"]) ? "Blocks: ".$response["result"]["blocks"]."\n" : "";
            $message .= isset($response["result"]["headers"]) ? "Headers: ".$response["result"]["headers"]."\n" : "";
            $message .= isset($response["result"]["difficulty"]) ? "Difficulty: ".$response["result"]["difficulty"]."\n" : "";

            return $message;
            
        } else return "Error: [".$response["error"]["code"]."] ".$response["error"]["message"];
    } else return "Error: Couldn't connect to node ...";
}

function logger($text) {
    file_put_contents(basename(__FILE__,".php").".log",sprintf("[%s] ",date('Y-m-d H:i:s')).$text."\n\n",FILE_APPEND);   
}

function pretty_json($json) {
    
    require_once 'classes/PrettyJSON.php';
    $error = '';
    $rHtml = '';
    $engine = new PrettyJSON();
	$engine->setJsonText($json);
	if ($engine->isError()) {
		$error = $engine->getError();
        return $error;
	} else {
		$rHtml = $engine->getHtml();
        return $rHtml;
	}
}

function setwebhook() {
    
    global $token, $script_domain;
    $params = array();
    $answer = file_get_contents("https://api.telegram.org/bot".$token."/getWebhookInfo?".http_build_query($params));
    echo "<h3>getWebhookInfo</h3>".pretty_json($answer);
    
    $answer = json_decode($answer,true);
        
    if ($answer) {
            $params = array();
            $params["url"] = "https://".$script_domain.":443/".basename(__FILE__);
            $answer = file_get_contents("https://api.telegram.org/bot".$token."/setWebhook?".http_build_query($params));
            echo "<h3>setWebhook</h3>".pretty_json($answer);
    }
}

function sendmessage($chat_id, $text,$parse_mode="") {
    global $token;
    
    $params = array();
    $params['chat_id'] = $chat_id;
    $params['text'] = $text;
    if (!empty($parse_mode)) { 
        $params["parse_mode"] = $parse_mode; 
        $params['disable_web_page_preview'] = true;
    }
    $url = "https://api.telegram.org/bot".$token."/sendMessage?".http_build_query($params);
    // logger($url);
    $answer = file_get_contents($url);
    // logger(var_export($answer,true));
}

function getmessagetype($action) { 
    if (isset($action["message"]["text"])) return "text";
    if (isset($action["message"]["sticker"])) return "sticker";
    return false;
}

/* Main */
if ((isset($_SERVER['REQUEST_METHOD'])) && ($_SERVER['REQUEST_METHOD'] == 'POST') 
                                        && ($_SERVER['CONTENT_TYPE'] == 'application/json')) {
    $json = file_get_contents('php://input');
    $action = json_decode($json, true);
    $chat_id = isset($action["message"]["chat"]["id"]) ? $action["message"]["chat"]["id"] : false;
    
    // здесь надо считать state из apc
    
    $bot_command = false;
    if (isset($action['message']['entities'][0]['type'])) {
        $type = $action['message']['entities'][0]['type'];
        if ($type == "bot_command") $bot_command = true;
    }
    
    $message = isset($action["message"]["text"]) ? $action["message"]["text"] : false;
    // logger(var_export($bot_command,true));
    
    if ($chat_id) {
        if (($chat_id != $admin_id)) {
            sendmessage($chat_id,sprintf("[%s] ",date('Y-m-d H:i:s'))." Bot is under construction ...");
            die();
        }
        
        if (getmessagetype($action) == "text") { 
            if (!$bot_command) {
                
                if ($message == "❓ Help") {
                    sendmessage($chat_id,"Sorry, no help available ...\n<b>KMD Donate</b>: RDecker69MM5dhDBosUXPNTzfoGqxPQqHu","HTML");    
                }
                
                if ($message == "💰 KMD Balance") {
                    
                    sendmessage($chat_id,"<b>Balances:</b>\n" . KMD_listunspent($komodod["user"],$komodod["password"],$komodod["ip"],$komodod["port"]),"HTML"); 
                }
                
                if ($message == "🏧 Status") {
                    
                    sendmessage($chat_id,KMD_getblockchaininfo($komodod["user"],$komodod["password"],$komodod["ip"],$komodod["port"]),"HTML"); 
                }
                
                } else
            {
                if ($message == "/start") {
                    $keyboard = [["💰 KMD Balance","🏧 Status","❓ Help"]];
                    $keyboard = array('keyboard' => $keyboard,'resize_keyboard' => true,'one_time_keyboard' => false);
                    $params['chat_id'] = $chat_id;
                    $params['text'] = 'Command?';
                    $params['reply_markup'] = json_encode($keyboard, TRUE);
                    // logger(var_export($params,true));
                    $answer = file_get_contents('https://api.telegram.org/bot'.$token.'/sendMessage?'.http_build_query($params));
    
                } else
                sendmessage($chat_id,"Unknown command: '".$message."' ...");
                
            }
        }
        
    }    
}
else {
    
    setwebhook();  
//    if (extension_loaded('apcu')) {
//        $bar = 'BAR';
//        apcu_store('foo', $bar);
//        var_dump(apcu_fetch('foo'));
//    }
    
}





?>