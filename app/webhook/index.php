<?php
function ErrorExit($errlog)
{
    error_log($errlog);
    header('HTTP/1.1 404 Not Found');
    echo $errlog;
    exit();
}

$project = empty($_SERVER['REQUEST_URI']) ? '/' : preg_replace(['/\/+/', '/\?.*$/'], ['/', ''], strtolower($_SERVER['REQUEST_URI']));
if (empty($project)) ErrorExit('参数错误');
if (!file_exists("/var/www{$project}")) ErrorExit('接口不存在');

if ($_SERVER['REQUEST_METHOD'] != 'POST') ErrorExit('FAILED - not POST - ' . $_SERVER['REQUEST_METHOD']);

$content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim($_SERVER['CONTENT_TYPE'])) : '';
if ($content_type != 'application/json') ErrorExit('FAILED - not application/json - ' . $content_type);

$payload = trim(file_get_contents("php://input"));
if (empty($payload)) ErrorExit('FAILED - no payload');

$header_signature = isset($_SERVER['HTTP_X_GITEA_SIGNATURE']) ? $_SERVER['HTTP_X_GITEA_SIGNATURE'] : '';
if (empty($header_signature)) ErrorExit('FAILED - header signature missing');

// calculate payload signature
$secret_key = 'http://' . (empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST']) . $project;
$payload_signature = hash_hmac('sha256', $payload, $secret_key, false);
if ($header_signature != $payload_signature) ErrorExit('FAILED - payload signature - ' . $secret_key);

// convert json to array
$decoded = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) ErrorExit('FAILED - json decode - ' . json_last_error());

if (!$connect = ssh2_connect('118.31.77.172', 22)) ErrorExit('FAILED - 链接失败');
if (!ssh2_auth_password($connect, 'root', 'Helloworld321')) ErrorExit('FAILED - 登陆失败');
if (empty($_GET['npm'])) {
    $stream = ssh2_exec($connect, "cd /data/docker-compose/app{$project} && umask 022 && git pull && exit");
} else {
    $stream = ssh2_exec($connect, "cd /data/docker-compose/app{$project} && umask 022 && git pull && docker run --rm -v /data/docker-compose/app{$project}:/home/app -w /home/app --device-read-bps /dev/vda:50m node npm run {$_GET['npm']} && exit");
}
stream_set_blocking($stream, true);
$output = stream_get_contents($stream);
fclose($stream);
echo $output;
