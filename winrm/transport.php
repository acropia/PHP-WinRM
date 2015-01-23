<?php

class HttpTransport {
	protected $endpoint;
	protected $username;
	protected $password;
	protected $userAgent;
	protected $timeout;

	public function __construct($endpoint, $username, $password) {
		print (__METHOD__."()\n");
		$this->endpoint = $endpoint;
		$this->username = $username;
		$this->password = $password;
		$this->userAgent = 'PHP WinRM client';
		$this->timeout = 30;
	}

	public function basicAuthOnly() {
		print (__METHOD__."()\n");
	}
	public function disableSspiAuth() {
		print (__METHOD__."()\n");
	}
}

class HttpPlainText extends HttpTransport {
	protected $headers;

	public function __construct($endpoint, $username='', $password='', $disableSspi=true, $basicAuthOnly=true) {
		print (__METHOD__."()\n");
		parent::__construct($endpoint, $username, $password);

		if ($disableSspi) {
			$this->disableSspiAuth();
		}
		if ($basicAuthOnly) {
			$this->basicAuthOnly();
		}

		$this->headers = array(
			'Content-Type' => 'application/soap+xml;charset=UTF-8',
			'User-Agent'   => $this->userAgent,
		);

	}

	public function sendMessage($message) {
		print (__METHOD__."()\n");
		$headers = $this->headers;
		$headers['Content-Length'] = strlen($message);

		$this->setupOpener();

		$request = Request($this->endpoint, $message, $headers);
		try {
			$response = urlopen($request, $this->timeout);
			$responseText = $response->read();
			return $responseText;
		}
		catch (HTTPError $e) {
			if ($e->code == 401) {
				print "UnauthorizedError";
			}
		}
		catch (URLError $e) {
			print "WinRMTranspoirtError";
		}

	}

	public function setupOpener() {
		print (__METHOD__."()\n");
		$passwordManager = HttpPasswordMgrWithDefaultRealm();
		$passwordManager->addPassword(null,$this->endpoint, $this->username, $this->password);
		$authManager = HttpBasicAuthHandler($passwordManager);
		$opener = buildOpener($authManager);
		installOpener($opener);
	}
}
