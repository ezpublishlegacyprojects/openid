<?php
include_once ('kernel/common/template.php');
include_once ('kernel/common/eztemplatedesignresource.php');
include_once ("lib/ezutils/classes/ezhttptool.php");
include_once ('extension/openid/classes/openid_lib_wrapper.php');
include_once ('extension/openid/classes/openid_user.php');

$Module = & $Params['Module'];
$http = eZHTTPTool::instance();
$ini = eZINI::instance();

$user = eZUser::currentUser();
// Check if user is anonymous
if ( $user->isAnonymous() )
{
  // some kind or error here?
}

$userID = $user->id();
$userObject = $user->attribute('contentobject');

// Grab the URL from the session
$openid_url = $http->sessionVariable( "OpenIDURL" );

// Complete the authentication process using the server's
// response.
$consumer = getConsumer();
$msg = '';
$return_to = getReturnTo('attach');
$response = $consumer->complete($return_to);
eZDebug::writeDebug($response);

// Check the response status.
if ($response->status == Auth_OpenID_CANCEL) 
{
  // TODO  Display login screen with appropriate error message
  // This means the authentication was cancelled.
  $msg = 'Verification cancelled.';
  $loginWarning = true;
} 
elseif ($response->status == Auth_OpenID_FAILURE) 
{
  // TODO  Display login screen with appropriate error message
  // Authentication failed; display the error message.
  $msg = "OpenID authentication failed: " . $response->message;
  $loginWarning = true;
}
elseif ($response->status == Auth_OpenID_SUCCESS) 
{
  // This means the authentication succeeded; extract the
  // identity URL and Simple Registration data (if it was
  // returned).
  $http->removeSessionVariable( "OpenIDURL" );

  $openid_user =& openid_user::create();
  $openid_user->setAttribute('openid_url', $openid_url);
  $openid_user->setAttribute('user_id', $userID);
  $openid_user->store();

  return $Module->redirectTo('/openid/list');
}
?>
