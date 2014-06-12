<?php

/**
 * connection class
 *
 * This class communicates with an adobe connect server making REST calls
 * to access the Adobe Connect API
 */

/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class colibriv2_connect_class {
    var $_serverurl;
    var $_serverport;
    var $_username;
    var $_password;
    var $_cookie;
    var $_xmlrequest;
    var $_xmlresponse;
    var $_apicall;
    var $_connection;
    var $_https;
    var $_headersresponse;

    public function __construct($serverurl,
                                $username = '', $password = '',
                                $cookie = '') {

        $result = parse_url($serverurl);

        $this->_serverurl = $serverurl;
        
        if (isset($result["port"])) {
          $this->_serverport = $result["port"];
        }

        if (isset($result["scheme"])) {
          $this->_https = ($result["scheme"] == "https");
        }
        
        $this->_username = $username;
        $this->_password = $password;
        $this->_cookie = $cookie;
        $this->_https = $https;
    }

    /**
     * Accessor methods
     */
    public function set_serverport($serverport = '') {
        $this->_serverport = $serverport;
    }

    public function set_serverurl($serverurl = '') {
        $this->_serverurl = $serverurl;
    }

    public function set_username($username = '') {
        $this->_username = $username;
    }

    public function set_password($password = '') {
        $this->_password = $password;
    }

    public function set_cookie($cookie = '') {
        $this->_cookie = $cookie;
    }

    public function set_xmlrequest($xml = '') {
        $this->_xmlrequest = $xml;
    }

    public function set_connection($connection = 0) {
        $this->_connection = $connection;
    }

    public function set_https($https = false) {
        $this->_https = $https;
    }

    public function get_serverurl() {
        return $this->_serverurl;
    }

    public function get_username() {
        return $this->_username;
    }

    public function get_password() {
        return $this->_password;
    }

    public function get_cookie() {
        return $this->_cookie;
    }

    public function get_connection() {
        return $this->_connection;
    }

    public function get_serverport() {
        return $this->_serverport;
    }

    private function get_default_header() {
        return array('Content-Type: text/xml');
    }

    public function get_https() {
        return $this->_https;
    }

    /**
     * Adds or replaces http:// with https:// for secured connections
     * @return string - server URL with the HTTPS protocol
     */
    private function make_https() {

        $serverurl = $this->_serverurl;
        $httpsexists = strpos($this->_serverurl, 'https://');
        $httpexists = strpos($this->_serverurl, 'http://');

        if (false === $httpsexists and false !== $httpexists) {
            $serverurl = str_replace('http://', 'https://', $this->_serverurl);
        } elseif (false === $httpsexists) {
            $serverurl = 'https://' . $this->_serverurl;
        }

        return $serverurl;
    }


    /**
     * Posts XML to the Adobe Connect server and returns the results
     * @param int $return_header 1 to include the response header, 0 to not
     * @param array $add_header an array of headers to add to the request
     */
    public function send_request($return_header = 0, $add_header = array(), $stop = false) {
        global $CFG;

        $ch = curl_init();

        $serverurl = $this->_serverurl;

        if ($this->_https) {

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            // $serverurl = $this->make_https();
        }


        if ($stop) {
//            echo $this->_serverurl . '?session='. $this->_cookie; die();
//            https://example.com/api/xml?action=principal=list
            curl_setopt($ch, CURLOPT_URL, $serverurl/* . '?action=login&external-auth=use'*/);

        } else {

            $querystring = (!empty($this->_cookie)) ?  '?session='. $this->_cookie : '';
            curl_setopt($ch, CURLOPT_URL, $serverurl . $querystring);

        }


       // Connect through a proxy if Moodle config says we should
       if(isset($CFG->proxyhost)) {

           curl_setopt($ch, CURLOPT_PROXY, $CFG->proxyhost);

           if(isset($CFG->proxyport)) {

               curl_setopt($ch, CURLOPT_PROXYPORT, $CFG->proxyport);
           }
       }
       
       // RR
       $sentcookie = $this->_cookie;
       if (isset($this->_cookie)) {         
         curl_setopt($ch, CURLOPT_COOKIE, "BREEZESESSION=" . $this->_cookie . ";HttpOnly;domain=.colibriv2.com;path=/");
       }
       // -RR

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_xmlrequest);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

        curl_setopt($ch, CURLOPT_PORT, $this->_serverport);

        $header = $this->get_default_header();
        $header = array_merge($header, $add_header);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        // Include header from response
        
        curl_setopt($ch, CURLOPT_HEADER, TRUE);

        $result = curl_exec($ch);

        // RR
        
        $headers_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->_headersresponse = substr($result, 0, $headers_size);          
        $this->update_cookie();
        
        if (!$return_header) {
          $result = substr($result, $headers_size);
        }

        /*
        print "<pre>";
        var_dump(
          array("request" => 
            array("cookie" => $sentcookie,
                  "post" => htmlentities($this->_xmlrequest))
                ),                    
          array("response" =>     
            array("cookie" => $this->_cookie,
                  "headers" => $this->_headersresponse,
                  "result" => htmlentities($result)
                  )
                )
              );
        print "</pre>";
        */

        // -RR
        
        curl_close($ch);

        return $result;
    }

    // RR    
    public function get_headersresponse()
	  { 
	    return $this->_headersresponse;
	  }
  
    public function update_cookie()
	  {
  //    print("UPDATE COOKIE!");
      
	    $headers = explode("\r\n", $this->get_headersresponse());
      
	    foreach($headers as $header) {
		    if (preg_match("/Cookie: BREEZESESSION=([^;]+);/", $header, $cookie)) {
			    // Set cookie
  //        print("NEW COOKIE!");
			    $this->_cookie = $cookie[1];			  
		    }
      }
	    return $this->_cookie;
	  }
    // -RR
    
    /**
     * Sends the HTTP header login request and returns the response xml
     * @param string username username to use for header x-user-id
     */
    public function request_http_header_login($return_header = 0, $username = '', $stop = false) {
        global $CFG;

        $hearder = array();
        $this->create_http_head_login_xml();

        // The first parameter is 1 because we want to include the response header
        // to extract the session cookie
        if (!empty($username)) {
            $header = array("$CFG->colibriv2_admin_httpauth: " . $username);
            // RR
            $return_header = 1;
            // -RR
        }

        $this->_xmlresponse = $this->send_request($return_header, $header, $stop);

        // RR
        $this->set_session_cookie($this->_headersresponse);
        // -RR

        return $this->_xmlresponse;
    }


    public function create_request($params = array(), $sentrequest = true) {
        if (empty($params)) {
            return false;
        }
        @date_default_timezone_set("GMT");

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('params');

        foreach($params as $key => $data) {
            $writer->startElement('param');
            $writer->writeAttribute('name', $key);
            $writer->text($data);
            $writer->endElement();
        }

        $writer->endElement();

        $writer->endDocument();

        $this->_xmlrequest = $writer->outputMemory();

        if ($sentrequest) {
            $this->_xmlresponse = $this->send_request();
        }

    }

    /**
     * Call to common-info
     * @param nothing
     * @return nothing
     */
    public function set_session_cookie($data) {
        $sessionval = false;
        $sessionstart = strpos($data, 'BREEZESESSION=');

        if (false !== $sessionstart) {
            $sessionend = strpos($data, ';');
//            $sessionend = strpos($data, 'Expires:');

            $sessionlength = strlen('BREEZESESSION=');
            $sessionvallength = $sessionend - ($sessionstart + $sessionlength);
            $sessionval = substr($data, $sessionstart+$sessionlength, $sessionvallength);
        }

        $this->_cookie = $sessionval;

        return $sessionval;
    }

    /**
     * Parses through xml and looks for the 'status' parameter
     * and return true if the 'code' attribute equals 'ok' otherwise
     * false is returned
     * @param string xml the xml to parse
     */
    public function read_status_xml() {
        $reader = new XMLReader();
        $reader->XML($this->_xmlresponse, 'UTF-8');
        $return = false;

        while ($reader->read()) {
            if (0 == strcmp($reader->name, 'status')) {
                if (1 == $reader->nodeType) {
                    if (0 == strcmp('ok', $reader->getAttribute('code'))) {
                        $return = true;
                    }
                }
            }
        }

        $reader->close();

         return $return;

    }

    /**
     * Parses through xml and looks for the 'cookie' parameter
     * @param string $xml the xml to parse through
     * @return string $sessoin returns the session id
     */
    public function read_cookie_xml($xml = '') {
            global $CFG, $USER, $COURSE;

            if (empty($xml)) {
                if (is_siteadmin($USER->id)) {
                    notice(get_string('adminemptyxml', 'colibriv2'),
                           $CFG->wwwroot . '/admin/settings.php?section=modsettingcolibriv2');
                } else {
                    notice(get_string('emptyxml', 'colibriv2'),
                           '', $COURSE);
                }
            }

            $session = false;
//            $accountid = false;
            $reader = new XMLReader();
            $reader->XML($xml, 'UTF-8');

            while ($reader->read()) {
	            if (0 == strcmp($reader->name, 'cookie')) {
                    if (1 == $reader->nodeType) {
                        $session = $reader->readString();
                    }
                }
            }

            $reader->close();

            $this->_cookie = $session;

            return $session;
    }

    public function response_to_object() {
        $xml = new SimpleXMLElement($this->_xmlresponse);

        return $xml;
    }

    public function call_success() {
        global $CFG, $USER, $COURSE;

        if (empty($this->_xmlresponse)) {
            if (is_siteadmin($USER->id)) {
                notice(get_string('adminemptyxml', 'colibriv2'),
                       $CFG->wwwroot . '/admin/settings.php?section=modsettingcolibriv2');
            } else {
                notice(get_string('emptyxml', 'colibriv2'),
                       '', $COURSE);
            }
        }

        $xml = new SimpleXMLElement($this->_xmlresponse);

        if (0 == strcmp('ok', $xml->status[0]['code'])) {
            return true;
        } else {
            return false;
        }
    }

    private function create_http_head_login_xml() {
        @date_default_timezone_set("GMT");

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('params');

        $writer->startElement('param');
        $writer->writeAttribute('name', 'action');
        $writer->text('login');
        $writer->endElement();

        $writer->startElement('param');
        $writer->writeAttribute('name', 'external-auth');
        $writer->text('use');
        $writer->endElement();

        $writer->endElement();

        $writer->endDocument();

        $this->_xmlrequest = $writer->outputMemory();

    }
}
