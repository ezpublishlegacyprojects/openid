<?php
include_once ('kernel/common/template.php');
include_once ('kernel/common/eztemplatedesignresource.php');
include_once ("lib/ezutils/classes/ezhttptool.php");
include_once ('extension/openid/classes/openid_user.php');
include_once ('extension/openid/classes/openid_lib_wrapper.php');

$Module = $Params['Module'];

$http = eZHTTPTool::instance();
$ini = eZINI::instance();

$userRedirectURI = '';

$loginWarning = false;

$siteAccessAllowed = true;
$siteAccessName = false;

if (isset ($Params['SiteAccessAllowed']))
	$siteAccessAllowed = $Params['SiteAccessAllowed'];
if (isset ($Params['SiteAccessName']))
	$siteAccessName = $Params['SiteAccessName'];

$postData = ''; // Will contain post data from previous page.
if ($http->hasSessionVariable('$_POST_BeforeLogin')) {
	$postData = $http->sessionVariable('$_POST_BeforeLogin');
  $http->removeSessionVariable('$_POST_BeforeLogin');
}

if ($Module->hasActionParameter('OpenIDURL'))
{
  $openid_url = $Module->actionParameter('OpenIDURL');
}
elseif ($http->hasSessionVariable( "OpenIDURL" ))
{
  $openid_url = $http->sessionVariable( "OpenIDURL" );
}
else
{
  $loginWarning = true;
}

// Normalise url
$openid_url =  openid_user::normaliseURL($openid_url);

