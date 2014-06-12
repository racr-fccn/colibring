<?php

/**
 * @package mod
 * @subpackage colibriv2
 * @author Akinsaya Delamarre (adelamarre@remote-learner.net)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('connect_class.php');
require_once('connect_class_dom.php');

define('COLIBRIV2_VIEW_ROLE', 'view');
define('COLIBRIV2_HOST_ROLE', 'host');
define('COLIBRIV2_MINIADMIN_ROLE', 'mini-host');
define('COLIBRIV2_REMOVE_ROLE', 'remove');

define('COLIBRIV2_PARTICIPANT', 1);
define('COLIBRIV2_PRESENTER', 2);
define('COLIBRIV2_REMOVE', 3);
define('COLIBRIV2_HOST', 4);

define('COLIBRIV2_TEMPLATE_POSTFIX', '- Template');
define('COLIBRIV2_MEETING_POSTFIX', '- Meeting');

define('COLIBRIV2_MEETPERM_PUBLIC', 0); //means the Acrobat Connect meeting is public, and anyone who has the URL for the meeting can enter the room.
define('COLIBRIV2_MEETPERM_PROTECTED', 1); //means the meeting is protected, and only registered users and accepted guests can enter the room.
define('COLIBRIV2_MEETPERM_PRIVATE', 2); // means the meeting is private, and only registered users and participants can enter the room

define('COLIBRIV2_TMZ_LENGTH', 6);

function colibriv2_connection_test($apiurl = '', $username = '',
                               $password = '', $prefix = '') {

    if (empty($apiurl) or
        empty($username) or
        empty($password) or
        empty($prefix)) {

        // TO-DO: Translate!
        echo "</p>One of the required parameters is blank or incorrect: <br />".
             "Host: $apiurl<br /> Username: $username<br /> Password: $password".
             "<br /> Prefix: $prefix</p>";

        die();
    }

    $messages = array();

    $_colibriv2DOM = new colibriv2_connect_class_dom($apiurl,
                                           $username,
                                           $password,
                                           '');

    $params = array(
        'action' => 'common-info'
    );

    // Send common-info call to obtain the session key
    echo '<p>Sending common-info call:</p>';
    $_colibriv2DOM->create_request($params);

    if (!empty($_colibriv2DOM->_xmlresponse)) {

        // Get the session key from the XML response
        $_colibriv2DOM->read_cookie_xml($_colibriv2DOM->_xmlresponse);

        $cookie = $_colibriv2DOM->get_cookie();
        if (empty($cookie)) {

            echo '<p>unable to obtain session key from common-info call</p>';
            echo '<p>xmlrequest:</p>';
            $doc = new DOMDocument();

            if ($doc->loadXML($_colibriv2DOM->_xmlrequest)) {
                echo '<p>' . htmlspecialchars($doc->saveXML()) . '</p>';
            } else {
                echo '<p>unable to display the XML request</p>';
            }

            echo '<p>xmlresponse:</p>';
            $doc = new DOMDocument();

            if ($doc->loadXML($_colibriv2DOM->_xmlresponse)) {
                echo '<p>' . htmlspecialchars($doc->saveHTML()) . '</p>';
            } else {
                echo '<p>unable to display the XML response</p>';
            }

        } else {

            // print success
            echo '<p style="color:#006633">successfully obtained the session key: ' . $_colibriv2DOM->get_cookie() . '</p>';

            // test logging in as the administrator
            $params = array(
                  'action' => 'login',
                  'login' => $_colibriv2DOM->get_username(),
                  'password' => $_colibriv2DOM->get_password(),
            );

            $_colibriv2DOM->create_request($params);

            // RR
            echo '<p style="color:#006633">current session key: ' . $_colibriv2DOM->get_cookie() . '</p>';
            // RR
            
            if ($_colibriv2DOM->call_success()) {
                echo '<p style="color:#006633">successfully logged in as admin user</p>';
                //$username

                //Test retrevial of folders
                echo '<p>Testing retrevial of shared content, recording and meeting folders:</p>';
                $folderscoid = _colibriv2_get_folder($_colibriv2DOM, 'content');

                if ($folderscoid) {
                    echo '<p style="color:#006633">successfully obtained shared content folder scoid: '. $folderscoid . '</p>';
                } else {

                    echo '<p>error obtaining shared content folder</p>';
                    echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                    echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';

                }

                $folderscoid = _colibriv2_get_folder($_colibriv2DOM, 'forced-archives');

                if ($folderscoid) {
                    echo '<p style="color:#006633">successfully obtained forced-archives (meeting recordings) folder scoid: '. $folderscoid . '</p>';
                } else {

                    echo '<p>error obtaining forced-archives (meeting recordings) folder</p>';
                    echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                    echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';

                }

                $folderscoid = _colibriv2_get_folder($_colibriv2DOM, 'meetings');

                if ($folderscoid) {
                    echo '<p style="color:#006633">successfully obtained meetings folder scoid: '. $folderscoid . '</p>';
                } else {

                    echo '<p>error obtaining meetings folder</p>';
                    echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                    echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';

                }

                //Test creating a meeting
                $folderscoid = _colibriv2_get_folder($_colibriv2DOM, 'meetings');

                $meeting = new stdClass();
                $meeting->name = 'testmeetingtest';
                $time = time();
                $meeting->starttime = $time;
                $time = $time + (60 * 60);
                $meeting->endtime = $time;

                if (($meetingscoid = _colibriv2_create_meeting($_colibriv2DOM, $meeting, $folderscoid))) {
                    echo '<p style="color:#006633">successfully created meeting <b>testmeetingtest</b> scoid: '. $meetingscoid . '</p>';
                } else {

                    echo '<p>error creating meeting <b>testmeetingtest</b> folder</p>';
                    echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                    echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';
                }

                //Test creating a user
                $user = new stdClass();
                $user->username = 'testusertest';
                $user->firstname = 'testusertest';
                $user->lastname = 'testusertest';
                $user->email = 'testusertest@test.com';

                if (!empty($emaillogin)) {
                    $user->username = $user->email;
                }

                $skipdeletetest = false;

                if (!($usrprincipal = _colibriv2_user_exists($_colibriv2DOM, $user))) {
                      $usrprincipal = _colibriv2_create_user($_colibriv2DOM, $user);
                    if ($usrprincipal) {
                        echo '<p style="color:#006633">successfully created user <b>testusertest</b> principal-id: '. $usrprincipal . '</p>';
                    } else {
                        echo '<p>error creating user  <b>testusertest</b></p>';
                        echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                        echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';

                        _colibriv2_logout($_colibriv2DOM);
                        die();
                    }
                } else {

                    echo '<p>user <b>testusertest</b> already exists skipping delete user test</p>';
                    $skipdeletetest = true;
                }

                //Test assigning a user a role to the meeting
                if (_colibriv2_check_user_perm($_colibriv2DOM, $usrprincipal, $meetingscoid, COLIBRIV2_PRESENTER, true)) {
                    echo '<p style="color:#006633">successfully assigned user <b>testusertest</b>'.
                         ' presenter role in meeting <b>testmeetingtest</b>: '. $usrprincipal . '</p>';
                } else {
                        echo '<p>error assigning user <b>testusertest</b> presenter role in meeting <b>testmeetingtest</b></p>';
                        echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                        echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';
                }

                //Test removing role from meeting
                if (_colibriv2_check_user_perm($_colibriv2DOM, $usrprincipal, $meetingscoid, COLIBRIV2_REMOVE_ROLE, true)) {
                    echo '<p style="color:#006633">successfully removed presenter role for user <b>testusertest</b>'.
                         ' in meeting <b>testmeetingtest</b>: '. $usrprincipal . '</p>';
                } else {
                        echo '<p>error remove presenter role for user <b>testusertest</b> in meeting <b>testmeetingtest</b></p>';
                        echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                        echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';
                }

                //Test removing user from server
                if (!$skipdeletetest) {
                    if (_colibriv2_delete_user($_colibriv2DOM, $usrprincipal)) {
                        echo '<p style="color:#006633">successfully removed user <b>testusertest</b> principal-id: '. $usrprincipal . '</p>';
                    } else {
                        echo '<p>error removing user <b>testusertest</b></p>';
                        echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                        echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';
                    }
                }

                //Test removing meeting from server
                if ($meetingscoid) {
                    if (_colibriv2_remove_meeting($_colibriv2DOM, $meetingscoid)) {
                        echo '<p style="color:#006633">successfully removed meeting <b>testmeetingtest</b> scoid: '. $meetingscoid . '</p>';
                    } else {
                        echo '<p>error removing meeting <b>testmeetingtest</b> folder</p>';
                        echo '<p style="color:#680000">XML request:<br />'. htmlspecialchars($_colibriv2DOM->_xmlrequest). '</p>';
                        echo '<p style="color:#680000">XML response:<br />'. htmlspecialchars($_colibriv2DOM->_xmlresponse). '</p>';
                    }
                }


            } else {
                echo '<p style="color:#680000">logging in as '. $username . ' was not successful, check to see if the username and password are correct </p>';
            }

       }

    } else {
        echo '<p style="color:#680000">common-info API call returned an empty document.  Please check your settings and try again </p>';
    }

    _colibriv2_logout($_colibriv2DOM);

}

/**
 * Returns the folder sco-id
 * @param object an adobe connection_class object
 * @param string $folder name of the folder to get
 * (ex. forced-archives = recording folder | meetings = meetings folder
 * | content = shared content folder)
 * @return mixed adobe connect folder sco-id || false if there was an error
 *
 */
