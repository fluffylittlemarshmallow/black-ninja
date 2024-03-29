<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 *
 * @filesource	jirasoapInterface.class.php
 * @author Francisco Mancardi
 *
 *
 * @internal IMPORTANT NOTICE
 *			 we use issueID on methods signature, to make clear that this ID 
 *			 is HOW issue in identified on Issue Tracker System, 
 *			 not how is identified internally at DB	level on TestLink
 *
 * @internal revisions
 * @since 1.9.4
 * 20120220 - franciscom - TICKET 4904: integrate with ITS on test project basis 
**/
class jirasoapInterface extends issueTrackerInterface
{

    protected $APIClient;
	protected $authToken;
    protected $statusDomain = array();
	protected $l18n;
	protected $labels = array('duedate' => 'its_duedate_with_separator');
	
	private $soapOpt = array("connection_timeout" => 1, 'exceptions' => 1);
	
	/**
	 * Construct and connect to BTS.
	 *
	 * @param str $type (see tlIssueTracker.class.php $systems property)
	 * @param xml $cfg
	 **/
	function __construct($type,$config)
	{
		$this->interfaceViaDB = false;
	    $this->setCfg($config);
		$this->completeCfg();
	    $this->connect();
	}

	/**
	 *
	 * check for configuration attributes than can be provided on
	 * user configuration, but that can be considered standard.
	 * If they are MISSING we will use 'these carved on the stone values' 
	 * in order	to simplify configuration.
	 *
	 *
	 **/
	function completeCfg()
	{
		$base = trim($this->cfg->uribase,"/") . '/' ;
	    if( !property_exists($this->cfg,'uriwsdl') )
	    {
	    	$this->cfg->uriwsdl = $base . 'rpc/soap/jirasoapservice-v2?wsdl';
		}
		
	    if( !property_exists($this->cfg,'uriview') )
	    {
	    	$this->cfg->uriview = $base . 'browse/';
		}
	    
	    if( !property_exists($this->cfg,'uricreate') )
	    {
	    	$this->cfg->uricreate = $base . 'secure/CreateIssue!default.jspa';
		}	    
	}


	/**
	 * status code (integer) for issueID 
	 *
	 * 
	 * @param string issueID
	 *
	 * @return 
	 **/
	public function getIssueStatusCode($issueID)
	{
		$issue = $this->getIssue($issueID);
		return (!is_null($issue) && is_object($issue))? $issue->statusCode : false;
	}

		
	/**
	 * Returns status in a readable form (HTML context) for the bug with the given id
	 *
	 * @param string issueID
	 * 
	 * @return string 
	 *
	 **/
	function getIssueStatusVerbose($issueID)
	{
        $issue = $this->getIssue($issueID);
		return (!is_null($issue) && is_object($issue))? $issue->statusVerbose : false;
	}
	
	
	/**
	 *
	 * @param string issueID
	 * 
	 * @return string returns the bug summary if bug is found, else null
	 **/
    function getIssueSummary($issueID)
    {
        $issue = $this->getIssue($issueID);
		return (!is_null($issue) && is_object($issue))? $issue->summary : null;
    }
	
    /**
     * @internal precondition: TestLink has to be connected to Jira 
     *
	 * @param string issueID
     *
     **/
    function getIssue($issueID)
    {
    	$issue = null;
        try
        {
            $issue = $this->APIClient->getIssue($this->authToken, $issueID);
            
			if(!is_null($issue) && is_object($issue))
			{
            	// We are going to have a set of standard properties
	            $issue->IDHTMLString = "<b>{$issueID} : </b>";
	            $issue->statusCode = $issue->status;
	            $issue->statusVerbose = array_search($issue->statusCode, $this->statusDomain);
				$issue->statusHTMLString = $this->buildStatusHTMLString($issue->statusCode);
				$issue->summaryHTMLString = $this->buildSummaryHTMLString($issue);
			}
        }
        catch (Exception $e)
        {
         	tLog("JIRA Ticket ID $issueID - " . $e->getMessage(), 'WARNING');
			$issue = null;
        }
        
        return $issue;
    }


    /**
     * checks id for validity
     *
	 * @param string issueID
     *
     * @return bool returns true if the bugid has the right format, false else
     **/
    function checkBugIDSyntax($issueID)
    {
    	return $this->checkBugIDSyntaxString($issueID);
    }

    /**
	 * @param string issueID
     *
     * @return bool true if issue exists on BTS
     **/
    function checkBugIDExistence($issueID)
    {
        if(($status_ok = $this->checkBugIDSyntax($issueID)))
        {
            $issue = $this->getIssue($issueID);
            $status_ok = !is_null($issue) && is_object($issue);
        }
        return $status_ok;
    }

