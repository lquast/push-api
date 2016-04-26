<?php

    require_once '../vendor/autoload.php';

    $androidApiKey = "android api key";
    $iosCertFile = "path/to/ioscert.pem";

    $message = "Hello World! :)";


    $devices = array(
        "fu",
        "ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff",
        "1c219d951fe2908beeb19290d56a3b34db95e4b05386b5c3914aad0e380b88ce",
        "594d818baa2b14e97ca00d711f5fe43c5956ee49eac8adf1b0c55a79544570ec"
    );

    DarkLuk42\PushMessage::$errorLogger = function($os, $device, $error, $auth, $live = null){
        $data = array(
            "os" => $os,
            "device" => $device,
            "error" => $error,
            "auth" => $auth,
            "live" => $live
        );
        $dir = dirname(__FILE__)."/".date("Y-m-d");
        if(!is_dir($dir))
        {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir."/".date("H-i-s")."_error.log", json_encode($data) . "\n", FILE_APPEND);
    };

    $ios = DarkLuk42\PushMessage::pushIos( DarkLuk42\PushMessage::ENVIRONMENT_DEV, $iosCertFile, $devices, $message );


    $devices = array(
        "abc",
        "APA91bFuGh0tStk0u1AY1ZlkJk4_KC5SulRim2ZGNhlgop3ZOKtoqbSNFmO2eOPZdCcYugjtjWdNg_4QLJu6dMRhLlZISi33aq-SmU6i-ZL2ZmLo4ywWHQuwAapLFA3iMFml4XTz"
    );
    $android = DarkLuk42\PushMessage::pushAndroid( $androidApiKey, $devices, $message );


    exit( json_encode( array(
        "android" => $android,
        "ios" => $ios
    ) ) );