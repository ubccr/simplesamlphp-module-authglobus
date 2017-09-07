# simplesamlphp-module-authglobus
A Globus Auth Module for SimpleSAMLphp

## Overview
  This is an extension for [SimpleSAMLphp](https://simplesamlphp.org/), enabling authenticating with the University of Chicago's [Globus Project](https://www.globus.org) with your SimpleSAMLPHP configuration. It was designed for use by the University at Buffalo Center for Computational Research's [XDMoD Project](http://open.xdmod.org/), but is written to be generic and reusable.
  

## Setup
  ### Automatic
  If you're using [Composer](https://getcomposer.org/), installing the module is as simple as: 
  
      'composer require ubccr/simplesamlphp-module-authglobus 1.*'

  ### From scratch
  First [install SimpleSAMLphp](https://simplesamlphp.org/docs/stable/simplesamlphp-install) as normal. Then copy the contents of modules/ into your own modules directory. Rename default-disable to default-enable. Append the configuration found in config-templates/ to your own config.
    
  ### Running Globus Auth
  You will need to first properly [register your application with Globus Auth](https://docs.globus.org/api/auth/developer-guide/#register-app). Thereafter, [head to your SimpleSAML configuration](../master/config-templates/authsources.php), and fill in the 'globus' module information in authsources.php as follows: 
        
        
       'key': The client ID value you were provided when you registered your application,
       'secret': The client secret. As per the documentation, make sure you save this. YOU CAN ONLY SEE IT ONCE!!!
       'scope': You should have these set up within your Globus App registry.
       'response_type': This should always be set to 'code'. 
       'redirect_uri': Should always be the linkback.php file + your endpoint. A la 'https://fakeaddress.buffalo.edu/simplesaml/module.php/globus/linkback.php'

  ### Running Globus Auth as an External IDP
  Set up your SAML SP as normal ([see our LDAP Authentication guide](http://open.xdmod.org/simpleSAMLphp-ldap.html) for a similar example), with 'globus-idp' as the identity provider. Create an SSL self-signed certificate and place it your certificates folder. You will then need to esnure you configure your globus-idp metadata as follows:

  File: saml20-idp-hosted.php
  
      $metadata['globus-idp'] = array(
        /*
        * The hostname for this IdP. This makes it possible to run multiple
        * IdPs from the same configuration. '__DEFAULT__' means that this one
        * should be used by default.
        */
        'host' => '__DEFAULT__',

        /*
        * The private key and certificate to use when signing responses.
        * These are stored in the cert-directory.
        */
        'privatekey' => '[[YOUR_PRIVATE_KEY]].pem',
        'certificate' => '[[YOUR_PRIVATE_CERT]].crt',

        /*
        * The authentication source which should be used to authenticate the
        * user. This must match one of the entries in config/authsources.php.
        */
        'auth' => 'globus'
      );

  File: saml20-idp-remote.php
  
        $metadata['globus-idp'] = array (
          'metadata-set' => 'saml20-idp-remote',
          'entityid' => 'globus-idp',
          'SingleSignOnService' =>
              array (
                  0 =>
                  array (
                      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                      'Location' => 'https://your.endpoint/simplesaml/saml2/idp/SSOService.php',
                  ),
              ),
          'SingleLogoutService' =>
              array (
                  0 =>
                  array (
                      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                      'Location' => 'https://your.endpoint/simplesaml/saml2/idp/SingleLogoutService.php',
                  ),
              ),
          'keys' =>
        array (
          0 =>
          array (
            'encryption' => true,
            'signing' => true,
            'type' => 'CERTTYPE',
            'CERTTYPE' => '...',
          ),
          1 =>
          array (
            'encryption' => true,
            'signing' => true,
            'type' => 'CERTTYPE',
            'CERTTYPE' => '...',
          ),
        ),
          'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
        );
        
## Suggestions, Bugfixes, Feedback

  While this module is used in the XDMoD Project, it is not officially supported as part of the overarching project or maintained as such. Tickets submitted to XDMoD support pertaining to this module cannot be supported, and are likely to go unanswered. Pull requests are welcome, but turnaround might be intermittent in lieu of other team priorities. 
  
  For feedback, commentary, requests, please reach out to the author/maintainer, [Rudra Chakraborty](mailto:rudracha@buffalo.edu).
  
  For questions specific to SimpleSAMLphp or Globus Auth, please contact the developers for those projects, we cannot provide support for them. Questions related to XDMoD but not to this module should be put through official XDMod support channels.


  
  

        
