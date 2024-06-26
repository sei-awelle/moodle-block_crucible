<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_crucible;

defined('MOODLE_INTERNAL') || die();

/**
 * Crucible block plugin
 *
 * @package        block_crucible
 * @copyright      2023 Carnegie Mellon Univeristy
 * @license        http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
Crucible Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

class crucible {

    private $client;

    function setup_system() {

        $issuerid = get_config('block_crucible', 'issuerid');
        if (!$issuerid) {
            debugging("Crucible does not have issuerid set", DEBUG_DEVELOPER);
            return;
        }
        $issuer = \core\oauth2\api::get_issuer($issuerid);

        try {
            $client = \core\oauth2\api::get_system_oauth_client($issuer);
        } catch (Exception $e) {
            debugging("get_system_oauth_client failed with $e->errorcode", DEBUG_NORMAL);
            $client = false;
        }

        $userinfo = $client->get_userinfo();

        // Check if 'idnumber' field is present in the user information
        if (!isset($userinfo['idnumber'])) {
            debugging('Identity provider does not have a mapping for idnumber', DEBUG_NORMAL);
            return;
        }

        if ($client === false) {
            debugging('Cannot connect as system account', DEBUG_NORMAL);
            $details = 'Cannot connect as system account';
            throw new \Exception($details);
            return false;
        }
        $this->client = $client;
    }

    //////////////////////PLAYER//////////////////////

    function get_player_views() {
        
        global $USER;
        $userID = $USER->idnumber;
    
        if ($this->client == null) {
            debugging("Session not set up.", DEBUG_DEVELOPER);
            return;
        }
    
        if (!$userID) {
            debugging("User has no idnumber.", DEBUG_DEVELOPER);
            return;
        }
    
        // Check if the URL is configured
        $url = get_config('block_crucible', 'playerapiurl');
        if (empty($url)) {
            return 0; 
        }

        // Web request
        $url .= "/users/" . $userID . "/views";
    
        $response = $this->client->get($url);
    
        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Player Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . "is Unable to Connect to Player Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }
    
        if (!$response) {
            debugging("No response received from Player endpoint.", DEBUG_DEVELOPER);
            return 0;
        }
    
        $r = json_decode($response);
        if (!$r) {
            return 0;
        }
        return $r;
    }

    function get_player_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber.", DEBUG_DEVELOPER);
            return;
        }
        
        // web request
        $url = get_config('block_crucible', 'playerapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/users/" . $userID;

        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Blueprint Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . "is Unable to Connect to Blueprint Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        
        if (!$response) {
            debugging("No response received from endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
    
        if (empty($r->permissions)) {
            return 0;
        } else {
            // Iterate through permissions array to find "SystemAdmin" key with value "true"
            foreach ($r->permissions as $permission) {
                if ($permission->key === "SystemAdmin") {
                    return $r->permissions;   
                }
            }
            return 0;
        }
        
        return 0;
    }

   //////////////////////BLUEPRINT//////////////////////
    function get_blueprint_msels() {

        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }

        if (!$userID) {
            debugging("User has no idnumber.", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'blueprintapiurl');
        if (empty($url)) {
            return 0; 
        }
        
        $url .= "/users/" . $userID . "/msels";
        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) for User: ". $userID . " on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Blueprint Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . "is Unable to Connect to Blueprint Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        if (!$response) {
            debugging("No response received from Blueprint endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
        if (!$r) {
            return 0;
        }
        return $r;
    }

    function get_blueprint_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber.", DEBUG_DEVELOPER);
            return;
        }
        
        // web request
        $url = get_config('block_crucible', 'blueprintapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/users/" . $userID;

        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Blueprint Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . "is Unable to Connect to Blueprint Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        
        if (!$response) {
            debugging("No response received from endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
    
        if (empty($r->permissions)) {
            return 0;
        } else {
            return $r->permissions;
        }

        /* user exists but no special perms */
        return 0;
    }

    //////////////////////CASTER//////////////////////

    function get_caster_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber.", DEBUG_DEVELOPER);
            return;
        }
        
        // web request
        $url = get_config('block_crucible', 'casterapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/users/" . $userID . "/permissions";
        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Caster Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . "is Unable to Connect to Caster Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        
        if (!$response) {
            debugging("No response received from endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);

        if (empty($r)) {
            return 0;
        } else {
            return $r;
        }

        /* user exists but no special perms */
        return 0;
    }

    //////////////////////CITE//////////////////////
    function get_cite_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'citeapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/users/" . $USER->idnumber;

        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("CITE Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . " is Unable to Connect to CITE Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        if (!$response) {
            debugging("No response received from endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
        
        if (empty($r->permissions)) {
            return 0;
        } else {
            return $r->permissions;
        }

        /* user exists but no special perms */
        return 0;
    }

    function get_cite_evaluations() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }

        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'citeapiurl');
        if (empty($url)) {
            return 0; 
        }

        //$url .= "/my-evaluations";
        $url .= "/evaluations?UserId=" . $userID;

        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("CITE Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . " is Unable to Connect to CITE Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        if (!$response) {
            debugging("No response received from CITE endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
        if (!$r) {
            return 0;
        }
        return $r;
    }

    //////////////////////GALLERY//////////////////////
    function get_gallery_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'galleryapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/users/" . $userID;

        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Gallery Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . " is Unable to Connect to Gallery Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        if (!$response) {
            debugging("No response received from Gallery endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
        
        if (empty($r->permissions)) {
            return 0;
        } else {
            return $r->permissions;
        }

        /* user exists but no special perms */
        return 0;
    }

    function get_gallery_exhibits() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'galleryapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/users/" . $userID . '/exhibits';
        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Gallery Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . "is Unable to Connect to Gallery Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        if (!$response) {
            debugging("No response received from Gallery endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
        if (!$r) {
            return 0;
        }
        return $r;
    }

    //////////////////////Rocket.Chat//////////////////////
    function get_rocketchat_user_info() {
        global $USER;
        $userID = $USER->idnumber;

        $username = $USER->username;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$username) {
            debugging("User has no username", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'rocketchatapiurl');
        $authToken = get_config('block_crucible', 'rocketchatauthtoken');
        $adminUserId = get_config('block_crucible', 'rocketchatuserid');

        if (empty($url) || empty($authToken) || empty($adminUserId)) {
            return -1; 
        }

        $url .= "/users.info?username=" . $username;

        $headers = [
            'X-Auth-Token: ' . $authToken,
            'X-User-Id: ' . $adminUserId,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            debugging('Rocket Chat API request failed: ' . curl_error($ch), DEBUG_DEVELOPER);
            return false;
        }

        curl_close($ch);

        $r = json_decode($response);

        if ($r->success === false) {
            debugging($r->error, DEBUG_DEVELOPER);
        } else if (property_exists($r, 'status') && $r->status === "error") {
            debugging($r->message, DEBUG_DEVELOPER);
        } else {
            return $r;
        }
        

        /* user exists but no special perms */
        return 0;
    }

    //////////////////////STEAMFITTER//////////////////////
    function get_steamfitter_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'steamfitterapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/users/" . $userID;

        $response = $this->client->get($url);

        if ($this->client->info['http_code'] === 401) {
            debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 403) {
            debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] === 404) {
            debugging("Steamfitter Not Found (404) " . $url, DEBUG_DEVELOPER);
            return 0;
        } else if ($this->client->info['http_code'] !== 200) {
            debugging("User: " . $userID . "is unable to Connect to Steamfitter Endpoint " . $url, DEBUG_DEVELOPER);
            return 0;
        }

        if (!$response) {
            debugging("No response received from Steamfitter endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
        
        if (empty($r->permissions)) {
            return 0;
        } else {
            return $r->permissions;
        }

        /* user exists but no special perms */
        return 0;
    }

     //////////////////////TopoMojo//////////////////////
     function get_topomojo_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'topomojoapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/user/" . $userID;
        $apiKey = get_config('block_crucible', 'topomojoapikey');

        if ($apiKey != null) {
            $headers = [
                'x-api-key: ' . $apiKey,
            ];
    
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
            $response = curl_exec($ch);
    
            if (curl_errno($ch)) {
                debugging('Topomojo API request failed: ' . curl_error($ch), DEBUG_DEVELOPER);
                return false;
            }
    
            curl_close($ch);
        } else {
            $response = $this->client->get($url);

            if ($this->client->info['http_code'] === 401) {
                debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] === 403) {
                debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] === 404) {
                debugging("Topomojo Not Found (404) " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] !== 200) {
                debugging("User: " . $userID . "is unable to Connect to Topomojo Endpoint " . $url, DEBUG_DEVELOPER);
                return 0;
            }
        }

        if (!$response) {
            debugging("No response received from Topomojo endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);

        
        if ($r->isAdmin || $r->isObserver || $r->isCreator || $r->isBuilder) {
            return $r;
        }
        return 0;

    }
    //////////////////////Gameboard//////////////////////
    function get_gameboard_permissions() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'gameboardapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/user/" . $userID;
        $apiKey = get_config('block_crucible', 'gameboardapikey');

        if ($apiKey != null) {
            $headers = [
                'x-api-key: ' . $apiKey,
            ];
    
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
            $response = curl_exec($ch);
    
            if (curl_errno($ch)) {
                debugging('Gameboard API request failed: ' . curl_error($ch), DEBUG_DEVELOPER);
                return false;
            }
    
            curl_close($ch);
        } else {
            $response = $this->client->get($url);

            if ($this->client->info['http_code'] === 401) {
                debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] === 403) {
                debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] === 404) {
                debugging("Gameboard Not Found (404) " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] !== 200) {
                debugging("User: " . $userID . "is unable to Connect to Gameboard Endpoint " . $url, DEBUG_DEVELOPER);
                return 0;
            }
        }

        if (!$response) {
            debugging("No response received from Gamebaord endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);

        
        if ($r->isAdmin || $r->isDirector || $r->isDesigner || $r->isObserver || $r->isTester || $r->isSupport || $r->isRegistrar) {
            return $r;
        }
        return 0;

    }

    function get_active_challenges() {
        global $USER;
        $userID = $USER->idnumber;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$userID) {
            debugging("User has no idnumber", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'gameboardapiurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/user/" . $userID . "/challenges/active";
        $apiKey = get_config('block_crucible', 'gameboardapikey');

        if ($apiKey != null) {
            $headers = [
                'x-api-key: ' . $apiKey,
            ];
    
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
            $response = curl_exec($ch);
    
            if (curl_errno($ch)) {
                debugging('Gameboard API request failed: ' . curl_error($ch), DEBUG_DEVELOPER);
                return false;
            }
    
            curl_close($ch);
        } else {
            $response = $this->client->get($url);

            if ($this->client->info['http_code'] === 401) {
                debugging("Unauthorized access (401) on " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] === 403) {
                debugging("Forbidden (403) on " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] === 404) {
                debugging("Gameboard Not Found (404) " . $url, DEBUG_DEVELOPER);
                return 0;
            } else if ($this->client->info['http_code'] !== 200) {
                debugging("User: " . $userID . "is unable to Connect to Gameboard Endpoint " . $url, DEBUG_DEVELOPER);
                return 0;
            }
        }

        if (!$response) {
            debugging("No response received from Gamebaord endpoint.", DEBUG_DEVELOPER);
            return 0;
        }

        $r = json_decode($response);
        if (!$r) {
            return 0;
        }
        return $r;
    }

    function get_misp_permissions() {
        global $USER;
        $email = $USER->email;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$email) {
            debugging("User has no email", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'mispappurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/admin/users";
        $apiKey = get_config('block_crucible', 'mispapikey');

        $headers = [
            'Authorization: ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        $users = json_decode($response, true);
        $userFound = false;

        foreach ($users as $user) {
            if (isset($user['User']['email']) && $user['User']['email'] === $email) {
                $userFound = true;
                if (isset($user['Role']['name']) && $user['Role']['name'] === 'admin') {
                    return $user;
                }
            }
        }

        if (!$userFound) {
            debugging("User with email {$email} not found.", DEBUG_DEVELOPER);
            return 0;
        }
        return 0;
    }

    function get_misp_user() {
        global $USER;
        $email = $USER->email;

        if ($this->client == null) {
            debugging("Session not set up", DEBUG_DEVELOPER);
            return;
        }
        if (!$email) {
            debugging("User has no email", DEBUG_DEVELOPER);
            return;
        }

        // web request
        $url = get_config('block_crucible', 'mispappurl');
        if (empty($url)) {
            return 0; 
        }

        $url .= "/admin/users";
        $apiKey = get_config('block_crucible', 'mispapikey');

        $headers = [
            'Authorization: ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        $users = json_decode($response, true);
        $userFound = false;

        foreach ($users as $user) {
            if (isset($user['User']['email']) && $user['User']['email'] === $email) {
                $userFound = true;
                return $user;
            }
        }

        if (!$userFound) {
            debugging("User with email {$email} not found.", DEBUG_DEVELOPER);
            return 0;
        }
        return 0;
    }
}