if ($http->hasSessionVariable('OpenIDAction'))
{
  $action = $http->sessionVariable('OpenIDAction');
  $http->removeSessionVariable( "OpenIDAction" );
  if ($action == 'login')
  {
    // Complete login here
    $consumer = getConsumer();
    $msg = '';
    $return_to = getReturnTo();
    eZDebug::writeDebug( $return_to );
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
      $openid_user = openid_user::fetchByURL($openid_url);
      // TODO need to make sure we have a openid_user object
      $userID = $openid_user->attribute('user_id');
      $user = $openid_user->attribute('user');
      // Authorisation OK - Log the user in
      eZUser::setCurrentlyLoggedInUser($user, $userID);
      eZUser::updateLastVisit($userID);
      $openid_user->updateLastVisit();
      // Check access - sourced from user/login
      if ($user instanceof eZUser) 
      {
        $uri = eZURI::instance(eZSys::requestURI());
        $access = accessType($uri, eZSys::hostname(), eZSys::serverPort(), eZSys::indexFile());
        $siteAccessResult = $user->hasAccessTo('user', 'login');
        $hasAccessToSite = false;
        // A check that the user has rights to access current siteaccess.
        if ($siteAccessResult['accessWord'] == 'limited') 
        {
          $policyChecked = false;
          foreach ($siteAccessResult['policies'] as $policy) 
          {
            if (isset ($policy['SiteAccess'])) 
            {
              $policyChecked = true;
              if (in_array(eZSys::ezcrc32($access['name']), $policy['SiteAccess'])) 
              {
                $hasAccessToSite = true;
                break;
              }
            }
            if ($hasAccessToSite)
              break;
          }
          if (!$policyChecked)
            $hasAccessToSite = true;
        }
        elseif ($siteAccessResult['accessWord'] == 'yes') 
        {
          $hasAccessToSite = true;
        }
        // If the user doesn't have the rights.
        if (!$hasAccessToSite) 
        {
          $user->logoutCurrent();
          $user = null;
          $siteAccessName = $access['name'];
          $siteAccessAllowed = false;
        }
      }
      // Work out where we are going to redirect them to
      $requireUserLogin = ( $ini->variable( "SiteAccessSettings", "RequireUserLogin" ) == "true" );
      if ( !$requireUserLogin )
      {
        if ( $http->hasSessionVariable( "LastAccessesURI" ) )
          $userRedirectURI = $http->sessionVariable( "LastAccessesURI" );
      }
    
      if ( $http->hasSessionVariable( "RedirectAfterLogin" ) )
      {
        $userRedirectURI = $http->sessionVariable( "RedirectAfterLogin" );
        $http->removeSessionVariable( "RedirectAfterLogin" );
      }
       
      $redirectionURI = $userRedirectURI;
      // Determine if we already know redirection URI.
      $haveRedirectionURI = ($redirectionURI != '' && $redirectionURI != '/');
    
      if (!$haveRedirectionURI)
        $redirectionURI = $ini->variable('SiteSettings', 'DefaultPage');
    
       /* If the user has successfully passed authorization
        * and we don't know redirection URI yet.
        */
       if (is_object($user) && !$haveRedirectionURI) 
       {
       /*
        * Choose where to redirect the user to after successful login.
        * The checks are done in the following order:
        * 1. Per-user.
        * 2. Per-group.
        *    If the user object is published under several groups, main node is chosen
        *    (it its URI non-empty; otherwise first non-empty URI is chosen from the group list -- if any).
        *
        * See doc/features/3.8/advanced_redirection_after_user_login.txt for more information.
        */
     
        // First, let's determine which attributes we should search redirection URI in.
        $userUriAttrName = '';
        $groupUriAttrName = '';
        if ($ini->hasVariable('UserSettings', 'LoginRedirectionUriAttribute')) 
        {
          $uriAttrNames = $ini->variable('UserSettings', 'LoginRedirectionUriAttribute');
          if (is_array($uriAttrNames)) 
          {
            if (isset ($uriAttrNames['user']))
              $userUriAttrName = $uriAttrNames['user'];
     
            if (isset ($uriAttrNames['group']))
              $groupUriAttrName = $uriAttrNames['group'];
          }
        }
     
        $userObject = $user->attribute('contentobject');
     
        // 1. Check if redirection URI is specified for the user
        $userUriSpecified = false;
        if ($userUriAttrName) 
        {
          $userDataMap = $userObject->attribute('data_map');
          if (!isset ($userDataMap[$userUriAttrName])) 
          {
            eZDebug::writeWarning("Cannot find redirection URI: there is no attribute '$userUriAttrName' in object '" .
            $userObject->attribute('name') .
              "' of class '" .
              $userObject->attribute('class_name') . "'.");
          }
          elseif (($uriAttribute = $userDataMap[$userUriAttrName]) && ($uri = $uriAttribute->attribute('content'))) 
          {
            $redirectionURI = $uri;
            $userUriSpecified = true;
          }
        }
     
        // 2.Check if redirection URI is specified for at least one of the user's groups (preferring main parent group).
        if (!$userUriSpecified && $groupUriAttrName && $user->hasAttribute('groups')) 
        {
          $groups = $user->attribute('groups');
     
          if (isset ($groups) && is_array($groups)) 
          {
            $chosenGroupURI = '';
            foreach ($groups as $groupID) 
            {
              $group = eZContentObject::fetch($groupID);
              $groupDataMap = $group->attribute('data_map');
              $isMainParent = ($group->attribute('main_node_id') == $userObject->attribute('main_parent_node_id'));
     
              if (!isset ($groupDataMap[$groupUriAttrName])) 
              {
                eZDebug::writeWarning("Cannot find redirection URI: there is no attribute '$groupUriAttrName' in object '" .
                  $group->attribute('name') .
                  "' of class '" .
                  $group->attribute('class_name') . "'.");
                continue;
              }
              $uri = $groupDataMap[$groupUriAttrName]->attribute('content');
              if ($uri) 
              {
                if ($isMainParent) 
                {
                  $chosenGroupURI = $uri;
                  break;
                }
                elseif (!$chosenGroupURI) 
                  $chosenGroupURI = $uri;
              }
            }
     
            if ($chosenGroupURI) // if we've chose an URI from one of the user's groups.
              $redirectionURI = $chosenGroupURI;
          }
        }
      }
     
      $userID = 0;
      if ($user instanceof eZUser)
        $userID = $user->id();
      if ($userID > 0) 
      {
        if ($http->hasPostVariable('Cookie')) 
        {
          $ini = eZINI::instance();
          $rememberMeTimeout = $ini->hasVariable('Session', 'RememberMeTimeout') ? $ini->variable('Session', 'RememberMeTimeout') : false;
          if ($rememberMeTimeout) 
          {
            $GLOBALS['RememberMeTimeout'] = $rememberMeTimeout;
            eZSessionStop();
            eZSessionStart();
            unset ($GLOBALS['RememberMeTimeout']);
          }
     
       }
       $http->removeSessionVariable('eZUserLoggedInID');
       $http->setSessionVariable('eZUserLoggedInID', $userID);
    
       // Remove all temporary drafts
       //include_once( 'kernel/classes/ezcontentobject.php' );
       eZContentObject::cleanupAllInternalDrafts($userID);
       return $Module->redirectTo($redirectionURI);
      }
    }
    
    $userIsNotAllowedToLogin = false;
    $failedLoginAttempts = false;
    $maxNumOfFailedLogin = !eZUser::isTrusted() ? eZUser::maxNumberOfFailedLogin() : false;
    
    // Should we show message about failed login attempt and max number of failed login
    if ( $loginWarning and isset( $GLOBALS['eZFailedLoginAttemptUserID'] ) )
    {
        $ini = eZINI::instance();
        $showMessageIfExceeded = $ini->hasVariable( 'UserSettings', 'ShowMessageIfExceeded' ) ? $ini->variable( 'UserSettings', 'ShowMessageIfExceeded' ) == 'true' : false;
    
        $failedUserID = $GLOBALS['eZFailedLoginAttemptUserID'];
        $failedLoginAttempts = eZUser::failedLoginAttempts( $failedUserID );
    
        $canLogin = eZUser::isEnabledAfterFailedLogin( $failedUserID );
        if ( $showMessageIfExceeded and !$canLogin )
            $userIsNotAllowedToLogin = true;
    }
  }
}
else
{
  if ($Module->isCurrentAction('Login') and 
      $Module->hasActionParameter('OpenIDURL') and
      !$http->hasPostVariable( "OpenIDRegister" )) {

  	$openid_url = $Module->actionParameter('OpenIDURL');
  	$userRedirectURI = $Module->actionParameter('UserRedirectURI');

  	if (trim($userRedirectURI) == "") {
  		// Only use redirection if RequireUserLogin is disabled
  		$requireUserLogin = ($ini->variable("SiteAccessSettings", "RequireUserLogin") == "true");
  		if (!$requireUserLogin) {
  			if ($http->hasSessionVariable("LastAccessesURI"))
  				$userRedirectURI = $http->sessionVariable("LastAccessesURI");
  		}

  		if ($http->hasSessionVariable("RedirectAfterLogin")) {
  			$userRedirectURI = $http->sessionVariable("RedirectAfterLogin");
  		}
  	}
    else
    {
      $http->setSessionVariable('RedirectAfterLogin', $userRedirectURI);
    }
	  // Save array of previous post variables in session variable
  	$post = $http->attribute('post');
  	$lastPostVars = array ();
  	foreach (array_keys($post) as $postKey) {
  		if (substr($postKey, 0, 5) == 'Last_')
  			$lastPostVars[substr($postKey, 5, strlen($postKey))] = $post[$postKey];
  	}
  	if (count($lastPostVars) > 0) {
  		$postData = $lastPostVars;
  		$http->setSessionVariable('LastPostVars', $lastPostVars);
  	}
  
    // TODO: need to check if this is a url and error if not.
  	if ($openid_url) {
  

      // See if we can get a registered URL
  		$openid_user = openid_user::fetch($openid_url);
    
      // TODO If the user is already logged in and the URL is not registedto to anyone - register URL for this user

	  	if ($openid_user) {
      // Get userID & user object  associated with URL 
	  		$userID = $openid_user->attribute('user_id');
	  		$user = $openid_user->attribute('user');

        // TODO Is the user is already logged in and the URL is registered to them - redircet to where they were going
  
        // TODO Is the user is already logged in and the URL is registered another user - error saying - logout and try again?

        // Store $openid_url in session
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
  				eZDebug::writeDebug("Could not redirect to server: " . $redirect_url->message, "openid::login");
          $loginWarning = true;
  			} else {
  				// Send redirect.
		    $http->setSessionVariable('OpenIDAction', 'login');
  				return $Module->redirectTo($redirect_url);
  			}
  
  		} else {
  			// URL is not registered
  			eZDebug::writeDebug($openid_url . " is not registered", "openid::login");
        $loginWarning = true;
        // Need to display a custom openid error and include the login screen
	  	  //	return $Module->redirectTo('user/login');
	  	}
  	} else {
  		/// User didn't enter a URL
      $loginWarning = true;
  		eZDebug::writeDebug("User did not enter a URL", "openid::login");
      // Need to display a custom openid error and include the login screen
	  	//return $Module->redirectTo('user/login');
	  }
  } elseif ($http->hasPostVariable( "OpenIDRegister" )) {
    // User has access the link directly
  	$openid_url = $Module->actionParameter('OpenIDURL');
    $http->setSessionVariable('OpenIDURL', $openid_url);
 
    return $Module->redirectTo( 'openid/register' );
  }
}

