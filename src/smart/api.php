<?php

/**
 * Smart Dealer RESTful Client API
 *
 * @package   Smart Dealership
 * @author    Patrick Otto <patrick@smartdealership.com.br>
 * @version   1.1.2
 * @access    public
 * @copyright Smart Dealer(c), 2015 - 2018
 * @see       http://www.smartdealer.com.br
 *
 * @param  string $sdl the client instance name ou URL of werbservice ex: dealership or http://domain.com/rest/
 * @param  string $usr REST username (for WWW-authentication)
 * @param  string $pwd REST password
 * @param  array $opt the API client options (not required)
 */

namespace Smart;

class Api {

    private $debug_str, $sdl, $usr, $pwd, $error, $header_options = array();
    var $settings = array(
        'handle' => 'curl',
        'timeout' => 10,
        'use_ssl' => true,
        'port' => 80,
        'debug' => false,
        'output_format' => 1,
        'output_compile' => true,
        'gzip' => false
    );
    var $ws_header_options = array(
        'output_format' => 'integer',
        'gzip' => 'boolean'
    );
    var $methods = array(
        '/config/affiliates/' => array(
            'method' => 'get',
            'desc' => 'return branches listing (affiliates)'
        ),
        '/config/categories/' => array(
            'method' => 'get',
            'desc' => 'return stock categories (car, truck, motorcycle)'
        ),
        '/config/affiliate/' => array(
            'method' => 'post',
            'desc' => 'register new customer/affiliate'
        ),
        '/parts/' => array(
            'method' => 'get',
            'desc' => 'returns a parts list'
        ),
        '/parts/:id' => array(
            'method' => 'get',
            'desc' => 'returns a parts list (page)'
        ),
        '/parts/provider/' => array(
            'method' => 'get',
            'desc' => 'returns to manufacturer list (providers)'
        ),
        '/parts/order/' => array(
            'method' => 'post',
            'desc' => 'create or update parts orders',
        ),
        '/parts/notify/' => array(
            'method' => 'post',
            'desc' => 'create or update pending the parts inventory (alerts)',
        ),
        '/parts/order/:id' => array(
            'method' => 'delete',
            'desc' => 'delete part orders',
        ),
        '/parts/tires/' => array(
            'method' => 'get',
            'desc' => 'get stock of tires',
        ),
        '/connect/channels/' => array(
            'method' => 'get',
            'desc' => 'list channnels available for integration (connect)',
        ),
        '/connect/contracts/' => array(
            'method' => 'get',
            'desc' => 'list my integration settings by cliente and channel',
        ),
        '/connect/contract/' => array(
            'method' => 'post',
            'desc' => 'set/create new integration settings (contracts)',
        ),
        '/connect/packs/' => array(
            'method' => 'get',
            'desc' => 'list packs of stock integration (connect)',
        ),
        '/connect/pack/:id' => array(
            'method' => 'get',
            'desc' => 'list all offers of pack',
        ),
        '/connect/offers/' => array(
            'method' => 'get',
            'desc' => 'list all offers related',
        ),
        '/connect/offer/' => array(
            'method' => 'post',
            'desc' => 'register new offer to publish',
        ),
        '/connect/offer/:id' => array(
            'method' => 'delete',
            'desc' => 'delete offer from pack',
        ),
        '/connect/codes/' => array(
            'method' => 'get',
            'desc' => 'translate response codes list',
        ),
        '/connect/contract/:id' => array(
            'method' => 'delete',
            'desc' => 'remove one contract (integration client settings)',
        ),
    );

    const WS_PATH = '.smartdealer.com.br/webservice/rest/';
    const WS_DF_TIMEOUT = 10;
    const WS_DF_PORT = 80;
    const WS_SIGNATURE = '7cac394e6e2864b8e2f98e7fe815ab6b';

    public function __construct($sdl, $usr, $pwd, Array $opt = array()) {

        $default = array(
            'options' => array(
                'default' => $this->protocol() . $sdl . self::WS_PATH
            )
        );

        $this->sdl = trim(filter_var($sdl, FILTER_VALIDATE_URL, $default), ' /');
        $this->usr = filter_var($usr, FILTER_SANITIZE_STRING | FILTER_SANITIZE_SPECIAL_CHARS);
        $this->pwd = filter_var($pwd, FILTER_SANITIZE_STRING);

        $this->settings($opt);

        // check server
        if (!$this->validWs()) {
            $this->logError('The URL of Rest Webservice is not valid or server not permitted this request!');
        }
    }

    public function get($rest, $arg = array()) {

        // reset
        $this->error = array();

        // detect pattern
        $pat = '/^' . preg_replace(array('/\//', '/\d+$/'), array('\/', ':id'), $rest) . '$/';

        // valid route
        if (!preg_grep($pat, array_keys($this->methods)))
            $this->logError('The ' . $rest . ' method is invalid. Get $api->methods() to list available.');

        return ($this->getError()) ? array() : $this->call($rest, $arg);
    }

