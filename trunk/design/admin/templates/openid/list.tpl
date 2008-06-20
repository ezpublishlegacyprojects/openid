<form method="post" action={'/openid/list'|ezurl}>
<div class="context-block">
  <div class="box-header"><div class="box-tc"><div class="box-ml"><div class="box-mr"><div class="box-tl"><div class="box-tr">
    <h1 class="context-title">OpenIDs for {$user.contentobject.name|wash}</h1>
    <div class="header-mainline"></div>
  </div></div></div></div></div></div>
  <div class="box-bc"><div class="box-ml"><div class="box-mr"><div class="box-bl"><div class="box-br"><div class="box-content">
<div class="box-ml"><div class="box-mr"><div class="box-content">

{if $msg}<p>{$msg|wash}</p>{/if}
{if $error_msg}<p class="error">{$error_msg|wash}</p>{/if}

{if $openid_urls}
        <table cellspacing="0" class="list">
          <tr>
            <th class="remove">Delete</th>
            <th class="wide">OpenID URL</th>
            <th class="created">Created</th>
            <th class="modified">Last Login</th>
          </tr>
  {foreach $openid_urls as $openid_url sequence array(bglight, bgdark) as $class}
          <tr class="{$class}">
            <td><input type="checkbox" name="DeleteIDArray[]" value="{$openid_url.id}" /></td>
            <td><a href={$openid_url.openid_url}>{$openid_url.openid_url|wash}</a></td>
            <td>{$openid_url.created_at|l10n( shortdatetime )}</td>
            <td>{if $openid_url.last_login|eq(0)}Never logged in{else}{$openid_url.last_login|l10n( shortdatetime )}{/if}</td>
          </tr>
  {/foreach}
        </table>
{else}
<p>You don't have any registered OpenID's</p>
{/if}
      </div></div></div>
      <div class="controlbar"><div class="box-bc"><div class="box-ml"><div class="box-mr"><div class="box-tc"><div class="box-bl"><div class="box-br">
        <div class="block">
          <input type="submit" name="RemoveSelected" value="Remove Selected" class="button"/>
          <input id="openid_url" type="text" style="margin-bottom: 0pt;" size="30" value="" name="openid_url"/>
          <input type="submit" name="RegisterNew" value="Register New OpenID" class="button" />
        </div>
      </div></div></div></div></div></div></div>

  </div></div></div></div></div></div>
</div>
</form>
