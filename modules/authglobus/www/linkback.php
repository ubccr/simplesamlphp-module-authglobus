<?php

/**
 * Handle linkback() response from Globus.
 */
 
if (!array_key_exists('state', $_REQUEST) || empty($_REQUEST['state'])) {
    throw new SimpleSAML_Error_BadRequest('Lost state for Globus endpoint.');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['state'], sspmod_authglobus_Auth_Source_Globus::STAGE_INIT);

// Find authentication source
if (!array_key_exists(sspmod_authglobus_Auth_Source_Globus::AUTHID, $state)) {
    throw new SimpleSAML_Error_BadRequest('No data in state for ' . sspmod_authglobus_Auth_Source_Globus::AUTHID);
}
$sourceId = $state[sspmod_authglobus_Auth_Source_Globus::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === null) {
    throw new SimpleSAML_Error_BadRequest('Could not find authentication source with id ' . var_export($sourceId, true));
}

try {
    $source->finalStep($state);
} catch (SimpleSAML_Error_Exception $e) {
    SimpleSAML_Auth_State::throwException($state, $e);
} catch (Exception $e) {
    SimpleSAML_Auth_State::throwException($state, new SimpleSAML_Error_AuthSource($sourceId, 'Error on globus linkback endpoint.', $e));
}

SimpleSAML_Auth_Source::completeAuth($state);
