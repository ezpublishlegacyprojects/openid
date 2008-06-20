<?php
include_once ('extension/openid/classes/openid_user.php');
include_once ('extension/openid/classes/openid_lib_wrapper.php');

$http = eZHTTPTool::instance();
$Module = $Params['Module'];

eZDebug::writeDebug( $_REQUEST );
 

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
  // redirect to normal register?
  // TODO Need to resolve this as currently the system will just register the
  // user without an open ID
}

// Normalise url
$openid_url =  openid_user::normaliseURL($openid_url);

if ( isset( $Params['UserParameters'] ) )
{
    $UserParameters = $Params['UserParameters'];
}
else
{
    $UserParameters = array();
}
$viewParameters = array();
$viewParameters = array_merge( $viewParameters, $UserParameters );

$Params['TemplateName'] = "design:openid/register.tpl";
$EditVersion = 1;

require_once( "kernel/common/template.php" );
$tpl = templateInit();
$tpl->setVariable( 'view_parameters', $viewParameters );

$Params['TemplateObject'] = $tpl;

// $http->removeSessionVariable( "RegisterUserID" );

// Create new user object if user is not logged in

if ( !$http->hasSessionVariable( "RegisterUserID" ) and !$http->hasPostVariable( "UserID" ) )
{
    // Check to see if the openID_url is already registered
    $openid_user = openid_user::fetchByURL($openid_url);
    if ($openid_user)
    {
      // If the URL is already registered then try and log them in
      eZDebug::writeDebug( "$openid_url is already registered" );
      $Module->setCurrentAction('Login','login');
      $Module->setActionParameter('OpenIDURL',$openid_url,'login');
      $parameters=array();
      return $Module->run( 'login', $parameters );
    }
 
    $ini = eZINI::instance();
    $errMsg = '';
    $checkErrNodeId = false;

    $defaultUserPlacement = (int)$ini->variable( "UserSettings", "DefaultUserPlacement" );

    $db = eZDB::instance();
    $sql = "SELECT count(*) as count FROM ezcontentobject_tree WHERE node_id = $defaultUserPlacement";
    $rows = $db->arrayQuery( $sql );
    $count = $rows[0]['count'];
    if ( $count < 1 )
    {
        $errMsg = ezi18n( 'design/standard/user', 'The node (%1) specified in [UserSettings].DefaultUserPlacement setting in site.ini does not exist!', null, array( $defaultUserPlacement ) );
        $checkErrNodeId = true;
        eZDebug::writeError( "$errMsg" );
        $tpl->setVariable( 'errMsg', $errMsg );
        $tpl->setVariable( 'checkErrNodeId', $checkErrNodeId );
    }
    $userClassID = $ini->variable( "UserSettings", "UserClassID" );
    $class = eZContentClass::fetch( $userClassID );

    $userCreatorID = $ini->variable( "UserSettings", "UserCreatorID" );
    $defaultSectionID = $ini->variable( "UserSettings", "DefaultSectionID" );
    // Create object by user 14 in section 1
    $contentObject = $class->instantiate( $userCreatorID, $defaultSectionID );
    $objectID = $contentObject->attribute( 'id' );

    // Store the ID in session variable
    $http->setSessionVariable( "RegisterUserID", $objectID );

    $userID = $objectID;

    $nodeAssignment = eZNodeAssignment::create( array( 'contentobject_id' => $contentObject->attribute( 'id' ),
                                                       'contentobject_version' => 1,
                                                       'parent_node' => $defaultUserPlacement,
                                                       'is_main' => 1 ) );
    $nodeAssignment->store();
    // *** OpenID starts here
    eZDebug::writeDebug('start OpenID'  );
     
    $required_attributes = array('email','nickname');
    $optional_attributes = array('fullname');
    /*
     * This bit is commented out as this sreg only allows  a limited number of
     * attributes (in the spec), so for now we'll manually add them.
     * In future look at Attribute exchange (AX)

    $datamap = $contentObject->attribute('data_map');
    foreach (array_keys($datamap) as $attribute_id)
    {
      $attribute =& $datamap[$attribute_id];
      $type = $attribute->attribute('is_a');
      if ( in_array($type,array('ezstring', 'eztext')) )
      {
        $identifier = $attribute->attribute('contentclass_attribute_identifier');
        if ($attribute->attribute('is_required'))
          $required_attributes[] = $identifier;
        else
          $optional_attributes[] = $identifier;
      }
    }
    eZDebug::writeDebug( $required_attributes, 'required_attributes' );
    eZDebug::writeDebug( $optional_attributes, 'optional_attributes' );
    */
  
    // go get the auth 
		$consumer = getConsumer();
	  $auth_request = $consumer->begin($openid_url);
    if ($auth_request)
    {
      eZDebug::writeDebug( $auth_request);
 
      $sreg_request = Auth_OpenID_SRegRequest::build($required_attributes, $optional_attributes);
      if ($sreg_request) 
        $auth_request->addExtension($sreg_request);

			$redirect_url = $auth_request->redirectURL(getTrustRoot(), getReturnTo('register'));
  		if (Auth_OpenID::isFailure($redirect_url)) {
				eZDebug::writeDebug("Could not redirect to server: " . $redirect_url->message);
			} else {
				// Send redirect.
		    $http->setSessionVariable('OpenIDAction', 'register');
				return $Module->redirectTo($redirect_url);
			}
    }
    else
    {
      $errMsg = ezi18n( 'design/standard/user', 'The URL (%1) is not valid', null, array( $openid_url ) );
      $checkErrNodeId = true;
      eZDebug::writeError( "$errMsg" );
      $tpl->setVariable( 'errMsg', $errMsg );
      $tpl->setVariable( 'checkErrNodeId', $checkErrNodeId );
      $http->removeSessionVariable( "OpenIDURL" );
      $http->removeSessionVariable( "RegisterUserID" );

      eZDebug::writeDebug( $auth_request  );
    }
    // *** OpenID stops here
}
else if ( $http->hasSessionVariable( "RegisterUserID" ) )
{
    $userID = $http->sessionVariable( "RegisterUserID" );
}
else if ( $http->hasPostVariable( "UserID" ) )
{
    $userID = $http->postVariable( "UserID" );
}

