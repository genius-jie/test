{* 
 Testlink Open Source Project - http://testlink.sourceforge.net/ 
 @filesource  mainPageLeft.tpl
 Purpose: smarty template - main page / site map                 
                                                                 
 @internal revisions
*}
{lang_get var='labels' s='title_product_mgmt,href_tproject_management,href_admin_modules,
   href_assign_user_roles,href_cfields_management,system_config,
   href_cfields_tproject_assign,href_keywords_manage,
   title_user_mgmt,href_user_management,
   href_roles_management,title_requirements,
   href_req_spec,href_req_assign,link_report_test_cases_created_per_user,
   title_test_spec,href_edit_tc,href_browse_tc,href_search_tc,
   href_search_req, href_search_req_spec,href_inventory,
   href_platform_management, href_inventory_management,
   href_print_tc,href_keywords_assign, href_req_overview,
   href_print_req,title_plugins,title_documentation,href_issuetracker_management,
   current_test_plan,ok,testplan_role,msg_no_rights_for_tp,
             title_test_execution,href_execute_test,href_rep_and_metrics,
             href_update_tplan,href_newest_tcversions,title_plugins,
             href_my_testcase_assignments,href_platform_assign,
             href_tc_exec_assignment,href_plan_assign_urgency,
             href_upd_mod_tc,title_test_plan_mgmt,title_test_case_suite,
             href_plan_management,href_assign_user_roles,
             href_build_new,href_plan_mstones,href_plan_define_priority,
             href_metrics_dashboard,href_add_remove_test_cases,
             href_exec_ro_access,
   href_codetracker_management,href_reqmgrsystem_management,href_req_monitor_overview'}


{$planView="lib/plan/planView.php"}
{$buildView="lib/plan/buildView.php?tplan_id="}
{$mileView="lib/plan/planMilestonesView.php"}
{$platformAssign="lib/platforms/platformsAssign.php?tplan_id="}
{* Show / Hide section logic *}
{$display_left_block_1=false}
{$display_left_block_2=false}
{$display_left_block_3=false}
{$display_left_block_4=false}
{$display_left_block_5=$tlCfg->userDocOnDesktop}
{$display_left_block_top = false}
{$display_left_block_bottom = false}

{if $gui->testprojectID && 
   ($gui->grants.project_edit == "yes" || 
    $gui->grants.tproject_user_role_assignment == "yes" ||
    $gui->grants.cfield_management == "yes" || 
    $gui->grants.platform_management == "yes" || 
    $gui->grants.keywords_view == "yes")}
    
    {$display_left_block_1=true}
{/if}

{if $gui->testprojectID && 
   ($gui->grants.cfield_management || 
    $gui->grants.cfield_assignment || 
    $gui->grants.issuetracker_management ||
    $gui->grants.codetracker_management || 
    $gui->grants.issuetracker_view || 
    $gui->grants.codetracker_view)}
   {$display_left_block_2=true}
{/if}

{if $gui->testprojectID && $gui->opt_requirements == TRUE && 
    ($gui->grants.reqs_view == "yes" || $gui->grants.reqs_edit == "yes" || $gui->grants.monitor_req == "yes" || $gui->grants.req_tcase_link_management == "yes")}
    {$display_left_block_3=true}
{/if}

{if $gui->testprojectID && $gui->grants.view_tc == "yes"}
    {$display_left_block_4=true}
{/if}

{$display_left_block_top=false}
{$display_left_block_bottom=false}

{if isset($gui->plugins.EVENT_LEFTMENU_TOP) &&  $gui->plugins.EVENT_LEFTMENU_TOP}
  {$display_left_block_top=true}
{/if}
{if isset($gui->plugins.EVENT_LEFTMENU_BOTTOM) &&  $gui->plugins.EVENT_LEFTMENU_BOTTOM}
  {$display_left_block_bottom=true}
{/if}



{$divStyle="width:300px;padding: 0px 0px 0px 10px;"}
{$aStyle="padding: 10px 15px;font-size:16px"}

{$projectView="lib/project/projectView.php"}
{$usersAssign="lib/usermanagement/usersAssign.php?featureType=testproject&featureID="}
{$cfAssignment="lib/cfields/cfieldsTprojectAssign.php"}
{$keywordsAssignment="lib/keywords/keywordsView.php?tproject_id="}
{$platformsView="lib/platforms/platformsView.php"}
{$cfieldsView="lib/cfields/cfieldsView.php"}
{$issueTrackerView="lib/issuetrackers/issueTrackerView.php"}
{$codeTrackerView="lib/codetrackers/codeTrackerView.php"}
{$reqOverView="lib/requirements/reqOverview.php"}
{$reqMonOverView="lib/requirements/reqMonitorOverview.php?tproject_id="}
{$tcSearch="lib/testcases/tcSearch.php?doAction=userInput&tproject_id="}
{$tcCreatedUser="lib/results/tcCreatedPerUserOnTestProject.php?do_action=uinput&tproject_id="}
{$assignReq="lib/general/frmWorkArea.php?feature=assignReqs"}
{$inventoryView="lib/inventory/inventoryView.php"}


<div class="vertical_menu" style="float: left; margin:0px 10px 10px 0px; width: 320px;">

  {if $display_left_block_top}
    {if isset($gui->plugins.EVENT_LEFTMENU_TOP)}
      <div class="list-group" style="{$divStyle}" id="plugin_left_top">
        {foreach from=$gui->plugins.EVENT_LEFTMENU_TOP item=menu_item}
		  <a href="{$menu_item['href']}" class="list-group-item" style="{$aStyle}">{$menu_item['label']}</a>
          <br/>
        {/foreach}
      </div>
    {/if}
  {/if}



 
 

     
{if $display_left_block_1}
  <div class="list-group" style="{$divStyle}">
  {*张立杰测试项目管理*}
  {if $gui->grants.project_edit == "yes"}
   			<div class="list-group-item" style="{$aStyle}">
	     		<a href="{$projectView}"  style="float:left;color:#555; text-decoration: none;">
	      		     {$labels.href_tproject_management}</a>
				     {include file="inc_help.tpl" helptopic="hlp_testObj" show_help_icon=true 
		              inc_help_style="float:left;margin:5px 0px 0px 5px"}
	            <div style="clear:both"></div>
   		    </div>
	{/if}

{*张立杰测试计划管理*}
   {if $gui->grants.mgt_testplan_create == "yes"}
   			<div class="list-group-item" style="{$aStyle}">
	     		<a href="{$planView}"  style="float:left;color:#555; text-decoration: none;">
	      		     {$labels.href_plan_management}</a>
				     {include file="inc_help2.tpl" helptopic="hlp_testPlan" show_help_icon=true 
		              inc_help_style="float:left;margin:5px 0px 0px 5px"}
	            <div style="clear:both"></div>
   		    </div>
	{/if}
{*张立杰测试版本管理*}
	  {if $gui->grants.testplan_create_build == "yes" and $gui->countPlans > 0}
   			<div class="list-group-item" style="{$aStyle}">
       		<a href="{$buildView}{$gui->testplanID}"   style="float:left;color:#555; text-decoration: none;">{$labels.href_build_new}</a>
		        
			     {include file="inc_help3.tpl" helptopic="hlp_customFields" show_help_icon=true 
	              inc_help_style="float:left;margin:5px 0px 0px 5px"}
              <div  class="zlj1" style="clear:both"></div>
              </div>
	    {/if}
 {*张立杰测试用例管理*}
	   {if $gui->testprojectID && $gui->grants.view_tc == "yes"}
	    <script type="text/javascript">
		    function display_left_block_4()
		    {
		        var p4 = new Ext.Panel({
		                                title: '{$labels.title_test_spec}',
		                                collapsible:false,
		                                collapsed: false,
		                                draggable: false,
		                                contentEl: 'testspecification_topics',
		                                baseCls: 'x-tl-panel',
		                                bodyStyle: "background:#c8dce8;padding:3px;",
		                                renderTo: 'menu_left_block_{$menuLayout.testSpecification}',
		                                width:'100%'
		                                });
		     }
		   </script>
   			<div class="list-group-item" style="{$aStyle}">
	     		<a href="{$gui->launcher}?feature=editTc"  style="float:left;color:#555; text-decoration: none;">
	      		     {if $gui->grants.modify_tc eq "yes"}
		          {lang_get s='href_edit_tc'}
			       {else}
			          {lang_get s='href_browse_tc'}
			       {/if}
			    </a>
			 {include file="inc_help4.tpl" helptopic="hlp_testsuite" show_help_icon=true 
		              inc_help_style="float:left;margin:5px 0px 0px 5px"}
	            <div style="clear:both"></div>
   		    </div>
	{/if}   
{*张立杰测试报告管理*}
	   {if $gui->grants.testplan_metrics == "yes"}
   			<div class="list-group-item" style="{$aStyle}">
	     		<a href="{$gui->launcher}?feature=showMetrics"  style="float:left;color:#555; text-decoration: none;">
	      		     {$labels.href_rep_and_metrics}</a>
				     {include file="inc_help5.tpl" helptopic="hlp_testreport" show_help_icon=true 
		              inc_help_style="float:left;margin:5px 0px 0px 5px"}
	            <div style="clear:both"></div>
   		    </div>
		{/if}
 
{*------测试报告-------
{if $gui->grants.testplan_metrics == "yes"}
<a href="{$gui->launcher}?feature=showMetrics" class="list-group-item" style="{$aStyle}">{$labels.href_rep_and_metrics}</a>
{/if}--------- *}
	 {* 张立杰自定义字段管理 *}   
			    {if $gui->grants.cfield_management == "yes"}
			      <a href="{$cfieldsView}" class="list-group-item" style="{$aStyle}">{$labels.href_cfields_management}</a>
			    {/if}
		{*删除平台*}    
		       {if $gui->grants.testplan_add_remove_platforms == "yes"}
		  	  <a href="{$platformAssign}{$gui->testplanID}" class="list-group-item" style="{$aStyle}">{$labels.href_platform_assign}</a>
		    {/if} 
			{*-----------张立杰暂时删除这个	
			    {if $gui->grants.issuetracker_management || $gui->grants.issuetracker_view}
			      <a href="{$issueTrackerView}" class="list-group-item" style="{$aStyle}">{$labels.href_issuetracker_management}</a>
			    {/if}
			
			    {if $gui->grants.codetracker_management || $gui->grants.codetracker_view}
			      <a href="{$codeTrackerView}" class="list-group-item" style="{$aStyle}">
			      {$labels.href_codetracker_management}</a>
			    {/if}
    
		
		
    {if $gui->grants.tproject_user_role_assignment == "yes"}
      <a href="{$usersAssign}{$gui->testprojectID}" class="list-group-item" style="{$aStyle}">{$labels.href_assign_user_roles}</a>
    {/if}
    
    
    {if $gui->grants.keywords_view == "yes"}
      <a href="{$keywordsAssignment}{$gui->testprojectID}" class="list-group-item" style="{$aStyle}">{$labels.href_keywords_manage}</a>
    {/if}

    {if $gui->grants.platform_management || $gui->grants.platform_view}
      <a href="{$platformsView}" class="list-group-item" style="{$aStyle}">{$labels.href_platform_management}</a>
    {/if}
    
    {if $gui->grants.project_inventory_view || $gui->grants.project_inventory_management}
       <a href="{$inventoryView}" class="list-group-item" style="{$aStyle}">{$labels.href_inventory_management}</a>
    {/if}*}


  </div>
{/if}

{if $display_left_block_3}
  <div class="list-group" style="{$divStyle}">
       {if $gui->grants.reqs_view == "yes" || $gui->grants.reqs_edit == "yes" }
          <a href="{$gui->launcher}?feature=reqSpecMgmt" class="list-group-item" style="{$aStyle}">{$labels.href_req_spec}</a>
          <a href="{$reqOverView}" class="list-group-item" style="{$aStyle}">{$labels.href_req_overview}</a>
          <a href="{$gui->launcher}?feature=printReqSpec" class="list-group-item" style="{$aStyle}">{$labels.href_print_req}</a>
          <a href="{$gui->launcher}?feature=searchReq" class="list-group-item" style="{$aStyle}">{$labels.href_search_req}</a>
          <a href="{$gui->launcher}?feature=searchReqSpec" class="list-group-item" style="{$aStyle}">{$labels.href_search_req_spec}</a>
       {/if}
       {if $gui->grants.req_tcase_link_management == "yes"}
          <a href="{$assignReq}" class="list-group-item" style="{$aStyle}">{$labels.href_req_assign}</a>
       {/if}
       {if $gui->grants.monitor_req == "yes"}
          <a href="{$reqMonOverView}{$gui->testprojectID}" class="list-group-item" style="{$aStyle}">{$labels.href_req_monitor_overview}</a>
      {/if}
  </div>
{/if}


  {if $display_left_block_bottom}
    {if isset($gui->plugins.EVENT_LEFTMENU_BOTTOM)}
	  <br/>
	  <div class="list-group" style="{$divStyle}" id="plugin_left_bottom">
        {foreach from=$gui->plugins.EVENT_LEFTMENU_BOTTOM item=menu_item}
		  <a href="{$menu_item['href']}" class="list-group-item" style="{$aStyle}">{$menu_item['label']}</a>
        {/foreach}
      </div>
    {/if}  
  {/if}
  
</div>