$tpl = templateInit();

$tpl->setVariable( 'openid_url', $openid_url, 'User' );
$tpl->setVariable( 'post_data', $postData, 'User' );
$tpl->setVariable( 'redirect_uri', $userRedirectURI, 'User' );
$tpl->setVariable( 'warning', array( 'bad_login' => $loginWarning ), 'User' );

$tpl->setVariable( 'site_access', array( 'allowed' => $siteAccessAllowed,
                                         'name' => $siteAccessName ) );
$tpl->setVariable( 'user_is_not_allowed_to_login', $userIsNotAllowedToLogin, 'User' );
$tpl->setVariable( 'failed_login_attempts', $failedLoginAttempts, 'User' );
$tpl->setVariable( 'max_num_of_failed_login', $maxNumOfFailedLogin, 'User' );


$Result = array();
$Result['content'] = $tpl->fetch( 'design:user/login.tpl' );
$Result['path'] = array( array( 'text' => ezi18n( 'kernel/user', 'User' ),
                                'url' => false ),
                         array( 'text' => ezi18n( 'kernel/user', 'Login' ),
                                'url' => false ) );
if ( $ini->variable( 'SiteSettings', 'LoginPage' ) == 'custom' )
    $Result['pagelayout'] = 'loginpagelayout.tpl';


?>
