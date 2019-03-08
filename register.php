<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$host = '10.0.0.4'; // where the rabbitmq server is
$port = 5672; // port number of service
$user = 'auth_client'; // username to connect to service
$pass = 'secure_pass'; // pass to connect to service
$vhost = 'user_auth';

$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
$channel  = $connection->channel();

$channel->exchange_declare('RegisterExchange', 'direct', false, false, false);
list($queue_name, ,) = $channel->queue_declare('', false, true, false, false);
$channel->queue_bind($queue_name, 'RegisterExchange', 'register_req');

//register
$register_callback = function ($req) {
        echo "Registering User..\n";
    $result = explode('~', $req->body);
    $user = $result[0];
    $pass = $result[1];
    $error = "E";
    $success = "S";
    $passhash = password_hash($pass, PASSWORD_DEFAULT);

    $msg = new AMQPMessage (
        $error,
        array('correlation_id' => $req->get('correlation_id'))
    );

    try {
        $servername = "10.0.0.4";
        $dbaseUser = "auth-client";
        $dbasePass = "njit490";
        $dbasename = "users";

        $pdo = new PDO("mysql:host=$servername;dbname=$dbasename", $dbaseUser, $dbasePass);
        echo 'connected', "\n";
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // calling stored procedure command
        //$sql = "CALL insertUser(?,?)";

        // prepare for execution of the stored procedure
        //$stmt = $pdo->prepare($sql);

        // pass value to the command
        //$stmt->bindParam(1, $user, PDO::PARAM_STR);
        //$stmt->bindParam(2, $passhash, PDO::PARAM_STR);

        // execute the stored procedure
        //$isSuccessful = $stmt->execute();
        echo $passhash, "\n";
        $pdo->query("CALL insertUser('$user', '$passhash')");


    $msg = new AMQPMessage (
        $success,
        array('correlation_id' => $req->get('correlation_id'))
    );
        echo "Successful\n";

    } catch (PDOException $e) {
        echo "Error occurred:" . $e->getMessage();
    }

    $req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
    echo "Delivered Message\n";

};

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue_name, '', false, true, false, false, $register_callback);

while (count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
