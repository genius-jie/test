<?php

/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 *
 * @filesource  jiradbInterface.class.php
 * @since 1.9.6
 *
 * @internal revision
 * @since 1.9.10
 * 
 **/

class jiradbInterface extends issueTrackerInterface
{

    var $defaultResolvedStatus;

    var $dbSchema;

    var $support;

    /**
     * Construct and connect to BTS.
     *
     * @param str $type
     *            (see tlIssueTracker.class.php $systems property)
     * @param xml $cfg            
     *
     */
    function __construct($type, $config, $name)
    {
        // connect() to DATABASE is done here
        parent::__construct($type, $config, $name);
        
        if (! $this->isConnected()) {
            return false;
        }
        
        $this->methodOpt['buildViewBugLink'] = array(
            'addSummary' => true,
            'colorByStatus' => true
        );
        $this->interfaceViaDB = true;
        
        $this->support = new jiraCommons();
        $this->support->guiCfg = array(
            'use_decoration' => true
        );
        
        // Tables used
        $this->dbSchema = new stdClass();
        $this->dbSchema->issues = 'jiraissue';
        $this->dbSchema->status = 'issuestatus';
        $this->dbSchema->project = 'project';
        // iris 新增数据库表查询
        $this->dbSchema->sprint = 'AO_60DB71_SPRINT';
        $this->dbSchema->user = 'AO_2D3BEA_USER_INDEX';
        $this->dbSchema->cfv = 'customfieldvalue';
        
        $this->getStatuses();
        if (property_exists($this->cfg, 'statuscfg')) {
            $this->setStatusCfg();
        }
        
        if (! property_exists($this->cfg, 'jiraversion')) {
            // throw new Exception("jiraversion is MANDATORY - Unable to continue");
            $msg = " - Issuetracker $this->name - jiraversion is MANDATORY - Unable to continue";
            tLog(__METHOD__ . $msg, 'ERROR');
            return false;
        } else {
            $this->completeCfg();
        }
        
        $this->defaultResolvedStatus = $this->support->initDefaultResolvedStatus($this->statusDomain);
        $this->setResolvedStatusCfg();
    }

    /**
     */
    function completeCfg()
    {
        // when working with simpleXML objects is better to use intermediate variables
        $pieces = explode('.', (string) $this->cfg->jiraversion);
        $this->cfg->majorVersionNumber = (int) $pieces[0];
    }

    /**
     */
    function getIssue($issueID)
    {
        $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
        if (! $this->isConnected()) {
            return false;
        }
        
        // ATTENTION:
        // Field names on Jira tables seems to be sometimes on CAPITALS
        // TICKET 6028: Integration with Jira 6.1 broken. - Due to JIRA schema changes
        if (intval($this->cfg->majorVersionNumber) >= 6) {
            $dummy = explode("-", $issueID);
            $addFields = ",ISSUES.project, ISSUES.issuenum, PROJECT.originalkey, PROJECT.id ";
            $addJoin = " JOIN {$this->dbSchema->project} PROJECT ON ISSUES.project = PROJECT.id ";
            $where = " WHERE ISSUES.issuenum='{$this->dbConnection->prepare_string($dummy[1])}' " . " AND PROJECT.originalkey='{$this->dbConnection->prepare_string($dummy[0])}'";
        } else {
            $addFields = ",ISSUES.pkey ";
            $addJoin = '';
            $where = " WHERE ISSUES.pkey='{$this->dbConnection->prepare_string($issueID)}'";
        }
        
        $sql = "/* $debugMsg */ " . 
	" SELECT ISSUES.ID AS id, ISSUES.summary,ISSUES.issuestatus AS status_code, " . " ST.pname AS status_verbose " . $addFields . " FROM {$this->dbSchema->issues} ISSUES " . " JOIN {$this->dbSchema->status} ST ON ST.ID = ISSUES.issuestatus " . $addJoin . $where;
        
        try {
            $rs = $this->dbConnection->fetchRowsIntoMap($sql, 'id');
        } catch (Exception $e) {
            $rs = null;
            $msg = "JIRA DB - Ticket ID $issueID - " . $e->getMessage();
            tLog($msg, 'WARNING');
        }
        
        $issue = null;
        if (! is_null($rs)) {
            $issueOnDB = current($rs);
            $issue = new stdClass();
            $issue->IDHTMLString = "<b>{$issueID} : </b>";
            
            $issue->summary = $issueOnDB['summary'];
            $issue->statusCode = $issueOnDB['status_code'];
            $issue->statusVerbose = $issueOnDB['status_verbose'];
            
            $issue->statusHTMLString = $this->support->buildStatusHTMLString($issue->statusVerbose);
            $issue->summaryHTMLString = $this->support->buildSummaryHTMLString($issue);
            
            $issue->isResolved = isset($this->resolvedStatus->byCode[$issue->statusCode]);
        }
        return $issue;
    }

