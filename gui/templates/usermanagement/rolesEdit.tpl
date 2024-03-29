{*
TestLink Open Source Project - http://testlink.sourceforge.net/

create/edit user role

@filesource	rolesEdit.tpl
@internal revisions
*}

{include file="inc_head.tpl" openHead="yes" jsValidate="yes" editorType=$gui->editorType}
{include file="inc_jsCheckboxes.tpl"} {* includes also ext.js *}

<script type="text/javascript">
{lang_get var="labels"
          s='btn_save,warning,warning_modify_role,warning_empty_role_name,th_rights,
             error_role_no_rights,caption_possible_affected_users,enter_role_notes,
             title_user_mgmt,caption_define_role,th_mgttc_rights,th_req_rights,
             th_product_rights,th_user_rights,th_kw_rights,th_cf_rights,th_system_rights,
             th_platform_rights,warn_demo,demo_update_role_disabled,th_issuetracker_rights,
             th_rolename,th_tp_rights,btn_cancel'}
             
var alert_box_title = "{$labels.warning|escape:'javascript'}";
var warning_modify_role = "{$labels.warning_modify_role|escape:'javascript'}";
var warning_empty_role_name = "{$labels.warning_empty_role_name|escape:'javascript'}";
var warning_error_role_no_rights = "{$labels.error_role_no_rights|escape:'javascript'}";

function validateForm(f)
{
  if (isWhitespace(f.rolename.value))
  {
      alert_message(alert_box_title,warning_empty_role_name);
      selectField(f, 'rolename');
      return false;
  }

  if( checkbox_count_checked(f.id) == 0)
  {
      alert_message(alert_box_title,warning_error_role_no_rights);
      return false;
  }

  return true;
}
</script>
</head>


<body>
{$cfg_section=$smarty.template|basename|replace:".tpl":""}
{config_load file="input_dimensions.conf" section=$cfg_section}


<h1 class="title">{$labels.title_user_mgmt} - {$labels.caption_define_role}</h1>

{***** TABS *****}
{include file="usermanagement/tabsmenu.tpl" grants=$gui->grants highlight=$gui->highlight}

{include file="inc_update.tpl" user_feedback=$gui->userFeedback}
<div class="workBack">

	<form name="rolesedit" id="rolesedit" method="post" action="lib/usermanagement/rolesEdit.php"
			{if $gui->grants->role_mgmt == "yes"}
				onSubmit="javascript:return validateForm(this);"
			{else}
				onsubmit="return false"
			{/if}
	>
	<input type="hidden" name="tproject_id" id="tproject_id"  value="{$gui->tproject_id}">
	<input type="hidden" name="roleid" value="{$gui->role->dbID}" />
	<table class="common">
		<tr><th>{$labels.th_rolename}
			{if $gui->mgt_view_events eq "yes" && $gui->role->dbID}
				<img style="margin-left:5px;" class="clickable" src="{$smarty.const.TL_THEME_IMG_DIR}/question.gif" onclick="showEventHistoryFor('{$gui->role->dbID}','roles')" alt="{lang_get s='show_event_history'}" title="{lang_get s='show_event_history'}"/>
			{/if}
		</th></tr>
		<tr><td>
			   <input type="text" name="rolename"
			          size="{#ROLENAME_SIZE#}" maxlength="{#ROLENAME_MAXLEN#}" value="{$gui->role->name|escape}"/>
 				 {include file="error_icon.tpl" field="rolename"}
		    </td></tr>
		<tr><th>{$labels.th_rights}</th></tr>
		<tr>
			<td>
				<table>
				<tr>
					<td><fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_tp_rights}</legend>
							{foreach from=$gui->rightsCfg->testplans item=id key=k}
							<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]}/>{$id}<br />
							{/foreach}
						</fieldset>
					</td>
					<td>
						<fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_mgttc_rights}</legend>
						{foreach from=$gui->rightsCfg->testcases item=id key=k}
						<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
						{/foreach}
						</fieldset>
					</td>
					<td>
						<fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_req_rights}</legend>
						{foreach from=$gui->rightsCfg->requirements item=id key=k}
						<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
						{/foreach}
						</fieldset>
					</td>
					<td>
						<fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_product_rights}</legend>
						{foreach from=$gui->rightsCfg->testprojects item=id key=k}
						<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
						{/foreach}
						</fieldset>
					</td>
				</tr>
				<tr>
					<td><fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_user_rights}</legend>
							{foreach from=$gui->rightsCfg->users item=id key=k}
							<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
							{/foreach}
						</fieldset>
					</td>
					<td><fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_kw_rights}</legend>
							{foreach from=$gui->rightsCfg->keywords item=id key=k}
							<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
							{/foreach}
						</fieldset>
					</td>
					<td><fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_cf_rights}</legend>
							{foreach from=$gui->rightsCfg->customfields item=id key=k}
							<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
							{/foreach}
						</fieldset>
					</td>
					<td><fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_system_rights}</legend>
							{foreach from=$gui->rightsCfg->system item=id key=k}
							<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
							{/foreach}
						</fieldset>
					</td>
				</tr>
				<tr>
					<td><fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_platform_rights}</legend>
							{foreach from=$gui->rightsCfg->platforms item=id key=k}
							<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
							{/foreach}
						</fieldset>
					</td>
					<td><fieldset class="x-fieldset x-form-label-left"><legend >{$labels.th_issuetracker_rights}</legend>
							{foreach from=$gui->rightsCfg->issuetrackers item=id key=k}
							<input class="tl-input" type="checkbox" name="grant[{$k}]" {$gui->checkboxStatus[$k]} />{$id}<br />
							{/foreach}
						</fieldset>
					</td>
				</tr>

			</table>
			</td>
		</tr>
		<tr><th>{$labels.enter_role_notes}</th></tr>
		<tr>
			<td width="80%">{$gui->notes}</td>
		</tr>

	</table>
	
	{$submitEnabled="1"}
	{if $tlCfg->demoMode}
		{if $gui->operation == 'doUpdate'}
			{$submitEnabled="0"}
		{/if}
  {/if}	
	<div class="groupBtn">
	{if $gui->grants->role_mgmt == "yes" && $gui->role->dbID != $smarty.const.TL_ROLES_NO_RIGHTS}
		{if $submitEnabled}
			<input type="hidden" name="doAction" value="{$gui->operation}" />
			<input type="submit" name="role_mgmt" value="{$labels.btn_save}"
		         {if $gui->role != null && $gui->affectedUsers neq null} onClick="return modifyRoles_warning()"{/if}
			/>
		{else}
			{$labels.demo_update_role_disabled}<br>
		{/if}
	
	{/if}
	
		<input type="button" name="cancel" value="{$labels.btn_cancel}"
			onclick="javascript: location.href=fRoot+'lib/usermanagement/rolesView.php?tproject_id={$gui->tproject_id}';" />
	</div>
	<br />
	{if $gui->affectedUsers neq null}
		<table class="common" style="width:50%">
		<caption>{$labels.caption_possible_affected_users}</caption>
		{foreach from=$gui->affectedUsers item=user}
		<tr>
			<td>{$user->getDisplayName()|escape}</td>
		</tr>
		{/foreach}
		</table>
	{/if}
	</form>

</div>

</body>
</html>