function _colibriv2_get_folder($_colibriv2, $folder = '') {
    $folderscoid = false;
    $params = array('action' => 'sco-shortcuts');

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        $folderscoid = _colibriv2_get_folder_sco_id($_colibriv2->_xmlresponse, $folder);
//        $params = array('action' => 'sco-contents', 'sco-id' => $folderscoid);
    }

    return $folderscoid;
}

/**
 * TODO: comment function and return something meaningful
 */
function _colibriv2_get_folder_sco_id($xml, $folder) {
    $scoid = false;

    $dom = new DomDocument();
    $dom->loadXML($xml);

    $domnodelist = $dom->getElementsByTagName('sco');

    if (!empty($domnodelist->length)) {

        for ($i = 0; $i < $domnodelist->length; $i++) {

            $domnode = $domnodelist->item($i)->attributes->getNamedItem('type');

            if (!is_null($domnode)) {

                if (0 == strcmp($folder, $domnode->nodeValue)) {
                    $domnode = $domnodelist->item($i)->attributes->getNamedItem('sco-id');

                    if (!is_null($domnode)) {
                        $scoid = (int) $domnode->nodeValue;

                    }
                }
            }
        }
    }

    return $scoid;

}

/**
 * Log in as the admin user.  This should only be used to conduct API calls.
 */
