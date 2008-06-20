<?php
include_once( "lib/ezdb/classes/ezdb.php" );
include_once( 'kernel/classes/ezpersistentobject.php' );
include_once( 'extension/openid/classes/openid_lib_wrapper.php' );

class openid_user extends eZPersistentObject
{

  function openid_user(&$row)
  {
    $this->eZPersistentObject( $row );
  }

  static function definition()
  {
    return array( 'fields' => array(
                    'id' => array(
                      'name' => 'id',
                      'datatype' => 'integer',
                      'default' => 0,
                      'required' => true ),
                    'openid_url' => array(
                      'name' => 'openid_url',
                      'datatype' => 'string',
                      'default' => 0,
                      'required' => true ),
                    'user_id' => array(
                      'name' => 'user_id',
                      'datatype' => 'integer',
                      'default' => 0,
                      'required' => true ),
                    'created_at' => array(
                      'name' => 'created_at',
                      'datatype' => 'integer',
                      'default' => 0,
                      'required' => true ),
                    'last_login' => array(
                      'name' => 'last_login',
                      'datatype' => 'integer',
                      'default' => 0,
                      'required' => true ),
                  ),
                  'function_attributes' => array(
                      'user' => 'getUser',
                  ),
                  'keys' => array( 'id' ),
                  "increment_key" => "id",
                  'sort' => array( 'openid_url' => 'asc' ),
                  'class_name' => 'openid_user',
                  'name' => 'openid_users' 
                );
  }

  static function &fetch( $id )
  {
    $conds = array( 'openid_url' => $id);
    $object = openid_user::fetchObject( openid_user::definition(), null, $conds);
    return $object;
  }

  static function &fetchByURL( $id )
  {
    $conds = array( 'openid_url' => $id);
    $object = openid_user::fetchObject( openid_user::definition(), null, $conds);
    return $object;
  }


  static function &fetchByUserID( $id )
  {
    $conds = array( 'user_id' => $id);
    $objects = openid_user::fetchObjectList( openid_user::definition(), null, $conds);
    return $objects;
  }

  function &getUser()
  {
    $user = eZUser::fetch($this->attribute('user_id'));
    return $user;
  }

  static function &create($row=array())
  {
    $object = new openid_user($row);
    $object->setAttribute('created_at', time() );
    return $object;
  }


    /*!
       Updates the user's last visit timestamp
    */
  function updateLastVisit()
  {
    if ( isset( $GLOBALS['openIDUpdatedLastVisit'] ) )
      return;

    $this->setAttribute('last_login', time() );
    $this->store();
    $GLOBALS['openIDUpdatedLastVisit'] = true;
  }

  static function normaliseURL($url)
  {
    $url =  Auth_OpenID_urinorm($url);
    // TODO Remove trailing slash if not present
    return $url;
  }
}

?>
