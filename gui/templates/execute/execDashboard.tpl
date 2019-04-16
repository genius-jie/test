{*
TestLink Open Source Project - http://testlink.sourceforge.net/

@filesource	execDashboard.tpl
@internal revisions
@since 1.9.10
*}
{$title_sep=$smarty.const.TITLE_SEP}
{$title_sep_type3=$smarty.const.TITLE_SEP_TYPE3}
{lang_get var='labels'
          s='build_is_closed,testplan_th_sprint_name,test_cases_cannot_be_executed,build,builds_notes,testplan,
             test_plan_notes,platform,platform_description'}

{$cfg_section=$smarty.template|basename|replace:".tpl":""}
{config_load file="input_dimensions.conf" section=$cfg_section}

{include file="inc_head.tpl" popup='yes' openHead='yes'}
{if #ROUND_EXEC_HISTORY# || #ROUND_TC_TITLE# || #ROUND_TC_SPEC#}
  {$round_enabled=1}
  <script language="JavaScript" src="{$basehref}gui/niftycube/niftycube.js" type="text/javascript"></script>
{/if}
 {$ll = $tlCfg->gui->planView->pagination->length}
{include file="DataTables.inc.tpl" DataTablesOID="item_view"
                                     DataTableslengthMenu=$ll }
</head>
<body>

<h1 class="title">
{$gui->pageTitlePrefix}  
{$labels.testplan} {$gui->testplan_name|escape} {$title_sep_type3} {$labels.build} {$gui->build_name|escape}
{if $gui->platform_info.name != ""}
  {$title_sep_type3}{$labels.platform}{$title_sep}{$gui->platform_info.name|escape}
{/if}
</h1>
<div id="main_content" class="workBack">
  {if $gui->build_is_open == 0}
    <div class="messages" style="align:center;">
    {$labels.build_is_closed}<br />
    {$labels.test_cases_cannot_be_executed}
    </div>
    <br />
  {/if}

  <div style="color: rgb(21, 66, 139);font-weight: bold;font-size: 11px;font-family: tahoma,arial,verdana,sans-serif;">
  {$labels.testplan} {$gui->testplan_name|escape}
  </div>
  <div id="testplan_notes" class="exec_additional_info">
  {if $gui->testPlanEditorType == 'none'}{$gui->testplan_notes|nl2br}{else}{$gui->testplan_notes}{/if}
  {if $gui->testplan_cfields neq ''} <div id="cfields_testplan" class="custom_field_container">{$gui->testplan_cfields}</div>{/if}
  </div>

  {if $gui->platform_info.id > 0}
    <div style="color: rgb(21, 66, 139);font-weight: bold;font-size: 11px;font-family: tahoma,arial,verdana,sans-serif;">
    {$labels.platform} {$gui->platform_info.name|escape}
    </div>
    <div id="platform_notes" class="exec_additional_info">
	{if $gui->platformEditorType == 'none'}{$gui->platform_info.notes|nl2br}{else}{$gui->platform_info.notes}{/if}
    </div>
  {/if}

  <div style="color: rgb(21, 66, 139);font-weight: bold;font-size: 11px;font-family: tahoma,arial,verdana,sans-serif;">
  {$labels.build} {$gui->build_name|escape}
  </div>
  <div id="build_notes" class="exec_additional_info">
  {if $gui->buildEditorType == 'none'}{$gui->build_notes|nl2br}{else}{$gui->build_notes}{/if}
  {if $gui->build_cfields != ''} <div id="cfields_build" class="custom_field_container">{$gui->build_cfields}</div>{/if}
  </div>


 {if $gui->testplan_sprint_name != ''}
  <div style="color: rgb(21, 66, 139);font-weight: bold;font-size: 11px;font-family: tahoma,arial,verdana,sans-serif;">
  {$labels.testplan_th_sprint_name}
  </div>
  <br>
  <div id="sprint_name" class="exec_additional_info">  
  {$gui->testplan_sprint_name}
  </div>
  <br>
  <div style="color: rgb(21, 66, 139);font-weight: bold;font-size: 14px;font-family: tahoma,arial,verdana,sans-serif;">
  bug统计：
  </div>
  <div>
	<table id='count_view' class="simple_tableruler">
    <thead>
    <tr>
      {foreach $gui->mycount as $key=>$value}
      <th>{$key}</th>
      {/foreach}
      <th style="background-color: #add8e6;">统计(bug总数)</th>
    </tr>
    </thead>
    <tbody>
    <tr>
    {foreach $gui->mycount as $key=>$value}
      <td>
      {$value}       
      </td>    
    {/foreach}
    <td>
    {count($gui->bugs)}
    </td>  
    </tr>
    </tbody>
  </table>

</div>

<br>
  <div style="color: rgb(21, 66, 139);font-weight: bold;font-size: 14px;font-family: tahoma,arial,verdana,sans-serif;">
  bug率统计：
  </div>
  <div>
    <table id='bugrate_view' class="simple_tableruler">
    <thead>
    <tr>
      <th style="background-color: #add8e6;">用例数</th>
      <th style="background-color: #add8e6;">bug数</th>
      <th style="background-color: #add8e6;">bug率</th>
    </tr>
    </thead>
    <tbody>
    <tr>
    <td>{$gui->testcasecount}</td> 
    <td>{count($gui->bugs)}</td> 
    {if $gui->testcasecount==0}
        <td>请先添加用例到测试计划</td> 
    {else}
        <td>{floor($gui->bugR*5/$gui->testcaseR*1000)/1000}</td> 
    {/if}    
    </tr>
    </tbody>
    </table>
  </div>
  {/if}
  
</div>
{if $gui->testplan_sprint_name != ''}
<h1>关联bug : {count($gui->bugs)}</h1>

<div>

	
	<table id='item_view' class="simple_tableruler sortable">
    <thead>
    <tr>
      <th title="关键字" style="width:8%;">{$tlImages.sort_hint}bugID</th>
      <th title="bug描述">{$tlImages.sort_hint}bug描述</th>
      <th title="状态" style="width:8%;">{$tlImages.sort_hint}状态</th>
      <th title="报告者" style="width:6%;">{$tlImages.sort_hint}测试</th>
      <th title="处理者" style="width:6%;">{$tlImages.sort_hint}开发</th>
    </tr>
    </thead>
    <tbody>
    {foreach item=bug from=$gui->bugs}
    <tr style="font-size:100%;line-height:25px;">
      <td>
      {$bug.id}      
      </td>
      <td>
        {$bug.link_to_bts}
      </td>
      <td>
        {$bug.status}
      </td>
      <td>
        {$bug.reporter}
      </td>
      <td>
        {$bug.assign}
      </td>
    </tr>
    {/foreach}
    </tbody>
  </table>
</div>
</div>
{/if}
</body>
</html>