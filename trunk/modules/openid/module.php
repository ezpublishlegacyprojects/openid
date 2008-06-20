<?php
$Module = array( "name" => "OpenID" );

$ViewList = array();
$ViewList["login"] = array( 
    'functions'               => array( 'login' ),
    'script'                  => 'login.php',
    'ui_context'              => 'authentication',
    'default_navigation_part' => 'ezopenidnavigationpart',
    'default_action'          => array( 
        array( 'name'       => 'Login',
               'type'       => 'post',
               'parameters' => array( 'openid_url') ) ),
    'single_post_actions'     => array( 'LoginButton' => 'Login' ,
                                        'OpenIDRegister' => 'Register'),
    'post_action_parameters' => array( 
      'Login' => 
        array( 'OpenIDURL'       => 'openid_url',
               'UserRedirectURI' => 'RedirectURI' ),
      'Register' => 
        array( 'OpenIDURL'       => 'openid_url',
               'UserRedirectURI' => 'RedirectURI' ),
     ),
    'params' => array( ) 
);


$ViewList["register"] = array( 
    'script'                  => 'register.php',
    'default_navigation_part' => 'ezopenidnavigationpart',
    'ui_context'              => 'edit',
    'single_post_actions' => array( 'PublishButton' => 'Publish',
                                    'CancelButton' => 'Cancel',
                                    'CustomActionButton' => 'CustomAction' ) 
);

$ViewList["list"] = array( 
    'script'                  => 'list.php',
    'default_navigation_part' => 'ezopenidnavigationpart',
    'single_post_actions'     => array( 'RemoveSelected' => 'Remove',
                                        'RegisterNew'    => 'Register' ),
    'post_action_parameters' => array( 
      'Remove' => 
        array( 'DeleteIDArray' => 'DeleteIDArray'),
      'Register' => 
        array('OpenIDURL' => 'openid_url'))
);


?>