if ($http->hasSessionVariable('OpenIDAction'))
{
   
  $action = $http->sessionVariable('OpenIDAction');
  $http->removeSessionVariable( "OpenIDAction" );
  if ($action == 'register')
  {
    $contentObject = eZContentObject::fetch( $userID );
    eZDebug::writeDebug( $contentObject );
     
    $objectID = $contentObject->attribute( 'id' );

    // Grab the URL from the session
    $openid_url = $http->sessionVariable( "OpenIDURL" );
    // Complete the authentication process using the server's
    // response.
    $consumer = getConsumer();
    $return_to = getReturnTo('register');
    $response = $consumer->complete($return_to);
    eZDebug::writeDebug( $response );
 
    

    // Check the response status.
    if ($response->status == Auth_OpenID_CANCEL) 
    {
      // This means the authentication was cancelled.
      $errMsg = ezi18n( 'design/standard/user', 'OpenID validation of (%1) was canceled: %2', null, array( $openid_url, $response->message ) );
      $checkErrNodeId = true;
      eZDebug::writeError( "$errMsg" );
      $tpl->setVariable( 'errMsg', $errMsg );
      $tpl->setVariable( 'checkErrNodeId', $checkErrNodeId );
      $http->removeSessionVariable( "OpenIDURL" );
      $http->removeSessionVariable( "RegisterUserID" );
    } 
    elseif ($response->status == Auth_OpenID_FAILURE) 
    {
      // Authentication failed; display the error message.
      $error_msg = "OpenID authentication failed: " . $response->message;
      $errMsg = ezi18n( 'design/standard/user', 'OpenID validation of (%1) failed :%2', null, array( $openid_url , $response->message ) );
      $checkErrNodeId = true;
      eZDebug::writeError( "$errMsg" );
      $tpl->setVariable( 'errMsg', $errMsg );
      $tpl->setVariable( 'checkErrNodeId', $checkErrNodeId );
      $http->removeSessionVariable( "OpenIDURL" );
      $http->removeSessionVariable( "RegisterUserID" );
    }
    elseif ($response->status == Auth_OpenID_SUCCESS) 
    {
      $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);
      eZDebug::writeDebug( $sreg_resp );
      $sreg = $sreg_resp->contents();
      eZDebug::writeDebug( $sreg );
      // Load up user
      $datamap = $contentObject->attribute('data_map');
      foreach (array_keys($datamap) as $attribute_id)
      {
/* The intention here is to pre populate the registration form with the
 * user info returned from the sreg request.  There appears to be an issue in
 * that if the login is present then the user doesn't get the opportunity to
 * change it.
 *
 * Need to split fullname into first & given and pre populate there
*/
        $attribute =& $datamap[$attribute_id];
        $type = $attribute->attribute('is_a');
        if ( $type == 'ezuser' )
        {
          eZDebug::writeDebug( $attribute );
          $attributeContent = $attribute->attribute('content');
#          $attributeContent->setAttribute('login', $sreg['nickname'] );
          if (isset($sreg['email']))
            $attributeContent->setAttribute('email', $sreg['email'] );
          $attributeContent->store();
          eZDebug::writeDebug( $attributeContent );
        }
      }
    }
  }
}
$Params['ObjectID'] = $userID;

