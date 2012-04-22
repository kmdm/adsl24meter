<?php
class ADSL24ClientException extends Exception {}
class ADSL24ClientLoginException extends Exception {}

class ADSL24Client 
{
    const ADSL24_ACCOUNT_URL = 'https://adsl24.co.uk/myaccount/';
    const ADSL24_USAGE_URL = 'usage/';
    const LOGIN_NEEDLE = '<span class="page_title">My account login</span>';
    const USAGE_REGEX = 'function +([a-z0-9_]+)[^a-z0-9_].*return *JSON.stringify *\( *\{(.*)\} *\) *;';
    private $cookies = NULL;

    public function __construct($cookies=NULL)
    {
        $this->cookies = $cookies;
    }

    private function makeRequest($url, $post_data=NULL) 
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)'
        );

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);
        
        if($post_data) {
            $post = array();
            foreach($post_data as $key=>$value)
                $post[] = "$key=" . urlencode($value);
            
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $post));

        }

        if($this->cookies) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);
        }
        
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        $data = curl_exec($ch);

        if(preg_match_all('|Set-Cookie: (.*);|U', $data, $matches)) {
            $this->cookies = implode(';', $matches[1]);
        }

        if(strpos($data, self::LOGIN_NEEDLE) !== FALSE) {
            throw new ADSL24ClientLoginException(
                'Error: Not logged in.'
            );
        }

        return $data;
    }

    public function login($username, $password) 
    {
        try {
            $data = $this->makeRequest(self::ADSL24_ACCOUNT_URL);
        } catch(ADSL24ClientLoginException $e) {
            $data = $this->makeRequest(self::ADSL24_ACCOUNT_URL, array(
                'action'=>'login',
                'service'=>'broadband',
                'user'=>$username,
                'pass'=>$password
            ));
        }
    }
    
    public function usage()
    {
        $data = $this->makeRequest(
            self::ADSL24_ACCOUNT_URL . self::ADSL24_USAGE_URL
        );
        
        $data = substr($data, strpos($data, "function ofc_ready") + 2);
        
        if(!preg_match_all('/'.self::USAGE_REGEX.'/isU', $data, $matches))
            throw new ADSL24ClientException("Unable to extract usage stats!");
        elseif(count($matches) != 3)
            throw new ADSL24ClientException("Invalid usage stats match!");

        $raw_stats = array();
        foreach($matches[0] as $i=>$raw_match)
            $raw_stats[$matches[1][$i]] = $matches[2][$i];

        
        $stats = array();
        foreach($raw_stats as $name=>$raw_stat) {
            $stat = json_decode('{'.$raw_stat.'}', TRUE);

            if(is_null($stat)) {
                throw new ADSL24ClientException(
                    "Unable to decode stat $name: $raw_stat"
                );
            }

            switch($name) {
                case 'get_data_1':
                    foreach($stat['elements'][0]['values'] as $value) 
                    switch($value['label']) {
                        case 'Used': 
                            $stats['used'] = floatval($value['value']);
                            break;
                        case 'Remaining':
                            $stats['remaining'] = floatval($value['value']);
                            break;
                    }

                    if(!isset($stats['used'])) {
                        throw new ADSL24ClientException(
                            "Unable to determine used bandwidth!"
                        );
                    } elseif(!isset($stats['remaining'])) {
                        throw new ADSL24ClientException(
                            "Unable to determine remaining bandwidth!"
                        );
                    }
                break;

                case 'get_data_2':

                break;
            }
        }

        return $stats;
    }
    
    public function getCookies()
    {
        return $this->cookies;
    }
}
?>