    public function post($rest, $arg = array()) {

        $a = '';

        // check server
        if (!$this->validWs()) {

            $this->logError('The URL of Rest Webservice is not valid or server not permitted this request!');

            return $this->output();
        }

        $time = (!empty($this->settings['timeout'])) ? (int) $this->settings['timeout'] : self::WS_DF_TIMEOUT;
        $port = (!empty($this->settings['port'])) ? (int) $this->settings['port'] : self::WS_DF_PORT;
        $auth = base64_encode($this->usr . ":" . $this->pwd);

        if (!empty($this->settings['handle'])) {
            switch ($this->settings['handle']) {
                case 'curl' :

                    // curl request 
                    $cr = curl_init($this->sdl . $rest);

                    curl_setopt($cr, CURLOPT_HTTPHEADER, $this->header_options);
                    curl_setopt($cr, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($cr, CURLOPT_TIMEOUT, $time);
                    curl_setopt($cr, CURLOPT_USERPWD, $this->usr . ":" . $this->pwd);
                    curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($cr, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                    curl_setopt($cr, CURLOPT_SSL_VERIFYPEER, !empty($this->settings['use_sll']));
                    curl_setopt($cr, CURLOPT_POST, true);
                    curl_setopt($cr, CURLOPT_POSTFIELDS, $arg);

                    // exec
                    $a = curl_exec($cr);

                    // validate
                    $this->validCurl($cr, $a);

                    // close
                    curl_close($cr);

                    break;
                case 'socket' :

                    // build query
                    $arg = http_build_query($arg);

                    $header = "POST / HTTP/1.0\r\n\r\n";
                    $header .= "Accept: text/html\r\n";
                    $header .= "Authorization: Basic $auth\r\n\r\n";
                    $header .= "Content-Type: application/x-www-form-urlencoded\r\n\r\n";
                    $header .= "Content-Length: " . strlen($arg) . "\r\n\r\n";
                    $header .= $arg . "\r\n\r\n";

                    $host = preg_replace('/^\w+\:\/\//', '', $this->sdl . $rest);
                    $fp = fsockopen($host, $port, $errno, $errstr, $time);

                    $a = '';

                    if (!$fp)
                        $this->logError($errstr);
                    else {
                        fwrite($fp, $header);
                        while (!feof($fp))
                            echo fgets($fp, 128);
                        fclose($fp);
                    }

                    break;
            }
        } else {
            $this->logError('required \'handle\' param on settings');
        }

        return $this->output($a);
    }

    public function delete($id) {

        $a = '';

        // check server
        if (!$this->validWs()) {

            $this->logError('The URL of Rest Webservice is not valid or server not permitted this request!');

            return $this->output();
        }

        $time = (!empty($this->settings['timeout'])) ? (int) $this->settings['timeout'] : self::WS_DF_TIMEOUT;
        $port = (!empty($this->settings['port'])) ? (int) $this->settings['port'] : self::WS_DF_PORT;
        $auth = base64_encode($this->usr . ":" . $this->pwd);

        if (!empty($this->settings['handle'])) {
            switch ($this->settings['handle']) {
                case 'curl' :

                    // curl request 
                    $cr = curl_init($this->sdl . $id);

                    curl_setopt($cr, CURLOPT_HTTPHEADER, $this->header_options);
                    curl_setopt($cr, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($cr, CURLOPT_TIMEOUT, $time);
                    curl_setopt($cr, CURLOPT_USERPWD, $this->usr . ":" . $this->pwd);
                    curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($cr, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                    curl_setopt($cr, CURLOPT_SSL_VERIFYPEER, !empty($this->settings['use_sll']));
                    curl_setopt($cr, CURLOPT_CUSTOMREQUEST, 'DELETE');

                    // exec
                    $a = curl_exec($cr);

                    // validate
                    $this->validCurl($cr, $a);

                    // close
                    curl_close($cr);

                    break;
            }
        } else {
            $this->logError('required \'handle\' param on settings');
        }

        return $this->output($a);
    }

    private function settings($opt) {

        // Api settings
        $this->settings = array_merge($this->settings, array_intersect_key($opt, $this->settings));

        // check dependecy (auto-disable)
        if (!function_exists('gzdecode')) {
            $this->settings['gzip'] = false;
        }

        // header (for WS reading)
        $header = array_intersect_key($this->settings, $this->ws_header_options);

        // compile 
        foreach ($header AS $a => $b) {
            $this->header_options[] = ((isset($this->ws_header_options[$a])) && gettype($b) == $this->ws_header_options[$a]) ? ucfirst(str_replace('_', '-', $a)) . ': ' . $b : null;
        }

        // remove invalid
        $this->header_options = array_filter($this->header_options);

        // fix CURL change header error
        $this->header_options[] = 'Expect: 100-continue';
    }

    public function methods() {
        return array_filter($this->methods);
    }

    public function call($rest, $arg) {

        // check server
        if (!$this->validWs()) {

            $this->logError('The URL of Rest Webservice is not valid or server not permitted this request!');

            return $this->output();
        }

        $time = (!empty($this->settings['timeout'])) ? (int) $this->settings['timeout'] : self::WS_DF_TIMEOUT;
        $port = (!empty($this->settings['port'])) ? (int) $this->settings['port'] : self::WS_DF_PORT;
        $auth = base64_encode($this->usr . ":" . $this->pwd);

        if (!empty($this->settings['handle'])) {
            switch ($this->settings['handle']) {
                case 'curl' :

                    // curl request 
                    $cr = curl_init($this->sdl . $rest);

                    curl_setopt($cr, CURLOPT_HTTPHEADER, $this->header_options);
                    curl_setopt($cr, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($cr, CURLOPT_TIMEOUT, $time);
                    curl_setopt($cr, CURLOPT_USERPWD, $this->usr . ":" . $this->pwd);
                    curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($cr, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                    curl_setopt($cr, CURLOPT_SSL_VERIFYPEER, !empty($this->settings['use_sll']));

                    // exec
                    $a = $this->curl_exec_follow($cr);

                    // validate
                    $this->validCurl($cr, $a);

                    // close
                    curl_close($cr);

                    break;
                case 'socket' :

                    $header = "GET / HTTP/1.0\r\n\r\n";
                    $header .= "Accept: text/html\r\n";
                    $header .= "Authorization: Basic $auth\r\n\r\n";

                    $host = preg_replace('/^\w+\:\/\//', '', $this->sdl . $rest);

                    $fp = fsockopen($host, $port, $errno, $errstr, $time);

                    if (!$fp)
                        $this->logError($errstr);
                    else {
                        fputs($fp, $header);
                        while (!feof($fp))
                            echo fgets($fp, 128);
                    }
                    fclose($fp);

                    break;
                case 'stream' :

                    // stream settings
                    $opts = array(
                        'http' => array(
                            'method' => "GET",
                            'header' => "Accept-language: en\r\nContent-type: application/json\r\nAuthorization: Basic $auth",
                        )
                    );

                    // create stream
                    $context = stream_context_create($opts);

                    // get URL
                    $a = file_get_contents($this->sdl . $rest, false, $context);

                    break;
                default:
                    $this->logError('invalid \'handle\' (use curl, socket, stream)');
            }
        } else {
            $this->logError('required \'handle\' param on settings');
        }

        return $this->output($a);
    }

    private function protocol() {
        return 'http' . ((!empty($this->settings['use_ssl'])) ? 's' : '') . '://';
    }

    private function logError($str) {
        $this->error[] = (string) $str;
    }

    public function getError() {
        return $this->error;
    }

    private function output($a = array()) {

        if ($a && is_string($a)) {
            $this->debug_str = $a;
        }

        if ($this->debug_str && stristr($this->debug_str, 'unauthorized')) {
            $this->logError('Your login or password is invalid. Not authenticated!');
        }

        // compile output format to (array)
        if ($this->settings['output_compile']) {
            $a = ($this->settings['output_format'] == 1 && $a && ($b = json_decode($a)) && json_last_error() == JSON_ERROR_NONE) ? $b : (($this->settings['output_format'] == 2) ? current((array) simplexml_load_string($a)) : array());
        }

        // return
        return $a;
    }

    private function validWs() {

        $url_open = ini_get('allow_url_fopen');

        // check dependencies
        if (!stristr($url_open, 'On') && !stristr($url_open, '1')) {

            $this->logError('Required allow_url_fopen enabled in your PHP settings (php.ini)');

            // return
            return false;
        }

        // pingback
        ob_start();
        $a = @get_headers($this->sdl);
        $b = ob_get_contents();
        ob_end_clean();

        $sign = (is_array($a)) ? (array) explode(':', current((array) preg_grep('/Server-Signature/i', $a))) : array();

        // send status
        return key_exists(0, $sign) && !strstr($a[0], '404') && trim(end($sign)) === self::WS_SIGNATURE;
    }

    private function validCurl($cr, &$a) {

        if (curl_errno($cr)) {
            $this->logError(curl_error($cr));
        } elseif ($a && $this->settings['gzip'] === true && function_exists('gzdecode') && stristr(mb_detect_encoding($a), 'utf')) {
            $a = gzdecode($a);
        }
    }

    protected function curl_exec_follow($ch, $maxredirect = 1) {

        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            $newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            $rch = curl_copy_handle($ch);

            curl_setopt($rch, CURLOPT_HEADER, true);
            curl_setopt($rch, CURLOPT_NOBODY, true);
            curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);

            do {
                curl_setopt($rch, CURLOPT_URL, $newurl);
                $header = curl_exec($rch);
                if (curl_errno($rch)) {
                    $code = 0;
                } else {
                    $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                    if ($code == 301 || $code == 302) {
                        preg_match('/Location:(.*?)\n/', $header, $matches);
                        $newurl = trim(array_pop($matches));
                    } else {
                        $code = 0;
                    }
                }
            } while ($code && $maxredirect--);
            curl_close($rch);
            curl_setopt($ch, CURLOPT_URL, $newurl);
        }
        return curl_exec($ch);
    }

}