function _colibriv2_login() {
    global $CFG, $USER, $COURSE;

    if (!isset($CFG->colibriv2_host) or
        !isset($CFG->colibriv2_admin_login) or
        !isset($CFG->colibriv2_admin_password)) {
            if (is_siteadmin($USER->id)) {
                notice(get_string('adminnotsetupproperty', 'colibriv2'),
                       $CFG->wwwroot . '/admin/settings.php?section=modsettingcolibriv2');
            } else {
                notice(get_string('notsetupproperty', 'colibriv2'),
                       '', $COURSE);
            }
    }

    if (isset($CFG->colibriv2_port) and
        !empty($CFG->colibriv2_port) and
        ((80 != $CFG->colibriv2_port) and (0 != $CFG->colibriv2_port))) {
        $port = $CFG->colibriv2_port;
    } else {
        $port = 80;
    }

    $https = false;

    if (isset($CFG->colibriv2_https) and (!empty($CFG->colibriv2_https))) {
        $https = true;
    }


    $_colibriv2 = new connect_class_dom($CFG->colibriv2_host,
                                      $CFG->colibriv2_port,
                                      $CFG->colibriv2_admin_login,
                                      $CFG->colibriv2_admin_password,
                                      '',
                                      $https);

    $params = array(
        'action' => 'common-info'
    );

    $_colibriv2->create_request($params);

    $_colibriv2->read_cookie_xml($_colibriv2->_xmlresponse);

    $params = array(
          'action' => 'login',
          'login' => $_colibriv2->get_username(),
          'password' => $_colibriv2->get_password(),
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        $_colibriv2->set_connection(1);
    } else {
        $_colibriv2->set_connection(0);
    }

    return $_colibriv2;
}


/**
 * Logout
 * @param object $_colibriv2 - connection object
 * @return true on success else false
 */
function _colibriv2_logout(&$_colibriv2) {
    if (!$_colibriv2->get_connection()) {
        return true;
    }

    $params = array('action' => 'logout');
    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        $_colibriv2->set_connection(0);
        return true;
    } else {
        $_colibriv2->set_connection(1);
        return false;
    }
}

/**
 * Calls all operations needed to retrieve and return all
 * templates defined in the shared templates folder and meetings
 * @param object $_colibriv2 connection object
 * @return array $templates an array of templates
 */
function _colibriv2_get_templates_meetings($_colibriv2) {
    $templates = array();
    $meetings = array();
    $meetfldscoid = false;
    $tempfldscoid = false;

    $params = array(
        'action' => 'sco-shortcuts',
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        // Get shared templates folder sco-id
        $tempfldscoid = _colibriv2_get_shared_templates($_colibriv2->_xmlresponse);
    }

    if (false !== $tempfldscoid) {
        $params = array(
            'action' => 'sco-expanded-contents',
            'sco-id' => $tempfldscoid,
        );

        $_colibriv2->create_request($params);

        if ($_colibriv2->call_success()) {
            $templates = _colibriv2_return_all_templates($_colibriv2->_xmlresponse);
        }
    }

//    if (false !== $meetfldscoid) {
//        $params = array(
//            'action' => 'sco-expanded-contents',
//            'sco-id' => $meetfldscoid,
//            'filter-type' => 'meeting',
//        );
//
//        $_colibriv2->create_request($params);
//
//        if ($_colibriv2->call_success()) {
//            $meetings = _colibriv2_return_all_meetings($_colibriv2->_xmlresponse);
//        }
//
//    }

    return $templates + $meetings;
}

/**
 * Parse XML looking for shared-meeting-templates attribute
 * and returning the sco-id of the folder
 * @param string $xml returned XML from a sco-shortcuts call
 * @return mixed sco-id if found or false if not found or error
 */
function _colibriv2_get_shared_templates($xml) {
    $scoid = false;

    $dom = new DomDocument();
    $dom->loadXML($xml);

    $domnodelist = $dom->getElementsByTagName('shortcuts');

    if (!empty($domnodelist->length)) {

//        for ($i = 0; $i < $domnodelist->length; $i++) {

            $innerlist = $domnodelist->item(0)->getElementsByTagName('sco');

            if (!empty($innerlist->length)) {

                for ($x = 0; $x < $innerlist->length; $x++) {

                    if ($innerlist->item($x)->hasAttributes()) {

                        $domnode = $innerlist->item($x)->attributes->getNamedItem('type');

                        if (!is_null($domnode)) {

                            if (0 == strcmp('shared-meeting-templates', $domnode->nodeValue)) {
                                $domnode = $innerlist->item($x)->attributes->getNamedItem('sco-id');

                                if (!is_null($domnode)) {
                                    $scoid = (int) $domnode->nodeValue;
                                }
                            }
                        }
                    }
                }
            }
//        }

    }

    return $scoid;
}

function _colibriv2_return_all_meetings($xml) {
    $meetings = array();
    $xml = new SimpleXMLElement($xml);

    if (empty($xml)) {
        return $meetings;
    }

    foreach($xml->{'expanded-scos'}[0]->sco as $key => $sco) {
        if (0 == strcmp('meeting', $sco['type'])) {
            $mkey = (int) $sco['sco-id'];
            $meetings[$mkey] = (string) current($sco->name) .' '. COLIBRIV2_MEETING_POSTFIX;
        }
    }

    return $meetings;
}

/**
 * Parses XML for meeting templates and returns an array
 * with sco-id as the key and template name as the value
 * @param strimg $xml XML returned from a sco-expanded-contents call
 * @return array of templates sco-id -> key, name -> value
 */