    /**
     * establishes connection to the bugtracking system
     *
     * @return bool returns true if the soap connection was established and the
     * wsdl could be downloaded, false else
     *
     **/
    function connect()
    {
		$this->interfaceViaDB = false;
		$op = $this->getClient(array('log' => true));
		if( ($this->connected = $op['connected']) )
		{ 
			// OK, we have got WSDL => server is up and we can do SOAP calls, but now we need 
			// to do a simple call with user/password only to understand if we are really connected
			try
			{
				$this->APIClient = $op['client'];
            	$this->authToken = $this->APIClient->login($this->cfg->username, $this->cfg->password);
            	$statusSet = $op['client']->getStatuses($this->authToken);
	            foreach ($statusSet as $key => $pair)
    	        {
        	    	$this->statusDomain[$pair->name]=$pair->id;
            	}
            	$this->l18n = init_labels($this->labels);
			}
			catch (SoapFault $f)
			{
				$this->connected = false;
				tLog(__CLASS_ . " - SOAP Fault: (code: {$f->faultcode}, string: {$f->faultstring})","ERROR");
			}
		}
        return $this->connected;
    }

    /**
     * 
     *
     **/
	function isConnected()
	{
		return $this->connected;
	}


    /**
     * 
     *
     **/
	function getClient($opt=null)
	{
		// IMPORTANT NOTICE - 2012-01-06 - If you are using XDEBUG, Soap Fault will not work
		$res = array('client' => null, 'connected' => false, 'msg' => 'generic ko');
		$my['opt'] = array('log' => false);
		$my['opt'] = array_merge($my['opt'],(array)$opt);
		
		try
		{
			// IMPORTANT NOTICE
			// $this->cfg is a simpleXML object, then is ABSOLUTELY CRITICAL 
			// DO CAST any member before using it.
			// If we do following call WITHOUT (string) CAST, SoapClient() fails
			// complaining '... wsdl has to be an STRING or null ...'
			$res['client'] = new SoapClient((string)$this->cfg->uriwsdl,$this->soapOpt);
			$res['connected'] = true;
			$res['msg'] = 'iupi!!!';
		}
		catch (SoapFault $f)
		{
			$res['connected'] = false;
			$res['msg'] = "SOAP Fault: (code: {$f->faultcode}, string: {$f->faultstring})";
			if($my['opt']['log'])
			{
				tLog("SOAP Fault: (code: {$f->faultcode}, string: {$f->faultstring})","ERROR");
			}	
		}
		return $res;
	}	




    /**
     *
     * @author francisco.mancardi@gmail.com>
     **/
    private function helperParseDate($date2parse)
    {
    	$ret = null;
        if (!is_null($date2parse))
        {
            $ret = date_parse($date2parse);
            $ret = ((gmmktime(0, 0, 0, $ret['month'], $ret['day'], $ret['year'])));
            $ret = $this->l18n['duedate'] . gmstrftime("%d %b %Y",($ret));
        }
        return $ret ;
    }



    /**
     *
     * @author francisco.mancardi@gmail.com>
     **/
	public static function getCfgTemplate()
  	{
		$template = "<!-- Template " . __CLASS__ . " -->\n" .
					"<issuetracker>\n" .
					"<username>JIRA LOGIN NAME</username>\n" .
					"<password>JIRA PASSWORD</password>\n" .
					"<uribase>http://testlink.atlassian.net/</uribase>\n" .
					"<uriwsdl>http://testlink.atlassian.net/rpc/soap/jirasoapservice-v2?wsdl</uriwsdl>\n" .
					"<uriview>testlink.atlassian.net/browse/</uriview>\n" .
					"<uricreate>testlink.atlassian.net/secure/CreateIssue!default.jspa</uricreate>\n" .
					"</issuetracker>\n";
		return $template;
  	}

 	
    /**
     *
     * @author francisco.mancardi@gmail.com>
     **/
	public function getStatusDomain()
  	{
		return $this->statusDomain;
  	}

	/**
	 *
	 **/
	function buildStatusHTMLString($statusCode)
	{
		$str = array_search($statusCode, $this->statusDomain);
		if (strcasecmp($str, 'closed') == 0 || strcasecmp($str, 'resolved') == 0 )
        {
                $str = "<del>" . $str . "</del>";
        }
        return "[{$str}] ";
	}

	/**
	 *
	 **/
  function buildSummaryHTMLString($issue)
  {
    $summary = $issue->summary;
    $strDueDate = $this->helperParseDate($issue->duedate);
    if( !is_null($strDueDate) )
    { 
     	$summary .= "<b> [$strDueDate] </b> ";
    }
    return $summary;
  }

  public static function checkEnv()
	{
	  $ret = array();
	  $ret['status'] = extension_loaded('soap');
	  $ret['msg'] = $ret['status'] ? 'OK' : 'You need to enable SOAP extension';
	  return $ret;
	}
}
?>