    /**
     * iris 根据输入的sprint名称获取jira数据库中的sprint的id和name信息
     *
     * @param unknown $name            
     */
    function getSprint($name)
    {
        $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
        
        if (! $this->isConnected()) {
            return false;
        }
        
        $sql = "/* $debugMsg */ " . " SELECT SPRINT.ID AS id, SPRINT.NAME AS name " . " FROM {$this->dbSchema->sprint} SPRINT " . "where SPRINT.NAME = '" . $this->dbConnection->prepare_string($name) . "'";
        
        try {
            $rs = $this->dbConnection->fetchRowsIntoMap($sql, 'id');
        } catch (Exception $e) {
            $rs = null;
            $msg = "JIRA DB - SPRINT NAME $name - " . $e->getMessage();
            tLog($msg, 'WARNING');
        }
        $sprint = null;
        if (! is_null($rs)) {
            $sprintOnDB = current($rs);
            $sprint = new stdClass();
            $sprint->id = $sprintOnDB['id'];
            $sprint->name = $sprintOnDB['name'];
        }
        return $sprint;
    }

    /**
     * iris 根据sprint的id获取sprint信息
     *
     * @param unknown $id            
     */
    function getSprintById($id)
    {
        $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
        
        if (! $this->isConnected()) {
            return false;
        }
        
        $sql = "/* $debugMsg */ " . " SELECT SPRINT.ID AS id, SPRINT.NAME AS name " . " FROM {$this->dbSchema->sprint} SPRINT " . "where SPRINT.ID = '" . $id . "'";
        
        try {
            $rs = $this->dbConnection->fetchRowsIntoMap($sql, 'id');
        } catch (Exception $e) {
            $rs = null;
            $msg = "JIRA DB - SPRINT id $id - " . $e->getMessage();
            tLog($msg, 'WARNING');
        }
        
        $sprint = null;
        if (! is_null($rs)) {
            $sprintOnDB = current($rs);
            $sprint = new stdClass();
            $sprint->id = $sprintOnDB['id'];
            $sprint->name = $sprintOnDB['name'];
        }
        return $sprint;
    }

    /**
     * iris 判断sprint是否存在
     *
     * @param unknown $name            
     * @return boolean
     */
    function sprintIsExist($name)
    {
        $sprint = $this->getSprint($name);
        $status_ok = ! is_null($sprint) && is_object($sprint);
        return $status_ok;
    }

    /**
     * iris 根据sprintid获取旗下关联的bugid和key
     * 具体的bug内容通过getIssuesIris获取
     *
     * @param unknown $SprintID            
     * @return boolean|Ambigous <NULL, multitype:>
     */
    function getBugs($SprintID)
    {
        $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
        
        if (! $this->isConnected()) {
            return false;
        }
        
        $sql = "/* $debugMsg */ " . " SELECT ISSUE.issuenum AS id, PROJECT.ORIGINALKEY AS pkey" . " FROM {$this->dbSchema->issues} ISSUE JOIN {$this->dbSchema->project} PROJECT ON ISSUE.PROJECT = PROJECT.id JOIN {$this->dbSchema->cfv} cfv ON cfv.ISSUE=ISSUE.ID " . "where ISSUE.issuetype = '10202' " . "and cfv.CUSTOMFIELD='10001' and cfv.STRINGVALUE='" . $SprintID . "' order by ISSUE.ID desc";
        try {
            $rs = $this->dbConnection->fetchRowsIntoMap($sql, 'id');
        } catch (Exception $e) {
            $rs = null;
            $msg = "JIRA DB - SPRINT id $SprintID - " . $e->getMessage();
            tLog($msg, 'WARNING');
        }
        
        $bug_list = array();
        $mycount = null;
$count = 0;
        
        foreach ($rs as $elem) {
            if (! isset($bug_list[$elem['id']])) {
                $dummy = $this->buildViewBugLink($elem['pkey'] . '-' . $elem['id']);
                $bug_list[$elem['id']]['id'] = $elem['pkey'] . '-' . $elem['id'];
                $bug_list[$elem['id']]['link_to_bts'] = $dummy->link;
                $bug_list[$elem['id']]['status'] = $dummy->status;
                $bug_list[$elem['id']]['reporter'] = $dummy->reporter;
                $bug_list[$elem['id']]['assign'] = $dummy->assign;
                if (! isset($mycount[$dummy->status])) {
                    $mycount[$dummy->status] = 1;
                } else {
                    $mycount[$dummy->status] = $mycount[$dummy->status]+1;
                }
if ($dummy->imports == "高") {
                    $count += 8;
                } elseif ($dummy->imports == "中") {
                    $count += 4;
                } elseif ($dummy->imports == "低") {
                    $count += 2;
                } elseif ($dummy->imports == "致命") {
                    $count += 10;
                }
            }
            unset($dummy);
        }
        
        return array(
            $bug_list,
            $mycount,
$count
        );
    }