$Module->addHook( 'post_publish', 'registerSearchObject', 1, false );

if ( !function_exists( 'checkContentActions' ) )
{
    function checkContentActions( $module, $class, $object, $version, $contentObjectAttributes, $EditVersion, $EditLanguage )
    {
        if ( $module->isCurrentAction( 'Cancel' ) )
        {
 
            //include_once( 'kernel/classes/ezredirectmanager.php' );
            eZRedirectManager::redirectTo( $module, '/' );

            $EditVersion = (int)$EditVersion;
            $objectID = $object->attribute( 'id' );
            $versionCount= $object->getVersionCount();
            $db = eZDB::instance();
            $db->begin();
            $db->query( "DELETE FROM ezcontentobject_link
                         WHERE from_contentobject_id=$objectID AND from_contentobject_version=$EditVersion" );
            $db->query( "DELETE FROM eznode_assignment
                         WHERE contentobject_id=$objectID AND contentobject_version=$EditVersion" );
            $version->removeThis();
            foreach ( $contentObjectAttributes as $contentObjectAttribute )
            {
                $objectAttributeID = $contentObjectAttribute->attribute( 'id' );
                $version = $contentObjectAttribute->attribute( 'version' );
                if ( $version == $EditVersion )
                {
                    $contentObjectAttribute->removeThis( $objectAttributeID, $version );
                }
            }
            if ( $versionCount == 1 )
            {
                $object->purge();
            }
            $db->commit();
            $http = eZHTTPTool::instance();
            $http->removeSessionVariable( "RegisterUserID" );
            $http->removeSessionVariable( "OpenIDURL" );
            return eZModule::HOOK_STATUS_CANCEL_RUN;
        }

        if ( $module->isCurrentAction( 'Publish' ) )
        {
            $http = eZHTTPTool::instance();

            $user = eZUser::currentUser();
            //include_once( 'lib/ezutils/classes/ezoperationhandler.php' );
            $operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $object->attribute( 'id' ),
                                                                                         'version' => $version->attribute( 'version') ) );

            $object = eZContentObject::fetch( $object->attribute( 'id' ) );

            // Check if user should be enabled and logged in
            unset($user);
            $user = eZUser::fetch( $object->attribute( 'id' ) );
            $user->loginCurrent();

            $receiver = $user->attribute( 'email' );
            $mail = new eZMail();
            if ( !$mail->validate( $receiver ) )
            {
            }
            require_once( "kernel/common/template.php" );
            //include_once( 'lib/ezutils/classes/ezmail.php' );
            //include_once( 'lib/ezutils/classes/ezmailtransport.php' );
            $ini = eZINI::instance();
            $tpl = templateInit();
            $tpl->setVariable( 'user', $user );
            $tpl->setVariable( 'object', $object );
            $hostname = eZSys::hostname();
            $tpl->setVariable( 'hostname', $hostname );
            $password = $http->sessionVariable( "GeneratedPassword" );

            $tpl->setVariable( 'password', $password );

            // Check whether account activation is required.
            $verifyUserEmail = $ini->variable( 'UserSettings', 'VerifyUserEmail' );

            if ( $verifyUserEmail == "enabled" ) // and if it is
            {
                // Disable user account and send verification mail to the user
                $userSetting = eZUserSetting::fetch( $user->attribute( 'contentobject_id' ) );
                $userSetting->setAttribute( 'is_enabled', 0 );
                $userSetting->store();

                // Log out current user
                eZUser::logoutCurrent();

                // Create enable account hash and send it to the newly registered user
                $hash = md5( time() . $user->attribute( 'contentobject_id' ) );
                //include_once( "kernel/classes/datatypes/ezuser/ezuseraccountkey.php" );
                $accountKey = eZUserAccountKey::createNew( $user->attribute( 'contentobject_id' ), $hash, time() );
                $accountKey->store();

                $tpl->setVariable( 'hash', $hash );
            }

            $templateResult = $tpl->fetch( 'design:user/registrationinfo.tpl' );
            $emailSender = $ini->variable( 'MailSettings', 'EmailSender' );
            if ( !$emailSender )
                $emailSender = $ini->variable( 'MailSettings', 'AdminEmail' );
            $mail->setSender( $emailSender );
            $mail->setReceiver( $receiver );
            $subject = ezi18n( 'kernel/user/register', 'Registration info' );
            if ( $tpl->hasVariable( 'subject' ) )
                $subject = $tpl->variable( 'subject' );
            $mail->setSubject( $subject );
            $mail->setBody( $templateResult );
            $mailResult = eZMailTransport::send( $mail );

            $feedbackTypes = $ini->variableArray( 'UserSettings', 'RegistrationFeedback' );
            foreach ( $feedbackTypes as $feedbackType )
            {
                switch ( $feedbackType )
                {
                    case 'email':
                    {
                        $mail = new eZMail();
                        $tpl->resetVariables();
                        $tpl->setVariable( 'user', $user );
                        $tpl->setVariable( 'object', $object );
                        $tpl->setVariable( 'hostname', $hostname );
                        $templateResult = $tpl->fetch( 'design:user/registrationfeedback.tpl' );

                        $feedbackReceiver = $ini->variable( 'UserSettings', 'RegistrationEmail' );
                        if ( !$feedbackReceiver )
                            $feedbackReceiver = $ini->variable( "MailSettings", "AdminEmail" );

                        $subject = ezi18n( 'kernel/user/register', 'New user registered' );
                        if ( $tpl->hasVariable( 'subject' ) )
                            $subject = $tpl->variable( 'subject' );
                        if ( $tpl->hasVariable( 'email_receiver' ) )
                            $feedbackReceiver = $tpl->variable( 'email_receiver' );

                        $mail->setReceiver( $feedbackReceiver );
                        $mail->setSubject( $subject );
                        $mail->setBody( $templateResult );
                        $mailResult = eZMailTransport::send( $mail );
                    } break;
                    default:
                    {
                        eZDebug::writeWarning( "Unknown feedback type '$feedbackType'", 'user/register' );
                    }
                }
            }

            $openid_url = $http->sessionVariable( "OpenIDURL" );
            $http->removeSessionVariable( "OpenIDURL" );

            $openid_user =& openid_user::create();
            $openid_user->setAttribute('openid_url', $openid_url);
            $openid_user->setAttribute('user_id', $object->attribute( 'id' ) );
            $openid_user->store();
            eZDebug::writeDebug( "Successfully verified and registered openID URL ".$openid_url);

            $msg = "Successfully verified and registered openID URL ".$openid_url;

            $http->removeSessionVariable( "GeneratedPassword" );
            $http->removeSessionVariable( "RegisterUserID" );

            // check for redirectionvariable
            if ( $http->hasSessionVariable( 'RedirectAfterUserRegister' ) )
            {
                $module->redirectTo( $http->sessionVariable( 'RedirectAfterUserRegister' ) );
                $http->removeSessionVariable( 'RedirectAfterUserRegister' );
            }
            else if ( $http->hasPostVariable( 'RedirectAfterUserRegister' ) )
            {
                $module->redirectTo( $http->postVariable( 'RedirectAfterUserRegister' ) );
            }
            else
            {
                $module->redirectTo( '/user/success/' );
            }
        }
    }
}
$Module->addHook( 'action_check', 'checkContentActions' );

$OmitSectionSetting = true;

$includeResult = include( 'kernel/content/attribute_edit.php' );
if ( $includeResult != 1 )
{
    return $includeResult;
}
$ini = eZINI::instance();

if ( $ini->variable( 'SiteSettings', 'LoginPage' ) == 'custom' )
{
    $Result['pagelayout'] = 'loginpagelayout.tpl';
}

$Result['path'] = array( array( 'url' => false,
                                'text' => ezi18n( 'kernel/user', 'User' ) ),
                         array( 'url' => false,
                                'text' => ezi18n( 'kernel/user', 'Register' ) ) );

?>