function _colibriv2_return_all_templates($xml) {
    $templates = array();

    $dom = new DomDocument();
    $dom->loadXML($xml);

    $domnodelist = $dom->getElementsByTagName('expanded-scos');

    if (!empty($domnodelist->length)) {

        $innerlist = $domnodelist->item(0)->getElementsByTagName('sco');

        if (!empty($innerlist->length)) {

            for ($i = 0; $i < $innerlist->length; $i++) {

                if ($innerlist->item($i)->hasAttributes()) {
                    $domnode = $innerlist->item($i)->attributes->getNamedItem('type');

                    if (!is_null($domnode) and 0 == strcmp('meeting', $domnode->nodeValue)) {
                        $domnode = $innerlist->item($i)->attributes->getNamedItem('sco-id');

                        if (!is_null($domnode)) {
                            $tkey = (int) $domnode->nodeValue;
                            $namelistnode = $innerlist->item($i)->getElementsByTagName('name');

                            if (!is_null($namelistnode)) {
                                $name = $namelistnode->item(0)->nodeValue;
                                $templates[$tkey] = (string) $name .' ' . COLIBRIV2_TEMPLATE_POSTFIX;
                            }
                        }
                    }
                }
            }
        }
    }

    return $templates;
}

/**
 * Returns information about all recordings that belong to a specific
 * meeting sco-id
 *
 * @param obj $_colibriv2 a connect_class object
 * @param int $folderscoid the recordings folder sco-id
 * @param int $sourcescoid the meeting sco-id
 *
 * @return mixed array an array of object with the recording sco-id
 * as the key and the recording properties as properties
 */
function _colibriv2_get_recordings($_colibriv2, $folderscoid, $sourcescoid) {
    $params = array('action' => 'sco-contents',
                    'sco-id' => $folderscoid,
                    //'filter-source-sco-id' => $sourcescoid,
                    'sort-name' => 'asc',
                    );

    // Check if meeting scoid and folder scoid are the same
    // If hey are the same then that means that forced recordings is not
    // enabled filter-source-sco-id should not be included.  If the
    // meeting scoid and folder scoid are not equal then forced recordings
    // are enabled and we can use filter by filter-source-sco-id
    // Thanks to A. gtdino
    if ($sourcescoid != $folderscoid) {
        $params['filter-source-sco-id'] = $sourcescoid;
    }

    $_colibriv2->create_request($params);

    $recordings = array();

    if ($_colibriv2->call_success()) {
        $dom = new DomDocument();
        $dom->loadXML($_colibriv2->_xmlresponse);

        $domnodelist = $dom->getElementsByTagName('scos');

        if (!empty($domnodelist->length)) {

//            for ($i = 0; $i < $domnodelist->length; $i++) {

                $innernodelist = $domnodelist->item(0)->getElementsByTagName('sco');

                if (!empty($innernodelist->length)) {

                    for ($x = 0; $x < $innernodelist->length; $x++) {

                        if ($innernodelist->item($x)->hasAttributes()) {

                            $domnode = $innernodelist->item($x)->attributes->getNamedItem('sco-id');

                            if (!is_null($domnode)) {
                                $meetingdetail = $innernodelist->item($x);

                                // Check if the SCO item is a recording or uploaded document.  We only want to display recordings
                                if (!is_null($meetingdetail->getElementsByTagName('duration')->item(0))) {

                                    $j = (int) $domnode->nodeValue;
                                    $value = (!is_null($meetingdetail->getElementsByTagName('name'))) ?
                                             $meetingdetail->getElementsByTagName('name')->item(0)->nodeValue : '';

                                    $recordings[$j]->name = (string) $value;

                                    $value = (!is_null($meetingdetail->getElementsByTagName('url-path'))) ?
                                             $meetingdetail->getElementsByTagName('url-path')->item(0)->nodeValue : '';

                                    $recordings[$j]->url = (string) $value;

                                    $value = (!is_null($meetingdetail->getElementsByTagName('date-begin'))) ?
                                             $meetingdetail->getElementsByTagName('date-begin')->item(0)->nodeValue : '';

                                    $recordings[$j]->startdate = (string) $value;

                                    $value = (!is_null($meetingdetail->getElementsByTagName('date-end'))) ?
                                             $meetingdetail->getElementsByTagName('date-end')->item(0)->nodeValue : '';

                                    $recordings[$j]->enddate = (string) $value;

                                    $value = (!is_null($meetingdetail->getElementsByTagName('date-created'))) ?
                                             $meetingdetail->getElementsByTagName('date-created')->item(0)->nodeValue : '';

                                    $recordings[$j]->createdate = (string) $value;

                                    $value = (!is_null($meetingdetail->getElementsByTagName('date-modified'))) ?
                                             $meetingdetail->getElementsByTagName('date-modified')->item(0)->nodeValue : '';

                                    $recordings[$j]->modified = (string) $value;

                                    $value = (!is_null($meetingdetail->getElementsByTagName('duration'))) ?
                                             $meetingdetail->getElementsByTagName('duration')->item(0)->nodeValue : '';

                                    $recordings[$j]->duration = (string) $value;
                                    
                                    $recordings[$j]->sourcesco = (int) $sourcescoid;
                                }

                            }
                        }
                    }
                }
//            }

            return $recordings;
        } else {
            return false;
        }
    } else {
        return false;
    }

}

/**
 * Parses XML and returns the meeting sco-id
 * @param string XML obtained from a sco-update call
 */
function _colibriv2_get_meeting_scoid($xml) {
    $scoid = false;

    $dom = new DomDocument();
    $dom->loadXML($xml);

    $domnodelist = $dom->getElementsByTagName('sco');

    if (!empty($domnodelist->length)) {
        if ($domnodelist->item(0)->hasAttributes()) {
            $domnode = $domnodelist->item(0)->attributes->getNamedItem('sco-id');

            if (!is_null($domnode)) {
                $scoid = (int) $domnode->nodeValue;
            }
        }
    }

    return $scoid;
}

/**
 * Update meeting
 * @param obj $_colibriv2 connect_class object
 * @param obj $meetingobj an colibriv2 module object
 * @param int $meetingfdl adobe connect meeting folder sco-id
 * @return bool true if call was successful else false
 */