    /**
     * iris
     * jira的定制bug链接内容
     * (non-PHPdoc)
     *
     * @see issueTrackerInterface::buildViewBugLink()
     */
    function buildViewBugLink($issueID, $opt = null)
    {
        $my['opt'] = $this->methodOpt[__FUNCTION__];
        $my['opt'] = array_merge($my['opt'], (array) $opt);
        
        $link = "<a href='" . $this->buildViewBugURL($issueID) . "' target='_blank'>";
        $issue = $this->getIssueIris($issueID);
        
        $ret = new stdClass();
        $ret->link = '';
        $ret->status = '';
        $ret->reporter = '';
        $ret->assign = '';
      $ret->imports = '';
   
        if (is_null($issue) || ! is_object($issue)) {
            $ret->link = "TestLink Internal Message: getIssue($issueID) FAILURE on " . __METHOD__;
            return $ret;
        }
        
        $useIconv = property_exists($this->cfg, 'dbcharset');
        
        if ($my['opt']['addSummary']) {
            if (! is_null($issue->summaryHTMLString)) {
                if ($useIconv) {
                    $link .= iconv((string) $this->cfg->dbcharset, $this->tlCharSet, $issue->summaryHTMLString);
                } else {
                    $link .= (string) $issue->summaryHTMLString;
                }
            }
        }
        $link .= "</a>";
        
        if ($my['opt']['colorByStatus'] && property_exists($issue, 'statusColor')) {
            $title = lang_get('access_to_bts');
            $link = "<div  title=\"{$title}\" style=\"display: inline; background: $issue->statusColor;\">$link</div>";
        }
        
        $ret = new stdClass();
        $ret->link = $link;
        $ret->status = $issue->statusVerbose;
        $ret->reporter = $issue->reporter;
        $ret->assign = $issue->assign;
 $ret->imports = $issue->imports;
        
        if (isset($my['opt']['raw']) && ! is_null(isset($my['opt']['raw']))) {
            foreach ($my['opt']['raw'] as $attr) {
                if (property_exists($issue, $attr)) {
                    $ret->$attr = $issue->$attr;
                }
            }
        }
        return $ret;
    }

