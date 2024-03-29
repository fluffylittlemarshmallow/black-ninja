<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 *
 * @filesource	mantissoapInterface.class.php
 * @author Francisco Mancardi
 * @since 1.9.4
 * @internal revisions
**/
class mantissoapInterface extends issueTrackerInterface
{
	// Copied from mantis configuration
	private $status_color = array('new'          => '#ffa0a0', # red,
                                'feedback'     => '#ff50a8', # purple
                                'acknowledged' => '#ffd850', # orange
                                'confirmed'    => '#ffffb0', # yellow
                                'assigned'     => '#c8c8ff', # blue
                                'resolved'     => '#cceedd', # buish-green
                                'closed'       => '#e8e8e8'); # light gray
	
	
	private $soapOpt = array("connection_timeout" => 1, 'exceptions' => 1);
	private $guiCfg = array();

	/**
	 * Construct and connect to BTS.
	 *
	 * @param str $type (see tlIssueTracker.class.php $systems property)
	 * @param xml $cfg
	 **/
	function __construct($type,$config)
	{
		$this->interfaceViaDB = false;
		$this->methodOpt['buildViewBugLink'] = array('addSummary' => true, 'colorByStatus' => true);

	    $this->setCfg($config);
		$this->completeCfg();
	    $this->connect();
	    
	    $this->guiCfg = array('use_decoration' => true);
	}

	
	/**
	 * Return the URL to the bugtracking page for viewing 
	 * the bug with the given id. 
	 *
	 * @param int id the bug id
	 * 
	 * @return string returns a complete URL to view the bug
	 **/
	function buildViewBugURL($id)
	{
		return $this->cfg->uriview . urlencode($id);
	}
	
	
    /**
     * establishes the soap connection to the bugtracking system
     *
     * @return bool returns true if the soap connection was established and the
     * wsdl could be downloaded, false else
     *
     **/
    function connect()
    {
		$op = $this->getClient(array('log' => true));
		if( ($this->connected = $op['connected']) )
		{ 
			// OK, we have got WSDL => server is up and we can do SOAP calls, but now we need 
			// to do a simple call with user/password only to understand if we are really connected
			try
			{
				$x = $op['client']->mc_enum_status($this->cfg->username,$this->cfg->password);
			}
			catch (SoapFault $f)
			{
				$this->connected = false;
				tLog("SOAP Fault: (code: {$f->faultcode}, string: {$f->faultstring})","ERROR");
			}
		}
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
	 * checks is bug id is present on BTS
	 * 
	 * @return integer returns 1 if the bug with the given id exists 
	 **/
	function checkBugIDExistence($id)
	{
		static $client;
		
		if (!$this->isConnected())
		{
			return 0;  // >>>---> bye!
		}
		
		if(is_null($client))
		{
			$dummy = $this->getClient();
			$client = $dummy['client'];
		}
		
		$status_ok = 0;
		$safe_id = intval($id);
		try
		{
			$status_ok = $client->mc_issue_exists($this->cfg->username,$this->cfg->password,$safe_id) ? 1 : 0;
		}
		catch (SoapFault $f) 
		{
			// from http://www.w3schools.com/soap/soap_fault.asp
			// VersionMismatch 	- 	Found an invalid namespace for the SOAP Envelope element
			// MustUnderstand 	- 	An immediate child element of the Header element, 
			//						with the mustUnderstand attribute set to "1", was not understood
			// Client 			-	The message was incorrectly formed or contained incorrect information
			// Server 			-	There was a problem with the server so the message ...
			
			// @ŢODO - 20120106 - need to think how to manage this situation in a better way
		}
		return $status_ok;
	}


	/**
	 * 
	 * 
	 *
	 * 
	 **/
	function getIssue($id)
	{
		static $client;
		
		if (!$this->isConnected())
		{
			return false;
		}
		
		if(is_null($client))
		{
			$dummy = $this->getClient();
			$client = $dummy['client'];
		}

		$status = false;
		$safe_id = intval($id);
		$issue = null;
		try
		{
			if($client->mc_issue_exists($this->cfg->username,$this->cfg->password,$safe_id))
			{
				$issue = $client->mc_issue_get($this->cfg->username,$this->cfg->password,$safe_id);
				if( !is_null($issue) && is_object($issue) )
				{				
					$issue->IDHTMLString = "<b>{$id} : </b>";
					$issue->statusCode = $issue->status->id; 
					$issue->statusVerbose = $issue->status->name; 
					$issue->statusHTMLString = $this->buildStatusHTMLString($issue->statusVerbose);
					$issue->statusColor = isset($this->status_color[$issue->statusVerbose]) ? 
										  $this->status_color[$issue->statusVerbose] : 'white';

					$issue->summaryHTMLString = $issue->summary;
                }
			}
		}
		catch (SoapFault $f) 
		{
			// from http://www.w3schools.com/soap/soap_fault.asp
			// VersionMismatch 	- 	Found an invalid namespace for the SOAP Envelope element
			// MustUnderstand 	- 	An immediate child element of the Header element, 
			//						with the mustUnderstand attribute set to "1", was not understood
			// Client 			-	The message was incorrectly formed or contained incorrect information
			// Server 			-	There was a problem with the server so the message ...
			
			// @ŢODO - 20120106 - need to think how to manage this situation in a better way
		}
		return $issue;
	}


	/**
	 * 
	 * 
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
	 *
	 * 
	 **/
	public static function getCfgTemplate()
  {
		$template = "<!-- Template " . __CLASS__ . " -->\n" .
      					"<issuetracker>\n" .
      					"<username>MANTIS LOGIN NAME</username>\n" .
      					"<password>MANTIS PASSWORD</password>\n" .
      					"<uribase>http://www.mantisbt.org/</uribase>\n" .
      					"<!-- IMPORTANT NOTICE --->\n" .
      					"<!-- You Do not need to configure uriwsdl,uriview,uricreate  -->\n" .
      					"<!-- if you have done Mantis standard installation -->\n" .
      					"<!-- In this situation DO NOT COPY these config lines -->\n" .
      					"<uriwsdl>http://www.mantisbt.org/api/soap/mantisconnect.php?wsdl</uriwsdl>\n" .
      					"<uriview>http://www.mantisbt.org/view.php?id=</uriview>\n" .
      					"<uricreate>http://www.mantisbt.org/</uricreate>\n" .
                "<!-- Optional -->\n" .
                "<statuscfg>\n" .
                "<status><code>10</code><verbose>new</verbose><color>#ffa0a0</color></status>\n" .
                "<status><code>20</code><verbose>feedback</verbose><color>#ff50a8</color></status>\n" .
                "<status><code>30</code><verbose>acknowledged</verbose><color>#ffd850</color></status>\n" .
                "<status><code>40</code><verbose>confirmed</verbose><color>#ffffb0</color></status>\n" .
                "<status><code>50</code><verbose>assigned</verbose><color>#c8c8ff</color></status>\n" .
                "<status><code>80</code><verbose>resolved</verbose><color>#cceedd</color></status>\n" .
                "<status><code>90</code><verbose>closed</verbose><color>#e8e8e8</color></status>\n" .
                "</statuscfg>\n" . 
      					"</issuetracker>\n";
		return $template;
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
	    	$this->cfg->uriwsdl = $base . 'api/soap/mantisconnect.php?wsdl';
		}
		
	    if( !property_exists($this->cfg,'uriview') )
	    {
	    	$this->cfg->uriview = $base . 'view.php?id=';
		}
	    
	    if( !property_exists($this->cfg,'uricreate') )
	    {
	    	$this->cfg->uricreate = $base;
		}	    
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
    	return $this->checkBugIDSyntaxNumeric($issueID);
    }



    /**
     *
     *
     **/
	function buildStatusHTMLString($statusVerbose)
	{
		$str = '';
		if ($statusVerbose !== false)
		{
			// status values depends on your mantis configuration at config_inc.php in $g_status_enum_string, 
			// below is the default:
			//'10:new,20:feedback,30:acknowledged,40:confirmed,50:assigned,80:resolved,90:closed'
			// With this replace if user configure status on mantis with blank we do not have problems
			//
			$tlStatus = str_replace(" ", "_", $statusVerbose);
			$str = lang_get('issue_status_' . $tlStatus);
			if($this->guiCfg['use_decoration'])
			{
				$str = "[" . $str . "] ";	
			}
		}
		return $str;
	}

  public function setStatusCfg()
  {
    $statusCfg = (array)$this->cfg->statuscfg;
    foreach($statusCfg['status'] as $cfx)
    {
      $e = (array)$cfx;
	    $this->status_color[$e['verbose']] = $e['color'];
    }
  }

  public function getStatusColor()
  {
  	  return $this->status_color;
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