function _colibriv2_update_meeting($_colibriv2, $meetingobj, $meetingfdl) {
    $params = array('action' => 'sco-update',
                    'sco-id' => $meetingobj->scoid,
                    'name' => htmlentities($meetingobj->name),
                    'folder-id' => $meetingfdl,
// updating meeting URL using the API corrupts the meeting for some reason
//                    'url-path' => '/'.$meetingobj->meeturl,
                    'date-begin' => $meetingobj->starttime,
                    'date-end' => $meetingobj->endtime,
                    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        return true;
    } else {
        return false;
    }

}

/**
 * Update a meeting's access permissions
 * @param obj $_colibriv2 connect_class object
 * @param int $meetingscoid meeting sco-id
 * @param int $perm meeting permission id
 * @return bool true if call was successful else false
 */
function _colibriv2_update_meeting_perm($_colibriv2, $meetingscoid, $perm) {
     $params = array('action' => 'permissions-update',
                     'acl-id' => $meetingscoid,
                     'principal-id' => 'public-access',
                    );

     switch ($perm) {
         case COLIBRIV2_MEETPERM_PUBLIC:
            $params['permission-id'] = 'view-hidden';
            break;
         case COLIBRIV2_MEETPERM_PROTECTED:
            $params['permission-id'] = 'remove';
            break;
         case COLIBRIV2_MEETPERM_PRIVATE:
         default:
            $params['permission-id'] = 'denied';
            break;
     }

     $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        return true;
    } else {
        return false;
    }


 }

/** CONTRIB-1976, CONTRIB-1992
 * This function adds a fraction of a second to the ISO 8601 date
 * @param int $time unix timestamp
 * @return mixed a string (ISO 8601) containing the decimal fraction of a second
 * or false if it was not able to determine where to put it
 */
function _colibriv2_format_date_seconds($time) {

    $newdate = false;
    $date = date("c", $time);

    $pos = strrpos($date, '-');
    $length = strlen($date);

    $diff = $length - $pos;

    if ((0 < $diff) and (COLIBRIV2_TMZ_LENGTH == $diff)) {
        $firstpart = substr($date, 0, $pos);
        $lastpart = substr($date, $pos);
        $newdate = $firstpart . '.000' . $lastpart;

        return $newdate;
    }

    $pos = strrpos($date, '+');
    $length = strlen($date);

    $diff = $length - $pos;

    if ((0 < $diff) and (COLIBRIV2_TMZ_LENGTH == $diff)) {
        $firstpart = substr($date, 0, $pos);
        $lastpart = substr($date, $pos);
        $newdate = $firstpart . '.000' . $lastpart;

        return $newdate;

    }

    return false;
}

/**
 * Creates a meeting
 * @param obj $_colibriv2 connect_class object
 * @param obj $meetingobj an colibriv2 module object
 * @param int $meetingfdl adobe connect meeting folder sco-id
 * @return mixed meeting sco-id on success || false on error
 */
function _colibriv2_create_meeting($_colibriv2, $meetingobj, $meetingfdl) {
    //date("Y-m-d\TH:i

    $starttime = _colibriv2_format_date_seconds($meetingobj->starttime);
    $endtime = _colibriv2_format_date_seconds($meetingobj->endtime);

    if (empty($starttime) or empty($endtime)) {
        $message = 'Failure (_colibriv2_find_timezone) in finding the +/- sign in the date timezone'.
                    "\n".date("c", $meetingobj->starttime)."\n".date("c", $meetingobj->endtime);
        debugging($message, DEBUG_DEVELOPER);
        return false;
    }

    $params = array('action' => 'sco-update',
                    'type' => 'meeting',
                    'name' => htmlentities($meetingobj->name),
                    'folder-id' => $meetingfdl,
                    'date-begin' => $starttime,
                    'date-end' => $endtime,
                    );

    if (!empty($meetingobj->meeturl)) {
        $params['url-path'] = $meetingobj->meeturl;
    }

    if (!empty($meetingobj->templatescoid)) {
        $params['source-sco-id'] = $meetingobj->templatescoid;
    }

    $_colibriv2->create_request($params);


    if ($_colibriv2->call_success()) {
        return _colibriv2_get_meeting_scoid($_colibriv2->_xmlresponse);
    } else {
        return false;
    }
}

/**
 * Finds a matching meeting sco-id
 * @param object $_colibriv2 a connect_class object
 * @param int $meetfldscoid Meeting folder sco-id
 * @param array $filter array key is the filter and array value is the value
 * (ex. array('filter-name' => 'meeting101'))
 * @return mixed array of objects with sco-id as key and meeting name and url as object
 * properties as value || false if not found or error occured
 */
