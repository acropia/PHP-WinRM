<?php

class Protocol {
	protected $endpoint;
	protected $timeout;
	protected $maxEndSize;
	protected $locale;
	protected $transport;
	protected $username;
	protected $password;
	protected $service;
	protected $keytab;
	protected $caTrustPath;

	public function __construct($endpoint,
			$transport='plaintext',
			$username=null,
			$password=null,
			$realm=null,
			$service=null,
			$keytab=null,
			$caTrustPath=null,
			$certPem=null,
			$certKeyPem=null) {

		print (__METHOD__."()\n");
		print (__METHOD__.": - endpoint: ".$endpoint."\n");
		print (__METHOD__.": - transport: ".$transport."\n");
		print (__METHOD__.": - username: ".$username."\n");
		print (__METHOD__.": - password: ".$password."\n");

		$this->endpoint = $endpoint;
		$this->timeout = 'PT60S';
		$this->maxEnvSize = 153600;
		$this->locale = 'en-US';

		if ($transport == 'plaintext') {
			$this->transport = new HttpPlainText($endpoint, $username, $password);
		}
		elseif ($transport == 'kerberos') {
			$this->transport = new HttpKerberos($endpoint);
		}
		elseif ($transport == 'ssl') {
			$this->transport = new HttpSSL($endpoint, $username, $password, $certPem, $certKeyPem);
		}
		else {
			throw new NotImplementedException('Transport '.$transport.' not implemented');
		}

		$this->username = $username;
		$this->password = $password;
		$this->service = $service;
		$this->keytab = $keytab;
		$this->caTrustPath = $caTrustPath;
	}

	public function openShell($inputStream='stdin', $outputStream='stdout stderr', $workingDirectory=null, $envVars=null, $noProfile=false, $codepage=437, $lifetime=null, $idleTimeout=null) {
		print (__METHOD__."()\n");
		$rq = array(
			'env:Envelope' => $this->getSoapHeader(
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd',
				'http://schemas.xmlsoap.org/ws/2004/09/transfer/Create'
			)
		);
		$header = $rq['env:Envelope']['env:Header'];
		$header['w:OptionSet'] = array(
			'w:Option' => array(
				'@Name' => 'WINRS_NOPROFILE',
				'#text' => strtoupper((string) $noProfile),
			), array(
				'@Name' => 'WINRS_CODEPAGE',
				'#text' => (string) $codepage,
			)
		);

		$shell = $rq['env:Envelope']->setDefault('env:Body')->setDefault('rsp:Shell');
		$shell['rsp:InputStreams'] = $inputStream;
		$shell['rsp:OutputStreams'] = $outputStream;

		if ($workingDirectory) {
			$shell['rsp:WorkingDirectory'] = $workingDirectory;
		}

		if ($idleTimeout) {
			$shell['rsp:IdleTimeout'] = $idleTimeout;
		}

		if ($envVars) {
			$env = $shell->setDefault('rsp:Environment');
			foreach ($envVars as $varKey=>$varValue) {
				$env['rsp:Variable'] = array(
					'@Name' => $varKey,
					'#text' => $varValue,
				);
			}
		}

		$rs = $this->sendMessage(xmlunparse($rq));

		$root = $et->fromstring($rs);
		return 'dinges';
	}
	public function runCommand($shellId, $command, $arguments=null, $consoleModeStdin=true, $skipCmdShell=false) {
		print (__METHOD__."()\n");
		$rq = array(
			'env:Envelope' => $this->getSoapHeader(
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd',
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/Command',
				$shellId
			)
		);
		$header = $rq['env:Envelope']['env:Header'];
		$header['w:OptionSet'] = array(
			'w:Option' => array(
				'@Name' => 'WINRS_CONSOLEMODE_STDIN',
				'#text' => strtoupper((string) $consoleModeStdin),
			), array(
				'@Name' => 'WINRS_SKIP_CMD_SHELL',
				'#text' => (string) $skipCmdShell,
			)
		);

		$cmdLine = $rq['env:Envelope']->setDefault('env:Body')->setDefault('rsp:CommandLine');
		$cmdLine['rsp:Command'] = array(
			'#text' => $command
		);

		if ($arguments) {
			$cmdLine['rsp:Arguments'] = ' '.join($arguments);
		}

		$rs = $this->sendMessage(xmlunparse($rq));

		$root = $et->fromstring($rs);
		$commandId = 'dinges';
		return $commandId; 
	}

