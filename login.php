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

$channel->exchange_declare('LoginExchange', 'direct', false, false, false);
list($queue_name, ,) = $channel->queue_declare('', false, true, false, false);
$channel->queue_bind($queue_name, 'LoginExchange', 'login_req');

//login
$login_callback = function ($req) {
        echo "Logging in User...\n";
    $result = explode('~', $req->body);
echo "This is body: ", $req->body, "\n";
echo "This is 0: ", $result[0], "\n";
echo "This is 1: ", $result[1], "\n";
    $user = $result[0];
    $pass = $result[1];
    $error = "E";
    $success = "S";

    $msg = new AMQPMessage (
        $error,
        array('correlation_id' => $req->get('correlation_id'))
    );


    try {
        $servername = '10.0.0.4';
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
        print_r($db_response);

            if (isset($db_response))
            {
                echo "In IF\n", "pass in DB is: '", $db_response['password'], "'\n";
                echo $pass, "\n";
                $passver = password_verify($pass, $db_response['password']);

                if($passver){
                    $msg = new AMQPMessage (
                        $success,
                        array('correlation_id' => $req->get('correlation_id'))
                    );
                    echo "Success\n";
                }
            }

    } catch (PDOException $e) {
        echo "Error occurred:" . $e->getMessage();
    }

    $req->delivery_info['channel']->basic_publish( $msg, '', $req->get('reply_to'));
    echo "Sent back Message\n";

};

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue_name, '', false, true, false, false, $login_callback);

while (count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