    /**
     * iris
     * 获取指定id的bug的详细内容:id,summery,reporter,status
     *
     * @param unknown $issueID
     *            TFAPP-445
     * @return boolean|Ambigous <NULL, stdClass>
     */
    function getIssueIris($issueID)
    {
        $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
        if (! $this->isConnected()) {
            return false;
        }
        
        // 通过-将bug的关键字分开，TFAPP和id
        $dummy = explode("-", $issueID);
        $addFields = ", USER.DISPLAY_NAME AS reporter,USER1.DISPLAY_NAME AS assign, ISSUES.project, ISSUES.issuenum, PROJECT.originalkey, PROJECT.id,cfo.customvalue as imports ";
        $addJoin = " JOIN {$this->dbSchema->project} PROJECT ON ISSUES.project = PROJECT.id " . "LEFT JOIN {$this->dbSchema->user} USER ON ISSUES.REPORTER = USER.USER_KEY LEFT JOIN {$this->dbSchema->user} USER1 ON ISSUES.ASSIGNEE = USER1.USER_KEY JOIN customfieldvalue cfv ON cfv.ISSUE=ISSUES.ID JOIN customfieldoption cfo ON cfv.STRINGVALUE=cfo.ID ";
        $where = " WHERE ISSUES.issuenum='{$this->dbConnection->prepare_string($dummy[1])}' " . " AND PROJECT.originalkey='{$this->dbConnection->prepare_string($dummy[0])}' AND cfv.CUSTOMFIELD='10206'";
        
        $sql = "/* $debugMsg */ " . " SELECT ISSUES.ID AS id, ISSUES.summary,ISSUES.issuestatus AS status_code, " . " ST.pname AS status_verbose " . $addFields . " FROM {$this->dbSchema->issues} ISSUES " . " JOIN {$this->dbSchema->status} ST ON ST.ID = ISSUES.issuestatus " . $addJoin . $where;
        
        try {
            $rs = $this->dbConnection->fetchRowsIntoMap($sql, 'id');
        } catch (Exception $e) {
            $rs = null;
            $msg = "JIRA DB - Ticket ID $issueID - " . $e->getMessage();
            tLog($msg, 'WARNING');
        }
        
        $issue = null;
        if (! is_null($rs)) {
            $issueOnDB = current($rs);
            $issue = new stdClass();
            $issue->IDHTMLString = "<b>{$issueID} : </b>";
         
$issue->imports = $issueOnDB['imports'];
   
            $issue->summary = $issueOnDB['summary'];
            $issue->statusCode = $issueOnDB['status_code'];
            $issue->statusVerbose = $issueOnDB['status_verbose'];
            $issue->reporter = $issueOnDB['reporter'];
            $issue->assign = $issueOnDB['assign'];
            
            $issue->statusHTMLString = $this->support->buildStatusHTMLString($issue->statusVerbose);
            $issue->summaryHTMLString = $this->support->buildSummaryHTMLString($issue);
            
            $issue->isResolved = isset($this->resolvedStatus->byCode[$issue->statusCode]);
        }
        return $issue;
    }

    /**
     */
    function getMyInterface()
    {
        return $this->cfg->interfacePHP;
    }

    /**
     */
    function getStatuses()
    {
        $debugMsg = 'Class:' . __CLASS__ . ' - Method: ' . __FUNCTION__;
        if (! $this->isConnected()) {
            return false;
        }
        
        // ATTENTION:
        // Field names on Jira tables seems to be sometimes on CAPITALS
        $sql = "/* $debugMsg */ " . " SELECT ST.ID AS id,ST.pname AS name FROM {$this->dbSchema->status} ST";
        try {
            $rs = $this->dbConnection->fetchRowsIntoMap($sql, 'id');
            foreach ($rs as $id => $elem) {
                $this->statusDomain[$elem['name']] = $id;
            }
        } catch (Exception $e) {
            tLog("JIRA DB " . __METHOD__ . $e->getMessage(), 'WARNING');
        }
    }

    /**
     *
     * @author francisco.mancardi@gmail.com>
     *        
     */
    public function getStatusDomain()
    {
        return $this->statusDomain;
    }

    /**
     * checks id for validity
     *
     * @param
     *            string issueID
     *            
     * @return bool returns true if the bugid has the right format, false else
     *        
     */
    function checkBugIDSyntax($issueID)
    {
        return $this->checkBugIDSyntaxString($issueID);
    }

    public static function getCfgTemplate()
    {
        $template = "<!-- Template " . __CLASS__ . " -->\n" . "<issuetracker>\n" . "<jiraversion>MANDATORY</jiraversion>\n" . "<dbhost>DATABASE SERVER NAME</dbhost>\n" . "<dbname>DATABASE NAME</dbname>\n" . "<dbtype>mysql</dbtype>\n" . "<dbuser>USER</dbuser>\n" . "<dbpassword>PASSWORD</dbpassword>\n" . "<uriview>http://localhost:8080/development/mantisbt-1.2.5/view.php?id=</uriview>\n" . "<uricreate>http://localhost:8080/development/mantisbt-1.2.5/</uricreate>\n" . "<!-- Configure This if you want NON STANDARD BEHAIVOUR for considered issue resolved -->\n" . "<resolvedstatus>\n" . "<status><code>80</code><verbose>resolved</verbose></status>\n" . "<status><code>90</code><verbose>closed</verbose></status>\n" . "</resolvedstatus>\n" . "</issuetracker>\n";
        return $template;
    }

    /**
     */
    function canCreateViaAPI()
    {
        return false;
    }
}
