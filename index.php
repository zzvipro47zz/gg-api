<?php

    ini_set('display_errors', 'On');

    require_once 'vendor/autoload.php';

    session_start();

    define('CREDENTIALS_PATH', 'php-yt-oauth2.json');

    function getClient() {
        $client = new Google_Client();
        $client->setApplicationName('API Samples');
        $client->setScopes('https://www.googleapis.com/auth/youtube.force-ssl');
        $client->setAuthConfig('client_secrets.json');
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        // Load previously authorized credentials from a file.
        $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
        if (file_exists($credentialsPath)) {
            $accessToken = json_decode(file_get_contents($credentialsPath), true);
        } else {
            $state = mt_rand();
            $client->setState($state);
            $_SESSION['state'] = $state;

            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            echo "<a href='$authUrl'>Click to Auth</a>";

            $authCode = @$_GET['code'];

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            /*if(!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), '0700', true);
            }*/
            file_put_contents($credentialsPath, json_encode($accessToken));
            echo 'Credentials saved to ' . $credentialsPath;
        }
        $client->setAccessToken($accessToken);
        
        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();
            $newAccessToken = $client->getAccessToken();
            $accessToken = array_merge($accessToken, $newAccessToken);
            $client->fetchAccessTokenWithRefreshToken($refreshToken);
            file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
        }

        return $client;
    }

    $client = getClient();
    $youtube = new Google_Service_YouTube($client);

    echo '<pre>';
    print_r($_SESSION);
    echo '</pre>';

    if (isset($_GET['code'])) {
        /*if (strval($_SESSION['state']) !== strval(@$_GET['state'])) {
            die('The session state did not match.');
        }*/

        $client->authenticate($_GET['code']);
        $_SESSION['token'] = $client->getAccessToken();
    }

    if (isset($_SESSION['token'])) {
        $client->setAccessToken($_SESSION['token']);
    }

    if (!$client->getAccessToken()) {
        echo 'no access token, whaawhaaa';
        exit;
    }

    function expandHomeDirectory($path) {
        // $homeDirectory = getenv('HOME');
        $homeDirectory = getcwd() . '/' . $path;
        return $homeDirectory;
    }

    function addPropertyToResource(&$ref, $property, $value) {
        $keys = explode(".", $property);
        $is_array = false;
        foreach ($keys as $key) {
            // Convert a name like "snippet.tags[]" to "snippet.tags" and
            // set a boolean variable to handle the value like an array.
            if (substr($key, -2) == "[]") {
                $key = substr($key, 0, -2);
                $is_array = true;
            }
            $ref = &$ref[$key];
        }

        // Set the property value. Make sure array values are handled properly.
        if ($is_array && $value) {
            $ref = $value;
            $ref = explode(",", $value);
        } elseif ($is_array) {
            $ref = array();
        } else {
            $ref = $value;
        }
    }

    function createResource($properties) {
        $resource = array();
        foreach ($properties as $prop => $value) {
            if ($value) {
                addPropertyToResource($resource, $prop, $value);
            }
        }
        return $resource;
    }

    function uploadMedia($client, $request, $filePath, $mimeType) {
        $chunkSizeBytes = 1 * 1024 * 1024;
        $media = new Google_Http_MediaFileUpload(
            $client,
            $request,
            $mimeType,
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($filePath));


        // Read the media file and upload it chunk by chunk.
        $status = false;
        $handle = fopen($filePath, "rb");
        while (!$status && !feof($handle)) {
          $chunk = fread($handle, $chunkSizeBytes);
          $status = $media->nextChunk($chunk);
        }

        fclose($handle);
        return $status;
    }

    function videosInsert($client, $service, $media_file, $properties, $part, $params) {
        $params = array_filter($params);
        $propertyObject = createResource($properties); // See full sample for function
        $resource = new Google_Service_YouTube_Video($propertyObject);
        $client->setDefer(true);
        $request = $service->videos->insert($part, $resource, $params);
        $client->setDefer(false);
        $response = uploadMedia($client, $request, $media_file, 'video/*');
        
        echo 'Upload Thành Công !!!';
    }

    

    if (isset($_POST['upload'])) {



        $media_file = 'test.webm'; // đây là url của video

        videosInsert(
            $client,
            $youtube,
            $media_file,
            array (
                'snippet.categoryId' => '22',
                'snippet.defaultLanguage' => '', // ngôn ngữ của video
                'snippet.description' => 'Test chức năng upload video lên youtube qua 1 tài khoản chính.',
                'snippet.tags[]' => '', // tags
                'snippet.title' => 'Tựa đề',
                'status.embeddable' => '',
                'status.license' => '',
                'status.privacyStatus' => 'private', // public = công khai, private = riêng tư (chế độ bài viết)
                'status.publicStatsViewable' => ''
            ),
            'snippet,status',
            array()
        );
    }
?>

<form action="" method="post">
    <input type="submit" name="upload" value="Upload">
</form>