function _colibriv2_meeting_exists($_colibriv2, $meetfldscoid, $filter = array()) {
    $matches = array();

    $params = array(
        'action' => 'sco-contents',
        'sco-id' => $meetfldscoid,
        'filter-type' => 'meeting',
    );

    if (empty($filter)) {
        return false;
    }

    $params = array_merge($params, $filter);
    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        $dom = new DomDocument();
        $dom->loadXML($_colibriv2->_xmlresponse);

        $domnodelist = $dom->getElementsByTagName('scos');

        if (!empty($domnodelist->length)) {

            $innernodelist = $domnodelist->item(0)->getElementsByTagName('sco');

            if (!empty($innernodelist->length)) {

                for ($i = 0; $i < $innernodelist->length; $i++) {

                    if ($innernodelist->item($i)->hasAttributes()) {

                        $domnode = $innernodelist->item($i)->attributes->getNamedItem('sco-id');

                        if (!is_null($domnode)) {

                            $key = (int) $domnode->nodeValue;

                            $meetingdetail = $innernodelist->item($i);

                            $value = (!is_null($meetingdetail->getElementsByTagName('name'))) ?
                                     $meetingdetail->getElementsByTagName('name')->item(0)->nodeValue : '';

                            if (!isset($matches[$key])) {
                                $matches[$key] = new stdClass();
                            }

                            $matches[$key]->name = (string) $value;

                            $value = (!is_null($meetingdetail->getElementsByTagName('url-path'))) ?
                                     $meetingdetail->getElementsByTagName('url-path')->item(0)->nodeValue : '';

                            $matches[$key]->url = (string) $value;

                            $matches[$key]->scoid = (int) $key;

                            $value = (!is_null($meetingdetail->getElementsByTagName('date-begin'))) ?
                                     $meetingdetail->getElementsByTagName('date-begin')->item(0)->nodeValue : '';

                            $matches[$key]->starttime = (string) $value;

                            $value = (!is_null($meetingdetail->getElementsByTagName('date-end'))) ?
                                     $meetingdetail->getElementsByTagName('date-end')->item(0)->nodeValue : '';

                            $matches[$key]->endtime = (string) $value;

                        }

                    }
                }
            }
        } else {
            return false;
        }

    } else {
        return false;
    }

    return $matches;
}

/**
 * Parse XML and returns the user's principal-id
 * @param string $xml XML returned from call to principal-list
 * @param mixed user's principal-id or false
 */
function _colibriv2_get_user_principal_id($xml) {
    $usrprincipalid = false;

    $dom = new DomDocument();
    $dom->loadXML($xml);

    $domnodelist = $dom->getElementsByTagName('principal-list');

    if (!empty($domnodelist->length)) {
        $domnodelist = $domnodelist->item(0)->getElementsByTagName('principal');

        if (!empty($domnodelist->length)) {
            if ($domnodelist->item(0)->hasAttributes()) {
                $domnode = $domnodelist->item(0)->attributes->getNamedItem('principal-id');

                if (!is_null($domnode)) {
                    $usrprincipalid = (int) $domnode->nodeValue;
                }
            }
        }
    }

    return $usrprincipalid;
}

/**
 * Check to see if a user exists on the Adobe connect server
 * searching by username
 * @param object $_colibriv2 a connection_class object
 * @param object $userdata an object with username as a property
 * @return mixed user's principal-id of match is found || false if not
 * found or error occured
 */
function _colibriv2_user_exists($_colibriv2, $usrdata) {
    $params = array(
        'action' => 'principal-list',
        'filter-login' => $usrdata->username,
//            'filter-type' => 'meeting',
// add more filters if this process begins to get slow
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        return _colibriv2_get_user_principal_id($_colibriv2->_xmlresponse);
    } else {
        return false;
    }


}

function _colibriv2_delete_user($_colibriv2, $principalid = 0) {

    if (empty($principalid)) {
        return false;
    }

    $params = array(
        'action' => 'principals-delete',
        'principal-id' => $principalid,
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        return true;
    } else {
        return false;
    }

}

/**
 * Creates a new user on the Adobe Connect server.
 * Parses XML from a principal-update call and returns
 * the principal-id of the new user.
 *
 * @param object $aconnet a connect_class object
 * @param object $usrdata an object with firstname,lastname,
 * username and email properties.
 * @return mixed principal-id of the new user or false
 */
function _colibriv2_create_user($_colibriv2, $usrdata) {
    $principal_id = false;

    $params = array(
        'action' => 'principal-update',
        'first-name' => $usrdata->firstname,
        'last-name' => $usrdata->lastname,
        'login' => $usrdata->username,
        'password' => strtoupper(md5($usrdata->username . time())),
        'extlogin' => $usrdata->username,
        'type' => 'user',
        'send-email' => 'false',
        'has-children' => 0,
        'email' => $usrdata->email,
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        $dom = new DomDocument();
        $dom->loadXML($_colibriv2->_xmlresponse);

        $domnodelist = $dom->getElementsByTagName('principal');

        if (!empty($domnodelist->length)) {
            if ($domnodelist->item(0)->hasAttributes()) {
                $domnode = $domnodelist->item(0)->attributes->getNamedItem('principal-id');

                if (!is_null($domnode)) {
                    $principal_id = (int) $domnode->nodeValue;
                }
            }
        }
    }

    return $principal_id;
}

function _colibriv2_assign_user_perm($_colibriv2, $usrprincipal, $meetingscoid, $type) {
    $params = array(
        'action' => 'permissions-update',
        'acl-id' => $meetingscoid, //sco-id of meeting || principal id of user 11209,
        'permission-id' => $type, //  host, mini-host, view
        'principal-id' => $usrprincipal, // principal id of user you are looking at
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
          return true;
//        print_object($_colibriv2->_xmlresponse);
    } else {
          return false;
//        print_object($_colibriv2->_xmlresponse);
    }
}

function _colibriv2_remove_user_perm($_colibriv2, $usrprincipal, $meetingscoid) {
    $params = array(
        'action' => 'permissions-update',
        'acl-id' => $meetingscoid, //sco-id of meeting || principal id of user 11209,
        'permission-id' => COLIBRIV2_REMOVE_ROLE, //  host, mini-host, view
        'principal-id' => $usrprincipal, // principal id of user you are looking at
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
//        print_object($_colibriv2->_xmlresponse);
    } else {
//        print_object($_colibriv2->_xmlresponse);
    }

}


/**
 * Check if a user has a permission
 * @param object $_colibriv2 a connect_class object
 * @param int $usrprincipal user principal-id
 * @param int $meetingscoid meeting sco-id
 * @param int $roletype can be COLIBRIV2_PRESENTER, COLIBRIV2_PARTICIPANT or COLIBRIV2_REMOVE
 * @param bool $assign set to true if you want to assign the user the role type
 * set to false to just check the user's permission.  $assign parameter is ignored
 * if $roletype is COLIBRIV2_REMOVE
 * @return TODO
 *
 */
