<?php
//include_once 'controller.php';

$check = 0;

$message = array();
$read = NULL;
$write = array();
$except = NULL;

$host = "localhost";
$port = 1250;


set_time_limit(0);


$socket = socket_create(AF_INET, SOCK_STREAM, 0);


$result = socket_bind($socket, $host, $port);


$result = socket_listen($socket, 20);
//  socket_set_nonblock($socket);

$clients = array();
do {



    $read = array($socket);

    $n = socket_select($read, $write, $except, 0);


    if ($n > 0) {
        if (in_array($socket, $read)) {


            $newsock = socket_accept($socket);
            @socket_recv($newsock, $data, 2048, 0);
            handshake($newsock, $data, $socket);

            //orgonizing 



            @socket_recv($newsock, $data, 2048, 0);
            $decoded_data = unmask($data);
            $json = json_decode($decoded_data);

            $c = new Client($newsock, $json->groups, $json->user);

            socket_set_nonblock($c->socket);
            $clients[] = $c;

            $newsock = null;
        }
    }
    $data = null;

    foreach ($clients as $client) {
        @socket_recv($client->socket, $data, 2048, 0);

        if ($data != null) {
            $decoded_data = unmask($data);
            $decoded_data = json_decode($decoded_data);

            if ($decoded_data == NULL) {   
                echo "someone disconnected";
                $i = array_search($client, $clients);
                unset($clients[$i]);
            
            } else {
                    echo json_encode($decoded_data);
                    foreach ($clients as $client) {
                            if (in_array($decoded_data->group,$client->group)){
                                
                                
                            @socket_write($client->socket, encode(json_encode($decoded_data)));
                            
                        }
                    }
                //}
            }
        }
    }
} while (true);



socket_close($socket);

//functions and classes
class Client
{
    public $socket, $group = array(), $id, $typing=false;

    public function __construct($socket, $group, $id)
    {
        $this->socket = $socket;
        $this->group = $group;
        $this->id = $id;
    }
}

function handshake($newsocket, $headers, $socket)
{

    if (preg_match("/GET (.*) HTTP/", $headers, $match))
        $root = $match[1];
    if (preg_match("/Host: (.*)\r\n/", $headers, $match))
        $host = $match[1];
    if (preg_match("/Origin: (.*)\r\n/", $headers, $match))
        $origin = $match[1];
    if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match))
        $key = $match[1];


    $acceptKey = $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    $acceptKey = base64_encode(sha1($acceptKey, true));


    $newHeader = "HTTP/1.1 101 Switching Protocols 
Upgrade: websocket 
Connection: Upgrade 
Sec-WebSocket-Accept: $acceptKey
";


    socket_write($newsocket, $newHeader);
    return true;
}

function unmask($payload)
{


    $length = ord($payload[1]) & 127;

    if ($length == 126) {
        $masks = substr($payload, 4, 4);
        $data = substr($payload, 8);
    } elseif ($length == 127) {
        $masks = substr($payload, 10, 4);
        $data = substr($payload, 14);
    } else {
        $masks = substr($payload, 2, 4);
        $data = substr($payload, 6);
    }

    $text = '';
    for ($i = 0; $i < strlen($data); ++$i) {
        $text .= $data[$i] ^ $masks[$i % 4];
    }
    return $text;
}

function encode($text)
{


    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($text);

    if ($length <= 125)
        $header = pack('CC', $b1, $length);
    elseif ($length > 125 && $length < 65536) $header = pack('CCS', $b1, 126, $length);
    elseif ($length >= 65536)
        $header = pack('CCN', $b1, 127, $length);

    return $header . $text;
}


