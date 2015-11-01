<?php

///////////////////////////////////////////////////////////////////////////////////////
//   IVONA_TTS             ////////////////////////////////////////////////////////////
//    by Titus 15.10.2015  ////////////////////////////////////////////////////////////
//    enhanced by Thorsten Kugelberg 30.10.2015 ///////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////
// contains dirty hack that has to be removed when hash functions are availabe in IPS /
///////////////////////////////////////////////////////////////////////////////////////


class IVONA_TTS{
    private $utc_tz      = "";
    private $access_key  = "";
    private $secret_key  = "";
    private $language    = "";
    private $voice       = "";
    private $rate        = "";
    private $volume      = "";
    private $hash_exists = "";

    public function __construct( $access_key, $secret_key , $language="de-DE", $voice="Marlene", $rate="medium", $volume="loud"){
        $this->utc_tz      = new \DateTimeZone( 'UTC' );
        $this->access_key  = $access_key;
        $this->secret_key  = $secret_key;
        $this->language    = $language;
        $this->voice       = $voice;
        $this->rate        = $rate;
        $this->volume      = $volume;
        $this->hash_exists = function_exists('hash_hmac');
    }

    public function save_mp3($text, $filename) {
            $mp3 = $this->get_mp3($text);
            file_put_contents($filename, $mp3);
     }

    public function get_mp3( $text )
    {
        $payload = json_encode(array( ('Input')      => array(('Data')     => utf8_encode($text)),
                                      ('Parameters') => array(('Rate')     => $this->rate,
                                                              ('Volume')   => $this->volume),
                                      ('Voice')      => array(('Name')     => $this->voice,
                                                              ('Language') => $this->language )));
       
        $datestamp                = new \DateTime( "now", $this->utc_tz );
        $longdate                 = $datestamp->format( "Ymd\\THis\\Z");
        $shortdate                = $datestamp->format( "Ymd" );
        $ksecret                  = 'AWS4' . $this->secret_key;
        if($this->hash_exists){
          $params                   = array( 'host'                 => 'tts.eu-west-1.ivonacloud.com',
                                             'content-type'         => 'application/json',
                                             'x-amz-content-sha256' => hash( 'sha256', $payload, false ),
                                             'x-amz-date'           => $longdate );
        }else{
          $hash_command             = "php -r \"print(hash( 'sha256', '".str_replace('"','\"',$payload)."', false ));\"";
          $params                   = array( 'host'                 => 'tts.eu-west-1.ivonacloud.com',
                                             'content-type'         => 'application/json',
                                             'x-amz-content-sha256' => exec($hash_command),
                                             'x-amz-date'           => $longdate );
        }
        $canonical_request        = $this->createCanonicalRequest( $params, $payload );
        if($this->hash_exists){
          $signed_request         = hash( 'sha256', $canonical_request, false );
        }else{
          $hash_command           = "php -r \"print(hash( 'sha256', '".$canonical_request."', false ));\"";
          $signed_request         = exec($hash_command);
        }
        $sign_string              = "AWS4-HMAC-SHA256\n{$longdate}\n$shortdate/eu-west-1/tts/aws4_request\n" . $signed_request;
        if($this->hash_exists){
          $signature              = hash_hmac( 'sha256', $sign_string, hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', 'tts', hash_hmac( 'sha256', 'eu-west-1', hash_hmac( 'sha256', $shortdate, $ksecret, true ) , true ) , true ), true ));
        }else{
          $signature_command      = "php -r \"print(hash_hmac( 'sha256', '".$sign_string."', hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', 'tts', hash_hmac( 'sha256', 'eu-west-1', hash_hmac( 'sha256', '".$shortdate."', '".$ksecret."', true ) , true ) , true ), true ) ) );\"";
          $signature              = exec($signature_command);;
        }
        $params['Authorization']  = "AWS4-HMAC-SHA256 Credential=" . $this->access_key . "/$shortdate/eu-west-1/tts/aws4_request, " .
                                    "SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date, " .
                                    "Signature=$signature";
        $params['content-length'] = strlen( $payload ) ;
        /*
         * Execute Crafted Request
         */
        $url    = "https://tts.eu-west-1.ivonacloud.com/CreateSpeech";
        $ch     = curl_init();
        $curl_headers = array();
        foreach( $params as $p => $k )
            $curl_headers[] = $p . ": " . $k;
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
        // debug opts
        {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'rw+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            $result = curl_exec($ch); // raw result
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            #echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        }
        return $result;
    }
    
    private function createCanonicalRequest( Array $params, $payload )
    {
        $canonical_request      = array();
        $canonical_request[]    = 'POST';
        $canonical_request[]    = '/CreateSpeech';
        $canonical_request[]    = '';
        $can_headers            = array(
          'host' => 'tts.eu-west-1.ivonacloud.com'
        );
        foreach( $params as $k => $v )
            $can_headers[ strtolower( $k ) ] = trim( $v );
        uksort( $can_headers, 'strcmp' );
        foreach ( $can_headers as $k => $v )
            $canonical_request[] = $k . ':' . $v;
        $canonical_request[] = '';
        $canonical_request[] = implode( ';', array_keys( $can_headers ) );
        if($this->hash_exists){
          $canonical_request[] = hash( 'sha256', $payload, false );
        }else{
          $hash_command        = "php -r \"print(hash( 'sha256', '".str_replace('"','\"',$payload)."', false ));\"";
          $canonical_request[] = exec($hash_command);
        }
        $canonical_request = implode( "\n", $canonical_request );
        return $canonical_request;
    }
} 
?>
