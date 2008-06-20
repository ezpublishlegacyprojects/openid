<?php

include_once ('kernel/common/template.php');
include_once ('kernel/common/eztemplatedesignresource.php');
include_once ("lib/ezutils/classes/ezhttptool.php");
include_once ('extension/openid/classes/openid_user.php');
include_once ('extension/openid/classes/openid_lib_wrapper.php');

$Module = $Params['Module'];
$http = eZHTTPTool::instance();
$ini = eZINI::instance();
$tpl = templateInit();
$msg = false;
$error_msg = false;

// Get the currently logged in user
$user = eZUser::currentUser();

// Check if user is anonymous
if ( $user->isAnonymous() )
{
  // some kind or error here?
}

$userID = $user->id();
$userObject = $user->attribute('contentobject');

if ($http->hasSessionVariable('OpenIDAction'))
{
  $action = $http->sessionVariable('OpenIDAction');
  $http->removeSessionVariable( "OpenIDAction" );
  if ($action == 'attach')
  {
    // Grab the URL from the session
    $openid_url = $http->sessionVariable( "OpenIDURL" );
    // Complete the authentication process using the server's
    // response.
    $consumer = getConsumer();
    $return_to = getReturnTo('list');
    $response = $consumer->complete($return_to);

    // Check the response status.
    if ($response->status == Auth_OpenID_CANCEL) 
    {
      // This means the authentication was cancelled.
      $error_msg = 'Verification cancelled.';
    } 
    elseif ($response->status == Auth_OpenID_FAILURE) 
    {
      // Authentication failed; display the error message.
      $error_msg = "OpenID authentication failed: " . $response->message;
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

      $msg = "Successfully verified and registered openID URL ".$openid_url;
    }
  }
}

if ($Module->isCurrentAction('Register') and $Module->hasActionParameter('OpenIDURL')) 
{
  $openid_url = $Module->actionParameter('OpenIDURL');
  // Normalise url
  $openid_url =  openid_user::normaliseURL($openid_url);
  if ($openid_url != '')
  {
    // Does the URL already exist
    $openid_user = openid_user::fetchByURL($openid_url);
    if (! $openid_user)
    {
      $user = eZUser::currentUser();
      $userID = $user->id();
      $consumer = getConsumer();
      // Begin the OpenID authentication process.
      $auth_request = $consumer->begin($openid_url);
      eZDebug::writeDebug( $openid_url );
      eZDebug::writeDebug( $auth_request );
   
      if ($auth_request)
      {
        // Redirect the user to the OpenID server for authentication.
        // Store the token for this authentication so we can verify the
        // response.

        // For OpenID 1, send a redirect.  For OpenID 2, use a Javascript
        // form to send a POST request to the server.
        $redirect_url = $auth_request->redirectURL(getTrustRoot(), getReturnTo('list'));

        // If the redirect URL can't be built, display an error
        // message.
        if (Auth_OpenID::isFailure($redirect_url)) {
          $error_msg = "Could not redirect to server: " . $redirect_url->message;
        } else {
          // Send redirect.
          $http->setSessionVariable('OpenIDURL', $openid_url);
          $http->setSessionVariable('OpenIDAction', 'attach');
          return $Module->redirectTo($redirect_url);
        }
      }
      else
      {
        $error_msg = "Invlaid OpenID URL :".$openid_url;
      }
    }
    else
    {
      $error_msg = "URL already registered:".$openid_url;
    }
  }
  else
  {
    $error_msg = "Invlaid URL :".$openid_url;
  }
}

if ($Module->isCurrentAction('Remove') and $Module->hasActionParameter('DeleteIDArray')) {

  $DeleteIDArray = $Module->actionParameter('DeleteIDArray');
  eZDebug::writeDebug( $DeleteIDArray );
  $cond = array('id' => array($DeleteIDArray));
  openid_user::removeObject(openid_user::definition(),$cond);
}

$title = "OpenID's for ".$userObject->attribute('name');

// Get a list of registered URLS for this user
$openid_urls = openid_user::fetchByUserID($userID);

$tpl->setVariable( 'user', $user );
$tpl->setVariable( 'openid_urls', $openid_urls );
$tpl->setVariable( 'msg', $msg );
$tpl->setVariable( 'error_msg', $error_msg );


$path[] = array( 'url'       => false,
                 'url_alias' => false,
                 'node_id'   => 0,
                 'text'      => $title);

$Result = array();
$Result['content'] = $tpl->fetch("design:openid/list.tpl");
$Result['path'] = $path;

// Set navigation_part_identifier & res info 
$Result['content_info']['navigation_part_identifier'] = false;
if ( $Module->singleFunction() )
  $function = $Module->Module["function"];
else
  $function = $Module->Functions[$Params['FunctionName']];

if ( isset( $function['default_navigation_part'] ) )
{
  $Result['content_info']['navigation_part_identifier'] = $function['default_navigation_part'];
}
$res = eZTemplateDesignResource::instance();
$res->setKeys( array( array( 'navigation_part_identifier', $Result['content_info']['navigation_part_identifier'] )) );

?>
