<?php

    namespace DarkLuk42;

    class PushMessage
    {
        const ANDROID_BULK_LIMIT = 10;
        const ENVIRONMENT_DEV = "DEV";
        const ENVIRONMENT_PROD = "PROD";

        public static $errorLogger = NULL;

        public static function pushAndroid( $apiAccessKey, $devices, $message, $extra = array() )
        {
            $extra = (object)$extra;

            if( count( $devices ) > self::ANDROID_BULK_LIMIT )
            {
                $devicesChunks = array_chunk( $devices, self::ANDROID_BULK_LIMIT );
                $result = array();
                foreach( $devicesChunks AS $devices )
                {
                    $result = array_merge( $result, self::pushAndroid( $apiAccessKey, $devices, $message, $extra ) );
                }
                return $result;
            }

            $devices = array_values( $devices );

            $fields = array(
                'registration_ids' => $devices,
                'data' => array(
                    'message' => $message,
                    'title' => isset( $extra->title ) ? $extra->title : "",
                    'vibrate' => isset( $extra->vibrate ) ? $extra->vibrate : 1,
                    'sound' => isset( $extra->sound ) ? $extra->sound : 1
                )
            );

            $headers = array(
                'Authorization: key=' . $apiAccessKey,
                'Content-Type: application/json'
            );

            $host = 'https://fcm.googleapis.com/fcm/send';

            $ch = curl_init( );
            curl_setopt( $ch, CURLOPT_URL, $host );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $fields ) );
            $result = curl_exec( $ch );

            if( curl_errno( $ch ) )
            {
                throw new \Exception( curl_error( $ch ) );
            }

            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close( $ch );

            $data = json_decode( $result );
            if( empty( $data ) )
            {
                throw new \Exception( "Failed to send push notifications. HTTP-Status: " . $httpStatus );
            }

            if( !empty( $data->error ) )
            {
                throw new \Exception( "Failed to send push notifications. " . json_encode( $data ) );
            }

            $result = array();
            foreach( $data->results AS $i => $r )
            {
                if(!empty($r->error))
                {
                    self::logError("ANDROID", $devices[$i], $r->error, substr($apiAccessKey, 0, 3) . str_pad("", strlen($apiAccessKey)-6, "*") . substr($apiAccessKey, -3, 3));
                }
                $result[$devices[$i]] = empty( $r->error );
            }

            return $result;
        }

        public static function logError($os, $device, $error, $auth, $live = null)
        {
            $tmp = self::$errorLogger;
            if(is_callable($tmp))
            {
                $tmp($os, $device, $error, $auth, $live);
            }
        }

        public static function pushIos( $environment, $certFile, $devices, $message, $extra = array() )
        {
            $extra = (object)$extra;

            $environment = $environment == self::ENVIRONMENT_PROD ? \ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION : \ApnsPHP_Abstract::ENVIRONMENT_SANDBOX;
            $push = new \ApnsPHP_Push( $environment, $certFile );
            $push->setLogger( new VoidLogger() );
            $push->setRootCertificationAuthority( dirname( __FILE__ ) . '/../../data/entrust_root_certification_authority.pem' );
            $push->connect();

            $result = array();
            foreach( $devices AS $device )
            {
                try
                {
                    $m = new \ApnsPHP_Message( $device );
                    $m->setText( $message );
                    if( isset( $extra->badge ) )
                    {
                        $m->setBadge( $extra->badge );
                    }
                    if( isset( $extra->sound ) )
                    {
                        $m->setSound( $extra->sound );
                    }
                    $push->add( $m );
                }
                catch(\Exception $e )
                {
                    self::logError("iOS", $device, $e->getMessage(), $certFile, $environment == \ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION);
                    $result[$device] = false;
                }
            }

            $push->send();

            $push->disconnect();

            $errors = $push->getErrors();
            if( !empty( $errors ) )
            {
                foreach( $errors AS $error )
                {
                    $m = $error["MESSAGE"];
                    foreach( $m->getRecipients() AS $device )
                    {
                        self::logError("iOS", $device, $error["ERRORS"][0]["statusMessage"], $certFile, $environment == \ApnsPHP_Abstract::ENVIRONMENT_PRODUCTION);
                        $result[$device] = false;
                    }
                }
            }

            foreach( $devices AS $device )
            {
                if( !isset( $result[$device] ) )
                {
                    $result[$device] = true;
                }
            }

            return $result;
        }
    }
