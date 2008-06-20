<div class="openid_login">
  <strong>Have an OpenID?</strong>
  Sign in below:
  <form action={"/openid/login"|ezurl} method="post">
    <input id="openid_url" type="text" style="margin-bottom: 0pt;" size="30" value="{$User:openid_url|wash}" name="openid_url"/>
    <input type="hidden" name="RedirectURI" value="{$User:redirect_uri|wash}" />
    <input class="button" type="submit" name="LoginButton" value="{'Login'|i18n('design/ezwebin/user/login','Button')}" tabindex="1" />
    <input id="OpenIDRegister" class="button" type="submit" tabindex="1" value="Register" name="OpenIDRegister"/>
    <br/>
    <small>e.g. http://username.myopenid.com</small>
  </form>
</div>