	public function getSoapHeader($action=null, $resourceUri=null, $shellId=null, $messageId=null) {
		print (__METHOD__."()\n");
		if ( ! $messageId) {
			$messageId = uuid.uuid4();
		}

		$header = array(
			'@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
			'@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
			'@xmlns:env' => 'http://www.w3.org/2003/05/soap-envelope',
			'@xmlns:a'   => 'http://schemas.xmlsoap.org/ws/2004/08/addressing',
			'@xmlns:b'   => 'http://schemas.dmtf.org/wbem/wsman/1/cimbinding.xsd',
			'@xmlns:n'   => 'http://schemas.xmlsoap.org/ws/2004/09/enumeration',
			'@xmlns:x'   => 'http://schemas.xmlsoap.org/ws/2004/09/transfer',
			'@xmlns:w'   => 'http://schemas.dmtf.org/wbem/wsman/1/wsman.xsd',
			'@xmlns:p'   => 'http://schemas.microsoft.com/wbem/wsman/1/wsman.xsd',
			'@xmlns:rsp' => 'http://schemas.microsoft.com/wbem/wsman/1/windows/shell',
			'@xmlns:cfg' => 'http://schemas.microsoft.com/wbem/wsman/1/config',
			'env:Header' => array(
				'a:To' => 'http://windows-host:5985/wsman',
				'a:ReplyTo' => array(
					'a:Address' => array(
						'@mustUnderstand' => 'true',
						'#text' => 'http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous'
					),
				),
				'w:MaxEnvelopeSize' => array(
					'@mustUnderstand' => 'true',
					'#text' => '153600'
				),
				'a:MessageID' => 'uuid:'.$messageId,
				'w:Locale' => array(
					'@mustUnderstand' => 'false',
					'@xml:lang' => 'en-US'
				),
				'p:DataLocale' => array(
					'@mustUnderstand' => 'false',
					'@xml:lang' => 'en-US'
				),
				# TODO: research this a bit http://msdn.microsoft.com/en-us/library/cc251561(v=PROT.13).aspx # NOQA
				# 'cfg:MaxTimeoutms': 600
				'w:OperationTimeout' => 'PT60S',
				'w:ResourceURI' => array(
					'@mustUnderstand' => 'true',
					'#text' => $resourceUri
				),
				'a:Action' => array(
					'@mustUnderstand' => 'true',
					'#text' => $action
				)
			)
		);
		if ($shellId) {
			$header['env:Header']['w:SelectorSet'] = array(
				'w:Selector' => array(
					'@Name' => 'ShellId',
					'#text' => $shellId
				)
			);
		}
		return $header;
	}

	public function sendMessage($message) {
		print (__METHOD__."()\n");
		return $this->transport->sendMessage($message);
	}
	public function getCommandOutput($shellId, $commandId) {
		print (__METHOD__."()\n");
		$stdoutBuffer = '';
		$stderrBuffer = '';

		$commandDone = false;

		while ( ! $commandDone) {
			$this->rawGetCommandOutput($shellId, $commandId);
			$stdoutBuffer[] = $stdoud;
			$stderrBuffer[] = $stderr;
		}
		return $stdoutBuffer.' '.$stderrBuffer;
	}

	public function rawGetCommandOutput($shellId, $commandId) {
		print (__METHOD__."()\n");
		$rq = array(
			'env:Envelope' => $this->getSoapHeader(
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd',
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/Receive',
				$shellId
			)
		);

		$stream = $rq['env:Envelope']->setDefault('envBody')->setDefault('rsp:Receive');
		$stream['@CommandId'] = $commandId;
		$stream['#text'] = 'stdout stderr';

		$rs = $this->sendMessage(xmlunparse($rq));
		$root = ET.fromstring($rs);
		$streamNodes = stream;
		$stdout = $stderr = '';
		$returnCode = -1;
		foreach ($streamNodes as $node) {
			if ($node.text) {
				if ($nodeattrib['Name'] == 'stdout') {
					$stdout .= (string) base64_decode($node->encode('ascii'));
				}
				elseif ($nodeattrib['Name'] == 'stderr') {
					$stderr .= (string) base64_decode($node->encode('ascii'));
				}
			}
		}

		$commandDone = strlen(node.done);

		if ($commandDone) {
			$returnCode = (int) next.ExitCode;
		}
		return array($stdout, $stderr, $returnCode, $commandDone);
	}

	public function cleanupCommand($shellId, $commandId) {
		print (__METHOD__."()\n");
		$messageId = uuid.uuid4();

		$rq = array(
			'env:Envelope' => $this->getSoapHeader(
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd',
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/Signal',
				$shellId,
				$messageId
			)
		);

		$signal = $rq['env:Envelope']->setDefault('env:Body')->setDefault('rsp:Signal');
		$signal['@CommandId'] = $commandId;
		$signal['rsp:Code'] = 'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/signal/terminate';

		$rs = $this->sendMessage(xmlunparse($rq));
		$root = $et->fromstring($rs);
		$relatesTo = $node['RelatesTo'];
		uuid.UUID($relatesTo.replace());// = $messageId;
	}
	public function closeShell($shellId) {
		print (__METHOD__."()\n");
		$messageId = uuid.uuid4();

		$rq = array(
			'env:Envelope' => $this->getSoapHeader(
				'http://schemas.microsoft.com/wbem/wsman/1/windows/shell/cmd',
				'http://schemas.xmlsoap.org/ws/2004/09/transfer/Delete',
				$shellId,
				$messageId
			)
		);

		$rq['env:Envelope'].setDefault('env:Body');

		$rs = $this->sendMessage(xmlunparse($rq));
		$root = $et->fromstring($rs);
		$relatedTo = $node['RelatesTo'];
		uuid.UUID($relatesTo.replace());// == $messageId;

	}
}