function _colibriv2_check_user_perm($_colibriv2, $usrprincipal, $meetingscoid, $roletype, $assign = false) {
    $perm_type = '';
    $hasperm = false;

    switch ($roletype) {
        case COLIBRIV2_PRESENTER:
            $perm_type = COLIBRIV2_MINIADMIN_ROLE;
            break;
        case COLIBRIV2_PARTICIPANT:
            $perm_type = COLIBRIV2_VIEW_ROLE;
            break;
        case COLIBRIV2_HOST:
            $perm_type = COLIBRIV2_HOST_ROLE;
            break;
        case COLIBRIV2_REMOVE:
            $perm_type = COLIBRIV2_REMOVE_ROLE;
            break;
        default:
            break;
    }

    $params = array(
        'action' => 'permissions-info',
    //  'filter-permission-id' => 'mini-host',
        'acl-id' => $meetingscoid, //sco-id of meeting || principal id of user 11209,
//        'filter-permission-id' => $perm_type, //  host, mini-host, view
        'filter-principal-id' => $usrprincipal, // principal id of user you are looking at
    );

    if (COLIBRIV2_REMOVE_ROLE != $perm_type) {
        $params['filter-permission-id'] = $perm_type;
    }

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        $dom = new DomDocument();
        $dom->loadXML($_colibriv2->_xmlresponse);

        $domnodelist = $dom->getElementsByTagName('permissions');

        if (!empty($domnodelist->length)) {
            $domnodelist = $domnodelist->item(0)->getElementsByTagName('principal');

            if (!empty($domnodelist->length)) {
                $hasperm = true;
            }
        }

        if (COLIBRIV2_REMOVE_ROLE != $perm_type and $assign and !$hasperm) {
            // TODO: check return values of the two functions below
            // Assign permission to user
            return _colibriv2_assign_user_perm($_colibriv2, $usrprincipal, $meetingscoid, $perm_type);
        } elseif (COLIBRIV2_REMOVE_ROLE == $perm_type) {
            // Remove user's permission
            return _colibriv2_remove_user_perm($_colibriv2, $usrprincipal, $meetingscoid);
        } else {
            return $hasperm;
        }
    }
}

/**
 * Remove a meeting
 * @param obj $_colibriv2 adobe connection object
 * @param int $scoid sco-id of the meeting
 * @return bool true of success false on failure
 */
function _colibriv2_remove_meeting($_colibriv2, $scoid) {
    $params = array(
        'action' => 'sco-delete',
        'sco-id' => $scoid,
    );

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {
        return true;
    } else {
        return false;
    }
}

/**
 * Move SCOs to the shared content folder
 * @param obj $_colibriv2 a connect_class object
 * @param array sco-ids as array keys
 * @return bool false if error or nothing to move true if a move occured
 */
function _colibriv2_move_to_shared($_colibriv2, $scolist) {
    // Get shared folder sco-id
    $shscoid = _colibriv2_get_folder($_colibriv2, 'content');

    // Iterate through list of sco and move them all to the shared folder
    if (!empty($shscoid)) {

        foreach ($scolist as $scoid => $data) {
            $params = array(
                'action' => 'sco-move',
                'folder-id' => $shscoid,
                'sco-id' => $scoid,
            );

            $_colibriv2->create_request($params);

        }

        return true;
    } else {
        return false;
    }
}

/**
 * Gets a list of roles that this user can assign in this context
 *
 * @param object $context the context.
 * @param int $rolenamedisplay the type of role name to display. One of the
 *      ROLENAME_X constants. Default ROLENAME_ALIAS.
 * @param bool $withusercounts if true, count the number of users with each role.
 * @param integer|object $user A user id or object. By default (null) checks the permissions of the current user.
 * @return array if $withusercounts is false, then an array $roleid => $rolename.
 *      if $withusercounts is true, returns a list of three arrays,
 *      $rolenames, $rolecounts, and $nameswithcounts.
 */
