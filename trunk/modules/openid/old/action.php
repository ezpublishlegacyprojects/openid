<?php
include_once ('kernel/common/template.php');
include_once ('kernel/common/eztemplatedesignresource.php');
include_once ("lib/ezutils/classes/ezhttptool.php");
include_once ('extension/openid/classes/openid_user.php');
include_once ('extension/openid/classes/openid_lib_wrapper.php');

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$ini = eZINI::instance();


if ($Module->isCurrentAction('Register') and $Module->hasActionParameter('OpenIDURL')) {
	$openid_url = $Module->actionParameter('OpenIDURL');
  if ($openid_url != '')
  {
    $user = eZUser::currentUser();
    $userID = $user->id();
    $http->setSessionVariable('OpenIDURL', $openid_url);

		$consumer = getConsumer();
    // Begin the OpenID authentication process.
	  $auth_request = $consumer->begin($openid_url);

		// Redirect the user to the OpenID server for authentication.
		// Store the token for this authentication so we can verify the
		// response.

		// For OpenID 1, send a redirect.  For OpenID 2, use a Javascript
		// form to send a POST request to the server.
		$redirect_url = $auth_request->redirectURL(getTrustRoot(), getReturnTo());

		// If the redirect URL can't be built, display an error
		// message.
		if (Auth_OpenID::isFailure($redirect_url)) {
			eZDebug::writeDebug("Could not redirect to server: " . $redirect_url->message);
		} else {
			// Send redirect.
			return $Module->redirectTo($redirect_url);
		}

   // $openid_user =& openid_user::create();
   // $openid_user->setAttribute('openid_url', $openid_url);
   // $openid_user->setAttribute('user_id', $userID);
   // $openid_user->store();
  }
}

if ($Module->isCurrentAction('Remove') and $Module->hasActionParameter('DeleteIDArray')) {

	$DeleteIDArray = $Module->actionParameter('DeleteIDArray');
  eZDebug::writeDebug( $DeleteIDArray );
  $cond = array('id' => array($DeleteIDArray));
  openid_user::removeObject(openid_user::definition(),$cond);
}
return $Module->redirectTo('openid/list');
