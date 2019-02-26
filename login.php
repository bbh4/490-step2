<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$host = '10.0.0.6'; // where the rabbitmq server is
$port = 5672; // port number of service
$user = 'auth_client'; // username to connect to service
$pass = 'secure_pass'; // pass to connect to service
$vhost = 'user_auth';

$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
$channel  = $connection->channel();

$channel->exchange_declare('LoginExchange', 'direct', false, false, false);
list($queue_name, ,) = $channel->queue_declare('', false, false, true, false);
$channel->queue_bind($queue_name, 'LoginExchange', 'login_req');

//login
$login_callback = function ($req) {
    $result = explode('~', $req->body);
    $user = $result[0];
    $pass = $result[1];
    $error = "E";
    $success = "S";

    $msg = new AMQPMessage (
        $error,
        array('correlation_id' => $req->get('correlation_id'))
    );


    try {
        $servername = '10.0.0.9';
        $dbaseUser = "auth-client";
        $dbasePass = "njit490";
        $dbasename = "users";
        $pdo = new PDO("mysql:host=$servername;dbname=$dbasename", $dbaseUser, $dbasePass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "connected\n";

        // calling stored procedure command
        $sql = "CALL getPassword(?)";
 // prepare for execution of the stored procedure
        $stmt = $pdo->prepare($sql);

        // pass value to the command
        $stmt->bindParam(1, $user, PDO::PARAM_STR);
         // execute the stored procedure
        $isSuccessful = $stmt->execute();

        $db_response = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!empty($db_response))
            {
                $passver = password_verify($pass, $db_response['password']);
                if((bool) $passver){
                    $msg = new AMQPMessage (
                        $success,
                        array('correlation_id' => $req->get('correlation_id'))
                    );
                }
            }

    } catch (PDOException $e) {
        echo "Error occurred:" . $e->getMessage();
    }

    $req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));

};

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue_name, '', false, false, false, false, $login_callback);

while (true) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
