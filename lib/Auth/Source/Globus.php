<?php

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/oauth/lib/Consumer.php';
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/oauth/libextinc/OAuth.php';

/**
 * Authenticate using the Globus Platform Auth Protocol
 * Documentation: https://docs.globus.org/api/auth/developer-guide
 * Globus Website: https://www.globus.org/
 *
 * @author  Rudra Chakraborty, Center for Computational Research - University at Buffalo. rudracha@buffalo.edu
 * @package SimpleSAMLphp
 */

class sspmod_authglobus_Auth_Source_Globus extends SimpleSAML_Auth_Source
{
    /**
     * The string used to identify our states.
    */
    const STAGE_INIT = 'Globus:init';

    /**
     * The key of the AuthId field in the state.
    */
    const AUTHID = 'Globus:AuthId';

    /**
     * Globus Endpoint
    */
    const API_ENDPT = 'https://auth.globus.org/v2';

    private $key;
    private $secret;
    private $scope;
    private $reponse_type;
    private $redirect_uri;
    private $curl;

    /**
     * Constructor for this authentication source.
     *
     * @param array $info   Information about this authentication source.
     * @param array $config Configuration.
     */
    public function __construct($info, $config)
    {
        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);

        $this->key = $config['key'];
        $this->secret = $config['secret'];
        $this->scope = $config['scope'];
        $this->response_type = $config['response_type'];
        $this->redirect_uri= $config['redirect_uri'];
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_USERAGENT, 'SSPHP Globus');
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 2);
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
    }

    /**
     * SAMLize an associative array, derived from LinkedIn module's flatten
     *
     * @param array $array
     * @param string $prefix
     *
     * @return array the array with the new concatenated keys
     */
    protected function samlize($array, $prefix = '')
    {
        $newArr = array();

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $newArr = $newArr + $this->samlize($value, $prefix . $key . '.');
            } else {
                $newArr[$prefix . $key] = array($value);
            }
        }

        return $newArr;
    }

    /**
     * Accepts a field containing a full name, attempts to extract a full name
     *
     * @param string $name
     *
     * @return an array containing first name and last name
     */
    protected function getFullName($name)
    {
        $trimmedName = trim($name);
        $lastName = 'UNKNOWN';
        $firstName = 'UNKNOWN';
    
        // we mandate that a complete name be provided (not just a first name or last)
        if(strpos($trimmedName, ',') !== false ) {
            list($lastName, $firstName) = explode(',', $trimmedName, 2);
        }
        elseif(strpos($trimmedName, ' ') !== false ) {
            list($firstName, $lastName) = explode(' ', $trimmedName, 2);
        }
    
        return array(
            'first_name' => trim($firstName),
            'last_name' => trim($lastName)
        );
    }

    /**
     * Wrapper for Curl Requests
     *
     * @param string path
     * @param boolean signed
     * @param string token
     * @param string qs
     * @param array contents
     * @param string method
     *
     * @return The response from the endpoint where we made the request.
     */
    protected function doCurlRequest(
        $path,
        $signed = false,
        $token = null,
        $qs = null,
        $contents = null,
        $method = 'GET'
    ) {
        if ($signed) {
            curl_setopt($this->curl, CURLOPT_USERPWD, $this->key . ":" . $this->secret);
        } else {
            curl_setopt($this->curl, CURLOPT_USERPWD, null);
        }

        $endPoint = self::API_ENDPT . $path . (($qs !== null) ? $qs : '');
        curl_setopt($this->curl, CURLOPT_URL, $endPoint);

        if ($method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, OauthUtil::build_http_query($contents));
        } else { // do GET by default
            $header = 'Authorization: Bearer ' . $token;
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array($header));
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        }

        $result = curl_exec($this->curl);
        $outInfo = curl_getinfo($this->curl);

        if (!$result) {
            throw new SimpleSAML_Error_Error(curl_error($this->curl));
        }

        if ($result && $outInfo['http_code'] === 200) {
            // return GETs as associative array, we want user info to be easily dumped into SAML attributes.
            return json_decode($result, $method !== 'POST');
        } else {
            switch($outInfo['http_code']) {
                case 400:
                    throw new SimpleSAML_Error_BadRequest($outInfo['http_code'] . ': Could not retrieve data from Globus service.' . print_r($outInfo, true));
                    break;
                case 403:
                    throw new SimpleSAML_Error_InvalidCredential($outInfo['http_code'] . ': Invalid credentials specified to Globus.' . print_r($outInfo, true));
                    break;
                case 404:
                    throw new SimpleSAML_Error_NotFound($outInfo['http_code'] . ': Globus resource not found' . print_r($outInfo, true));
                    break;
                default:
                    throw new SimpleSAML_Error_Error($outInfo['http_code'] . ': An unknown error occured.' . print_r($outInfo, true));
                    break;
            }
        }
    }

    /**
     * Obtain an Authorization Code
     *
     * @param array &$state Information about the current authentication.
     */
    public function authenticate(&$state)
    {
        // We are going to need the authId in order to retrieve this authentication source later
        $state[self::AUTHID] = $this->authId;

        $stateID = SimpleSAML_Auth_State::getStateId($state);
        SimpleSAML_Logger::debug("globus auth at state = " . $stateID);
        $request = array(
            'client_id' => $this->key,
            'scope' => $this->scope,
            'state' => $stateID,
            'response_type' => 'code',
            'redirect_uri' => $this->redirect_uri
        );

        $state['globusauth:request'] = $request;
        $urlAppend = \SimpleSAML\Utils\HTTP::addURLParameters(self::API_ENDPT . '/oauth2/authorize', $request);
        SimpleSAML_Auth_State::saveState($state, self::STAGE_INIT);

        // Get an Authorization Code from Globus
        /* Example: https://auth.globus.org/v2/oauth2/authorize?client_id=69ba5e62-7285-45db-952d-e0bb73b5eac7&scope=urn:globus:auth:scope:transfer.api.globus.org:all urn:globus:auth:scope:auth.globus.org:view_identities offline_access&response_type=code&redirect_uri=https://www.example.org/my_app/login&state=g6l14b2xlgx4dtce8d2ja714i
        */
        $consumer = new sspmod_oauth_Consumer($this->key, $this->secret);
        $authorizeUrl = $consumer->getAuthorizeRequest($urlAppend, $request);
    }

    /**
     * Exchange authorization code for an access token
     *
     * @param array &$state Information about the current authentication.
     */
    public function finalStep(&$state)
    {
        $request = array(
            'code' => $_GET['code'],
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code'
        );

        // Exchange the code we got earlier for an access token
        $result = $this->doCurlRequest('/oauth2/token', true, null, null, $request, 'POST');

        // Use this access token to tell us about our current user + affiliations
        $userInfo = $this->doCurlRequest('/oauth2/userinfo', false, $result->access_token);
        $fullname = $this->getFullName($userInfo['name']);

        $attributes = array(
            'preferred_username' => array($userInfo['preferred_username']),
            'identity_provider' => array($userInfo['identity_provider']),
            'identity_provider_display_name' => array($userInfo['identity_provider_display_name']),
            'sub' => array($userInfo['sub']),
            'email' => array($userInfo['email']),
            'name' => array($userInfo['name']),
            'first_name' => array($fullname['first_name']),
            'last_name' => array($fullname['last_name']),
            'organization' => array($userInfo['organization'])
        );
        $state['Attributes'] = array_merge($attributes, $this->samlize($userInfo['identity_set'], 'identity.'));
    }
}