function colibriv2_get_assignable_roles($context, $rolenamedisplay = ROLENAME_ALIAS, $withusercounts = false, $user = null) {
    global $USER, $DB;

    // make sure there is a real user specified
    if ($user === null) {
        $userid = !empty($USER->id) ? $USER->id : 0;
    } else {
        $userid = !empty($user->id) ? $user->id : $user;
    }

    if (!has_capability('moodle/role:assign', $context, $userid)) {
        if ($withusercounts) {
            return array(array(), array(), array());
        } else {
            return array();
        }
    }

    $parents = get_parent_contexts($context, true);
    $contexts = implode(',' , $parents);

    $params = array();
    $extrafields = '';
    if ($rolenamedisplay == ROLENAME_ORIGINALANDSHORT or $rolenamedisplay == ROLENAME_SHORT) {
        $extrafields .= ', r.shortname';
    }

    if ($withusercounts) {
        $extrafields = ', (SELECT count(u.id)
                             FROM {role_assignments} cra JOIN {user} u ON cra.userid = u.id
                            WHERE cra.roleid = r.id AND cra.contextid = :conid AND u.deleted = 0
                          ) AS usercount';
        $params['conid'] = $context->id;
    }

    if (is_siteadmin($userid)) {
        // show all roles allowed in this context to admins
        $assignrestriction = "";
    } else {
        $assignrestriction = "JOIN (SELECT DISTINCT raa.allowassign AS id
                                      FROM {role_allow_assign} raa
                                      JOIN {role_assignments} ra ON ra.roleid = raa.roleid
                                     WHERE ra.userid = :userid AND ra.contextid IN ($contexts)
                                   ) ar ON ar.id = r.id";
        $params['userid'] = $userid;
    }
    $params['contextlevel'] = $context->contextlevel;
    $sql = "SELECT r.id, r.name $extrafields
              FROM {role} r
              $assignrestriction
              JOIN {role_context_levels} rcl ON r.id = rcl.roleid
             WHERE rcl.contextlevel = :contextlevel
          ORDER BY r.sortorder ASC";
    $roles = $DB->get_records_sql($sql, $params);

    // Only include Adobe Connect roles
    $param = array('shortname' => 'colibriv2presenter');
    $presenterid    = $DB->get_field('role', 'id', $param);

    $param = array('shortname' => 'colibriv2participant');
    $participantid  = $DB->get_field('role', 'id', $param);

    $param = array('shortname' => 'colibriv2host');
    $hostid         = $DB->get_field('role', 'id', $param);

    foreach ($roles as $key => $data) {
        if ($key != $participantid and $key != $presenterid and $key != $hostid) {
            unset($roles[$key]);
        }
    }

    $rolenames = array();
    foreach ($roles as $role) {
        if ($rolenamedisplay == ROLENAME_SHORT) {
            $rolenames[$role->id] = $role->shortname;
            continue;
        }
        $rolenames[$role->id] = $role->name;
        if ($rolenamedisplay == ROLENAME_ORIGINALANDSHORT) {
            $rolenames[$role->id] .= ' (' . $role->shortname . ')';
        }
    }
    if ($rolenamedisplay != ROLENAME_ORIGINALANDSHORT and $rolenamedisplay != ROLENAME_SHORT) {
        $rolenames = role_fix_names($rolenames, $context, $rolenamedisplay);
    }

    if (!$withusercounts) {
        return $rolenames;
    }

    $rolecounts = array();
    $nameswithcounts = array();
    foreach ($roles as $role) {
        $nameswithcounts[$role->id] = $rolenames[$role->id] . ' (' . $roles[$role->id]->usercount . ')';
        $rolecounts[$role->id] = $roles[$role->id]->usercount;
    }
    return array($rolenames, $rolecounts, $nameswithcounts);
}

/**
 * This function accepts a username and an email and returns the user's
 * adobe connect user name, depending on the module's configuration settings
 * 
 * @param string - moodle username
 * @param string - moodle email
 * 
 * @return string - user's adobe connect user name
 */
function _colibriv2_set_username($username, $email) {
    global $CFG;
    
    if (isset($CFG->colibriv2_email_login) and !empty($CFG->colibriv2_email_login)) {
        return $email;
    } else {
        return $username;
    }
}

/**
 * This function search through the user-meetings folder for a folder named
 * after the user's login name and returns the sco-id of the user's folder
 * 
 * @param obj - adobe connection connection object
 * @param string - the name of the user's folder
 * @return mixed - sco-id of the user folder (int) or false if no folder exists
 * 
 */
function _colibriv2_get_user_folder_sco_id($_colibriv2, $folder_name) {

    $scoid   = false;
    $usr_meet_scoid = _colibriv2_get_folder($_colibriv2, 'user-meetings');
    
    if (empty($usr_meet_scoid)) {
        return $scoid;
    }
    
    $params = array('action' => 'sco-expanded-contents',
                    'sco-id' => $usr_meet_scoid,
                    'filter-name' => $folder_name);

    $_colibriv2->create_request($params);

    if ($_colibriv2->call_success()) {

        $dom = new DomDocument();
        $dom->loadXML($_colibriv2->_xmlresponse);
    
        $domnodelist = $dom->getElementsByTagName('sco');
    
        if (!empty($domnodelist->length)) {
            if ($domnodelist->item(0)->hasAttributes()) {
                $domnode = $domnodelist->item(0)->attributes->getNamedItem('sco-id');
    
                if (!is_null($domnode)) {
                    $scoid = (int) $domnode->nodeValue;
                }
            }
        }
    }
    
    return $scoid;
}

/**
 * This function returns the user's adobe connect login username based off of
 * the adobe connect module's login configuration settings (Moodle username or
 * Moodle email)
 * 
 * @param int userid
 * @return mixed - user's login username or false if something bad happened
 */ 
function _colibriv2_get_connect_username($userid) {
    global $DB;
    
    $username = '';
    $param    = array('id' => $userid);
    $record   = $DB->get_record('user', $param, 'id,username,email');

    if (!empty($userid) && !empty($record)) {
        $username = _colibriv2_set_username($record->username, $record->email);
    }
    
    return $username;
}

/**
 * TEST FUNCTIONS - DELETE THIS AFTER COMPLETION OF TEST
 */
/* 
function texpandsco ($_colibriv2, $scoid) {
    global $USER;
    
    $folderscoid = false;
    $params = array('action' => 'sco-expanded-contents',
                    'sco-id' => $scoid,
                    'filter-name' => $USER->email);

    $_colibriv2->create_request($params);

//    if ($_colibriv2->call_success()) {
//    }

}

function tout ($data) {
    $filename = '/tmp/tout.xml';
    $somecontent = $data;
    
    if (is_writable($filename)) {
        if (!$handle = fopen($filename, 'w')) {
             echo "Cannot open file ($filename)";
             return;
        }
    
        // Write $somecontent to our opened file.
        if (fwrite($handle, $somecontent) === FALSE) {
            echo "Cannot write to file ($filename)";
            return;
        }
    
        //echo "Success, wrote ($somecontent) to file ($filename)";
    
        fclose($handle);
    
    } else {
        echo "The file $filename is not writable";
    }
} */
